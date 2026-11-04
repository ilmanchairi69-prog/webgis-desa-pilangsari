<?php

use App\Models\House;
use App\Models\MapFeature;
use App\Models\MapLayer;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__).'/vendor/autoload.php';

$projectPath = dirname(__DIR__);
$databasePath = $projectPath.'/storage/framework/testing/hardening-browser.sqlite';
if (PHP_VERSION !== '8.4.27' || ! extension_loaded('pdo_sqlite') || file_exists($databasePath)) {
    throw new RuntimeException('Browser fixture requires PHP 8.4.27, SQLite, and a new dedicated database file.');
}

foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $databasePath, 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'sync',
    'APP_CONFIG_CACHE' => $projectPath.'/storage/framework/testing/no-browser-config.php',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
touch($databasePath);
$app = require $projectPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $databasePath) {
    throw new RuntimeException('Unsafe browser test database configuration.');
}
Artisan::call('migrate', ['--no-interaction' => true]);
$accounts = [];
foreach (['super' => ['super_admin', null], 'rt' => ['admin_rt', '01'], 'other' => ['admin_rt', '02']] as $label => [$role, $rt]) {
    $password = bin2hex(random_bytes(16));
    $email = $label.'@sqa.example.test';
    User::create([
        'name' => 'Synthetic SQA '.$label, 'email' => $email,
        'password' => Hash::make($password),
        'role' => $role, 'rt_code' => $rt, 'is_active' => true,
    ]);
    $accounts[$label] = ['email' => $email, 'password' => $password];
}
$layer = MapLayer::create([
    'name' => 'SQA houses', 'slug' => 'sqa-houses', 'layer_type' => 'point',
    'color' => '#166534', 'is_public' => true, 'status' => 'published',
]);
$feature = MapFeature::create([
    'layer_id' => $layer->id, 'name' => 'Private synthetic label', 'category' => 'rumah',
    'latitude' => -7.3574198, 'longitude' => 110.9874323,
    'geometry' => ['type' => 'Point', 'coordinates' => [110.9874323, -7.3574198]],
    'verification_status' => 'pending', 'publication_status' => 'draft',
]);
$house = House::create([
    'map_feature_id' => $feature->id, 'house_code' => 'BGN-SQA-OTHER',
    'rt_code' => '02', 'address' => 'PRIVATE-SQA-ADDRESS',
    'latitude' => -7.3574198, 'longitude' => 110.9874323,
    'verification_status' => 'pending', 'publication_status' => 'draft',
    'public_visible' => true, 'created_by' => User::where('email', $accounts['other']['email'])->value('id'),
]);
file_put_contents($projectPath.'/storage/framework/testing/hardening-browser-credentials.json', json_encode([
    'accounts' => $accounts, 'other_house_id' => $house->id,
    'database' => $databasePath, 'base_url' => 'http://127.0.0.1:8101',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Dedicated SQLite browser fixtures prepared; credentials are private test artifacts, never printed.\n";
