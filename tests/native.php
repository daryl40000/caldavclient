<?php
// Native database checks are restricted to the existing disposable test runtime.
if (PHP_SAPI !== 'cli' || getenv('DOLI_DB_NAME') !== 'mietvertrag_test' || getenv('DOLI_DB_HOST') !== 'coni-mietvertrag-db') { fwrite(STDERR, "Dedicated test runtime required.\n"); exit(1); }
define('NOLOGIN', 1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/core/modules/modCalDAvClient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/caldavclient/class/caldavsync.class.php';
function nativeCheck($ok, $message) { if (!$ok) throw new RuntimeException($message); }
class NativeFailingMapping extends CalDAVEventMapping
{
    public function update($direction = null, $etag = null, $local_sync_hash = null) { $this->error = 'Injected mapping update failure'; return -1; }
    public function delete() { $this->error = 'Injected mapping delete failure'; return -1; }
}
function nativeActionCount($db) {
    $result=$db->query('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.'actioncomm');
    nativeCheck((bool) $result,'Native action count failed');
    return (int) $db->fetch_object($result)->total;
}
function nativeActionSnapshot($db,$id) {
    $result=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.'actioncomm WHERE id='.(int) $id);
    nativeCheck((bool) $result,'Native action snapshot failed');
    return $db->fetch_object($result);
}
$user = new User($db);
nativeCheck($user->fetch('', 'testadmin') > 0, 'Test user missing');
$user->getrights();
$module = new modCalDAvClient($db);
$upgrade = new ReflectionMethod(modCalDAvClient::class, 'applyDatabaseFixes');
nativeCheck($upgrade->invoke($module) > 0, 'Initial schema upgrade failed');
nativeCheck($upgrade->invoke($module) > 0, 'Repeated schema upgrade failed');
$r = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX."caldav_event_mapping LIKE 'local_sync_hash'");
$column = $r ? $db->fetch_object($r) : null;
nativeCheck($column && $column->Type === 'varchar(64)' && $column->Null === 'YES', 'Nullable sync baseline missing');
$conf->global->MAIN_STORE_FULL_EVENT_IN_GMT = 'gmt';
$conf->global->MAIN_DOLIBARR_USER_TIMEZONE = 'Europe/Berlin';
$db->begin();
try {
    $connection = new CalDAVConnection($db);
    $connection->name = 'Fictitious native date test'; $connection->url = 'https://example.invalid';
    $connection->username = 'fixture'; $connection->password = 'fixture';
    $connection->enabled = 0; $connection->active_by_default = 0;
    nativeCheck($connection->create($user, 1) > 0, 'Fixture connection failed');
    $calendar = new CalDAVCalendar($db);
    $calendar->fk_connection = $connection->id; $calendar->name = 'Fixture calendar';
    $calendar->url = 'https://example.invalid/calendar/'; $calendar->enabled = 0; $calendar->active_by_default = 0;
    nativeCheck($calendar->create($user, 1) > 0, 'Fixture calendar failed');
    $sync = new CalDAVSync($db); $client = new CalDAVClient($connection);
    $convert = new ReflectionMethod(CalDAVSync::class, 'convertDolibarrToEventArray');
    $generate = new ReflectionMethod(CalDAVClient::class, 'generateICalendar');
    $parse = new ReflectionMethod(CalDAVClient::class, 'parseICalendar');
    $hash = new ReflectionMethod(CalDAVSync::class, 'localSyncHash');
    foreach (array(true, false) as $allDay) {
        $action = new ActionComm($db); $action->type_code = 'AC_OTH';
        $action->label = 'Fictitious calendar date test'; $action->note_private = 'Fixture only'; $action->location = 'Fixture';
        $action->datep = strtotime('2026-03-29T00:00:00Z');
        $action->datef = $action->datep + ($allDay ? 86399 : 3600);
        $action->fulldayevent = (int) $allDay; $action->userownerid = $user->id; $action->percentage = 0;
        nativeCheck($action->create($user, 1) > 0, 'Fixture event failed');
        nativeCheck($action->fetch($action->id) > 0, 'Fixture readback failed');
        $event = $convert->invoke($sync, $action, 'native-fixture-'.$action->id);
        $ical = $generate->invoke($client, $event);
        $parsed = $parse->invoke($client, $ical, 'fixture-etag')[0];
        nativeCheck($parsed['all_day'] === $allDay, 'All-day flag changed on native roundtrip');
        nativeCheck($parsed['start']->getTimestamp() === $action->datep, 'Native start shifted');
        nativeCheck($parsed['end']->getTimestamp() - ($allDay ? 1 : 0) === $action->datef, 'Native end shifted');
        $baseline = $hash->invoke($sync, $action);
        $mapping = new CalDAVEventMapping($db);
        nativeCheck($mapping->create($action->id, $event['uid'], $calendar->id, 'dolibarr', 'etag-1', $baseline) > 0, 'Native baseline creation failed');
        nativeCheck($mapping->fetchByActionCommAndCalendar($action->id, $calendar->id) > 0 && $mapping->local_sync_hash === $baseline, 'Native baseline readback failed');
        $action->label .= ' changed'; $changed = $hash->invoke($sync, $action);
        nativeCheck($changed !== $baseline && $mapping->update('dolibarr', 'etag-2', $changed) > 0, 'Native baseline update failed');
        nativeCheck($mapping->fetchByCalDAVUID($event['uid'], $calendar->id) > 0 && $mapping->local_sync_hash === $changed && $mapping->caldav_etag === 'etag-2', 'Native updated state readback failed');
    }
    // These checks deliberately run inside the fixture transaction: nested native rollback
    // only decrements Dolibarr's counter, so the sync operation must use a real savepoint.
    $createInbound = new ReflectionMethod(CalDAVSync::class, 'createEventInDolibarr');
    $updateInbound = new ReflectionMethod(CalDAVSync::class, 'updateEventInDolibarr');
    $deleteInbound = new ReflectionMethod(CalDAVSync::class, 'deleteEventInDolibarr');
    $beforeCount = nativeActionCount($db);
    nativeCheck($createInbound->invoke($sync,$parsed,$calendar) < 0, 'Duplicate remote UID must fail mapping creation');
    nativeCheck(nativeActionCount($db) === $beforeCount, 'Mapping creation failure left a native orphan');
    nativeCheck($action->fetch($action->id) > 0, 'Original fixture was lost by nested rollback');
    $expectedHash = $hash->invoke($sync,$action);
    $beforeRecord = nativeActionSnapshot($db,$action->id);
    $failingMapping = new NativeFailingMapping($db);
    foreach (get_object_vars($mapping) as $property=>$value) $failingMapping->$property=$value;
    $changedRemote = $parsed;
    $changedRemote['summary'] = 'Fictitious incoming change that must roll back';
    $changedRemote['etag'] = 'remote-changed';
    nativeCheck($updateInbound->invoke($sync,$changedRemote,$failingMapping,$calendar,$expectedHash) < 0, 'Injected mapping update must fail sync');
    nativeCheck(nativeActionSnapshot($db,$action->id) == $beforeRecord, 'Mapping update failure changed native fields');
    nativeCheck($deleteInbound->invoke($sync,$failingMapping,$expectedHash) < 0, 'Injected mapping deletion must fail sync');
    nativeCheck(nativeActionSnapshot($db,$action->id) == $beforeRecord, 'Mapping deletion failure removed native event');
    $mappingReadback = new CalDAVEventMapping($db);
    nativeCheck($mappingReadback->fetchByActionCommAndCalendar($action->id,$calendar->id) > 0
        && $mappingReadback->local_sync_hash === $mapping->local_sync_hash
        && $mappingReadback->caldav_etag === $mapping->caldav_etag, 'Failed inbound operations advanced native mapping');
    nativeCheck(nativeActionCount($db) === $beforeCount, 'Atomicity checks changed fixture population');

} finally { $db->rollback(); }
echo "Native schema, date roundtrip and atomic rollback checks passed\n";
