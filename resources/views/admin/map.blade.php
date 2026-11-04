@extends('layouts.admin')
@section('title','Peta Internal')
@section('top-title','Peta Internal')
@push('head')<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">@endpush
@section('content')
<div class="page-heading"><div><div class="section-overline">PETA INTERNAL</div><h1>Peta titik survei</h1><p>Peta ini memuat titik internal dan nama kepala keluarga sesuai kewenangan akun. Jangan bagikan tautan atau tangkapan layarnya ke publik.</p></div><a href="{{ route('admin.houses.create') }}" class="btn btn-dark">+ Input rumah</a></div>
<div class="panel map-admin-panel"><form method="get" class="map-admin-search"><label>Cari kode, nama kepala keluarga, RT, atau alamat<input type="search" name="q" value="{{ $search }}" placeholder="Kode rumah, nama KK, RT 01, atau alamat"></label><button type="submit" class="btn btn-dark">Cari</button></form><div id="admin-map" class="admin-map-canvas"></div><div class="map-legend"><span><i class="legend-house"></i>Rumah internal</span><span>Jumlah titik pada hasil pencarian: <strong>{{ $points->count() }}</strong></span></div></div>
@endsection
@push('scripts')<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script><script>window.adminHousePoints={{ Illuminate\Support\Js::from($points) }};</script><script src="{{ asset('js/cluster-map.js') }}"></script><script src="{{ asset('js/admin-map.js') }}"></script>@endpush
