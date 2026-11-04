@extends('layouts.public')
@section('title', 'Peta Interaktif')
@include('public.map-assets')
@section('content')
<section class="portal-map-heading"><div class="portal-container"><a class="portal-breadcrumb" href="{{ route('home') }}">Beranda <span>/ WebGIS Desa</span></a><span class="portal-eyebrow">JELAJAHI DESA PILANGSARI</span><h1>Satu peta. <em>Lebih dekat.</em></h1><p>Temukan lokasi rumah dan fasilitas umum. Pilih kategori, cari lokasi, dan kenali wilayah Desa Pilangsari.</p></div></section>
<section class="portal-container portal-map-page" aria-label="Peta publik dan pencarian">
    @include('public.map-workspace')
    <div class="portal-map-notes"><div>@include('public.portal-icon', ['name' => 'globe'])<div><h2>Batas sebagai referensi</h2><p>Batas berasal dari berkas referensi QGIS. Konfirmasikan batas resmi kepada pemerintah desa sebelum digunakan untuk keperluan legal atau pertanahan.</p></div></div><div>@include('public.portal-icon', ['name' => 'shield'])<div><h2>Data keluarga terlindungi</h2><p>Hanya informasi lokasi yang diizinkan tampil di peta publik. Nama dan identitas anggota keluarga tidak ditampilkan.</p></div></div></div>
</section>
@endsection
