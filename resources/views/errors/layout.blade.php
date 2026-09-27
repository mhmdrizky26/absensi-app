<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('code') · {{ config('app.name') }}</title>
        @if (file_exists(public_path('build/manifest.json')))
            @fonts
        @endif
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr)); background: #f3f2f2; color: #201e1d; font-family: "Archivo", system-ui, sans-serif; }
            .poster { background: #201e1d; color: #f3f2f2; padding: clamp(24px, 6vw, 48px); display: flex; flex-direction: column; justify-content: space-between; gap: 32px; }
            .brand { font-weight: 800; font-size: 18px; }
            .code { font-size: clamp(72px, 14vw, 160px); font-weight: 800; line-height: .9; letter-spacing: -.04em; color: #8cb0ff; }
            .panel { padding: clamp(24px, 6vw, 48px); display: flex; flex-direction: column; justify-content: center; gap: 16px; }
            .panel > * { width: 100%; max-width: 560px; margin-inline: auto; }
            h1 { margin: 0; font-size: 32px; line-height: 1.1; letter-spacing: -.015em; }
            p { margin: 0; color: color-mix(in srgb, #201e1d 70%, transparent); line-height: 1.55; }
            .actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 12px; border-top: 2px solid color-mix(in srgb, #201e1d 40%, transparent); }
            a { display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; font-weight: 800; text-decoration: none; color: #201e1d; border: 1px solid color-mix(in srgb, #201e1d 40%, transparent); }
            a.primary { background: #2360e8; border-color: #2360e8; color: #f3f2f2; }
        </style>
    </head>
    <body>
        <div class="poster">
            <div class="brand">{{ config('app.name') }}</div>
            <div class="code">@yield('code')</div>
        </div>
        <div class="panel">
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div class="actions">
                <a class="primary" href="{{ url('/') }}">Ke halaman utama</a>
                <a href="javascript:history.back()">Kembali</a>
            </div>
        </div>
    </body>
</html>
