@extends('layouts.admin')
@section('title','Verifikasi & Publikasi')
@section('top-title','Verifikasi & Publikasi')
@section('content')
<div class="page-heading"><div><div class="section-overline">PENGENDALIAN KUALITAS</div><h1>Verifikasi & Publikasi</h1><p>Verifikasi data dan publikasi ke peta publik adalah dua proses terpisah.</p></div></div>
<div class="privacy-callout"><span>✓</span><p><strong>Alur pemeriksaan</strong><br>Periksa RT, koordinat peta, alamat, dan kelengkapan kartu keluarga. Setelah diverifikasi, Super Admin masih harus memutuskan apakah titik generik boleh dipublikasikan. Data pribadi tidak pernah masuk peta publik.</p></div>
<div class="panel"><div class="panel-heading"><div><h2>Pemeriksaan & publikasi</h2><p>{{ $houses->total() }} rumah dalam pemeriksaan atau pengelolaan publikasi.</p></div></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Kode / RT</th><th>Alamat / titik</th><th>Kepala keluarga (internal)</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
@forelse($houses as $house)<tr><td><strong>{{ $house->house_code }}</strong><small class="table-sub">RT {{ $house->rt_code }}</small></td><td>{{ $house->address }}<small class="table-sub">{{ $house->coordinatePresentation()['label'] }}</small></td><td>{{ $house->familyCards->map(fn($c) => $c->head_name ?: $c->head_of_family_name)->filter()->implode(', ') ?: '—' }}<small class="table-sub">{{ $house->familyCards->sum(fn($c)=>$c->members->count()) }} anggota</small>@if($house->rejection_note)<small class="table-sub" style="color:#a63b32"><strong>Catatan penolakan:</strong> {{ $house->rejection_note }}</small>@endif</td><td><span class="status status-{{ $house->verification_status }}">{{ ucfirst($house->verification_status) }}</span><br><span class="status status-{{ $house->publication_status }}">{{ ucfirst($house->publication_status) }}</span><small class="table-sub">Titik publik: {{ $house->public_visible ? 'diizinkan' : 'tidak diizinkan' }}</small></td><td class="review-actions">
<a class="table-link" href="{{ route('admin.houses.edit',$house) }}">Tinjau/edit</a>
@if($house->verification_status!=='verified')<form method="post" action="{{ route('admin.houses.verify',$house) }}" onsubmit="return confirm('Tandai data rumah sebagai terverifikasi?')">@csrf<button type="submit" class="btn btn-small btn-green">Verifikasi</button></form>@endif
@if($house->verification_status==='verified' && $house->publication_status!=='published')
@if($house->public_visible)
<details class="publish-details"><summary class="btn btn-small btn-dark">Publikasikan</summary>
<form method="post" action="{{ route('admin.houses.publish',$house) }}" style="margin-top:10px;padding:12px;border:1px solid #dce8dc;border-radius:10px;background:#f7fbf7">@csrf
<label style="display:flex;gap:8px;align-items:flex-start;font-size:12px;line-height:1.5"><input type="checkbox" name="publication_declaration" value="1" required style="margin-top:2px"><span>Saya sebagai Super Admin bertanggung jawab bahwa data rumah, koordinat, dan izin penayangan titik generik telah diperiksa. Saya memastikan nama KK, nomor KK, anggota keluarga, dan alamat rinci tidak dipublikasikan.</span></label>
<button type="submit" class="btn btn-small btn-dark" style="margin-top:10px">Setujui & publikasikan</button></form></details>
@else<small class="table-sub" style="color:#a63b32">Belum ada izin publikasi titik. Minta Admin RT memperbarui persetujuan pada data rumah.</small>@endif
@endif
@if($house->publication_status==='published')<form method="post" action="{{ route('admin.houses.unpublish',$house) }}">@csrf<button type="submit" class="btn btn-small btn-light">Tarik publikasi</button></form>@endif
<details class="reject-details"><summary>Tolak data</summary><form method="post" action="{{ route('admin.houses.reject',$house) }}">@csrf<label>Alasan penolakan<textarea name="rejection_note" required minlength="5" rows="2" placeholder="Jelaskan data yang perlu diperbaiki"></textarea></label><button class="btn btn-small btn-danger" type="submit">Simpan penolakan</button></form></details>
</td></tr>@empty<tr><td colspan="5" class="empty-cell">Belum ada rumah yang menunggu pemeriksaan. Input rumah baru akan masuk ke antrian ini.</td></tr>@endforelse
</tbody></table></div><div class="pagination-wrap">{{ $houses->links() }}</div></div>
@endsection
