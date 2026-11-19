@php
    $isVillagePortal = request()->routeIs('home', 'map', 'profil', 'pemerintahan', 'informasi', 'agenda');
    $isLoginPage = request()->routeIs('login', 'login.public-alias');
    $seoPages = [
        'home' => ['Beranda Desa Pilangsari', 'Kenali Desa Pilangsari di Kecamatan Gesi, Sragen, dan jelajahi informasi serta peta interaktif desa.'],
        'profil' => ['Profil Desa Pilangsari', 'Mengenal sejarah, wilayah, kehidupan masyarakat, dan potensi Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen.'],
        'pemerintahan' => ['Pemerintahan Desa Pilangsari', 'Mengenal struktur pemerintahan, perangkat desa, dan pelayanan masyarakat Desa Pilangsari.'],
        'agenda' => ['Agenda Desa Pilangsari', 'Informasi kegiatan dan agenda di lingkungan Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen.'],
        'map' => ['Peta Interaktif Desa Pilangsari', 'Jelajahi peta Desa Pilangsari serta lokasi rumah dan fasilitas umum yang telah dipublikasikan.'],
    ];
    $seoRoute = request()->routeIs('informasi') ? 'agenda' : request()->route()?->getName();
    $seo = $seoPages[$seoRoute] ?? null;
    $baseUrl = rtrim(config('app.url'), '/');
    $canonicalUrl = $seo ? $baseUrl.route($seoRoute, [], false) : null;
    $seoTitle = $seo ? $seo[0].' | WebGIS Desa Pilangsari' : 'Masuk Admin | WebGIS Desa Pilangsari';
@endphp
<!doctype html>
<html lang="id">
<head>
    @include('favicon')
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seoTitle }}</title>
    @if($seo)
    <meta name="robots" content="index, follow">
    <meta name="description" content="{{ $seo[1] }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="WebGIS Desa Pilangsari">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seo[1] }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $baseUrl }}/images/portal/landscape-reference.jpg">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seo[1] }}">
    <meta name="twitter:image" content="{{ $baseUrl }}/images/portal/landscape-reference.jpg">
    @else
    <meta name="robots" content="noindex, nofollow">
    @endif
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
<body class="public-body{{ $isVillagePortal ? ' village-portal' : '' }}{{ $isLoginPage ? ' login-page' : '' }}">
<a class="skip-link" href="#main-content">Lewati ke konten</a>
@if($isVillagePortal)
@include('public.portal-navigation')
@else
<header class="site-header">
    <a class="brand" href="{{ route('home') }}" aria-label="Beranda Desa Pilangsari">
        @if($isLoginPage)<img class="login-header-logo" src="{{ asset('images/portal/logo-sragen.png') }}" width="44" height="58" alt="Lambang Kabupaten Sragen">@else @include('public.village-logo') @endif
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
@elseif($isLoginPage)
<footer class="site-footer login-footer">
    <div class="footer-brand"><img class="login-footer-logo" src="{{ asset('images/portal/logo-sragen.png') }}" width="44" height="58" alt="Lambang Kabupaten Sragen"><div><strong>Pemerintah Desa Pilangsari</strong><p>Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah</p></div></div>
    <p class="footer-note">Portal Informasi Geografis Desa Pilangsari.</p>
    <a href="{{ route('home') }}">Kembali ke halaman publik</a>
</footer>
@else
<footer class="site-footer">
    <div class="footer-brand">@include('public.village-logo')<div><strong>Pemerintah Desa Pilangsari</strong><p>Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah</p></div></div>
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
@unless($isLoginPage)
@include('public.complaint-widget')
@endunless
@stack('scripts')
</body>
</html>
