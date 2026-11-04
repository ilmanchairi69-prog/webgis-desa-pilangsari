<?php

require dirname(__DIR__).'/vendor/autoload.php';

use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Api\PublicMapApiController;
use App\Http\Controllers\PublicMapController;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use App\Services\UserAccountMutation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$projectPath = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($projectPath)->safeLoad();
$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'] ?? '3306';
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$databaseName = 'webgis_sqa_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
if (PHP_VERSION !== '8.4.27' || ! preg_match('/^webgis_sqa_[0-9_]+[a-f0-9]{6}$/', $databaseName)) {
    throw new RuntimeException('Unsafe test database identifier or PHP runtime.');
}
$administrator = new PDO('mysql:host='.$host.';port='.$port.';charset=utf8mb4', $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$administrator->exec('CREATE DATABASE `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $databaseName, 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'null', 'BCRYPT_ROUNDS' => '4',
    'APP_CONFIG_CACHE' => $projectPath.'/storage/framework/testing/no-mysql-sqa-config.php',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require $projectPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== $databaseName || ! str_starts_with($databaseName, 'webgis_sqa_')) {
    throw new RuntimeException('Test database guard failed.');
}
$checks = [];
$check = function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
    if (! $passed) {
        throw new RuntimeException('MySQL SQA failed: '.$name);
    }
};
$results = ['database' => $databaseName, 'php' => PHP_VERSION];
try {
    $check('normal_migration', Artisan::call('migrate', ['--no-interaction' => true]) === 0);
    $check('migration_status', Artisan::call('migrate:status', ['--no-interaction' => true]) === 0);
    $check('migration_count', DB::table('migrations')->count() === 16);
    $check('table_count', (int) DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->n === 18);
    $check('foreign_keys_enabled', (int) DB::selectOne('SELECT @@foreign_key_checks AS enabled')->enabled === 1);

    $admin = User::create(['name' => 'SQA Admin', 'email' => 'admin@sqa.example.test', 'password' => bin2hex(random_bytes(12)), 'role' => 'super_admin', 'is_active' => true]);
    $layer = MapLayer::create(['name' => 'SQA layer', 'slug' => 'sqa-layer', 'layer_type' => 'point', 'color' => '#166534', 'is_public' => true, 'status' => 'published']);
    $feature = MapFeature::create([
        'layer_id' => $layer->id, 'name' => 'PRIVATE-SQA-FEATURE-NAME', 'category' => 'rumah',
        'latitude' => -7.3574198, 'longitude' => 110.9874323,
        'geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]],
        'verification_status' => 'pending', 'publication_status' => 'draft',
    ]);
    $house = House::create([
        'map_feature_id' => $feature->id, 'house_code' => 'BGN-SQA-MYSQL', 'rt_code' => '01',
        'address' => 'PRIVATE-SQA-ADDRESS', 'latitude' => -7.3574198, 'longitude' => 110.9874323,
        'verification_status' => 'pending', 'publication_status' => 'draft', 'public_visible' => true,
    ]);
    $card = FamilyCard::create(['house_id' => $house->id, 'head_name' => 'PRIVATE-SQA-HEAD', 'card_number' => 'PRIVATE-SQA-KK']);
    FamilyMember::create(['family_card_id' => $card->id, 'full_name' => 'PRIVATE-SQA-MEMBER']);
    $check('family_relations', $house->familyCards()->firstOrFail()->members()->count() === 1);
    $check('encrypted_card_storage', DB::table('family_cards')->value('card_number') !== 'PRIVATE-SQA-KK');

    $request = Request::create('/admin/houses/'.$house->id.'/publish', 'POST', ['publication_declaration' => 1]);
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);
    $request->setUserResolver(fn () => $admin);
    $controller = new VerificationController;
    $controller->verify($request, $house);
    $house->refresh();
    $check('verify_does_not_publish', $house->verification_status === 'verified' && $house->publication_status === 'draft');
    $controller->publish($request, $house);
    $house->refresh();
    $check('eligible_publish_persisted', $house->publication_status === 'published' && $house->published_by === $admin->id);
    $versioned = (new PublicMapApiController)->features($layer->slug)->getData(true);
    $unversioned = (new PublicMapController)->data()->getData(true);
    $check('eligible_versioned_visible', count($versioned['features']) === 1);
    $check('eligible_unversioned_visible', count($unversioned['features']) === 1);
    $check('public_family_privacy', ! str_contains(json_encode([$versioned, $unversioned]), 'PRIVATE-SQA'));
    $controller->unpublish($request, $house);
    $check('withdrawn_versioned_hidden', count((new PublicMapApiController)->features($layer->slug)->getData(true)['features']) === 0);
    $check('withdrawn_unversioned_hidden', count((new PublicMapController)->data()->getData(true)['features']) === 0);

    $before = User::count();
    try {
        UserAccountMutation::run(function (): void {
            User::create(['name' => 'Rollback', 'email' => 'rollback@sqa.example.test', 'password' => bin2hex(random_bytes(12))]);
            throw new RuntimeException('Controlled isolated transaction rollback');
        });
    } catch (RuntimeException $exception) {
        $check('transaction_exception_propagated', $exception->getMessage() === 'Controlled isolated transaction rollback');
    }
    $check('transaction_rolled_back', User::count() === $before);
    $lockName = 'webgis:accounts:'.sha1($databaseName);
    $lock = $administrator->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    $check('independent_connection_lock_acquired', (int) $lock->fetchColumn() === 1);
    $operationCalled = false;
    try {
        UserAccountMutation::run(function () use (&$operationCalled): void {
            $operationCalled = true;
        });
        $check('contended_account_mutation_rejected', false);
    } catch (ValidationException) {
        $check('contended_account_mutation_rejected', true);
    } finally {
        $release = $administrator->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
    $check('no_operation_during_lock_contention', ! $operationCalled);
    $check('lock_released_after_rollback', UserAccountMutation::run(fn () => true) === true);
    $free = $administrator->prepare('SELECT IS_FREE_LOCK(?)');
    $free->execute([$lockName]);
    $check('lock_free_after_success', (int) $free->fetchColumn() === 1);

    try {
        FamilyMember::create(['family_card_id' => 9999999, 'full_name' => 'SQA orphan']);
        $check('foreign_key_rejects_orphan', false);
    } catch (QueryException) {
        $check('foreign_key_rejects_orphan', true);
    }
    try {
        House::create(['house_code' => $house->house_code]);
        $check('unique_rejects_duplicate_house_code', false);
    } catch (QueryException) {
        $check('unique_rejects_duplicate_house_code', true);
    }

    $rows = [];
    for ($i = 0; $i < 500; $i++) {
        $rows[] = [
            'layer_id' => $layer->id, 'name' => 'SQA feature '.$i, 'category' => 'fasilitas',
            'latitude' => -7.3574198, 'longitude' => 110.9874323,
            'geometry' => json_encode(['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]]),
            'verification_status' => 'verified', 'publication_status' => 'published',
        ];
    }
    DB::table('map_features')->insert($rows);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $start = microtime(true);
    $response = (new PublicMapApiController)->features($layer->slug);
    $results['performance'] = [
        'features' => count($response->getData(true)['features']),
        'queries' => count(DB::getQueryLog()),
        'milliseconds' => round((microtime(true) - $start) * 1000, 2),
        'payload_bytes' => strlen($response->getContent()),
    ];
    DB::disableQueryLog();
    $check('bulk_features_visible', $results['performance']['features'] === 500);
    $check('bulk_query_count_bounded', $results['performance']['queries'] <= 5);
    $beforeRows = [House::count(), FamilyCard::count(), FamilyMember::count(), MapFeature::count()];
    $check('latest_migration_rollback_on_test_database', Artisan::call('migrate:rollback', ['--step' => 1, '--no-interaction' => true]) === 0);
    $check('latest_rollback_preserves_existing_rows', $beforeRows === [House::count(), FamilyCard::count(), FamilyMember::count(), MapFeature::count()]);
    $check('latest_migration_reapply_on_populated_test_database', Artisan::call('migrate', ['--no-interaction' => true]) === 0);
    $check('reapply_preserves_existing_rows', $beforeRows === [House::count(), FamilyCard::count(), FamilyMember::count(), MapFeature::count()]);
    $results['status'] = 'PASS';
} catch (Throwable $exception) {
    $results['status'] = 'FAIL';
    $results['exception_type'] = $exception::class;
    $results['exception_location'] = str_replace($projectPath.'/', '', str_replace('\\', '/', $exception->getFile())).':'.$exception->getLine();
    $results['failed_checks'] = array_keys(array_filter($checks, fn ($passed) => ! $passed));
} finally {
    $results['checks'] = $checks;
    $results['database_retained'] = true;
    file_put_contents($projectPath.'/storage/framework/testing/mysql-release-sqa-results.json', json_encode($results, JSON_PRETTY_PRINT));
}
echo json_encode($results, JSON_PRETTY_PRINT).PHP_EOL;
exit($results['status'] === 'PASS' ? 0 : 1);
