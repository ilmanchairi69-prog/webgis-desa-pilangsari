@extends('layouts.admin')
@section('title','Rumah & Keluarga')
@section('top-title','Rumah & Keluarga')
@push('head')
<style>
.houses-page{color:#18231c;max-width:1500px;margin:auto}
.houses-page .house-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:4px 0 24px}
.houses-page .house-heading h1{font-size:clamp(30px,3vw,42px);letter-spacing:-.045em;margin:7px 0}
.houses-page .house-heading p{margin:0;color:#78847b;line-height:1.65}
.houses-page .filter-card,.houses-page .table-card{background:#fff;border:1px solid #e4e9e3;border-radius:20px;box-shadow:0 8px 28px rgba(22,40,27,.035);overflow:hidden}
.houses-page .filter-card{padding:22px;margin-bottom:22px}
.houses-page .filter-grid{display:grid;grid-template-columns:minmax(260px,1.5fr) minmax(190px,.9fr) auto auto;gap:14px;align-items:end}
.houses-page .filter-grid label{display:grid;gap:9px;color:#536056;font-size:14px;font-weight:650}
.houses-page .filter-grid input,.houses-page .filter-grid select{height:54px;width:100%;box-sizing:border-box;border:1px solid #dce5dc;border-radius:13px;padding:0 16px;background:#fff;color:#202a23;font:inherit;outline:none}
.houses-page .filter-grid input:focus,.houses-page .filter-grid select:focus{border-color:#27764a;box-shadow:0 0 0 3px rgba(39,118,74,.1)}
.houses-page .filter-grid .btn{height:54px;display:inline-flex;align-items:center;justify-content:center;padding:0 24px;border-radius:13px;white-space:nowrap}
.houses-page .table-head{padding:23px 26px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.houses-page .table-head h2{font-size:21px;margin:0 0 5px;letter-spacing:-.025em}
.houses-page .table-head p{margin:0;color:#8a958c;font-size:14px}
.houses-page .table-wrap{overflow-x:auto}
.houses-page table{width:100%;min-width:1100px;border-collapse:collapse}
.houses-page th{background:#f8faf7;color:#8a958c;text-transform:uppercase;letter-spacing:.08em;font-size:10px;font-weight:750;text-align:left;padding:15px 18px;border-top:1px solid #edf0ec;border-bottom:1px solid #e9eee8;white-space:nowrap}
.houses-page td{padding:19px 18px;border-bottom:1px solid #edf0ec;vertical-align:middle;color:#59665d;font-size:13px;line-height:1.55}
.houses-page tbody tr:hover{background:#fbfcfa}
.houses-page .house-code{font-weight:750;color:#26332a;white-space:nowrap}
.houses-page .sub{display:block;color:#9aa49c;font-size:11px;margin-top:4px}
.houses-page .coord{font-variant-numeric:tabular-nums;white-space:nowrap}
.houses-page .status-pill{display:inline-flex;padding:6px 11px;border-radius:999px;font-size:11px;font-weight:750}
.houses-page .status-pending{background:#fff4cf;color:#8a6500}
.houses-page .status-verified{background:#e4f5e8;color:#237344}
.houses-page .status-rejected{background:#fde8e7;color:#b33a32}
.houses-page .status-draft{background:#eef1f3;color:#66717b}
.houses-page .status-published{background:#e4f5e8;color:#237344}
.houses-page .actions{display:flex;flex-direction:column;align-items:flex-start;gap:8px;white-space:nowrap}
.houses-page .actions a{font-weight:700;text-decoration:none;color:#267448}
.houses-page .actions a.danger{color:#c7443b}
.houses-page .empty{padding:48px 20px;text-align:center;color:#89958c}
.houses-page .table-foot{padding:18px 24px;display:flex;justify-content:flex-end}
.houses-page .alert-success{margin-bottom:18px}
@media(max-width:900px){.houses-page .filter-grid{grid-template-columns:1fr 1fr}.houses-page .filter-grid .btn{width:100%}}
@media(max-width:600px){.houses-page .house-heading{align-items:stretch;flex-direction:column}.houses-page .house-heading .btn{width:100%;justify-content:center}.houses-page .filter-card{padding:16px}.houses-page .filter-grid{grid-template-columns:1fr}.houses-page .table-head{padding:18px;align-items:flex-start;flex-direction:column}}
</style>
@endpush
@section('content')
<div class="houses-page">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <header class="house-heading">
        <div><div class="section-overline">PENGELOLAAN DATA DESA</div><h1>Rumah & Keluarga</h1><p>Kelola data bangunan, lokasi survei, dan kartu keluarga sesuai kewenangan akun.</p></div>
        <a class="btn btn-dark" href="{{ route('admin.houses.create') }}">＋ Input rumah</a>
    </header>
    <section class="filter-card">
        <form method="GET" action="{{ route('admin.houses.index') }}" class="filter-grid">
            <label>Cari kode, nama kepala keluarga, RT, atau alamat
                <input type="search" name="q" value="{{ $keyword }}" placeholder="Contoh: BGN-..., nama KK, RT 01, alamat">
            </label>
            <label>Status verifikasi
                <select name="status">
                    <option value="">Semua status</option>
                    <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                    <option value="verified" @selected($selectedStatus === 'verified')>Verified</option>
                    <option value="rejected" @selected($selectedStatus === 'rejected')>Rejected</option>
                </select>
            </label>
            <button class="btn btn-dark" type="submit">Terapkan</button>
            <a class="btn btn-light" href="{{ route('admin.houses.index') }}">Reset</a>
        </form>
    </section>
    <section class="table-card">
        <div class="table-head"><div><h2>Daftar rumah</h2><p>{{ $houses->total() }} data sesuai pencarian, status, dan cakupan akun.</p></div><a class="text-link" href="{{ route('admin.map') }}">Lihat peta internal ↗</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Kode rumah</th><th>RT</th><th>Kepala keluarga</th><th>Alamat</th><th>Koordinat</th><th>Verifikasi</th><th>Publikasi</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($houses as $house)
                    <tr>
                        <td><span class="house-code">{{ $house->house_code }}</span><span class="sub">{{ $house->house_label ?: 'Tanpa label' }}</span></td>
                        <td>{{ $house->rt_code ? 'RT '.$house->rt_code : '—' }}</td>
                        <td>{{ $house->display_head_names ?: '—' }}<span class="sub">{{ $house->display_member_count }} anggota tercatat</span></td>
                        <td>{{ $house->address ?: '—' }}</td>
                        <td class="coord">
                            @php($coordinate = $house->coordinatePresentation())
                            @if($coordinate['status'] === 'valid')
                                {{ number_format($coordinate['latitude'],6,'.','') }}<br>{{ number_format($coordinate['longitude'],6,'.','') }}
                            @else <span class="sub">{{ $coordinate['label'] }}</span> @endif
                        </td>
                        <td><span class="status-pill status-{{ $house->verification_status }}">{{ ucfirst($house->verification_status) }}</span></td>
                        <td><span class="status-pill status-{{ $house->publication_status }}">{{ ucfirst($house->publication_status) }}</span></td>
                        <td><div class="actions"><a href="{{ route('admin.houses.show',$house) }}">Detail</a><a href="{{ route('admin.houses.edit',$house) }}">Edit</a><a class="danger" href="{{ route('admin.houses.archive.confirm',$house) }}">Arsipkan</a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">Tidak ada rumah yang cocok dengan filter ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-foot">{{ $houses->links() }}</div>
    </section>
</div>
@endsection
