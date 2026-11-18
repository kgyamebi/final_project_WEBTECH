<?php
declare(strict_types=1);

putenv('DB_NAME=insideaccra_test');
$_ENV['DB_NAME'] = 'insideaccra_test';

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\DisruptionEngine;
use App\Services\JourneyPlanner;
use App\Services\ModerationService;
use App\Services\PlaceSearch;
use App\Services\ReportService;
use App\Services\SeedData;
use App\ValidationException;

$failures = 0;
function check(bool $cond, string $message): void
{
    global $failures;
    if ($cond) {
        echo "PASS  $message\n";
        return;
    }
    $failures++;
    echo "FAIL  $message\n";
}

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
run_sql_script($pdo, (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
SeedData::run();

check(gate(null, 'report.moderate') === false, 'guest cannot moderate');
check(gate(['role_name' => 'user'], 'admin.manage') === false, 'user cannot administer');
check(gate(['role_name' => 'moderator'], 'report.moderate') === true, 'moderator can review');
check(gate(['role_name' => 'moderator'], 'admin.manage') === false, 'moderator cannot administer');
check(gate(['role_name' => 'admin'], 'admin.manage') === true, 'admin can administer');

$leaky = public_incident([
    'id' => 1,
    'public_ref' => 'IA-X',
    'title' => 'T',
    'description' => 'D',
    'category_name' => 'Flooding / drainage',
    'category_slug' => 'flooding',
    'status' => 'pending_review',
    'severity' => 'low',
    'latitude' => 5.6,
    'longitude' => -0.2,
    'place_label' => 'X',
    'observed_at' => utc_now(),
    'created_at' => utc_now(),
    'updated_at' => utc_now(),
    'resolved_at' => null,
    'is_demo' => 0,
    'duplicate_of_id' => null,
    'email' => 'secret@example.com',
    'reporter_id' => 9,
    'phone' => '0200000000',
]);
check(!array_key_exists('email', $leaky) && !array_key_exists('reporter_id', $leaky) && !array_key_exists('phone', $leaky), 'public incident hides contact details');
check(str_contains($leaky['trust_label'], 'pending verification'), 'pending reports are labelled unverified');

$madina = PlaceSearch::resolve('Madina');
$circle = PlaceSearch::resolve('Circle');
$kaneshie = PlaceSearch::resolve('Kaneshie');
check($madina['status'] === 'ok' && $circle['status'] === 'ok', 'sample place names resolve');
check(PlaceSearch::resolve('Station')['status'] === 'ambiguous', 'ambiguous place names are not guessed');
check(PlaceSearch::resolve('Kumasi')['status'] === 'none', 'unknown places are not invented');

$direct = JourneyPlanner::search($madina['place'], $circle['place']);
check(count($direct['options']) >= 1 && $direct['options'][0]['transfers'] === 0, 'Madina to Circle returns a direct journey');
check($direct['options'][0]['legs'][0]['code'] === 'IA-01', 'direct journey uses stored route IA-01');
check($direct['options'][0]['legs'][0]['fare_label'] === 'Fare not verified', 'missing fare is labelled not verified');
check(in_array('Duration not verified', $direct['options'][0]['limitations'], true), 'duration is not invented');
$codes = array_map(static fn (array $leg): string => $leg['code'], $direct['options'][0]['legs']);
check($codes === ['IA-01'], 'direct result does not add an extra route');

$transfer = JourneyPlanner::search($madina['place'], $kaneshie['place']);
check($transfer['options'] !== [] && $transfer['options'][0]['transfers'] === 1, 'Madina to Kaneshie requires one transfer');
$legCodes = array_map(static fn (array $leg): string => $leg['code'], $transfer['options'][0]['legs']);
check($legCodes === ['IA-01', 'IA-03'], 'transfer uses Circle corridor routes IA-01 then IA-03');

$none = JourneyPlanner::search(
    ['label' => 'East', 'lat' => 5.70, 'lng' => -0.13],
    ['label' => 'Far east', 'lat' => 5.72, 'lng' => -0.125]
);
check($none['options'] === [], 'coordinates without a stored route produce no journey');

$pending = q_one('SELECT id FROM reports WHERE public_ref = "IA-DEMO-POTHOLE"');
$pendingImpact = q_one('SELECT id FROM affected_routes WHERE report_id = ? AND active = 1', [(int) $pending['id']]);
check($pendingImpact === null, 'pending report does not create an active warning');

$flood = q_one('SELECT id FROM reports WHERE public_ref = "IA-DEMO-FLOOD"');
$floodImpact = q_one(
    'SELECT a.reason, a.calculated_at, a.impact_level FROM affected_routes a JOIN routes rt ON rt.id = a.route_id WHERE a.report_id = ? AND a.active = 1 AND rt.code = "IA-01"',
    [(int) $flood['id']]
);
check($floodImpact !== null && in_array($floodImpact['impact_level'], ['likely', 'possible'], true), 'verified flood flags IA-01');
check($floodImpact && str_contains($floodImpact['reason'], 'Proximity does not mean the road is closed'), 'warning explains it is not a closure');
check($floodImpact && $floodImpact['calculated_at'] !== null, 'warning stores calculation time');

$resolved = q_one('SELECT id FROM reports WHERE public_ref = "IA-DEMO-LAPAZ"');
$resolvedImpact = q_one('SELECT id FROM affected_routes WHERE report_id = ? AND active = 1', [(int) $resolved['id']]);
check($resolvedImpact === null, 'resolved report has no active warning');

$map = \App\Services\MapData::payload([]);
$refs = array_column($map['incidents'], 'public_ref');
check(!in_array('IA-DEMO-REJECT', $refs, true), 'rejected reports stay off the public map');

$ama = q_one('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE email = "ama@example.com"');
$mod = q_one('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE email = "kofi.mod@example.com"');
$created = ReportService::create($ama, [
    'category_id' => (string) q_one('SELECT id FROM report_categories WHERE slug = "flooding"')['id'],
    'title' => 'Test flood beside the sample line',
    'description' => 'A test report placed on the sample corridor for the disruption check.',
    'latitude' => '5.6297',
    'longitude' => '-0.1763',
    'place_label' => 'Test point',
    'observed_at' => gmdate('Y-m-d H:i:s', time() - 3600),
    'severity' => 'high',
], []);
$active = q_one('SELECT id FROM affected_routes WHERE report_id = ? AND active = 1', [(int) $created['id']]);
check($active === null, 'new submission stays unverified and unflagged');

$threw = false;
try {
    ModerationService::changeStatus($mod, (int) $created['id'], ['status' => 'verified', 'reason' => 'short']);
} catch (ValidationException $e) {
    $threw = isset($e->fields['reason']);
}
check($threw, 'status change requires a reason');

ModerationService::changeStatus($mod, (int) $created['id'], [
    'status' => 'verified',
    'reason' => 'Checked against the sample corridor for the automated test.',
    'severity' => 'high',
]);
$verifiedImpact = q_one('SELECT impact_level, reason, calculated_at FROM affected_routes WHERE report_id = ? AND active = 1', [(int) $created['id']]);
check($verifiedImpact !== null, 'verification creates a route warning');

ModerationService::changeStatus($mod, (int) $created['id'], [
    'status' => 'resolved',
    'reason' => 'Test cleared the sample report so the warning should end.',
]);
$after = q_one('SELECT id FROM affected_routes WHERE report_id = ? AND active = 1', [(int) $created['id']]);
check($after === null, 'resolving a report clears the active warning');

$history = q_one('SELECT COUNT(*) AS c FROM report_status_history WHERE report_id = ?', [(int) $created['id']]);
check((int) $history['c'] >= 3, 'submission and status changes are recorded');

$other = ReportService::find((int) $created['id']);
check((int) $other['reporter_id'] === (int) $ama['id'], 'report keeps the reporting user');
$stranger = ['id' => 99999, 'role_name' => 'user'];
check(!gate($stranger, 'report.moderate'), 'another user role cannot moderate');

echo $failures === 0 ? "ALL PASSED\n" : "$failures FAILED\n";
exit($failures === 0 ? 0 : 1);
