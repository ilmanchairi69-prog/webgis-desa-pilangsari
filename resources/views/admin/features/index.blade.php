@extends('layouts.admin')
@section('top-title', 'Objek Peta')
@section('title', 'Objek Peta')
@section('content')
<section class="page-heading"><span class="eyebrow">ADMINISTRASI WEBGIS</span><h1>Objek Peta</h1><p>Kelola titik dan objek informasi peta.</p><a class="btn btn-dark" href="{{ route('admin.features.create') }}">+ Tambah objek</a></section>
<section class="panel">
@if(session('success'))<div class="success-message">{{ session('success') }}</div>@endif
<div class="table-scroll"><table class="data-table"><thead><tr><th>Nama</th><th>Layer</th><th>Kategori</th><th>Verifikasi</th><th>Publikasi</th><th>Aksi</th></tr></thead><tbody>
@forelse($features as $feature)
<tr><td>{{ $feature->name }}</td><td>{{ $feature->layer?->name }}</td><td>{{ $feature->category ?? '—' }}</td><td>{{ $feature->verification_status }}</td><td>{{ $feature->publication_status }}</td><td><a href="{{ route('admin.features.edit', $feature) }}">Edit</a> <form method="POST" action="{{ route('admin.features.destroy', $feature) }}" class="inline-form">@csrf @method('DELETE')<button type="submit" onclick="return confirm('Arsipkan objek ini?')">Arsipkan</button></form></td></tr>
@empty<tr><td colspan="6">Belum ada data.</td></tr>@endforelse
</tbody></table></div>
{{ $features->links() }}
</section>
@endsection
