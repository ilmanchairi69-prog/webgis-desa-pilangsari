#!/usr/bin/env bash
# Run through SSH as the site owner. Hostinger serves a physical public_html.
set -Eeuo pipefail
umask 077

fail() { printf '%s\n' "$1" >&2; exit 1; }
[[ $# == 3 ]] || fail 'Three release arguments are required.'
archive=$1
digest=$2
commit=$3
[[ $digest =~ ^[0-9a-f]{64}$ && $commit =~ ^[0-9a-f]{40}$ ]] || fail 'Invalid release identity.'
site=https://desa.pilangsari.id
home_dir=$(cd "$HOME" && pwd -P)
base="$home_dir/webgis-pilangsari"
releases="$base/releases"
shared="$base/shared"
current="$base/current"
docroot="$home_dir/domains/desa.pilangsari.id/public_html"

for utility in realpath mktemp tar sha256sum flock curl awk stat python3; do
    command -v "$utility" >/dev/null || fail "Missing server command: $utility"
done
owned_dir() {
    [[ -d $1 && ! -L $1 && -O $1 && $(realpath -e -- "$1") == "$1" ]] &&
        (( (8#$(stat -c %a -- "$1") & 022) == 0 ))
}
for path in "$base" "$releases" "$shared"; do
    owned_dir "$path" || fail 'Provision owner-controlled private release directories first.'
done
[[ -f $shared/.env && ! -L $shared/.env && -O $shared/.env ]] &&
    (( (8#$(stat -c %a -- "$shared/.env") & 077) == 0 )) || fail 'Private production environment is absent or permissions are too broad.'
[[ -L $current ]] && owned_dir "$docroot" || fail 'Expected a private current link and an owner-controlled physical public_html.'
upload_dir=$(dirname -- "$archive")
[[ $(dirname -- "$upload_dir") == "$home_dir" && $(basename -- "$upload_dir") =~ ^\.webgis-upload\.[a-zA-Z0-9]+$ &&
   $archive == "$upload_dir/release.tar.gz" && -f $archive && ! -L $archive && -O $archive ]] &&
    owned_dir "$upload_dir" || fail 'Unexpected private archive location.'
[[ $(sha256sum "$archive" | awk '{print $1}') == "$digest" ]] || fail 'Release checksum mismatch.'

[[ ! -L $base/.deploy.lock ]] || fail 'Unexpected deployment lock.'
exec 9>>"$base/.deploy.lock"
flock -n 9 || fail 'Another deployment is in progress.'
previous=$(realpath -e -- "$current")
[[ $(dirname "$previous") == "$releases" ]] && owned_dir "$previous" && owned_dir "$previous/public" ||
    fail 'Existing release cannot be retained for rollback.'
public_fingerprint() {
    # GNU tar records links themselves (including storage), never their contents.
    tar --format=gnu --sort=name --mtime=@0 --owner=0 --group=0 --numeric-owner \
        -C "$1" -cf - . | sha256sum | awk '{print $1}'
}
exchange_roots() {
    python3 - "$docroot" "$exchange_dir" <<'PY'
import ctypes
import os
import sys

exchange = ctypes.CDLL(None, use_errno=True).renameat2
exchange.argtypes = (ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint)
if exchange(-100, os.fsencode(sys.argv[1]), -100, os.fsencode(sys.argv[2]), 2):
    raise SystemExit("Atomic public directory exchange failed (errno %d)." % ctypes.get_errno())
PY
}
if [[ -f $previous/public-tree.sha256 && ! -L $previous/public-tree.sha256 ]]; then
    read -r old_hash < "$previous/public-tree.sha256"
    [[ $old_hash =~ ^[0-9a-f]{64}$ ]] && cmp -s "$docroot/webgis-release.txt" "$previous/public/webgis-release.txt" ||
        fail 'Existing webroot does not match the active release.'
else
    shopt -s dotglob nullglob
    entries=("$docroot"/*)
    shopt -u dotglob nullglob
    [[ ${#entries[@]} == 1 && ${entries[0]:-} == "$docroot/default.php" && -f $docroot/default.php && ! -L $docroot/default.php ]] ||
        fail 'Unmanaged public_html contains content that must be preserved before activation.'
    old_hash=$(public_fingerprint "$docroot")
fi
[[ $(public_fingerprint "$docroot") == "$old_hash" ]] || fail 'Changed public files must be reconciled before deployment.'
old_inode=$(stat -c '%d:%i' -- "$docroot")
previous_env_hash=$(sha256sum "$shared/.env" | awk '{print $1}')

php_bin=
for candidate in "$(command -v php || true)" /opt/alt/php84/usr/bin/php; do
    if [[ -x $candidate ]] && "$candidate" -r 'exit(PHP_VERSION_ID >= 80401 && PHP_VERSION_ID < 90000 ? 0 : 1);'; then
        php_bin=$candidate
        break
    fi
done
[[ -n $php_bin ]] || fail 'PHP CLI 8.4.1 or compatible is required.'
export PATH="$(dirname "$php_bin"):$PATH"
"$php_bin" -r 'foreach (["ctype","curl","dom","fileinfo","filter","hash","iconv","json","libxml","mbstring","openssl","pcre","PDO","pdo_mysql","session","tokenizer","xml"] as $name) { if (!extension_loaded($name)) { fwrite(STDERR, "Missing PHP module: ".$name.PHP_EOL); exit(1); } }'
# Dependencies were installed and platform-checked in CI. The production
# autoloader and the explicit CLI extension checks below need no server Composer.
"$php_bin" -r 'printf("PHP CLI %s\n", PHP_VERSION);'

# The release archive is produced by CI. Permit ordinary files/directories only.
tar --list --verbose --gzip --numeric-owner --file="$archive" |
    awk 'substr($1,1,1)!="d" && substr($1,1,1)!="-" {invalid=1} END {exit invalid}' || fail 'Unexpected archive entry type.'
tar --list --gzip --quoting-style=escape --file="$archive" |
    awk '{n=$0;sub(/^\.\//,"",n);if(n=="" || n=="." )next;if(n ~ /(^\/|(^|\/)\.\.(\/|$)|\\)/ || n !~ /^(app|bootstrap|config|database|public|resources|routes|vendor)(\/|$)|^(artisan|composer\.json|composer\.lock)$/) bad=1;} END{exit bad}' ||
    fail 'Unexpected archive path.'
release=$(mktemp -d "$releases/$commit.XXXXXXXX")
tx=$(mktemp -d "$base/.switch.XXXXXXXX")
tar -xzf "$archive" --directory="$release" --no-same-owner --no-same-permissions
cd "$release"
for path in public/.htaccess public/index.php public/build/manifest.json vendor/autoload.php bootstrap/app.php artisan; do
    [[ -s $path ]] || fail 'Incomplete release archive.'
done
[[ -z $(find public -type f \( -iname '.env*' -o -iname '.user.ini' -o -iname '*.phar' -o -iname '*.phtml' -o -iname '*.php' \) ! -path 'public/index.php' -print -quit) ]] ||
    fail 'Unexpected configuration or executable in the public tree.'
[[ ! -e public/hot && ! -e public/webgis-release.txt ]] || fail 'Conflicting public release markers.'
if [[ -e $previous/public/storage || -L $previous/public/storage ]]; then
    [[ -L $previous/public/storage && $(realpath -e -- "$previous/public/storage") == "$shared/storage/app/public" ]] ||
        fail 'Existing public storage must be preserved before switching releases.'
fi

# Keep attachments, sessions, locks, and logs across releases; compile views in
# the candidate so preparing a release cannot clear the active compiled views.
mkdir -p storage/framework/views bootstrap/cache
for leaf in app framework/cache framework/sessions logs; do
    destination="$shared/storage/$leaf"
    [[ $(realpath -m -- "$destination") == "$destination" ]] || fail 'Unexpected shared-storage symlink.'
    mkdir -p "$destination"
    owned_dir "$destination" && [[ -w $destination ]] || fail 'Shared storage is unavailable.'
    ln -s -- "$destination" "storage/$leaf"
done
for leaf in app/private app/public framework/cache/data framework/cache/locks; do
    destination="$shared/storage/$leaf"
    [[ $(realpath -m -- "$destination") == "$destination" ]] || fail 'Unexpected shared-storage path.'
    mkdir -p "$destination"
    owned_dir "$destination" || fail 'Invalid shared-storage permissions.'
done
ln -s -- "$shared/.env" .env
"$php_bin" -r 'require "vendor/autoload.php";' >/dev/null 2>&1 || fail 'Production dependencies are incompatible with server PHP.'
"$php_bin" artisan package:discover --no-interaction >/dev/null 2>&1 || fail 'Package discovery failed.'

"$php_bin" -d display_errors=0 -d log_errors=0 <<'PHP'
<?php
try {
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (realpath($app->basePath()) !== realpath(getcwd()) ||
        realpath($app->storagePath('app/private')) !== realpath(dirname(realpath('.env')).'/storage/app/private')) {
        throw new RuntimeException();
    }
    $key = $app['config']->get('app.key');
    if (!$app->environment('production') || $app['config']->get('app.debug') !== false ||
        $app['config']->get('app.url') !== 'https://desa.pilangsari.id' ||
        !is_string($key) || !str_starts_with($key, 'base64:') ||
        strlen((string) base64_decode(substr($key, 7), true)) !== 32) {
        throw new RuntimeException();
    }
    $db = $app['db']->connection();
    $name = 'u675170483_webgis_desa';
    if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== $name || $db->getConfig('username') !== $name) {
        throw new RuntimeException();
    }
    $identity = $db->selectOne("SELECT DATABASE() AS db_name, SUBSTRING_INDEX(CURRENT_USER(), '@', 1) AS user_name");
    if ($identity->db_name !== $name || $identity->user_name !== $name) {
        throw new RuntimeException();
    }
    $migrator = $app->make('migrator');
    $required = array_keys($migrator->getMigrationFiles($app->databasePath('migrations')));
    $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
    $pending = array_diff($required, $ran);
    if (!$required || $pending) {
        fwrite(STDERR, count($pending)." migrations pending. Review and obtain approval before database changes.\n");
        exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Production configuration, database identity, or read-only schema check failed.\n");
    exit(1);
}
PHP
# Run each Artisan command in the SSH shell and check its own exit status.
for task in config:cache event:cache route:cache view:cache; do
    COLUMNS=100 LINES=24 "$php_bin" artisan "$task" --quiet --no-interaction >/dev/null 2>&1 || fail "Candidate cache step failed: $task"
done
printf '%s\n' "$commit" > public/webgis-release.txt
chmod 711 "$release"
chmod -R u=rwX,go=rX public
ln -s -- "$shared/storage/app/public" public/storage
[[ $(realpath -e -- public/storage) == "$shared/storage/app/public" ]] || fail 'Public storage symlink is incorrect.'

# Before the exchange this is the candidate public tree. Afterwards the same
# private path holds the complete preceding physical webroot for recovery.
exchange_dir="$release/previous-public_html"
mkdir "$exchange_dir"
cp -a -- public/. "$exchange_dir/"
"$php_bin" -r 'printf("<?php\nheader(%s);\nrequire %s;\n", var_export("X-WebGIS-Release: ".$argv[2], true), var_export($argv[1], true));' \
    "$release/public/index.php" "$commit" > "$exchange_dir/index.php"
[[ -s $exchange_dir/index.php ]] && "$php_bin" -l "$exchange_dir/index.php" >/dev/null || fail 'Public entry point is invalid.'
chgrp --reference="$docroot" "$exchange_dir"
chmod --reference="$docroot" "$exchange_dir"
[[ $(stat -c %d -- "$exchange_dir") == "$(stat -c %d -- "$docroot")" ]] || fail 'Atomic public exchange requires the same filesystem.'
new_inode=$(stat -c '%d:%i' -- "$exchange_dir")
public_fingerprint "$exchange_dir" > "$release/public-tree.sha256"

# Resolve the live URL without redirects; a holding page can also return 200.
get_page() {
    local path=$1 separator='?'
    [[ $path != *\?* ]] || separator='&'
    code=$(curl -q --silent --compressed --proto '=https' --max-time 18 --connect-timeout 6 \
        --user-agent WebGISDeployBot --header 'Cache-Control: no-cache' --output "$tx/response" --dump-header "$tx/headers" \
        --write-out '%{http_code}' "$site$path${separator}release-check=$(basename "$tx")-$RANDOM")
}
has_previous_site() {
    if [[ -f $previous/public/webgis-release.txt ]]; then
        get_page /webgis-release.txt && [[ $code == 200 ]] && cmp -s "$tx/response" "$previous/public/webgis-release.txt" || return 1
    fi
    get_page / && [[ $code == 200 ]] && [[ -s $tx/response ]]
}
has_previous_site || fail 'Existing site failed its baseline HTTPS check.'
ln -s -- "$release" "$tx/next"
ln -s -- "$previous" "$tx/rollback"

switched=0
restore_public() {
    case "$(stat -c '%d:%i' -- "$docroot")" in
        "$new_inode") exchange_roots || return 1 ;;
        "$old_inode") ;;
        *) return 1 ;;
    esac
    [[ $(stat -c '%d:%i' -- "$docroot") == "$old_inode" ]]
}
on_exit() {
    local status=$?
    trap - EXIT HUP INT TERM
    if (( status != 0 && switched )); then
        if restore_public && mv -Tf -- "$tx/rollback" "$current" && [[ $(realpath -e -- "$current") == "$previous" ]] && has_previous_site; then
            printf '%s\n' 'Previous release restored and checked over HTTPS.' >&2
        else
            printf '%s\n' 'URGENT: automatic rollback could not be verified.' >&2
        fi
    fi
    rm -f -- "$tx/response" "$tx/headers" "$tx/rollback" "$tx/next"
    rmdir -- "$tx" || true
    exit "$status"
}
trap on_exit EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM
[[ $(realpath -e -- "$current") == "$previous" ]] || fail 'Live release changed during preparation.'
[[ $(stat -c '%d:%i' -- "$docroot") == "$old_inode" ]] || fail 'Live public directory changed during preparation.'
switched=1
exchange_roots
[[ $(stat -c '%d:%i' -- "$docroot") == "$new_inode" && $(public_fingerprint "$exchange_dir") == "$old_hash" ]] ||
    fail 'The previous webroot changed during activation.'
mv -Tf -- "$tx/next" "$current"
[[ $(realpath -e -- "$current") == "$release" ]] || fail 'Private release pointer is incorrect.'
get_page /webgis-release.txt
[[ $code == 200 ]] && cmp -s "$tx/response" public/webgis-release.txt || fail 'HTTPS did not serve this exact commit.'
for page in / /profil /pemerintahan /agenda /peta; do
    get_page "$page"
    [[ $code == 200 ]] && grep -Fq "<link rel=\"canonical\" href=\"$site$page\">" "$tx/response" || fail "Public page check failed: $page"
    tr -d '\r' < "$tx/headers" | grep -Fxiq "X-WebGIS-Release: $commit" || fail 'PHP served a different release.'
done
get_page /Admin/login
[[ $code == 200 ]] && grep -Fq 'content="noindex, nofollow"' "$tx/response" || fail 'Login page failed its HTTPS check.'
for restricted in /admin /admin/audit-logs; do
    get_page "$restricted"
    [[ $code == 302 ]] || fail 'Admin access control did not redirect.'
done
for asset in css/portal.css js/portal.js build/manifest.json; do
    get_page "/$asset"
    [[ $code == 200 ]] && cmp -s "$tx/response" "public/$asset" || fail 'Production asset mismatch.'
done
get_page /sitemap.xml
[[ $code == 200 ]] && grep -Fq "$site/peta" "$tx/response" || fail 'Sitemap check failed.'
get_page /api/map/public
[[ $code == 200 ]] || fail 'Public map API failed.'
"$php_bin" -r '
    $map = json_decode(file_get_contents($argv[1]), true);
    if (($map["type"] ?? null) !== "FeatureCollection" || empty($map["boundary"]) || !is_array($map["features"] ?? null)) {
        exit(1);
    }
    foreach ($map["features"] as $feature) {
        $p = $feature["properties"] ?? [];
        if (($p["kind"] ?? null) === "house" && (($p["name"] ?? null) !== "Lokasi rumah warga terverifikasi"
            || array_diff(array_keys($p), ["id", "kind", "system_key", "object_key", "name", "category"]))) {
            exit(1);
        }
    }
' "$tx/response" || fail 'Public map shape, village boundary or house privacy check failed.'
[[ $(sha256sum "$shared/.env" | awk '{print $1}') == "$previous_env_hash" ]] || fail 'Shared environment changed during deployment.'
switched=0
printf '%s\n' "Deployment verified for $commit."
