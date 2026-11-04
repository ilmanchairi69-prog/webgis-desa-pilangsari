@extends('layouts.admin')
@section('title', 'Informasi Publikasi')
@section('top-title', 'Informasi Publikasi')
@section('content')
<section>
    <div class="publication-intro"><div><div class="section-overline">PUBLIKASI PER OBJEK</div><h1>Informasi Publikasi</h1>
    <p>Visibilitas peta ditentukan oleh verifikasi, izin tampil, status publikasi, arsip, dan koordinat setiap rumah atau fasilitas. Status kategori lama tidak lagi menyembunyikan objek yang memenuhi syarat.</p></div><a class="btn btn-light" href="{{ route('map') }}" target="_blank" rel="noopener">Lihat peta publik <span aria-hidden="true">&#8599;</span></a></div>
    <div class="publication-grid">
    @foreach(\App\Models\PublicationCategory::Labels as $key => $label)
        <article class="publication-category publication-category-readonly" data-eligible-count="{{ $readyCounts[$key] }}">
            <div class="publication-card-heading">
                <span class="publication-card-icon {{ $key === 'facilities' ? 'facility' : '' }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24">@if($key === 'houses')<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/>@else<path d="M4 21V7h16v14M2 21h20M8 7V3h8v4M8 11h2M14 11h2M8 15h2M14 15h2"/>@endif</svg>
                </span>
                <span class="publication-status">Publikasi individual</span>
            </div>
            <h2>{{ $label }}</h2>
            <p><strong>{{ $readyCounts[$key] }}</strong> objek memenuhi syarat individual dan koordinat saat ini.</p>
            <p class="publication-visibility-note">Kelola status setiap objek melalui halaman {{ $key === 'houses' ? 'Verifikasi & Publikasi' : 'Fasilitas Umum' }}. Kategori tetap dipakai sebagai identitas dan filter visual peta.</p>
        </article>
    @endforeach
    </div>
</section>
@endsection
