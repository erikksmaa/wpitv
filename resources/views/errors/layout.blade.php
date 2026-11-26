<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') — @yield('title') | WPiTV</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:linear-gradient(155deg,#07111F 0%,#0D2B54 60%,#155DAC 100%);color:#F0F6FF;font-family:'Inter',system-ui,-apple-system,sans-serif}
        main{max-width:520px;width:100%;background:rgba(11,23,48,.88);border:1px solid rgba(148,163,184,.2);border-radius:18px;padding:2.25rem;text-align:center;box-shadow:0 24px 50px rgba(0,0,0,.35)}
        .brand{display:inline-flex;align-items:center;gap:.65rem;margin-bottom:1.5rem;text-decoration:none;color:#fff;font-weight:700;letter-spacing:-.01em}
        .brand img{height:34px;width:auto}
        .code{display:inline-block;padding:.25rem .75rem;border-radius:999px;background:rgba(39,136,216,.18);color:#7FC5FF;font-weight:700;font-size:.82rem;letter-spacing:.08em;margin-bottom:.75rem}
        h1{margin:0 0 .65rem;font-size:clamp(1.45rem,3vw,1.9rem);line-height:1.25}
        p{margin:0 0 1.75rem;color:#B8CDEF;font-size:.96rem;line-height:1.6}
        .actions{display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center}
        a.btn{display:inline-flex;align-items:center;justify-content:center;padding:.72rem 1.25rem;border-radius:10px;font-weight:600;font-size:.9rem;text-decoration:none;background:#155DAC;color:#fff;transition:background .18s}
        a.btn:hover{background:#1D70CE}
        a.btn-ghost{background:rgba(255,255,255,.08);color:#E2EEFF;border:1px solid rgba(255,255,255,.16)}
        a.btn-ghost:hover{background:rgba(255,255,255,.14)}
    </style>
</head>
<body>
<main>
    <a href="{{ url('/') }}" class="brand">
        <img src="{{ asset('logo-wpi.png') }}" alt="Logo WPiTV">
        <span>WPiTV</span>
    </a>
    <div><span class="code">KESALAHAN @yield('code')</span></div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn">Kembali ke Beranda</a>
        @yield('extra_action')
    </div>
</main>
</body>
</html>
