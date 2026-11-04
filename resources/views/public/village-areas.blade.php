<div class="village-grid village-areas">
@foreach ([['Tanggung', 'Sutarno', 'Tanggung, Ngrawoh, Duren, Dukuh'], ['Bener', 'Ahmad Syaifudin', 'Bener, Nglojok, Templek, Pilangsari'], ['Taraman', 'Wagiyo', 'Taraman, Munggur, Pilangsari']] as $area)
<article class="village-card"><span class="portal-eyebrow">KEBAYANAN {{ $loop->iteration }}</span><h3>{{ $area[0] }}</h3>@if(request()->routeIs('pemerintahan'))<p>Koordinator wilayah: {{ $area[1] }}</p>@endif<p>{{ $area[2] }}</p></article>
@endforeach
</div>
