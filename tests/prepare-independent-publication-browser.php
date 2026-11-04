<?php

use App\Models\Facility;
use App\Models\FamilyCard;
use App\Models\FamilyMember;
use App\Models\House;
use App\Models\PublicationCategory;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__).'/vendor/autoload.php';

$projectPath = dirname(__DIR__);
$run = 'independent-publication-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
$directory = $projectPath.'/storage/framework/testing/'.$run;
$databasePath = $directory.'/fixture.sqlite';
if (PHP_VERSION !== '8.4.27' || ! extension_loaded('pdo_sqlite') || file_exists($directory)) {
    throw new RuntimeException('Requires PHP 8.4.27, SQLite and a new isolated fixture directory.');
}
mkdir($directory, 0700, true);
touch($databasePath);
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $databasePath, 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'sync',
    'APP_CONFIG_CACHE' => $directory.'/no-cached-config.php',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require $projectPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== $databasePath
    || ! str_starts_with(realpath($databasePath), realpath($projectPath.'/storage/framework/testing'))) {
    throw new RuntimeException('Unsafe database configuration.');
}
Artisan::call('migrate', ['--no-interaction' => true]);
$password = bin2hex(random_bytes(18));
$user = User::create([
    'name' => 'Synthetic Browser Administrator', 'email' => 'independent@sqa.example.test',
    'password' => Hash::make($password), 'role' => 'super_admin', 'is_active' => true,
]);
PublicationCategory::query()->update(['source_layer_id' => null, 'is_public' => true]);
$house = House::create([
    'house_code' => 'BGN-SQA-INDEPENDENT', 'map_feature_id' => null,
    'house_label' => 'PRIVATE-HOUSE-LABEL', 'address' => 'PRIVATE-HOUSE-ADDRESS', 'rt_code' => '01',
    'latitude' => -7.3574198, 'longitude' => 110.9874323,
    'verification_status' => 'pending', 'publication_status' => 'draft',
    'public_visible' => true, 'created_by' => $user->id,
]);
$card = FamilyCard::create([
    'house_id' => $house->id, 'head_name' => 'PRIVATE-HEAD-NAME',
    'card_number' => '9999999999999999', 'notes' => 'PRIVATE-CARD-NOTES',
]);
FamilyMember::create([
    'family_card_id' => $card->id, 'full_name' => 'PRIVATE-MEMBER-NAME',
    'date_of_birth' => '1991-02-03', 'relationship' => 'PRIVATE-RELATIONSHIP',
]);
$facility = Facility::create([
    'name' => 'Fasilitas Uji Tanpa Layer', 'category' => 'Fasilitas Umum',
    'latitude' => -7.3554198, 'longitude' => 110.9894323,
    'status' => 'draft', 'created_by' => $user->id,
]);
foreach ([
    'UNVERIFIED' => ['verification_status' => 'pending', 'publication_status' => 'published'],
    'DRAFT' => ['verification_status' => 'verified', 'publication_status' => 'draft'],
    'WITHDRAWN' => ['verification_status' => 'verified', 'publication_status' => 'draft'],
    'ARCHIVED' => ['verification_status' => 'verified', 'publication_status' => 'published', 'archived_at' => now()],
    'INVALID' => ['verification_status' => 'verified', 'publication_status' => 'published', 'latitude' => 0, 'longitude' => 0],
] as $code => $states) {
    House::create(array_replace([
        'house_code' => 'BGN-HIDDEN-'.$code, 'map_feature_id' => null,
        'latitude' => -7.3574198, 'longitude' => 110.9874323, 'public_visible' => true,
    ], $states));
}
foreach ([
    'DRAFT' => ['status' => 'draft'],
    'WITHDRAWN' => ['status' => 'draft'],
    'ARCHIVED' => ['status' => 'published', 'archived_at' => now()],
    'INVALID' => ['status' => 'published', 'latitude' => 0, 'longitude' => 0],
] as $name => $states) {
    Facility::create(array_replace([
        'name' => 'HIDDEN-'.$name, 'category' => 'Fasilitas Umum',
        'latitude' => -7.3554198, 'longitude' => 110.9894323,
    ], $states));
}
if (DB::table('map_layers')->count() !== 0 || DB::table('map_features')->count() !== 0
    || House::whereNotNull('map_feature_id')->exists()) {
    throw new RuntimeException('Fixture must have zero layers/features and no house-feature references.');
}
$manifest = [
    'database' => $databasePath, 'directory' => $directory,
    'base_url' => 'http://127.0.0.1:8136', 'debug_url' => 'http://127.0.0.1:9256',
    'email' => $user->email, 'password' => $password,
    'house' => ['id' => $house->id, 'latitude' => $house->latitude, 'longitude' => $house->longitude],
    'facility' => ['id' => $facility->id, 'latitude' => $facility->latitude, 'longitude' => $facility->longitude],
    'map_layers' => 0, 'map_features' => 0, 'house_map_feature_id' => null,
];
file_put_contents($directory.'/private-fixture.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($projectPath.'/storage/framework/testing/independent-publication-current.json', json_encode(['manifest' => $directory.'/private-fixture.json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Isolated browser fixture prepared: '.$run.". Credentials were not printed.\n";
