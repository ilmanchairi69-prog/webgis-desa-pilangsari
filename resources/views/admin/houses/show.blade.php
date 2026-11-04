@extends('layouts.admin')
@section('title','Detail Rumah')
@section('top-title','Detail Rumah')
@push('head')
<style>
.house-detail{max-width:1200px;margin:auto;color:#1c2920}
.house-detail .hero{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin:8px 0 24px}
.house-detail h1{font-size:clamp(28px,3vw,40px);letter-spacing:-.04em;margin:8px 0}
.house-detail .muted{color:#829087;line-height:1.65}
.house-detail .hero-actions{display:flex;gap:9px;flex-wrap:wrap}
.house-detail .hero-actions .btn{border-radius:12px;padding:12px 17px}
.house-detail .detail-card{background:#fff;border:1px solid #e4eae3;border-radius:18px;padding:24px;margin-bottom:18px;box-shadow:0 8px 28px rgba(22,40,27,.03)}
.house-detail .detail-card h2{font-size:19px;margin:0 0 20px}
.house-detail .detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.house-detail .detail-item{padding:15px;border-radius:13px;background:#f8faf7;min-width:0}
.house-detail .detail-item small{display:block;color:#849087;font-size:11px;margin-bottom:7px}
.house-detail .detail-item strong{display:block;overflow-wrap:anywhere;font-size:14px}
.house-detail .detail-line{padding:14px 0;border-top:1px solid #edf0ec;line-height:1.6}
.house-detail .family-card{border:1px solid #e8ede7;border-radius:14px;padding:17px;margin-top:12px}
.house-detail .family-card h3{margin:0 0 5px;font-size:16px}
.house-detail .member-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:12px 0 0;padding:0;list-style:none}
.house-detail .member-list li{background:#f8faf7;padding:10px 12px;border-radius:10px;font-size:13px}
.house-detail .flash{border:1px solid #cde8d3;background:#f0faf2;color:#267448;padding:13px 16px;border-radius:12px;margin-bottom:18px}
@media(max-width:800px){.house-detail .detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.house-detail .hero{flex-direction:column}.house-detail .hero-actions{width:100%}.house-detail .hero-actions .btn{flex:1;justify-content:center}.house-detail .detail-card{padding:17px}.house-detail .member-list{grid-template-columns:1fr}}
</style>
@endpush
@section('content')
<div class="house-detail">
    @if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif
    <header class="hero">
        <div><div class="section-overline">DATA INTERNAL</div><h1>{{ $house->house_code }}</h1><p class="muted">Informasi bangunan, lokasi survei, dan data keluarga yang terhubung.</p></div>
        <div class="hero-actions">
            <a class="btn btn-light" href="{{ route('admin.houses.index') }}">Kembali</a>
            <a class="btn btn-dark" href="{{ route('admin.houses.edit',$house) }}">Edit data</a>
            <a class="btn btn-light" href="{{ route('admin.houses.archive.confirm',$house) }}">Arsipkan</a>
        </div>
    </header>
    @if($house->rejection_note)
        <section class="detail-card" style="border-color:#f0c7c1;background:#fff8f6">
            <h2 style="color:#a63b32">Catatan penolakan sebelumnya</h2>
            <p style="margin:0;line-height:1.7;color:#713b35">{{ $house->rejection_note }}</p>
            <p class="muted" style="margin:10px 0 0">Perbaiki data yang diminta, lalu simpan perubahan untuk mengirim ulang ke antrean verifikasi.</p>
        </section>
    @endif
    <section class="detail-card">
        <h2>Informasi bangunan</h2>
        <div class="detail-grid">
            <div class="detail-item"><small>Kode rumah</small><strong>{{ $house->house_code }}</strong></div>
            <div class="detail-item"><small>RT</small><strong>{{ $house->rt_code ? 'RT '.$house->rt_code : 'Belum ditentukan' }}</strong></div>
            <div class="detail-item"><small>Verifikasi</small><strong>{{ ucfirst($house->verification_status) }}</strong></div>
            <div class="detail-item"><small>Publikasi</small><strong>{{ ucfirst($house->publication_status) }}</strong></div>
        </div>
        <div class="detail-line"><strong>Label rumah</strong><br>{{ $house->house_label ?: '—' }}</div>
        <div class="detail-line"><strong>Alamat lengkap</strong><br>{{ $house->address ?: 'Belum diisi' }}</div>
        @php($coordinate = $house->coordinatePresentation(7))
        <div class="detail-line"><strong>Koordinat</strong><br>
            {{ $coordinate['label'] }}
            @if($coordinate['status'] === 'valid')
                <br><a href="https://www.google.com/maps?q={{ $coordinate['latitude'] }},{{ $coordinate['longitude'] }}" target="_blank" rel="noopener">Buka lokasi di Google Maps ↗</a>
            @endif
        </div>
    </section>
    <section class="detail-card">
        <h2>Kartu keluarga <span class="muted">({{ $house->familyCards->count() }})</span></h2>
        @forelse($house->familyCards as $card)
            <article class="family-card">
                <h3>{{ $card->head_name ?: $card->head_of_family_name ?: 'Nama kepala keluarga belum diisi' }}</h3>
                <p class="muted">{{ $card->familyMembers->count() }} anggota keluarga tercatat</p>
                @if($card->familyMembers->isNotEmpty())
                    <ul class="member-list">
                        @foreach($card->familyMembers as $member)
                            <li>{{ $member->full_name }} @if($member->relationship)<span class="muted">· {{ $member->relationship }}</span>@endif</li>
                        @endforeach
                    </ul>
                @else <p class="muted">Belum ada anggota tambahan.</p> @endif
            </article>
        @empty <p class="muted">Belum ada kartu keluarga.</p> @endforelse
    </section>
</div>
@endsection
