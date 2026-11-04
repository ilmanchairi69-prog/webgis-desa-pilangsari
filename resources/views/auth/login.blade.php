@extends('layouts.public')
@section('title', 'Masuk Admin')
@section('content')
<section class="login-stage"><div class="login-decoration"><div class="section-overline">AREA PENGELOLA</div><h1>Kelola data dengan<br><em>pemeriksaan yang jelas.</em></h1><p>Akses pengelola hanya untuk akun yang dibuat oleh Super Admin. Setiap perubahan penting dicatat pada log audit.</p><a href="{{ route('home') }}" class="text-link">← Kembali ke portal publik</a></div><div class="login-panel"><div class="login-badge">P</div><div class="section-overline">WEBGIS PILANGSARI</div><h2>Masuk ke sistem</h2><p class="login-lead">Gunakan akun pengelola yang sudah terdaftar.</p>
@if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('login.store') }}" class="form-stack">@csrf
    <label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
    <label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
    <label class="checkbox-line"><input type="checkbox" name="remember" value="1"> Ingat sesi pada perangkat ini</label>
    <button class="btn btn-dark full-width" type="submit">Masuk ke Dashboard <span>→</span></button>
</form><small class="privacy-note">Jangan bagikan kata sandi. Gunakan akun pribadi masing-masing.</small></div></section>
@endsection
