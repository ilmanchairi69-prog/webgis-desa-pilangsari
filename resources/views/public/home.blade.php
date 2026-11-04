@extends('layouts.public')
@section('title', 'Beranda')
@include('public.map-assets')
@section('content')
<section class="portal-hero" aria-labelledby="portal-hero-title">
    <img class="portal-hero-image" src="{{ asset('images/portal/landscape-reference.jpg') }}" alt="" width="1376" height="768" fetchpriority="high">
    <div class="portal-container portal-hero-content portal-hero-layout">
      <div class="portal-hero-copy">
        <span class="portal-eyebrow portal-hero-eyebrow"><span></span> SELAMAT DATANG DI PORTAL DESA</span>
        <h1 id="portal-hero-title">Selamat Datang di<br><em>Desa Pilangsari</em></h1>
        <p class="portal-hero-location">Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah</p>
        <p class="portal-hero-description">Kenali desa, jelajahi wilayahnya, dan temukan lokasi publik di sekitar Anda. Satu portal untuk terhubung lebih dekat dengan Pilangsari.</p>
        <div class="portal-actions"><a class="portal-button portal-button-yellow" href="{{ route('map') }}">@include('public.portal-icon', ['name' => 'map']) Jelajahi WebGIS @include('public.portal-icon', ['name' => 'arrow'])</a><a class="portal-button portal-button-outline" href="{{ route('profil') }}">Profil Desa</a></div>
      </div>
      <figure class="portal-hero-feature"><img src="{{ asset('images/portal/landscape-reference.jpg') }}" alt="Ilustrasi lanskap pedesaan dengan persawahan" width="1376" height="768"><figcaption><strong>Desa Pilangsari</strong><span>Kecamatan Gesi &middot; Kabupaten Sragen</span><small>Ilustrasi lanskap pedesaan</small></figcaption></figure>
    </div>
    <span class="portal-image-credit">Ilustrasi lanskap pedesaan</span>
</section>
<section class="portal-summary" aria-label="Ringkasan peta publik dan wilayah"><div class="portal-container portal-summary-grid">
    <div><strong>{{ number_format($publishedHouseMarkers) }}</strong><span>Lokasi rumah terpublikasi</span></div><div><strong>{{ number_format($publishedFacilities) }}</strong><span>Fasilitas umum terpublikasi</span></div><div><strong>3</strong><span>Kebayanan</span></div><div><strong>17</strong><span>RT</span></div>
</div></section>
<section class="portal-section portal-quick-section"><div class="portal-container portal-quick-grid">
    <a class="portal-quick-card" href="{{ route('map') }}"><span class="portal-icon-tile tile-yellow">@include('public.portal-icon', ['name' => 'map'])</span><h2>Peta Interaktif</h2><p>Jelajahi lokasi rumah dan fasilitas umum.</p><span class="portal-card-link">Buka WebGIS @include('public.portal-icon', ['name' => 'arrow'])</span></a>
    <a class="portal-quick-card" href="{{ route('profil') }}"><span class="portal-icon-tile tile-red">@include('public.portal-icon', ['name' => 'house'])</span><h2>Profil Desa</h2><p>Kenali Pilangsari dan wilayah administratifnya.</p><span class="portal-card-link">Kenali desa @include('public.portal-icon', ['name' => 'arrow'])</span></a>
    <a class="portal-quick-card" href="{{ route('pemerintahan') }}"><span class="portal-icon-tile tile-blue">@include('public.portal-icon', ['name' => 'book'])</span><h2>Pemerintahan Desa</h2><p>Kenali perangkat dan organisasi pemerintahan desa.</p><span class="portal-card-link">Lihat pemerintahan @include('public.portal-icon', ['name' => 'arrow'])</span></a>
    <a class="portal-quick-card" href="#privasi"><span class="portal-icon-tile tile-green">@include('public.portal-icon', ['name' => 'shield'])</span><h2>Privasi Data</h2><p>Informasi wilayah dengan data keluarga terlindungi.</p><span class="portal-card-link">Pelajari privasi @include('public.portal-icon', ['name' => 'arrow'])</span></a>
</div></section>
<section class="portal-section portal-section-cream" id="profil"><div class="portal-container">
    <div class="portal-section-title"><span class="portal-eyebrow">MENGENAL DESA KITA</span><h2>Selamat datang di Pilangsari</h2><p>Ruang informasi geografis untuk desa yang lebih terhubung.</p></div>
    <div class="portal-profile-card"><figure><img src="{{ asset('images/portal/landscape-reference.jpg') }}" alt="Ilustrasi lanskap pedesaan dengan persawahan dan permukiman" width="1376" height="768" loading="lazy"><figcaption>Ilustrasi lanskap pedesaan dari situs referensi.</figcaption></figure><div class="portal-profile-copy"><span class="portal-eyebrow">PROFIL SINGKAT</span><h3>Desa Pilangsari,<br>dekat melalui informasi.</h3><p>Desa Pilangsari berada di Kecamatan Gesi, Kabupaten Sragen, Provinsi Jawa Tengah. Desa agraris ini memiliki potensi pertanian, perkebunan, peternakan, wisata alam, dan wisata religi.</p><p>Jelajahi batas referensi wilayah serta lokasi rumah dan fasilitas umum yang telah memenuhi persyaratan publikasi.</p><dl class="portal-profile-facts"><div><dt>Kecamatan</dt><dd>Gesi</dd></div><div><dt>Kabupaten</dt><dd>Sragen</dd></div><div><dt>Provinsi</dt><dd>Jawa Tengah</dd></div></dl><a class="portal-text-link" href="#geografis">Jelajahi wilayah desa @include('public.portal-icon', ['name' => 'arrow'])</a></div></div>
