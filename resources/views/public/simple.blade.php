@extends('layouts.app')
@section('title', $title)
@section('content')
<section class="page-heading"><span class="eyebrow">PORTAL DESA PILANGSARI</span><h1>{{ $title }}</h1><p>{{ $description }}</p></section>
<section class="section simple-content"><div class="empty-state"><span class="empty-icon">✳</span><h2>Halaman dalam pengembangan</h2><p>Konten akan ditambahkan setelah data dan kebutuhan Pemerintah Desa dikonfirmasi.</p><a class="button button-primary" href="{{ route('home') }}">Kembali ke beranda</a></div></section>
@endsection
