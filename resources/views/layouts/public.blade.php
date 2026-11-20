@php
    $isVillagePortal = request()->routeIs('home', 'map', 'profil', 'pemerintahan', 'informasi', 'agenda', 'public.*');
    $isLoginPage = request()->routeIs('login', 'login.public-alias');
    $seoPages = [
        'home' => ['Desa Pilangsari Gesi | Website Resmi Pemerintah Desa Pilangsari', 'Website resmi Pemerintah Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen. Temukan profil desa, informasi pemerintahan, agenda, layanan masyarakat, dan peta wilayah.'],
        'profil' => ['Profil Desa Pilangsari Gesi', 'Mengenal sejarah, wilayah administratif, kehidupan masyarakat, dan potensi Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen.'],
        'pemerintahan' => ['Pemerintah Desa Pilangsari Gesi', 'Kenali struktur pemerintahan, perangkat desa, kebayanan, dan pelayanan Pemerintah Desa Pilangsari, Kecamatan Gesi, Sragen.'],
        'agenda' => ['Agenda Desa Pilangsari Gesi', 'Informasi kegiatan dan agenda resmi di lingkungan Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen.'],
        'map' => ['Peta Desa Pilangsari Gesi | WebGIS', 'Jelajahi peta digital Desa Pilangsari serta lokasi rumah dan fasilitas umum yang telah diverifikasi untuk publikasi.'],
    ];
    $seoRoute = request()->routeIs('informasi') ? 'agenda' : request()->route()?->getName();
    $defaultSeo = $seoPages[$seoRoute] ?? null;
    $seo = isset($seoPage) ? [$seoPage['title'], $seoPage['description']] : $defaultSeo;
    $baseUrl = rtrim(config('app.url'), '/');
    $canonicalRoute = $seoPage['canonicalRoute'] ?? $seoRoute;
    $canonicalUrl = $seo ? $baseUrl.route($canonicalRoute, [], false) : null;
    $isIndexable = $seo && ($seoPage['index'] ?? true);
    $seoTitle = $seo ? $seo[0] : 'Masuk Admin | WebGIS Desa Pilangsari';
    $breadcrumbs = $seoPage['breadcrumbs'] ?? [
        ['name' => 'Beranda', 'route' => 'home'],
        ['name' => $seo[0] ?? 'Masuk Admin', 'route' => $canonicalRoute],
    ];
    $organizationId = $baseUrl.'/#organization';
    $websiteId = $baseUrl.'/#website';
    $structuredData = [];
    if ($seo) {
        $structuredData[] = [
            '@type' => 'GovernmentOrganization',
            '@id' => $organizationId,
            'name' => 'Pemerintah Desa Pilangsari',
            'url' => $baseUrl.'/',
            'logo' => $baseUrl.'/images/portal/logo-sragen.png',
            'areaServed' => 'Desa Pilangsari, Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah',
        ];
        $structuredData[] = [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => $baseUrl.'/',
            'name' => 'Website Resmi Desa Pilangsari',
            'publisher' => ['@id' => $organizationId],
            'inLanguage' => 'id-ID',
        ];
        $structuredData[] = [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl.'#webpage',
            'url' => $canonicalUrl,
            'name' => $seoTitle,
            'description' => $seo[1],
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $organizationId],
            'inLanguage' => 'id-ID',
        ];
        if (count($breadcrumbs) > 1) {
            $structuredData[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($breadcrumbs)->values()->map(fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $baseUrl.route($item['route'], [], false),
                ])->all(),
            ];
        }
        if ($seoRoute === 'agenda') {
            $structuredData[] = [
                '@type' => 'Event',
                'name' => 'Sosialisasi Website dan Peta Digital Desa Pilangsari',
                'startDate' => '2026-11-21T09:00:00+07:00',
                'endDate' => '2026-11-21T11:00:00+07:00',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location' => ['@type' => 'Place', 'name' => 'Balai Desa Pilangsari'],
                'organizer' => ['@id' => $organizationId],
                'url' => $canonicalUrl,
                'description' => 'Presentasi dan sosialisasi website resmi serta WebGIS Desa Pilangsari kepada Pemerintah Desa.',
            ];
        }
    }
@endphp
<!doctype html>
<html lang="id">
<head>
    @include('favicon')
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seoTitle }}</title>
    @if($seo)
    <meta name="robots" content="{{ $isIndexable ? 'index, follow' : 'noindex, follow' }}">
    <meta name="description" content="{{ $seo[1] }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="WebGIS Desa Pilangsari">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seo[1] }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $baseUrl }}/images/portal/landscape-reference.jpg">
    <meta property="og:image:width" content="1376">
    <meta property="og:image:height" content="768">
    <meta property="og:image:alt" content="Lanskap pedesaan untuk portal Desa Pilangsari">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seo[1] }}">
    <meta name="twitter:image" content="{{ $baseUrl }}/images/portal/landscape-reference.jpg">
    <script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org', '@'.'graph' => $structuredData], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
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
