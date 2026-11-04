<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WebGIS Desa Pilangsari')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
<header class="site-header">
    <a class="brand" href="{{ route('home') }}">
        <span class="brand-mark">P</span>
        <span><strong>WEBGIS PILANGSARI</strong><small>Kecamatan Gesi · Kabupaten Sragen</small></span>
    </a>
    <button class="menu-toggle" aria-label="Buka menu" onclick="document.querySelector('.nav-links').classList.toggle('open')">☰</button>
    <nav class="nav-links">
        <a href="{{ route('map') }}">Peta Interaktif</a>
        @auth
            @if(auth()->user()?->role === 'admin_rt')
                <a href="{{ route('admin.houses.index') }}">Data Bangunan RT</a>
            @else
                <a href="{{ route('admin.dashboard') }}">Dashboard Admin</a>
                <a href="{{ route('admin.verification.index') }}">Verifikasi</a>
                <a href="{{ route('admin.users.index') }}">Kelola Akun</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button type="submit">Keluar</button></form>
        @else
            <a class="nav-admin" href="{{ route('login') }}">Login Admin</a>
        @endauth
    </nav>
</header>
<main>@yield('content')</main>
<footer class="site-footer">
    <div><strong>WebGIS Desa Pilangsari</strong><p>Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah</p></div>
    <small>© {{ date('Y') }} WebGIS Desa Pilangsari · Data peta harus diverifikasi sebelum digunakan secara resmi.</small>
</footer>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