</div></section>
<section class="portal-section"><div class="portal-container"><div class="portal-section-title"><span class="portal-eyebrow">POTENSI DESA</span><h2>Potensi Pilangsari</h2><p>Pertanian dan peternakan tumbuh bersama potensi wisata desa.</p></div><div class="village-grid">@foreach(['Pertanian', 'Perkebunan', 'Peternakan', 'Wisata alam', 'Wisata religi'] as $potential)<article class="village-card"><h3>{{ $potential }}</h3></article>@endforeach</div><p><a class="portal-text-link" href="{{ route('profil') }}">Kenali potensi desa &rarr;</a></p></div></section>
<section class="portal-section portal-section-soft"><div class="portal-container portal-government-intro"><div><span class="portal-eyebrow">PEMERINTAHAN DESA</span><h2>Melayani dan tumbuh bersama masyarakat.</h2><p>Kenali perangkat desa, susunan pemerintahan, dan wilayah kebayanan yang mendukung pelayanan masyarakat Pilangsari.</p><a class="portal-button portal-button-dark" href="{{ route('pemerintahan') }}">Pemerintahan Desa @include('public.portal-icon', ['name' => 'arrow'])</a></div><div class="portal-government-fact"><span>PEMERINTAH DESA</span><strong>Pilangsari</strong><p>Kecamatan Gesi<br>Kabupaten Sragen</p></div></div></section>
<section class="portal-section" id="geografis"><div class="portal-container">
    <div class="portal-section-heading"><div><span class="portal-eyebrow">WEBGIS DESA PILANGSARI</span><h2>Kenali wilayah. Temukan lokasi.</h2><p>Jelajahi peta langsung di sini, atau buka halaman peta untuk tampilan yang lebih luas.</p></div><a class="portal-button portal-button-dark" href="{{ route('map') }}">Buka Peta @include('public.portal-icon', ['name' => 'arrow'])</a></div>
    @include('public.map-workspace')
    <p class="portal-map-disclaimer">Batas pada peta merupakan referensi dari berkas QGIS. Konfirmasikan batas resmi kepada pemerintah desa sebelum digunakan untuk keperluan legal atau pertanahan.</p>
</div></section>
<section class="portal-section portal-section-soft" id="informasi"><div class="portal-container">
    <div class="portal-section-title"><span class="portal-eyebrow">INFORMASI UNTUK ANDA</span><h2>Lebih mudah menjelajahi desa</h2><p>Panduan singkat menggunakan informasi geografis publik.</p></div>
    <div class="portal-guide-grid">
        <article class="portal-guide-card"><span class="portal-guide-number">01</span><span class="portal-icon-tile tile-blue">@include('public.portal-icon', ['name' => 'search'])</span><h3>Temukan lokasi publik</h3><p>Ketik nama lokasi atau kategori pada pencarian peta. Klik penanda untuk membaca informasi lokasi yang tersedia.</p><a class="portal-text-link" href="#geografis">Coba pencarian @include('public.portal-icon', ['name' => 'arrow'])</a></article>
        <article class="portal-guide-card"><span class="portal-guide-number">02</span><span class="portal-icon-tile tile-yellow">@include('public.portal-icon', ['name' => 'pin'])</span><h3>Dua kategori, satu peta</h3><p>Penanda hijau menunjukkan Rumah Warga. Penanda kuning menunjukkan Fasilitas Umum. Aktifkan kategori sesuai kebutuhan Anda.</p><a class="portal-text-link" href="{{ route('map') }}">Jelajahi kategori @include('public.portal-icon', ['name' => 'arrow'])</a></article>
        <article class="portal-guide-card"><span class="portal-guide-number">03</span><span class="portal-icon-tile tile-green">@include('public.portal-icon', ['name' => 'shield'])</span><h3>Lokasi terbuka, data terlindungi</h3><p>Peta menampilkan informasi publik yang diizinkan. Nomor KK, nama kepala keluarga, dan anggota keluarga tidak ditampilkan.</p><a class="portal-text-link" href="#privasi">Tentang privasi @include('public.portal-icon', ['name' => 'arrow'])</a></article>
    </div>
</div></section>
<section class="portal-privacy" id="privasi"><div class="portal-container portal-privacy-inner"><span class="portal-privacy-icon">@include('public.portal-icon', ['name' => 'shield'])</span><div><span class="portal-eyebrow">INFORMASI YANG BERTANGGUNG JAWAB</span><h2>Jelajahi desa, tetap jaga privasi.</h2><p>Hanya lokasi yang memenuhi persyaratan publikasi yang tampil di peta. Informasi keluarga tetap berada dalam pengelolaan internal yang terbatas.</p></div><a class="portal-button portal-button-yellow" href="{{ route('map') }}">Jelajahi Peta @include('public.portal-icon', ['name' => 'arrow'])</a></div></section>
@endsection
