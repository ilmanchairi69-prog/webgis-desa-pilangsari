@php($isVillagePortal = request()->routeIs('home', 'map', 'profil', 'pemerintahan', 'informasi', 'agenda'))
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Pemerintah Desa Pilangsari') — Portal Informasi Desa</title>
    <meta name="description" content="Portal informasi dan peta publik Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah.">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/visit-widget.css') }}?v={{ filemtime(public_path('css/visit-widget.css')) }}">
    <script src="{{ asset('js/visit-widget.js') }}?v={{ filemtime(public_path('js/visit-widget.js')) }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/complaint-widget.css') }}?v={{ filemtime(public_path('css/complaint-widget.css')) }}">
    <script src="{{ asset('js/complaint-widget.js') }}?v={{ filemtime(public_path('js/complaint-widget.js')) }}" defer></script>
    @if(config('services.turnstile.site_key'))<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script>@endif
    @stack('head')
    <meta name="theme-color" content="#123c31">
    <link rel="stylesheet" href="{{ asset('css/interface.css') }}?v={{ filemtime(public_path('css/interface.css')) }}">
    <script src="{{ asset('js/interface.js') }}?v={{ filemtime(public_path('js/interface.js')) }}" defer></script>
    @if($isVillagePortal)
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/portal-design.css') }}?v={{ filemtime(public_path('css/portal-design.css')) }}">
    <script src="{{ asset('js/portal.js') }}?v={{ filemtime(public_path('js/portal.js')) }}" defer></script>
    @endif
</head>
<body class="public-body{{ $isVillagePortal ? ' village-portal' : '' }}">
<a class="skip-link" href="#main-content">Lewati ke konten</a>
@if($isVillagePortal)
@include('public.portal-navigation')
@else
<header class="site-header">
    <a class="brand" href="{{ route('home') }}" aria-label="Beranda Desa Pilangsari">
        <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M24 4 40 11v13c0 10-7 16-16 20C15 40 8 34 8 24V11z" fill="none" stroke="currentColor" stroke-width="2.3"/><path d="M24 13v22M16 21c5 0 8 3 8 8M32 18c-5 0-8 3-8 8M15 15c4 0 7 2 9 6M33 13c-4 1-7 4-9 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span class="brand-copy"><strong>Desa Pilangsari<span class="brand-period">.</span></strong><small>Portal informasi geografis desa</small></span>
    </a>
    <button class="nav-toggle" type="button" aria-label="Buka menu" aria-expanded="false" aria-controls="public-navigation" data-nav-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <nav class="site-nav" id="public-navigation" data-nav aria-label="Navigasi portal">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Beranda</a>
        <a href="{{ route('home') }}#profil">Profil</a>
        <a href="{{ route('home') }}#pemerintahan">Pemerintahan</a>
        <a href="{{ route('home') }}#layanan">Layanan</a>
        <a href="{{ route('map') }}" class="{{ request()->routeIs('map') ? 'active' : '' }}">Peta Desa</a>
        <a href="{{ route('home') }}#informasi">Informasi</a>
        <a class="nav-cta" href="{{ route('map') }}">Jelajahi Peta <span>↗</span></a>
    </nav>
</header>
@endif
@if(session('success'))<div class="flash flash-success public-flash">{{ session('success') }}</div>@endif
<main id="main-content" tabindex="-1">@yield('content')</main>
@if($isVillagePortal)
@include('public.portal-footer')
@else
<footer class="site-footer">
    <div class="footer-brand"><span class="brand-mark small-mark">P</span><div><strong>Pemerintah Desa Pilangsari</strong><p>Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah</p></div></div>
    <p class="footer-note">Peta dan informasi publik ditampilkan setelah data melalui pemeriksaan. Data keluarga tidak dipublikasikan pada peta.</p>
    <a href="{{ route('login') }}">Akses Admin</a>
</footer>
@endif
@if($isVillagePortal)
<a class="public-admin-access" href="{{ url('/Admin/login') }}" aria-label="Akses halaman login Admin">
    @include('public.portal-icon', ['name' => 'shield'])
    <span>Akses Admin</span>
</a>
@endif

@include('public.visit-widget')
@include('public.complaint-widget')
@stack('scripts')
</body>
</html>
