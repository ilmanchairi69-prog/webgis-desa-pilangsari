# WebGIS Desa Pilangsari

Portal informasi dan sistem informasi geografis Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah. Aplikasi ini menyediakan informasi desa, peta interaktif, pengaduan masyarakat, serta ruang pengelolaan data bagi petugas desa.

## Fitur

- **Portal publik:** beranda, profil desa, pemerintahan desa, informasi dan agenda, serta peta desa.
- **Peta interaktif:** pencarian dan tampilan lokasi rumah serta fasilitas yang memenuhi ketentuan publikasi, dengan batas referensi GeoJSON. Batas tersebut bukan penetapan batas resmi untuk keperluan hukum atau pertanahan.
- **Agenda desa:** kalender interaktif dan detail kegiatan pada `/agenda` dan `/informasi`. Data saat ini masih berupa contoh statis di `resources/views/public/agenda.blade.php`, termasuk kegiatan 7 dan 8 November 2026; ganti dengan jadwal resmi sebelum digunakan sebagai informasi kegiatan sebenarnya. Belum tersedia pengelolaan agenda atau database agenda.
- **Pengaduan masyarakat:** formulir publik dengan nomor tiket, validasi dan Cloudflare Turnstile. Lampiran JPG, PNG, WEBP, atau PDF maksimal 5 MB disimpan secara privat. Tanggapan admin disimpan sebagai catatan internal dan tidak otomatis dikirim kepada pelapor.
- **Statistik kunjungan:** hitungan agregat berdasarkan sesi dengan jeda 30 menit tanpa aktivitas, memakai kalender `Asia/Jakarta`. Total hanya mencakup kunjungan sejak fitur mulai mencatat.
- **Pengelolaan internal:** data rumah, keluarga, fasilitas, peta, verifikasi, publikasi, akun pengguna, dan log audit sesuai peran.

## Teknologi

- PHP 8.4 direkomendasikan untuk lingkungan proyek ini; dependensi terkunci yang terpasang memerlukan PHP minimal 8.4.1.
- Laravel 13 dan Blade, Composer, PHPUnit, dan Laravel Pint.
- MySQL atau SQLite sesuai konfigurasi Laravel; PHPUnit dikonfigurasi menggunakan SQLite `:memory:`.
- Node.js dan npm untuk Vite dan Tailwind CSS. Node.js 24 tersedia pada lingkungan pengembangan yang telah digunakan.
- Leaflet untuk peta; aset Leaflet dimuat dari CDN sehingga membutuhkan koneksi jaringan pada browser.

## Persiapan lokal

Petunjuk berikut untuk **instalasi baru**, bukan untuk menimpa konfigurasi atau data yang sudah ada. Pada Windows/Laragon, jalankan dari direktori proyek:

1. Pastikan PHP CLI yang aktif adalah versi 8.4 atau lebih baru, Composer dan Node.js tersedia, serta ekstensi PHP yang dibutuhkan Composer dan driver database telah aktif.
2. Pasang dependensi dan buat file environment hanya jika belum ada:

   ```powershell
   composer install
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   npm.cmd ci
   ```

   Gunakan `npm.cmd` di PowerShell jika kebijakan eksekusi Windows memblokir `npm.ps1`.
3. Untuk instalasi baru, atur `APP_URL` dan koneksi database di `.env`. Contoh MySQL:

   ```dotenv
   APP_URL=http://127.0.0.1:8000
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nama_database
   DB_USERNAME=nama_pengguna
   DB_PASSWORD=isi_secara_lokal
   ```

   Buat database kosong melalui alat pengelolaan database. Jika memilih SQLite, siapkan file database dan ekstensi SQLite sesuai konfigurasi.
4. Hanya untuk instalasi baru dengan `APP_KEY` kosong, buat kunci aplikasi. Jangan mengganti `APP_KEY` instalasi yang sudah memiliki data terenkripsi.

   ```powershell
   php artisan key:generate
   ```

5. Periksa konfigurasi dan rencana migration sebelum menjalankannya pada database yang sesuai. Untuk database yang sudah berisi data, buat dan uji backup terlebih dahulu.

   ```powershell
   php artisan migrate:status --no-interaction
   php artisan migrate --no-interaction
   npm.cmd run build
   ```

6. Jalankan server lokal:

   ```powershell
   php artisan serve
   ```

   Buka alamat yang ditampilkan Artisan, biasanya `http://127.0.0.1:8000`. Untuk pengembangan aset, jalankan `npm.cmd run dev` pada terminal lain bila diperlukan. Banyak aset portal saat ini dimuat langsung dari `public/css` dan `public/js`; perubahan pada aset Vite memerlukan build atau server Vite.

