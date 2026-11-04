@extends('layouts.admin')
@section('title','Edit Rumah')
@section('top-title','Edit Rumah')
@push('head')
<style>
.house-edit{max-width:1050px;margin:auto;color:#1b2820}
.house-edit .edit-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin:8px 0 24px}
.house-edit h1{font-size:clamp(28px,3vw,40px);letter-spacing:-.04em;margin:8px 0}
.house-edit .muted{color:#849087;line-height:1.65}
.house-edit .edit-card{background:#fff;border:1px solid #e3e9e2;border-radius:18px;padding:26px;box-shadow:0 8px 28px rgba(22,40,27,.035);margin-bottom:18px}
.house-edit .edit-card h2{font-size:19px;margin:0 0 20px}
.house-edit .edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.house-edit .field{display:grid;gap:8px;min-width:0}
.house-edit .field.full{grid-column:1/-1}
.house-edit .field label{font-size:13px;font-weight:700;color:#526056}
.house-edit input,.house-edit textarea,.house-edit select{width:100%;box-sizing:border-box;border:1px solid #dce5dc;border-radius:12px;padding:13px 15px;background:#fff;color:#202a23;font:inherit;min-height:48px;outline:none}
.house-edit textarea{min-height:94px;resize:vertical}
.house-edit input:focus,.house-edit textarea:focus{border-color:#267448;box-shadow:0 0 0 3px rgba(38,116,72,.1)}
.house-edit input[readonly]{background:#f5f7f4;color:#7c887f}
.house-edit .help{font-size:12px;color:#89958c;line-height:1.6}
.house-edit .family-edit{padding:15px;border:1px solid #e8ede7;border-radius:13px;margin-top:12px}
.house-edit .edit-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.house-edit .edit-actions .btn{min-height:48px;display:inline-flex;align-items:center;justify-content:center;padding:0 20px;border-radius:12px}
.house-edit .error-box{background:#fff4f2;border:1px solid #f1cbc5;color:#a63b32;padding:14px 18px;border-radius:12px;margin-bottom:18px}
@media(max-width:650px){.house-edit .edit-hero{flex-direction:column}.house-edit .edit-grid{grid-template-columns:1fr}.house-edit .field.full{grid-column:auto}.house-edit .edit-card{padding:18px}.house-edit .edit-actions{flex-direction:column-reverse}.house-edit .edit-actions .btn{width:100%}}
</style>
@endpush
@section('content')
<div class="house-edit">
    <header class="edit-hero">
        <div><div class="section-overline">PERUBAHAN DATA SURVEI</div><h1>Edit Rumah</h1><p class="muted">Perbarui informasi bangunan, alamat, lokasi, kepala keluarga, dan anggota keluarga.</p></div>
        <a class="btn btn-light" href="{{ route('admin.houses.show',$house) }}">Kembali ke detail</a>
    </header>
    @if($errors->any())
        <div class="error-box"><strong>Perubahan belum dapat disimpan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($house->rejection_note)
        <div class="error-box"><strong>Catatan penolakan dari Super Admin:</strong><p>{{ $house->rejection_note }}</p><span>Perbaiki data di bawah ini. Setelah disimpan, rumah akan kembali ke antrean verifikasi.</span></div>
    @endif
    @php
        $editLat = ($house->latitude === null || (float)$house->latitude === 0.0) ? $house->mapFeature?->latitude : $house->latitude;
        $editLng = ($house->longitude === null || (float)$house->longitude === 0.0) ? $house->mapFeature?->longitude : $house->longitude;
    @endphp
    <form method="POST" action="{{ route('admin.houses.update',$house) }}">
        @csrf @method('PUT')
        <section class="edit-card">
            <h2>Informasi bangunan</h2>
            <div class="edit-grid">
                <div class="field"><label>Kode rumah</label><input value="{{ $house->house_code }}" readonly></div>
                <div class="field"><label for="house_label">Label rumah</label><input id="house_label" name="house_label" maxlength="200" value="{{ old('house_label',$house->house_label) }}" placeholder="Contoh: Rumah dekat pos ronda"></div>
                <div class="field">
                    <label for="rt_code">RT</label>
                    @if(auth()->user()->isRtAdmin())
                        <input value="RT {{ auth()->user()->rt_code }}" readonly>
                        <input type="hidden" name="rt_code" value="{{ auth()->user()->rt_code }}">
                    @else
                        <input id="rt_code" name="rt_code" maxlength="20" value="{{ old('rt_code',$house->rt_code) }}" placeholder="Contoh: 01">
                    @endif
                </div>
                <div class="field full"><label for="address">Alamat lengkap *</label><textarea id="address" name="address" required maxlength="500" placeholder="Dukuh/jalan, nomor rumah, RT/RW, atau patokan">{{ old('address',$house->address) }}</textarea></div>
            </div>
        </section>
        <section class="edit-card">
            <h2>Lokasi bangunan</h2>
            <div class="edit-grid">
                <div class="field"><label for="latitude">Latitude *</label><input id="latitude" name="latitude" type="number" step="any" min="-90" max="90" required value="{{ old('latitude', $editLat !== null ? number_format((float)$editLat,7,'.','') : '') }}"></div>
                <div class="field"><label for="longitude">Longitude *</label><input id="longitude" name="longitude" type="number" step="any" min="-180" max="180" required value="{{ old('longitude', $editLng !== null ? number_format((float)$editLng,7,'.','') : '') }}"></div>
                <p class="help field full">Gunakan angka desimal dengan titik. Pastikan pin/koordinat tepat pada bangunan. Perubahan koordinat akan mengembalikan status verifikasi menjadi Pending.</p>
            </div>
        </section>
        <section class="edit-card">
            <h2>Izin publikasi titik rumah</h2>
            <label style="display:flex;gap:10px;align-items:flex-start;line-height:1.65">
                <input type="checkbox" name="public_visible" value="1" {{ old('public_visible', $house->public_visible) ? 'checked' : '' }} style="width:18px;min-height:18px;margin-top:3px">
                <span>Saya menyatakan izin warga/pemilik rumah untuk menampilkan titik lokasi rumah secara umum pada peta publik telah diperoleh. Nama kepala keluarga, nomor KK, nama anggota keluarga, dan alamat rinci tetap privat.</span>
            </label>
            <p class="help">Perubahan rumah atau data keluarga mengembalikan rumah ke antrean verifikasi. Publikasi hanya dapat dilakukan Super Admin setelah pemeriksaan ulang.</p>
        </section>
        <section class="edit-card">
            <h2>Kartu keluarga dan anggota</h2>
            <p class="help">Data keluarga hanya tersedia bagi pengelola yang berwenang dan tidak ditampilkan pada peta publik.</p>
            @forelse($house->familyCards as $card)
                <div class="family-edit field">
                    <label for="card-{{ $card->id }}">Kartu keluarga {{ $loop->iteration }}</label>
                    <input id="card-{{ $card->id }}" name="family_cards[{{ $card->id }}][head_name]" required maxlength="160" value="{{ old('family_cards.'.$card->id.'.head_name',$card->head_name ?: $card->head_of_family_name) }}">
                    @foreach($card->familyMembers as $member)
                        @php($memberInput = 'family_cards.'.$card->id.'.members.'.$member->id)
                        @php($memberName = 'family_cards['.$card->id.'][members]['.$member->id.']')
                        <fieldset class="family-edit">
                            <legend>Anggota {{ $loop->iteration }}</legend>
                            <div class="edit-grid">
                                <div class="field"><label for="member-name-{{ $member->id }}">Nama anggota</label><input id="member-name-{{ $member->id }}" class="family-member-name" name="{{ $memberName }}[full_name]" required maxlength="200" value="{{ old($memberInput.'.full_name', $member->full_name) }}"></div>
                                <div class="field"><label for="member-relationship-{{ $member->id }}">Hubungan keluarga</label><input id="member-relationship-{{ $member->id }}" class="family-member-relationship" name="{{ $memberName }}[relationship]" maxlength="50" value="{{ old($memberInput.'.relationship', $member->relationship) }}"></div>
                                <div class="field"><label for="member-dob-{{ $member->id }}">Tanggal lahir</label><input id="member-dob-{{ $member->id }}" class="family-member-dob" name="{{ $memberName }}[date_of_birth]" type="date" max="{{ now()->toDateString() }}" value="{{ old($memberInput.'.date_of_birth', $member->date_of_birth?->format('Y-m-d')) }}"></div>
                                <div class="field"><label for="member-gender-{{ $member->id }}">Jenis kelamin</label><select id="member-gender-{{ $member->id }}" class="family-member-gender" name="{{ $memberName }}[gender]">@foreach(['' => 'Belum diisi', 'Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan'] as $value => $label)<option value="{{ $value }}" @selected(old($memberInput.'.gender', $member->gender) === $value)>{{ $label }}</option>@endforeach</select></div>
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            @empty
                <p class="muted">Belum ada kartu keluarga pada bangunan ini.</p>
            @endforelse
        </section>
        <section class="edit-card">
            <p class="help">Perubahan akan dicatat dalam audit log. Data anggota keluarga tidak dihapus. Rumah yang diperbarui akan masuk kembali ke antrean verifikasi.</p>
            <div class="edit-actions"><a class="btn btn-light" href="{{ route('admin.houses.show',$house) }}">Batal</a><button class="btn btn-dark" type="submit">Simpan perubahan</button></div>
        </section>
    </form>
</div>
@endsection
