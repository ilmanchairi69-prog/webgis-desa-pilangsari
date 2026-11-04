@extends('layouts.admin')

@section('title', $layer->exists ? 'Edit Layer' : 'Tambah Layer')
@section('top-title', 'Manajemen Layer')

@push('head')
<link rel="stylesheet" href="{{ asset('css/layer-management.css') }}">
@endpush

@section('content')
<div class="layer-page">

    <section class="layer-heading">
        <div>
            <span class="layer-eyebrow">PENGELOLAAN DATA SPASIAL</span>
            <h1>{{ $layer->exists ? 'Edit Layer' : 'Tambah Layer' }}</h1>
            <p>Atur identitas dan karakteristik kelompok data geografis.</p>
        </div>

        <a href="{{ route('admin.layers.index') }}" class="layer-secondary-btn">
            Kembali
        </a>
    </section>

    @if($errors->any())
        <div class="layer-alert layer-alert-error">
            <strong>Data belum dapat disimpan.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="layer-form-panel">
        <div class="layer-form-heading">
            <h2>Informasi Layer</h2>
            <p>Lengkapi informasi berikut dengan benar.</p>
        </div>

        <form method="POST"
              action="{{ $layer->exists ? route('admin.layers.update', $layer) : route('admin.layers.store') }}">

            @csrf
            @if($layer->exists)
                @method('PUT')
            @endif

            <div class="layer-form-grid">

                <label class="layer-field layer-field-full">
                    <span>Nama Layer <b>*</b></span>
                    <input type="text"
                           name="name"
                           required
                           maxlength="150"
                           placeholder="Contoh: Rumah Warga Desa Pilangsari"
                           value="{{ old('name', $layer->name) }}">
                </label>

                <label class="layer-field layer-field-full">
                    <span>Deskripsi</span>
                    <textarea name="description"
                              rows="4"
                              maxlength="1000"
                              placeholder="Jelaskan fungsi dan isi layer...">{{ old('description', $layer->description) }}</textarea>
                </label>

                <label class="layer-field">
                    <span>Jenis Geometri <b>*</b></span>
                    <select name="layer_type" required>
                        @foreach(($layer->exists && $layer->layer_type !== 'point' ? [$layer->layer_type => 'Geometri referensi (dipertahankan)'] : ['point'=>'Titik (Point)']) as $value => $label)
                            <option value="{{ $value }}"
                                @selected(old('layer_type', $layer->layer_type) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="layer-field">
                    <span>Sumber Data</span>
                    <input type="text"
                           name="source_name"
                           maxlength="255"
                           placeholder="Contoh: Survei lapangan"
                           value="{{ old('source_name', $layer->source_name) }}">
                </label>

                <label class="layer-field layer-field-full">
                    <span>Warna Layer <b>*</b></span>
                    <div class="layer-color-input">
                        <input type="color"
                               id="layerColorPicker"
                               value="{{ old('color', $layer->color ?? '#16803c') }}">
                        <input type="text"
                               id="layerColorText"
                               name="color"
                               required
                               pattern="#[0-9A-Fa-f]{6}"
                               maxlength="7"
                               value="{{ old('color', $layer->color ?? '#16803c') }}">
                    </div>
                    <small>Pilih warna untuk membedakan tampilan objek pada peta.</small>
                </label>

                @if($layer->exists)
                    <div class="layer-field">
                        <span>Status Layer <b>*</b></span>
                        <select name="status" required>
                            @foreach(['draft'=>'Draft','published'=>'Published','archived'=>'Archived'] as $value => $label)
                                <option value="{{ $value }}"
                                    @selected(old('status', $layer->status) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <small>Status layer ini tidak menentukan publikasi rumah atau fasilitas.</small>
                    </div>

                    <input type="hidden" name="is_public" value="{{ $layer->is_public ? '1' : '0' }}">
                    <p class="layer-field">Layer ini tetap menjadi wadah penyimpanan objek. Publikasi ditentukan oleh status setiap rumah atau fasilitas.</p>
                @else
                    <div class="layer-notice layer-field-full">
                        <strong>Layer baru dibuat sebagai Draft dengan penanda Internal.</strong>
                        <p>Status layer tidak menghalangi publikasi rumah atau fasilitas. Visibilitas publik ditentukan oleh syarat dan publikasi individual setiap objek.</p>
                    </div>
                @endif

            </div>

            @if($layer->exists)
                <div class="layer-privacy-note">
                    <strong>Perlindungan data</strong>
                    <p>
                        Visibilitas publik diatur melalui status setiap objek, bukan penanda layer atau toggle kategori.
                        Status verifikasi, publikasi, persetujuan, dan privasi setiap objek
                        tetap diperiksa secara terpisah.
                    </p>
                </div>
            @endif

            <div class="layer-form-actions">
                <a href="{{ route('admin.layers.index') }}" class="layer-secondary-btn">
                    Batal
                </a>
                <button type="submit" class="layer-primary-btn">
                    Simpan Layer
                </button>
            </div>

        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('layerColorPicker');
    const text = document.getElementById('layerColorText');

    if (!picker || !text) return;

    picker.addEventListener('input', function () {
        text.value = picker.value.toUpperCase();
    });

    text.addEventListener('input', function () {
        if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) {
            picker.value = text.value;
        }
    });
});
</script>
@endpush