Seeder awal dapat dijalankan secara sengaja pada instalasi baru dengan `php artisan db:seed --no-interaction`. Seeder menyiapkan layer, kategori, dan metadata sumber awal; periksa dampaknya terlebih dahulu pada database yang sudah ada. Jangan gunakan `migrate:fresh`, `db:wipe`, atau perintah reset pada database yang berisi data penting.

Untuk membuat Super Admin pertama pada instalasi baru, jalankan `php artisan webgis:make-super-admin` secara interaktif. Perintah meminta kredensial tanpa menampilkannya dan menolak pembuatan jika Super Admin sudah ada. Jangan menaruh kredensial di source code atau dokumentasi.

## Konfigurasi pengaduan dan privasi

Isi kunci Cloudflare Turnstile pada `.env` dengan nilai milik lingkungan yang bersangkutan:

```dotenv
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

Daftarkan hostname yang digunakan pada widget Turnstile. Tanpa kedua kunci, pengiriman pengaduan tidak tersedia. Backend memverifikasi token melalui Siteverify; server membutuhkan akses jaringan ke Cloudflare. Jangan membagikan Secret Key atau menyimpannya di frontend. Jika cache konfigurasi aktif setelah mengubah `.env`, jalankan `php artisan config:clear --no-interaction` dan pastikan proses web membaca konfigurasi terbaru.

Lampiran pengaduan disimpan di `storage/app/private/complaints` dan hanya dapat diunduh oleh Super Admin melalui route yang dilindungi. Jangan mengekspos `storage/app/private` melalui web server. Data keluarga dan identitas pribadi tidak disajikan dalam API peta publik.

## Hak akses

| Pengguna | Cakupan |
| --- | --- |
| Pengunjung | Halaman publik, peta, statistik agregat, dan pengiriman pengaduan. |
| Admin RT aktif | Dashboard, peta internal, dan pengelolaan data rumah sesuai cakupan RT. |
| Super Admin aktif | Pengelolaan pengaduan, verifikasi dan publikasi, fasilitas, layer, fitur peta, akun, dan audit selain fitur bersama. |

Route pengelolaan memakai middleware autentikasi dan status akun; fungsi khusus Super Admin juga dibatasi oleh `role:super_admin` di backend.

## Halaman dan endpoint utama

| Alamat | Kegunaan |
| --- | --- |
| `/` | Beranda dan peta ringkas. |
| `/profil` | Profil dan wilayah desa. |
| `/pemerintahan` | Pemerintahan dan perangkat desa. |
| `/agenda`, `/informasi` | Kalender dan contoh agenda desa. |
| `/peta` | Peta publik interaktif. |
| `/statistik-kunjungan` | Statistik kunjungan agregat dalam JSON. |
| `/api/map/public` | Data peta publik dalam GeoJSON. |
| `/api/v1/map/...` | Endpoint publik peta versi 1; lihat `routes/api.php`. |
| `/admin/login` | Login pengelola. |
| `/admin/complaints` | Pengelolaan pengaduan khusus Super Admin. |

Jalankan `php artisan route:list --no-interaction` untuk daftar route yang berlaku. Pengiriman pengaduan menggunakan `POST /pengaduan` dengan CSRF, validasi server, pembatasan request, dan Turnstile.

## Pengujian

Jalankan pengujian pada database pengujian terisolasi, bukan database operasional:

```powershell
php artisan test --compact
```

Contoh pengujian yang lebih terarah:

```powershell
php artisan test --compact tests/Feature/PublicVillagePagesTest.php
php artisan test --compact tests/Feature/ComplaintTest.php
php artisan test --compact tests/Feature/WebsiteVisitStatisticsTest.php
php artisan test --compact tests/Feature/PublicApiPrivacyConsistencyTest.php
```

Konfigurasi `phpunit.xml` menggunakan SQLite dalam memori. Pastikan ekstensi `pdo_sqlite` tersedia. Pengujian Turnstile yang menggunakan HTTP fake tidak menggantikan uji langsung dengan kunci dan hostname yang sah.

## Struktur proyek

- `app/`: controller, model, middleware, service, dan perintah Artisan.
- `routes/`: route web dan API.
- `resources/views/`: layout serta halaman Blade publik dan admin.
- `public/css`, `public/js`, `public/images`, dan `public/data`: aset portal, skrip, gambar, dan GeoJSON referensi.
- `database/`: migration, factory, dan seeder.
- `storage/app/private/`: lampiran dan data privat; lindungi dari akses publik.
- `tests/`: pengujian feature dan unit.

## Operasional

Arahkan document root server ke direktori `public`, gunakan HTTPS pada lingkungan publik, batasi akses ke `.env` dan penyimpanan privat, dan sediakan backup database serta lampiran privat di lokasi terpisah. Jangan menjalankan `composer run setup` pada instalasi yang sudah berisi data: skrip tersebut mencakup pembuatan `APP_KEY` dan migration. Periksa migration serta lakukan backup sebelum deployment atau perubahan database.
