<?php
// Isolated date contracts: no database, network or real ActionComm writes.
require_once __DIR__.'/../lib/caldav/caldavclient_ical.trait.php';
require_once __DIR__.'/../class/caldavsync/caldavsync_support.trait.php';
define('DOL_MAIN_URL_ROOT', 'https://dolibarr.example.invalid');
$conf = (object)['entity' => 1];
$settings = array('MAIN_DOLIBARR_USER_TIMEZONE' => 'Europe/Berlin', 'MAIN_STORE_FULL_EVENT_IN_GMT' => 'gmt');
function getDolGlobalString($key, $default = '') { global $settings; return $settings[$key] ?? $default; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
class DateFixture {
    use CalDAVClientICalTrait, CalDAVSyncSupportTrait;
    public function parse($body) { return $this->parseICalendar("BEGIN:VEVENT\r\nUID:test\r\n".$body."\r\nEND:VEVENT", 'tag')[0]; }
    public function uid($id, $calendar) { return $this->generateCalDAVUID($id, $calendar); }
    public function convert($action) { return $this->convertDolibarrToEventArray($action, 'test'); }
    private function getMainUserIdForActionComm($action) { return 0; }
}
$f = new DateFixture;
check($f->uid(1, 2) === $f->uid(1, 2), 'UID stable across retries');
check($f->uid(1, 2) !== $f->uid(1, 3), 'UID differs per calendar');
$firstUid = $f->uid(1, 2);
$settings['MAIN_INSTANCE_UNIQUE_ID'] = 'different-instance';
check($firstUid !== $f->uid(1, 2), 'UID differs per instance');
date_default_timezone_set('Europe/Berlin');
$e = $f->parse('DTSTART:20261104T120000Z');
check($e['start']->format('c') === '2026-11-04T12:00:00+00:00', 'Z must be constructed in UTC even under Berlin process timezone');
check($e['end'] == $e['start'], 'Missing timed DTEND has zero duration');
foreach (['20260115T120000' => '+01:00', '20260715T120000' => '+02:00'] as $date => $offset) {
    $e = $f->parse('DTSTART;TZID=Europe/Berlin:'.$date);
    check($e['start']->format('P') === $offset && $e['start']->format('H:i') === '12:00', 'TZID winter/summer wall time');
}
$e = $f->parse("DTSTART;TZID=Europe/Berlin:20260329T013000\r\nDTEND;TZID=Europe/Berlin:20260329T033000");
check($e['end']->getTimestamp() - $e['start']->getTimestamp() === 3600, 'DST transition is one elapsed hour');
$e = $f->parse("DTSTART;VALUE=DATE:20260329\r\nDTEND;VALUE=DATE:20260330");
check($e['all_day'] && $e['end']->getTimestamp() - $e['start']->getTimestamp() === 86400, 'DATE is one calendar day independent of DST');
$e = $f->parse('DTSTART;VALUE=DATE:20260329');
check($e['end']->format('Ymd') === '20260330', 'DATE without DTEND defaults to next day');
$e = $f->parse("DTSTART;VALUE=DATE:20260328\r\nDTEND;VALUE=DATE:20260331");
check($e['end']->format('Ymd') === '20260331', 'Multi-day DATE end stays exclusive');
$action = (object)['datep' => $e['start']->getTimestamp(), 'datef' => $e['end']->getTimestamp()-1, 'fulldayevent' => 1, 'label' => 'test', 'note_private' => '', 'location' => ''];
$converted = $f->convert($action);
check($converted['start'] == $e['start'] && $converted['end'] == $e['end'], 'Native inclusive GMT all-day storage roundtrip');
$action->datef = $action->datep+86399;
check($f->convert($action)['end']->format('Ymd') === '20260329', 'Native single UTC day converts to next DATE boundary');
$action->fulldayevent = 0;
$action->datep = 1793793600;
$action->datef = $action->datep+3600;
$converted = $f->convert($action);
check($converted['start']->getTimestamp() === $action->datep && $converted['end']->getTimestamp() === $action->datef, 'Timed native epochs do not shift');
$e = $f->parse("DTSTART:20261104T120000\r\nSUMMARY:A\\,B\\;C\\nD\\\\E &amp; test\r\nLAST-MODIFIED:20261103T120000Z\r\nRRULE:FREQ=DAILY");
check($e['start']->format('P') === '+01:00', 'Floating timestamp uses configured native timezone');
check($e['summary'] === "A,B;C\nD\\E &amp; test", 'Unescape TEXT once without HTML entity decode');
check($e['last_modified']->format('c') === '2026-11-03T12:00:00+00:00', 'LAST-MODIFIED available for conflict decisions');
check($e['unsupported_recurrence'] && strpos($e['raw_ical'], 'RRULE:') !== false, 'Recurrence is flagged with source retained');
$settings['MAIN_DOLIBARR_USER_TIMEZONE'] = 'UTC';
check($f->parse('DTSTART:20261104T120000')['start']->format('P') === '+00:00', 'Explicit UTC is respected');
$settings['MAIN_DOLIBARR_USER_TIMEZONE'] = '';
check($f->parse('DTSTART:20261104T120000')['start']->format('P') === '+01:00', 'Missing configuration falls back to native process timezone');
foreach (['DTSTART:20260230T120000Z', 'DTSTART;VALUE=DATE:20260230', 'DTSTART;TZID=Europe/Berlin:20260329T023000', "DTSTART:20261104T120000Z\r\nDTEND:20261104T110000Z", 'DTSTART;VALUE=DATE-TIME:20261104'] as $bad) {
    try { $f->parse($bad); throw new RuntimeException('Invalid date accepted: '.$bad); }
    catch (UnexpectedValueException $expected) {}
}
echo "Date checks passed\n";
