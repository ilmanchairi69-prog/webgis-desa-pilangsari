<!doctype html>
<html lang="id">
<head>
    @include('favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Desa Pilangsari') — Sistem Informasi Desa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
<header class="site-header">
    <a class="brand" href="{{ route('home') }}">
        <span class="brand-mark">P</span>
        <span><strong>DESA PILANGSARI</strong><small>Kecamatan Gesi · Kabupaten Sragen</small></span>
    </a>
    <button class="menu-toggle" aria-label="Buka menu" onclick="document.querySelector('.nav-links').classList.toggle('open')">☰</button>
    <nav class="nav-links">
        <a href="{{ route('home') }}">Beranda</a>
        <a href="{{ route('profile') }}">Profil Desa</a>
        <a href="{{ route('map') }}">Peta Digital</a>
        <a href="{{ route('umkm') }}">UMKM</a>
        <a href="{{ route('agenda') }}">Agenda</a>
        <a href="{{ route('news') }}">Berita</a>
        <a href="{{ route('complaint') }}">Pengaduan</a>
        <a class="nav-admin" href="{{ url('/admin') }}">Admin</a>
    </nav>
</header>
<main>@yield('content')</main>
<footer class="site-footer">
    <div><strong>Desa Pilangsari</strong><p>Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah</p></div>
    <div class="footer-links"><a href="#">Kontak</a><a href="#">Kebijakan Privasi</a><a href="#">Sitemap</a></div>
    <small>© {{ date('Y') }} Sistem Informasi Desa Pilangsari · Starter prototype</small>
</footer>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
