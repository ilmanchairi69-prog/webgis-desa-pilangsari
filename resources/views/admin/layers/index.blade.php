@extends('layouts.admin')

@section('title', 'Manajemen Layer')
@section('top-title', 'Manajemen Layer')

@push('head')
<link rel="stylesheet" href="{{ asset('css/layer-management.css') }}">
@endpush

@section('content')
<div class="layer-page">

    <section class="layer-heading">
        <div>
            <span class="layer-eyebrow">PENGELOLAAN DATA SPASIAL</span>
            <h1>Manajemen Layer</h1>
            <p>Kelola kelompok data geografis yang digunakan pada peta WebGIS Desa Pilangsari.</p>
        </div>

        <a href="{{ route('admin.layers.create') }}" class="layer-primary-btn">
            <span>＋</span> Tambah Layer
        </a>
    </section>

    <section class="layer-information">
        <strong>Menampilkan rumah dan fasilitas pada peta publik</strong>
        <p>
            Pengaturan di halaman ini mengelola layer GIS. Status dan penanda layer
            tidak menentukan publikasi rumah atau fasilitas.
            Publikasikan setiap objek melalui halaman rumah atau fasilitas setelah seluruh syarat individual terpenuhi.
        </p>
    </section>

    @if(session('success'))
        <div class="layer-alert layer-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="layer-alert layer-alert-error">
            <strong>Periksa kembali data:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $totalLayers = $layers->total();
        $publishedLayers = $layers->getCollection()->where('status', 'published')->count();
        $publicLayers = $layers->getCollection()->where('is_public', true)->count();
        $internalLayers = $layers->getCollection()->where('is_public', false)->count();
    @endphp

    <section class="layer-stat-grid">
        <article class="layer-stat-card">
            <span class="layer-stat-label">Total Layer</span>
            <strong>{{ $totalLayers }}</strong>
            <small>Seluruh kelompok data</small>
        </article>

        <article class="layer-stat-card">
            <span class="layer-stat-label">Layer Published</span>
            <strong>{{ $publishedLayers }}</strong>
            <small>Status layer; bukan publikasi objek</small>
        </article>

        <article class="layer-stat-card">
            <span class="layer-stat-label">Penanda Publik Layer</span>
            <strong>{{ $publicLayers }}</strong>
            <small>Penanda lama; bukan publikasi objek</small>
        </article>

        <article class="layer-stat-card">
            <span class="layer-stat-label">Penanda Internal Layer</span>
            <strong>{{ $internalLayers }}</strong>
            <small>Penanda layer pada halaman ini</small>
        </article>
    </section>

    <section class="layer-panel">
        <div class="layer-panel-heading">
            <div>
                <h2>Daftar Layer</h2>
                <p>Atur identitas, jenis geometri, dan penanda layer GIS.</p>
            </div>
            <span class="layer-total">{{ $totalLayers }} layer</span>
        </div>

        <div class="layer-table-wrap">
            <table class="layer-table">
                <thead>
                    <tr>
                        <th>Nama Layer</th>
                        <th>Geometri</th>
                        <th>Warna</th>
                        <th>Jumlah Objek</th>
                        <th>Status Layer</th>
                        <th>Penanda Lama</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($layers as $layer)
                        <tr>
                            <td>
                                <div class="layer-name-cell">
                                    <strong>{{ $layer->name }}</strong>
                                    <small>{{ $layer->slug }}</small>
                                </div>
                            </td>

                            <td>
                                <span class="layer-type">
                                    {{ match($layer->layer_type) {
                                        'point' => 'Titik',
                                        'line' => 'Garis',
                                        'polygon' => 'Poligon',
                                        default => ucfirst($layer->layer_type)
                                    } }}
                                </span>
                            </td>

                            <td>
                                <span class="layer-color">
                                    <i style="background: {{ $layer->color }}"></i>
                                    {{ $layer->color }}
                                </span>
                            </td>

                            <td>
                                <strong>{{ $layer->features_count }}</strong>
                                <small class="layer-object-label">objek</small>
                            </td>

                            <td>
                                <span class="layer-status status-{{ $layer->status }}">
                                    {{ ucfirst($layer->status) }}
                                </span>
                            </td>

                            <td>
                                @if($layer->is_public && $layer->status === 'published')
                                    <span class="layer-access access-public">Publik (layer)</span>
                                @else
                                    <span class="layer-access access-internal">Internal (layer)</span>
                                @endif
                            </td>

                            <td>
                                <div class="layer-actions">
                                    <a href="{{ route('admin.layers.edit', $layer) }}" class="layer-edit-btn">
                                        Atur
                                    </a>

                                    @if($layer->status !== 'archived')
                                        <form method="POST"
                                              action="{{ route('admin.layers.destroy', $layer) }}"
                                              onsubmit="return confirm('Arsipkan layer ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="layer-archive-btn">
                                                Arsip
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="layer-empty">
                                Belum ada layer yang tersedia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="layer-pagination">
            {{ $layers->links() }}
        </div>
    </section>

    <section class="layer-information">
        <strong>Catatan pengelolaan publikasi</strong>
        <p>
            Publikasi rumah dan fasilitas diperiksa langsung dari data objek, tanpa mensyaratkan
            status, relasi layer, atau toggle kategori global.
            Verifikasi, publikasi individual, persetujuan, koordinat, dan arsip setiap objek
            tetap diperiksa terpisah.
        </p>
    </section>

</div>
@endsection
