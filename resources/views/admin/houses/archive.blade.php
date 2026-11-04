@extends('layouts.admin')
@section('title','Arsip Rumah')
@section('top-title','Konfirmasi Arsip Rumah')
@push('head')
<style>
.house-archive{max-width:820px;margin:18px auto;color:#1c2920}
.house-archive .archive-card{background:#fff;border:1px solid #e4eae3;border-radius:20px;padding:28px;box-shadow:0 8px 28px rgba(22,40,27,.035)}
.house-archive h1{font-size:32px;letter-spacing:-.04em;margin:8px 0}
.house-archive .muted{color:#849087;line-height:1.65}
.house-archive .archive-warning{padding:15px 17px;background:#fff8e8;border:1px solid #f2dfac;color:#795d16;border-radius:12px;margin:20px 0;line-height:1.6}
.house-archive .archive-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
.house-archive .archive-actions .btn{min-height:48px;display:inline-flex;align-items:center;padding:0 20px;border-radius:12px}
@media(max-width:600px){.house-archive .archive-card{padding:20px}.house-archive .archive-actions{flex-direction:column-reverse}.house-archive .archive-actions .btn{justify-content:center;width:100%}}
</style>
@endpush
@section('content')
<div class="house-archive">
    <section class="archive-card">
        <div class="section-overline">KONFIRMASI TINDAKAN</div>
        <h1>Arsipkan rumah ini?</h1>
        <p class="muted">Rumah akan dikeluarkan dari daftar aktif dan peta internal. Tindakan ini bukan penghapusan permanen.</p>
        <h2>{{ $house->house_code }}</h2>
        <p><strong>RT:</strong> {{ $house->rt_code ? 'RT '.$house->rt_code : 'Belum ditentukan' }}</p>
        <p><strong>Alamat:</strong> {{ $house->address ?: 'Belum diisi' }}</p>
        <p><strong>Kepala keluarga:</strong> {{ $house->familyCards->map(fn($card) => $card->head_name ?: $card->head_of_family_name)->filter()->implode(', ') ?: '—' }}</p>
        <p><strong>Kartu keluarga:</strong> {{ $house->familyCards->count() }}</p>
        <div class="archive-warning"><strong>Perlu diketahui:</strong> data rumah, kartu keluarga, dan anggota keluarga tetap tersimpan di database sebagai arsip. Data tidak dihapus permanen.</div>
        <form method="POST" action="{{ route('admin.houses.archive',$house) }}" class="archive-actions">
            @csrf
            <a class="btn btn-light" href="{{ route('admin.houses.show',$house) }}">Batalkan</a>
            <button type="submit" class="btn btn-dark" onclick="return confirm('Yakin mengarsipkan rumah {{ $house->house_code }}?')">Ya, arsipkan rumah</button>
        </form>
    </section>
</div>
@endsection
