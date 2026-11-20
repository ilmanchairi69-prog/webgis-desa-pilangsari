@extends('layouts.public')

@push('head')
<link rel="stylesheet" href="{{ asset('css/content-pages.css') }}?v={{ filemtime(public_path('css/content-pages.css')) }}">
@endpush

@section('content')
@php
    $routeLabels = [
        'home' => 'Beranda',
        'profil' => 'Profil Desa',
        'pemerintahan' => 'Pemerintahan Desa',
        'agenda' => 'Agenda Desa',
        'map' => 'Peta Desa',
    ];
    $catalog = collect(config('public-pages.pages'));
@endphp
<article class="content-page">
    <header class="portal-map-heading content-page-hero">
        <div class="portal-container">
            <nav class="portal-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Beranda</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $page['heading'] }}</span>
            </nav>
            <span class="portal-eyebrow">{{ $page['eyebrow'] }}</span>
            <h1>{{ $page['heading'] }}</h1>
            <p>{{ $page['intro'] }}</p>
        </div>
    </header>

    <div class="portal-container content-page-layout">
        <div class="content-page-main">
            @unless($page['index'])
                <aside class="content-status" aria-label="Status informasi">
                    <strong>Informasi sedang diverifikasi</strong>
                    <p>Halaman ini tersedia untuk menyiapkan struktur layanan, tetapi belum disertakan dalam sitemap dan belum ditujukan untuk pengindeksan.</p>
                </aside>
            @endunless

            @foreach($page['sections'] as $section)
                <section class="content-section" aria-labelledby="section-{{ $loop->iteration }}">
                    <h2 id="section-{{ $loop->iteration }}">{{ $section['title'] }}</h2>
                    @foreach($section['paragraphs'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </section>
            @endforeach

            @isset($page['source'])
                <p class="content-source">Sumber fakta: <a href="{{ $page['source']['url'] }}" rel="noopener noreferrer">{{ $page['source']['label'] }}</a>.</p>
            @endisset
        </div>

        <aside class="content-page-aside" aria-labelledby="related-title">
            <h2 id="related-title">Informasi terkait</h2>
            <nav aria-label="Informasi terkait">
                @foreach($page['related'] as $relatedRoute)
                    @php
                        $relatedPage = $catalog->firstWhere('route', $relatedRoute);
                        $relatedLabel = $routeLabels[$relatedRoute] ?? ($relatedPage['heading'] ?? 'Informasi Desa');
                    @endphp
                    <a href="{{ route($relatedRoute) }}">{{ $relatedLabel }} <span aria-hidden="true">&rarr;</span></a>
                @endforeach
            </nav>
            <a class="portal-button portal-button-dark" href="{{ route('map') }}">Jelajahi WebGIS</a>
        </aside>
    </div>
</article>
@endsection
