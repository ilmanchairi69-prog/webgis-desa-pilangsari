<div class="portal-topbar"><div class="portal-container"><span>@include('public.portal-icon', ['name' => 'pin']) Kecamatan Gesi, Kabupaten Sragen, Jawa Tengah</span><span class="portal-topbar-note">Informasi wilayah, lebih dekat.</span></div></div>
<header class="portal-header">
    <div class="portal-container portal-header-inner">
        <a class="portal-brand" href="{{ route('home') }}" aria-label="Beranda Pemerintah Desa Pilangsari">
            <img src="{{ asset('images/portal/logo-sragen.png') }}" width="44" height="58" alt="Lambang Kabupaten Sragen">
            <span><strong>PEMERINTAH DESA <b>PILANGSARI</b></strong><small>KECAMATAN GESI · KABUPATEN SRAGEN</small></span>
        </a>
        <button class="portal-menu-toggle" type="button" data-portal-toggle aria-expanded="false" aria-controls="portal-navigation" aria-label="Buka menu navigasi">@include('public.portal-icon', ['name' => 'menu']) <span>Menu</span></button>
        <nav class="portal-navigation" id="portal-navigation" aria-label="Navigasi publik">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
            <a href="{{ route('profil') }}" @if(request()->routeIs('profil')) aria-current="page" @endif>Profil Desa</a>
            <a href="{{ route('pemerintahan') }}" @if(request()->routeIs('pemerintahan')) aria-current="page" @endif>Pemerintahan</a>
            <a href="{{ route('agenda') }}" @if(request()->routeIs('informasi', 'agenda')) aria-current="page" @endif>Agenda</a>
            <a href="{{ route('map') }}" @if(request()->routeIs('map')) aria-current="page" @endif>Peta Desa</a>
            <a class="portal-button portal-button-yellow portal-nav-cta" href="{{ route('map') }}">Jelajahi Peta @include('public.portal-icon', ['name' => 'arrow'])</a>
        </nav>
    </div>
</header>
