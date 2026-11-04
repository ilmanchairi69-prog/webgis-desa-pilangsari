<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(): View { return view('public.home'); }
    public function profile(): View { return view('public.simple', ['title' => 'Profil Desa', 'description' => 'Informasi profil Desa Pilangsari akan dikelola melalui sistem.']); }
    public function news(): View { return view('public.simple', ['title' => 'Berita Desa', 'description' => 'Berita dan pengumuman resmi desa.']); }
    public function umkm(): View { return view('public.simple', ['title' => 'UMKM Desa', 'description' => 'Direktori UMKM dan produk lokal desa.']); }
    public function agenda(): View { return view('public.simple', ['title' => 'Agenda Kegiatan', 'description' => 'Agenda kegiatan masyarakat dan pemerintah desa.']); }
    public function gallery(): View { return view('public.simple', ['title' => 'Galeri Desa', 'description' => 'Dokumentasi foto dan kegiatan desa.']); }
    public function complaint(): View { return view('public.simple', ['title' => 'Pengaduan & Saran', 'description' => 'Formulir pengaduan akan diaktifkan pada pengembangan berikutnya.']); }
}
