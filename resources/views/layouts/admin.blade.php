<!doctype html>
<html lang="id">
<head>
    @include('favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#123c31">
    <title>@yield('title','Dashboard') &mdash; WebGIS Pilangsari</title>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    @stack('head')
    <link rel="stylesheet" href="{{ asset('css/interface.css') }}?v={{ filemtime(public_path('css/interface.css')) }}">
    <script src="{{ asset('js/interface.js') }}?v={{ filemtime(public_path('js/interface.js')) }}" defer></script>
</head>
<body class="admin-body">
<a href="#main-content" class="skip-link">Lewati ke konten</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-navigation" aria-label="Navigasi pengelola">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="brand-mark small-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3 7 6-3 6 3 6-3v13l-6 3-6-3-6 3V7Z"/><path d="M9 4v13M15 7v13"/></svg></span>
            <span><strong>Pilangsari<span class="brand-period">.</span></strong><small>WEBGIS DESA</small></span>
        </a>
        <div class="sidebar-user"><span class="user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isSuperAdmin() ? 'Super Admin' : 'Admin RT '.auth()->user()->rt_code }}</small></div></div>
        <nav class="admin-nav" aria-label="Menu administrasi">
            <div class="nav-caption">RUANG KERJA</div>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg><span>Dashboard</span></a>
            <a href="{{ route('admin.map') }}" class="{{ request()->routeIs('admin.map') ? 'active' : '' }}"><svg viewBox="0 0 24 24"><path d="m3 7 6-3 6 3 6-3v13l-6 3-6-3-6 3V7ZM9 4v13M15 7v13"/></svg><span>Peta Internal</span></a>
            <a href="{{ route('admin.houses.index') }}" class="{{ request()->routeIs('admin.houses.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/></svg><span>Rumah & Keluarga</span></a>
            @if(auth()->user()->isSuperAdmin())
                <div class="nav-caption">PENGELOLAAN DATA</div>
                <a href="{{ route('admin.facilities.index') }}" class="{{ request()->routeIs('admin.facilities.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"><path d="M4 21V7h16v14M2 21h20M8 7V3h8v4M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3"/></svg><span>Fasilitas Umum</span></a>
                <a href="{{ route('admin.verification.index') }}" class="{{ request()->routeIs('admin.verification.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3Z"/><path d="m8 12 3 3 5-6"/></svg><span>Verifikasi & Publikasi</span></a>
                <a href="{{ route('admin.complaints.index') }}" class="{{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"><path d="M4 4h16v12H9l-5 4V4ZM8 8h8M8 12h6"/></svg><span>Pengaduan Masyarakat</span></a>
                {{-- Layer GIS tetap dikelola oleh backend; tidak ditawarkan sebagai menu pengelola. --}}
                <div class="nav-caption">ADMINISTRASI</div>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M16 4a4 4 0 0 1 0 8M19 21v-3a6 6 0 0 0-2-4"/></svg><span>Akun Pengguna</span></a>
                <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"><svg viewBox="0 0 24 24"><path d="M8 3h11v18H5V7l3-4ZM9 3v5H5M9 12h6M9 16h6"/></svg><span>Log Audit</span></a>
            @endif
        </nav>
        <div class="sidebar-bottom">
            <a href="{{ route('home') }}" target="_blank" rel="noopener">Portal publik <span aria-hidden="true">&#8599;</span></a>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar dari sistem <span aria-hidden="true">&#8599;</span></button></form>
            <small>Desa Pilangsari &middot; Kabupaten Sragen</small>
        </div>
    </aside>
    <button class="sidebar-backdrop" data-sidebar-dismiss type="button" aria-label="Tutup menu" tabindex="-1" hidden></button>
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar-heading"><button class="admin-menu-toggle" data-sidebar-toggle type="button" aria-controls="admin-navigation" aria-expanded="false" aria-label="Buka menu administrasi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><div><span class="topbar-overline">WEBGIS / RUANG PENGELOLA</span><strong>@yield('top-title','Dashboard')</strong></div></div>
            <div class="topbar-right"><a class="topbar-public-link" href="{{ route('map') }}" target="_blank" rel="noopener">Lihat peta <span aria-hidden="true">&#8599;</span></a><span class="status-chip"><i></i> Sesi aktif</span><span class="topbar-avatar" title="{{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span></div>
        </header>
        <main class="admin-content" id="main-content" tabindex="-1">
            @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="flash flash-error" role="alert"><strong>Periksa kembali data:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
        <footer class="admin-footer"><span>&copy; {{ date('Y') }} WebGIS Desa Pilangsari</span><span>Data terlindungi. Akses sesuai kewenangan.</span></footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
