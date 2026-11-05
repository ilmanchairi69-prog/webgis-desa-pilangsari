<!doctype html>
<html lang="id">
<head>
    @include('favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin — WebGIS Pilangsari</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body{margin:0;min-height:100vh;background:#f3f6f1;display:grid;place-items:center;font-family:Inter,system-ui,sans-serif;color:#17251b}
        .login-card{width:min(420px,calc(100% - 36px));background:#fff;border:1px solid #e1e8df;border-radius:22px;padding:32px;box-shadow:0 20px 60px #17351c12;box-sizing:border-box}
        .login-mark{width:48px;height:48px;border-radius:14px;background:#16803c;color:white;display:grid;place-items:center;font-weight:800;font-size:22px}
        h1{font-size:26px;margin:22px 0 8px}p{color:#647267;line-height:1.5;margin:0 0 24px}
        label{display:block;font-size:13px;font-weight:700;margin:16px 0 7px}
        input[type=email],input[type=password]{width:100%;box-sizing:border-box;padding:12px 13px;border:1px solid #d7dfd5;border-radius:10px;font:inherit}
        .remember{display:flex;align-items:center;gap:8px;font-weight:500}
        button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:10px;background:#16803c;color:white;font-weight:700;font-size:15px;cursor:pointer}
        .error{background:#fff1f0;color:#a12a22;padding:11px;border-radius:9px;font-size:13px;margin:14px 0}
        .back{display:block;text-align:center;margin-top:20px;color:#16803c;text-decoration:none;font-size:14px}
    </style>
</head>
<body>
    <main class="login-card">
        <div class="login-mark">P</div>
        <h1>Login Admin</h1>
        <p>Masuk untuk mengelola layer dan objek pada WebGIS Desa Pilangsari.</p>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email">Email admin</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            <label for="password">Kata sandi</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
            <label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
            <button type="submit">Masuk ke panel admin</button>
        </form>
        <a class="back" href="{{ route('home') }}">← Kembali ke peta</a>
    </main>
</body>
</html>
