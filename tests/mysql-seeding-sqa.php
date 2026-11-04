<?php

require dirname(__DIR__).'/vendor/autoload.php';

use App\Models\AuditLog;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use Database\Seeders\WebGisDemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();
$name = 'webgis_sqa_seed_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
if (PHP_VERSION !== '8.4.27' || ! preg_match('/^webgis_sqa_seed_[0-9_]+[a-f0-9]{6}$/', $name)) {
    throw new RuntimeException('Unsafe test database identifier.');
}
$pdo = new PDO('mysql:host='.$_ENV['DB_HOST'].';port='.($_ENV['DB_PORT'] ?? '3306').';charset=utf8mb4', $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
foreach (['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $name, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_CONFIG_CACHE' => $root.'/storage/framework/testing/no-seed-test-config.php'] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== $name) {
    throw new RuntimeException('Test database guard failed.');
}
$checks = [];
$assert = function (string $label, bool $passed) use (&$checks): void {
    $checks[$label] = $passed;
    if (! $passed) {
        throw new RuntimeException('MySQL seeding check failed: '.$label);
    }
};
$snapshot = function (): array {
    $hashes = [];
    foreach (['users', 'map_layers', 'map_features', 'feature_categories', 'map_data_sources', 'houses', 'family_cards', 'family_members', 'facilities', 'audit_logs', 'cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs'] as $table) {
        $hashes[$table] = hash('sha256', DB::table($table)->orderByRaw('1')->get()->toJson());
    }

    return $hashes;
};
$result = ['database' => $name];
try {
    $assert('normal_test_migration', Artisan::call('migrate', ['--no-interaction' => true]) === 0);
    for ($i = 1; $i <= 3; $i++) {
        User::create(['name' => 'Synthetic seed test '.$i, 'email' => 'seed'.$i.'@sqa.example.test', 'password' => bin2hex(random_bytes(16)), 'role' => $i === 1 ? 'super_admin' : 'admin_rt', 'is_active' => true]);
    }
    $legacy = MapLayer::create(['name' => 'Fasilitas Desa', 'slug' => 'fasilitas-desa', 'layer_type' => 'point', 'is_public' => false, 'status' => 'draft']);
    $reference = MapLayer::create(['name' => 'Batas Wilayah Desa', 'slug' => 'batas-wilayah-desa', 'layer_type' => 'polygon', 'is_public' => true, 'status' => 'published']);
    $existingFeature = MapFeature::create(['layer_id' => $legacy->id, 'name' => 'Existing unrelated feature', 'verification_status' => 'pending', 'publication_status' => 'draft']);
    $existingAudit = AuditLog::create(['action' => 'existing.seed.test', 'entity_type' => 'system', 'created_at' => now()]);
    $usersHash = $snapshot()['users'];
    $referenceBefore = $reference->fresh()->getRawOriginal();
    $assert('explicit_seed_success', Artisan::call('db:seed', ['--class' => WebGisDemoSeeder::class, '--no-interaction' => true]) === 0);
    $assert('users_unchanged', $snapshot()['users'] === $usersHash);
    $assert('legacy_layer_id_preserved', $legacy->fresh()->slug === 'fasilitas-umum-desa-pilangsari' && $existingFeature->fresh()->layer_id === $legacy->id);
    $assert('boundary_layer_preserved', $reference->fresh()->getRawOriginal() === $referenceBefore);
    $assert('old_audit_preserved', $existingAudit->fresh() !== null);
    $assert('master_categories_10', DB::table('feature_categories')->count() === 10);
    $assert('master_sources_4', DB::table('map_data_sources')->count() === 4);
    $assert('houses_36', DB::table('houses')->count() === 36);
    $assert('features_36_new_plus_existing', DB::table('map_features')->count() === 37);
    $assert('cards_54', DB::table('family_cards')->count() === 54);
    $assert('members_162', DB::table('family_members')->count() === 162);
    $assert('facilities_3', DB::table('facilities')->count() === 3);
    $first = $snapshot();
    $assert('second_seed_success', Artisan::call('db:seed', ['--class' => WebGisDemoSeeder::class, '--no-interaction' => true]) === 0);
    $assert('full_idempotence', $snapshot() === $first);
    $assert('foreign_key_checks_enabled', (int) DB::selectOne('SELECT @@foreign_key_checks AS n')->n === 1);
    $assert('all_dummy_private', DB::table('houses')->where('publication_status', '!=', 'draft')->count() === 0 && DB::table('houses')->where('public_visible', true)->count() === 0);
    $result['status'] = 'PASS';
} catch (Throwable $exception) {
    $result['status'] = 'FAIL';
    $result['exception_type'] = $exception::class;
    $result['exception_line'] = $exception->getLine();
} finally {
    $result['checks'] = $checks;
    $result['database_retained'] = true;
    file_put_contents($root.'/storage/framework/testing/mysql-seeding-sqa-results.json', json_encode($result, JSON_PRETTY_PRINT));
}
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
exit($result['status'] === 'PASS' ? 0 : 1);
