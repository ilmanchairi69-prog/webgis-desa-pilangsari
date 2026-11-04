@extends('layouts.admin')
@section('top-title', 'Objek Peta')
@section('title', $feature->exists ? 'Edit Objek Peta' : 'Tambah Objek Peta')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
#house-picker-map { height: 420px; width: 100%; border: 1px solid #d6ded7; border-radius: 12px; margin: 12px 0; }
.editor-help { color: #58665d; font-size: 14px; }
</style>
@endpush

@section('content')
<section class="page-heading">
    <span class="eyebrow">ADMINISTRASI WEBGIS</span>
    <h1>{{ $feature->exists ? 'Edit Objek Peta' : 'Tambah Objek Peta' }}</h1>
</section>

<section class="panel form-panel feature-form-panel">
    @if($errors->any())
        <div class="error-message">
            <strong>Periksa input:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $feature->exists ? route('admin.features.update', $feature) : route('admin.features.store') }}">
        @csrf
        @if($feature->exists) @method('PUT') @endif

        <label>Nama objek
            <input name="name" required maxlength="200" value="{{ old('name', $feature->name) }}">
        </label>

        <label>Layer
            <select name="layer_id" required>
                <option value="">Pilih layer</option>
                @foreach($layers as $layer)
                    <option value="{{ $layer->id }}" @selected(old('layer_id', $feature->layer_id) == $layer->id)>
                        {{ $layer->name }} ({{ $layer->layer_type }})
                    </option>
                @endforeach
            </select>
        </label>

        <label>Kategori
            <input name="category" list="feature-category-master" maxlength="100" value="{{ old('category', $feature->category) }}">
            <datalist id="feature-category-master">@foreach($categories as $category)<option value="{{ $category['value'] }}">{{ $category['name'] }}</option>@endforeach</datalist>
        </label>

        <label>Deskripsi
            <textarea name="description" rows="3">{{ old('description', $feature->description) }}</textarea>
        </label>

        <h2>Lokasi objek (Point)</h2>
        <p class="editor-help">Klik peta atau isi koordinat. Batas desa merupakan referensi dan tidak dapat diedit melalui form ini.</p>
        <div id="house-picker-map" aria-label="Pemilih titik objek"></div>
        <p id="house-picker-status" role="status"></p>
        <div class="form-two">
            <label>Latitude
                <input id="house-lat" required type="number" step="any" min="-90" max="90" name="latitude" value="{{ old('latitude', $feature->latitude) }}">
            </label>
            <label>Longitude
                <input id="house-lng" required type="number" step="any" min="-180" max="180" name="longitude" value="{{ old('longitude', $feature->longitude) }}">
            </label>
        </div>

        <label>Sumber data
            <input name="source_name" list="map-source-master" maxlength="255" value="{{ old('source_name', $feature->source_name) }}" placeholder="Pilih referensi atau tulis sumber yang dapat diverifikasi">
            <datalist id="map-source-master">@foreach($dataSources as $source)<option value="{{ $source }}">@endforeach</datalist>
        </label>

        <button class="btn btn-dark" type="submit">Simpan draft</button>
        <a class="btn btn-light" href="{{ route('admin.features.index') }}">Batal</a>
    </form>
</section>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/house-form.js') }}"></script>
@endpush
