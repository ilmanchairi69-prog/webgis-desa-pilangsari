<footer class="portal-footer">
    <div class="portal-container portal-footer-grid">
        <div class="portal-footer-identity"><a class="portal-brand" href="{{ route('home') }}"><img src="{{ asset('images/portal/logo-sragen.png') }}" width="44" height="58" alt="Lambang Kabupaten Sragen"><span><strong>DESA <b>PILANGSARI</b></strong><small>PORTAL INFORMASI DAN WEBGIS</small></span></a><p>Mengenal wilayah dan menemukan lokasi publik Desa Pilangsari melalui informasi geografis yang mudah diakses.</p></div>
        <div><h2>Jelajahi Portal</h2><a href="{{ route('home') }}">Beranda</a><a href="{{ route('profil') }}">Profil desa</a><a href="{{ route('pemerintahan') }}">Pemerintahan desa</a><a href="{{ route('agenda') }}">Agenda desa</a></div>
        <div><h2>Peta Desa</h2><a href="{{ route('map') }}">Peta interaktif</a><a href="{{ route('home') }}#geografis">Wilayah desa</a><a href="{{ route('home') }}#privasi">Privasi data publik</a></div>
        <div><h2>Wilayah Administratif</h2><p>Desa Pilangsari<br>Kecamatan Gesi<br>Kabupaten Sragen<br>Provinsi Jawa Tengah</p></div>
    </div>
    <div class="portal-container portal-footer-bottom"><span>© {{ date('Y') }} Pemerintah Desa Pilangsari.</span><span>Data keluarga terlindungi. Peta untuk informasi publik.</span></div>
</footer>
