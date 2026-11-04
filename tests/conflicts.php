<?php
// Isolated contract checks: no real database, network or Dolibarr bootstrap.
require __DIR__.'/../class/caldavsync/caldavsync_inbound.trait.php';
require __DIR__.'/../class/caldavsync/caldavsync_outbound.trait.php';
require __DIR__.'/../lib/caldav/caldavclient_fetch.trait.php';
require __DIR__.'/../lib/caldav/caldavclient_ical.trait.php';
require __DIR__.'/../lib/caldav/caldavclient_crud.trait.php';
define('DOL_DATA_ROOT',sys_get_temp_dir());
define('MAIN_DB_PREFIX','llx_');
function dol_syslog(...$args) {}
function getDolGlobalInt($name) { return 0; }
function getDolGlobalString($name,$fallback='') { return $name==='MAIN_DOLIBARR_USER_TIMEZONE' ? 'Europe/Berlin' : $fallback; }
function check($condition,$message) { if (!$condition) throw new RuntimeException($message); }
#[AllowDynamicProperties]
class ActionComm {
    public static $records=[], $writes=[];
    public function __construct($db) {}
    public function fetch($id) { if (!isset(self::$records[$id])) return 0; foreach(self::$records[$id] as $k=>$v) $this->$k=$v; return 1; }
    public function update($user,$notrigger=0) { check($notrigger===1,'Inbound update triggered outbound'); self::$records[$this->id]=get_object_vars($this); self::$writes[]='update'; return 1; }
    public function delete($user,$notrigger=0) { check($notrigger===1,'Inbound delete triggered outbound'); unset(self::$records[$this->id]); self::$writes[]='delete'; return 1; }
    public function create($user,$notrigger=0) { check($notrigger===1,'Inbound create triggered outbound'); $this->id=10; $this->entity=1; self::$records[10]=get_object_vars($this); self::$writes[]='create'; return 10; }
}
class CalDAVEventMapping {
    public static $records=[], $writes=[], $failWrite=null;
    public $fk_actioncomm=1,$caldav_uid='uid',$fk_calendar=1,$local_sync_hash,$caldav_etag='"old"',$error;
    public function __construct($db) {}
    public static function getAllByCalendar($db,$id) { return array_values(array_filter(self::$records,fn($m)=>$m->fk_calendar===$id)); }
    public function update($direction=null,$etag=null,$hash=null) { if(self::$failWrite==='update') { $this->error='fixture update failure'; return -1; } $this->caldav_etag=$etag; $this->local_sync_hash=$hash; self::$writes[]='update'; return 1; }
    public function create($local,$uid,$cal,$direction,$etag,$hash=null) { if(self::$failWrite==='create') { $this->error='fixture create failure'; return -1; } $this->fk_actioncomm=$local; $this->caldav_uid=$uid; $this->fk_calendar=$cal; $this->caldav_etag=$etag; $this->local_sync_hash=$hash; self::$records[]=$this; self::$writes[]='create'; return 1; }
    public function delete() { if(self::$failWrite==='delete') { $this->error='fixture delete failure'; return -1; } self::$writes[]='delete'; return 1; }
}
class CalDAVCalendar {
    public $id=1,$entity=1,$enabled=1,$fk_connection=1,$url='https://example.invalid/cal/',$name='fixture';
    public function __construct($db) {}
    public function fetch($id) { $this->id=$id; return 1; }
}
class CalDAVConnection {
    public $entity=1,$enabled=1;
    public function __construct($db) {}
    public function fetch($id) { return 1; }
}
class FixtureDB {
    public $sql=[], $lockAvailable=true, $beforeBegin, $snapshot;
    public function begin() {
        if ($this->beforeBegin) { ($this->beforeBegin)(); $this->beforeBegin=null; }
        $this->snapshot=serialize([ActionComm::$records,ActionComm::$writes,CalDAVEventMapping::$records,CalDAVEventMapping::$writes]); return 1;
    }
    public function commit() { $this->snapshot=null; return 1; }
    public function rollback() { if($this->snapshot!==null) [ActionComm::$records,ActionComm::$writes,CalDAVEventMapping::$records,CalDAVEventMapping::$writes]=unserialize($this->snapshot); $this->snapshot=null; return 1; }
    public function query($sql) {
        $this->sql[]=$sql; $rows=[];
        if (strpos($sql,'GET_LOCK')!==false) $rows=[(object)['acquired'=>(int) $this->lockAvailable]];
        if (strpos($sql,'SELECT a.id FROM')===0) {
            preg_match('/m.fk_calendar=(\d+)/',$sql,$match); $calendar=(int) $match[1];
            foreach (ActionComm::$records as $id=>$record) {
                $mapped=false; foreach(CalDAVEventMapping::$records as $m) if($m->fk_calendar===$calendar && $m->fk_actioncomm===$id) $mapped=true;
                if (!$mapped) $rows[]=(object)['id'=>$id];
            }
        }
        return (object)['rows'=>$rows];
    }
    public function fetch_object($res) { return array_shift($res->rows); }
    public function escape($value) { return addslashes($value); }
    public function lasterror() { return 'fixture failure'; }
}
class CalDAVClient {
    use CalDAVClientFetchTrait,CalDAVClientICalTrait,CalDAVClientCrudTrait;
    public static $events=[], $requests=[], $putCode=204;
    public function __construct($connection=null) {}
    private function request($method,$url,$body=null,$headers=[]) {
        self::$requests[]=[$method,$url,$body,$headers];
        if ($method==='REPORT') {
            $xml='<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">';
            foreach(self::$events as $event) $xml.='<d:response><d:href>'.htmlspecialchars($event['href'],ENT_XML1).'</d:href><d:propstat><d:status>HTTP/1.1 200 OK</d:status><d:prop><d:getetag>'.htmlspecialchars($event['etag'],ENT_XML1).'</d:getetag><c:calendar-data>'.htmlspecialchars($event['raw'],ENT_XML1).'</c:calendar-data></d:prop></d:propstat></d:response>';
            return ['code'=>207,'body'=>$xml.'</d:multistatus>'];
        }
        if ($method==='PUT' && self::$putCode===204) {
            self::$events[0]=['href'=>$url,'etag'=>'"new"','raw'=>$body];
        }
        return ['code'=>self::$putCode,'body'=>'','headers'=>"ETag: \"new\"\r\n"];
    }
}
class SyncFixture {
    use CalDAVSyncInboundTrait,CalDAVSyncOutboundTrait;
    public $db,$error;
    public function __construct() { $this->db=new FixtureDB; }
    public function run($calendar) { return $this->syncBidirectional($calendar,(object)[]); }
    public function hash($local) { return $this->localSyncHash($local); }
    private function isSystemAutoActionComm($local) { return false; }
    private function generateCalDAVUID($id,$cal) { return 'local-'.$id.'-'.$cal; }
    private function convertDolibarrToEventArray($local,$uid,$calendar) { return ['uid'=>$uid,'summary'=>$local->label,'description'=>$local->note_private,'location'=>$local->location,'start'=>new DateTime('@'.$local->datep),'end'=>new DateTime('@'.$local->datef),'all_day'=>false]; }
}
$user=(object)['id'=>1]; $conf=(object)['entity'=>1];
$calendar=(object)['id'=>1,'url'=>'https://example.invalid/cal/','name'=>'fixture'];
function setupFixture() {
    $sync=new SyncFixture;
    ActionComm::$records=[1=>['id'=>1,'entity'=>1,'label'=>'Original','note_private'=>'','location'=>'','datep'=>strtotime('2026-06-01T12:00:00Z'),'datef'=>strtotime('2026-06-01T13:00:00Z'),'fulldayevent'=>0,'datem'=>100]];
    $local=new ActionComm(null); $local->fetch(1);
    $mapping=new CalDAVEventMapping(null); $mapping->local_sync_hash=$sync->hash($local);
    CalDAVEventMapping::$records=[$mapping]; CalDAVEventMapping::$writes=[]; CalDAVEventMapping::$failWrite=null; ActionComm::$writes=[];
    CalDAVClient::$requests=[]; CalDAVClient::$putCode=204;
    CalDAVClient::$events=[['href'=>'/cal/actual-resource.ics','etag'=>'"old"','raw'=>"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:uid\r\nSUMMARY:Original\r\nDTSTART:20260601T120000Z\r\nDTEND:20260601T130000Z\r\nX-UNKNOWN:keep\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n"]];
    return $sync;
}
$sync=setupFixture();
check($sync->run($calendar)===0,'Unchanged sync must be no-op');
check(ActionComm::$writes===[] && CalDAVEventMapping::$writes===[],'No timestamp churn when unchanged');
$sync=setupFixture(); CalDAVClient::$events[0]['etag']='"remote"'; CalDAVClient::$events[0]['raw']=str_replace('SUMMARY:Original','SUMMARY:Remote',CalDAVClient::$events[0]['raw']);
check($sync->run($calendar)===1 && ActionComm::$records[1]['label']==='Remote','Remote-only change must import');
check(count(array_filter(CalDAVClient::$requests,fn($r)=>$r[0]==='PUT'))===0,'Import must not write remote');
check($sync->run($calendar)===0,'Repeated remote import must be no-op');
$sync=setupFixture(); ActionComm::$records[1]['label']='Local'; // datem deliberately unchanged: same-second edit.
check($sync->run($calendar)===1,'Same-second local-only change must export');
$put=array_values(array_filter(CalDAVClient::$requests,fn($r)=>$r[0]==='PUT'))[0];
check($put[1]==='https://example.invalid/cal/actual-resource.ics','Must use actual href, not UID.ics');
check(in_array('If-Match: "old"',$put[3],true),'Update must be conditional');
check(strpos($put[2],'X-UNKNOWN:keep')!==false,'Unknown remote properties must survive');
check($sync->run($calendar)===0,'Repeated export must be no-op');
$sync=setupFixture(); ActionComm::$records[1]['label']='Local'; CalDAVClient::$events[0]['etag']='"remote"';
check($sync->run($calendar)===-1 && strpos($sync->error,'Conflict')!==false,'Simultaneous same-second changes must conflict');
check(ActionComm::$writes===[] && CalDAVEventMapping::$writes===[] && count(CalDAVClient::$requests)===1,'Conflict must retain both sides and baseline');
$sync=setupFixture(); ActionComm::$records[1]['label']='Local'; CalDAVClient::$putCode=412;
check($sync->run($calendar)===-1 && CalDAVEventMapping::$writes===[],'HTTP412 must not advance mapping');
$sync=setupFixture(); CalDAVEventMapping::$records[0]->fk_calendar=2; CalDAVClient::$events=[];
check($sync->run($calendar)===1,'Other-calendar mapping must not prevent initial export');
$put=array_values(array_filter(CalDAVClient::$requests,fn($r)=>$r[0]==='PUT'))[0];
check(in_array('If-None-Match: *',$put[3],true),'Initial export must never overwrite an existing resource');
check($sync->run($calendar)===0,'Initial export rerun must be no-op');
check(strpos(implode("\n",$sync->db->sql),'m.fk_calendar=1')!==false,'Unmapped query must scope mapping join to calendar');
$sync=setupFixture(); ActionComm::$records[1]['label']='Local'; CalDAVClient::$events[0]['href']='https://evil.invalid/cal/event.ics';
check($sync->run($calendar)===-1 && count(CalDAVClient::$requests)===1,'Cross-origin href must never be written');
$sync=setupFixture(); ActionComm::$records[1]['label']='Local';
CalDAVClient::$events[0]['raw']=str_replace('BEGIN:VEVENT',"BEGIN:VTIMEZONE\r\nTZID:Custom\r\nBEGIN:STANDARD\r\nDTSTART:19701025T030000\r\nEND:STANDARD\r\nEND:VTIMEZONE\r\nBEGIN:VEVENT",CalDAVClient::$events[0]['raw']);
CalDAVClient::$events[0]['raw']=str_replace('END:VEVENT',"BEGIN:VALARM\r\nACTION:DISPLAY\r\nDESCRIPTION:Keep reminder\r\nTRIGGER:-PT15M\r\nEND:VALARM\r\nEND:VEVENT",CalDAVClient::$events[0]['raw']);
check($sync->run($calendar)===1,'Alarm metadata must permit safe local update');
$raw=CalDAVClient::$events[0]['raw'];
check(strpos($raw,'DTSTART:19701025T030000')!==false && strpos($raw,'DESCRIPTION:Keep reminder')!==false,'VTIMEZONE and nested VALARM properties must survive');
$sync=setupFixture(); ActionComm::$records[1]['label']='Local'; CalDAVClient::$events[0]['raw']=str_replace('END:VEVENT',"ATTENDEE:mailto:invitee@example.invalid\r\nEND:VEVENT",CalDAVClient::$events[0]['raw']);
check($sync->run($calendar)===-1 && count(CalDAVClient::$requests)===1,'Attendee updates must conflict without invitations');
$sync=setupFixture(); CalDAVClient::$events[0]['etag']='"metadata-only"';
check($sync->run($calendar)===0 && ActionComm::$writes===[],'Metadata-only remote change must not rewrite local event');
$sync=setupFixture(); CalDAVEventMapping::$records[0]->local_sync_hash=null;
check($sync->run($calendar)===0 && count(CalDAVEventMapping::$writes)===1,'Identical legacy mapping may bootstrap baseline');
$sync=setupFixture(); CalDAVEventMapping::$records[0]->local_sync_hash=null; ActionComm::$records[1]['label']='Diverged';
check($sync->run($calendar)===-1 && CalDAVEventMapping::$writes===[],'Diverged legacy mapping must conflict');
$sync=setupFixture(); $sync->db->lockAvailable=false;
check($sync->syncCalendar(1,'bidirectional')===-1 && CalDAVClient::$requests===[],'Busy calendar lock must prevent all remote operations');
$sync=setupFixture();
check($sync->syncCalendar(1,'bidirectional')===0,'Unlocked calendar must synchronize');
check(strpos(end($sync->db->sql),'RELEASE_LOCK')!==false,'Calendar lock must always release');
$sync=setupFixture(); CalDAVClient::$events[0]['etag']='';
check($sync->syncCalendar(1,'bidirectional')===-1,'Incomplete snapshot must fail');
check(strpos(end($sync->db->sql),'RELEASE_LOCK')!==false,'Calendar lock must release on failed snapshot');
$sync=setupFixture(); unset(ActionComm::$records[1]);
check($sync->run($calendar)===1,'Local deletion must propagate to unchanged remote');
$delete=array_values(array_filter(CalDAVClient::$requests,fn($r)=>$r[0]==='DELETE'))[0];
check($delete[1]==='https://example.invalid/cal/actual-resource.ics' && in_array('If-Match: "old"',$delete[3],true),'Remote deletion must use actual href and ETag');
$sync=setupFixture(); CalDAVClient::$events=[];
check($sync->run($calendar)===1 && ActionComm::$writes===['delete'],'Remote deletion must remove unchanged native event');
$sync=setupFixture(); CalDAVClient::$events=[]; ActionComm::$records[1]['label']='Changed locally';
check($sync->run($calendar)===-1 && ActionComm::$writes===[],'Remote deletion versus local edit must conflict');
$sync=setupFixture(); CalDAVClient::$events[0]['etag']='"remote"'; CalDAVClient::$events[0]['raw']=str_replace('SUMMARY:Original','SUMMARY:Remote',CalDAVClient::$events[0]['raw']);
$sync->db->beforeBegin=function() { ActionComm::$records[1]['label']='Concurrent native edit'; };
check($sync->run($calendar)===-1 && ActionComm::$records[1]['label']==='Concurrent native edit','Fresh native edit between decision and row lock must survive');
check(ActionComm::$writes===[] && CalDAVEventMapping::$writes===[],'Concurrent edit must not advance mapping or mutate local');
$sync=setupFixture(); CalDAVClient::$events=[];
$sync->db->beforeBegin=function() { ActionComm::$records[1]['label']='Concurrent native edit'; };
check($sync->run($calendar)===-1 && isset(ActionComm::$records[1]),'Concurrent native edit must prevent deletion');
foreach (['create','update','delete'] as $operation) {
    $sync=setupFixture(); CalDAVEventMapping::$failWrite=$operation;
    if ($operation==='create') { ActionComm::$records=[]; CalDAVEventMapping::$records=[]; }
    elseif ($operation==='update') { CalDAVClient::$events[0]['etag']='"remote"'; CalDAVClient::$events[0]['raw']=str_replace('SUMMARY:Original','SUMMARY:Remote',CalDAVClient::$events[0]['raw']); }
    else CalDAVClient::$events=[];
    $before=serialize(ActionComm::$records);
    check($sync->run($calendar)===-1,'Mapping '.$operation.' failure must fail sync');
    check(serialize(ActionComm::$records)===$before && ActionComm::$writes===[],'Mapping '.$operation.' failure must roll back native mutation');
}
echo "Bidirectional conflict checks passed\n";
