<?php
// Run with PHP CLI; all fixtures are in memory and make no network/database requests.
require_once __DIR__.'/../class/caldavsync/caldavsync_inbound.trait.php';
require_once __DIR__.'/../lib/caldav/caldavclient_fetch.trait.php';
require_once __DIR__.'/../lib/caldav/caldavclient_ical.trait.php';
define('DOL_DATA_ROOT', sys_get_temp_dir());
define('MAIN_DB_PREFIX','llx_');
function dol_syslog(...$args) {}
function getDolGlobalInt($name) { return 0; }
function getDolGlobalString($name,$fallback="") { return $name === "MAIN_DOLIBARR_USER_TIMEZONE" ? "Europe/Berlin" : $fallback; }
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
class ActionComm {
    public static $records = array();
    public static $calls = array();
    public $id, $datep, $datef, $label = 'fixture', $entity=1, $note_private='', $location='', $fulldayevent=0;
    public function __construct($db) {}
    public function fetch($id) { if (!isset(self::$records[$id])) return 0; $this->id=$id; [$this->datep,$this->datef]=self::$records[$id]; return 1; }
    public function create($user, $notrigger=0) { self::$calls[]=['create',$notrigger]; $this->id=100; return 100; }
    public function update($user, $notrigger=0) { self::$calls[]=['update',$notrigger]; return 1; }
    public function delete($user, $notrigger=0) { self::$calls[]=['delete',$notrigger]; return 1; }
}
class CalDAVEventMapping {
    public static $mappings = array(), $deleted = array(), $failDelete = false;
    public $error = "fixture mapping failure";
    public $fk_actioncomm, $caldav_uid;
    public function __construct($db) {}
    public function fetchByCalDAVUID($uid,$calendar) { foreach (self::$mappings as $m) { if ($m->caldav_uid===$uid) { $this->fk_actioncomm=$m->fk_actioncomm; return 1; } } return 0; }
    public function create(...$args) { return 1; }
    public function update(...$args) { return 1; }
    public function delete() { if (self::$failDelete) return -1; self::$deleted[]=$this->fk_actioncomm; return 1; }
    public static function getAllByCalendar($db,$id) { return self::$mappings; }
}
class CalDAVClient {
    use CalDAVClientFetchTrait, CalDAVClientICalTrait;
    public static $response, $requestXml;
    public function __construct($connection=null) {}
    private function request(...$args) { self::$requestXml=$args[2]; return self::$response; }
}
class SafetyDB { public function begin() {return 1;} public function commit() {return 1;} public function rollback() {return 1;} public function query($sql) {return true;} }
class SyncFixture {
    use CalDAVSyncInboundTrait;
    public $db=null, $error;
    public function invoke($name,...$args) { return $this->$name(...$args); }
}
$user=(object)['id'=>1];
$conf=(object)['entity'=>1];
$calendar=(object)['id'=>1,'url'=>'https://example.invalid/calendar','name'=>'fixture'];
$sync=new SyncFixture; $sync->db=new SafetyDB;
$start=new DateTime('2026-01-01T00:00:00Z'); $end=new DateTime('2027-01-01T00:00:00Z');
foreach ([1=>['2025-01-01','2025-01-02'],2=>['2026-06-01','2026-06-02'],3=>['2027-02-01','2027-02-02']] as $id=>$dates) {
    ActionComm::$records[$id]=array_map('strtotime',$dates);
    $m=new CalDAVEventMapping(null); $m->fk_actioncomm=$id; $m->caldav_uid='uid-'.$id; CalDAVEventMapping::$mappings[]=$m;
}
// Remote uid-2 moved beyond the former +12-month window; uid-1 remains historical.
$ical1="BEGIN:VEVENT\r\nUID:uid-1\r\nDTSTART:20250101T000000Z\r\nDTEND:20250102T000000Z\r\nEND:VEVENT";
$ical2="BEGIN:VEVENT\r\nUID:uid-2\r\nDTSTART:20300101T000000Z\r\nDTEND:20300102T000000Z\r\nEND:VEVENT";
$xml='<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">';
foreach ([$ical1,$ical2] as $ical) $xml.='<d:response><d:propstat><d:status>HTTP/1.1 200 OK</d:status><d:prop><c:calendar-data>'.htmlspecialchars($ical,ENT_XML1).'</c:calendar-data></d:prop></d:propstat></d:response>';
$xml.='</d:multistatus>';
CalDAVClient::$response=['code'=>207,'body'=>$xml];
check($sync->invoke('syncCalDAVToDolibarr',$calendar,(object)[])===2,'Complete REPORT must import both mapped events');
check(strpos(CalDAVClient::$requestXml,'time-range')===false,'Sync REPORT must contain no date filter');
check(ActionComm::$calls===[['update',1],['update',1],['delete',1]],'Moved and historical remote events must survive; missing event must delete without triggers');
check(CalDAVEventMapping::$deleted===[3],'Successful deletion must remove only missing mapping');
(new CalDAVClient)->getEvents($calendar->url,clone $start,clone $end,false);
check(strpos(CalDAVClient::$requestXml,'time-range')!==false,'UI REPORT must retain date filter');
$event=['uid'=>'remote','summary'=>'fixture','start'=>new DateTime('2026-06-01'),'end'=>new DateTime('2026-06-02')];
$sync->invoke('createEventInDolibarr',$event,$calendar);
$sync->invoke('updateEventInDolibarr',$event,CalDAVEventMapping::$mappings[1],$calendar);
check(array_slice(ActionComm::$calls,-2)===[['create',1],['update',1]],'Inbound writes must suppress outbound triggers');
$before=ActionComm::$calls;
foreach ([['code'=>503,'body'=>'unavailable'],['code'=>207,'body'=>''],['code'=>207,'body'=>'<broken'],['code'=>207,'body'=>'<other/>'],['code'=>207,'body'=>'<d:multistatus xmlns:d="DAV:"><d:response><d:status>HTTP/1.1 403 Forbidden</d:status></d:response></d:multistatus>'],['code'=>207,'body'=>'<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:response><c:calendar-data>BEGIN:VEVENT
UID:broken
END:VEVENT</c:calendar-data></d:response></d:multistatus>']] as $response) {
    CalDAVClient::$response=$response;
    check($sync->invoke('syncCalDAVToDolibarr',$calendar,(object)[])===-1,'Incomplete REPORT must fail sync');
    check(ActionComm::$calls===$before,'Failed REPORT must not mutate events');
}
CalDAVClient::$response=['code'=>207,'body'=>'<d:multistatus xmlns:d="DAV:"/>'];
check((new CalDAVClient)->getEvents($calendar->url,clone $start,clone $end,false)===[],'Valid empty REPORT must remain valid');
CalDAVEventMapping::$mappings=[CalDAVEventMapping::$mappings[2]];
CalDAVEventMapping::$failDelete=true;
check($sync->invoke('syncCalDAVToDolibarr',$calendar,(object)[])===-1,'Mapping deletion failure must fail sync');
check($sync->error==='fixture mapping failure','Mapping deletion failure must retain error');
echo "Sync safety checks passed\n";
