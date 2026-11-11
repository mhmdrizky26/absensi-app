@php
    $appName = config('app.name');
    $description = 'Absensi siswa '.config('app.school_name').': scan kartu QR di gerbang, pantauan kehadiran harian, dan rekap untuk wali kelas.';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ $appName }}</title>
        <meta name="description" content="{{ $description }}">
        {{-- Aplikasi berisi data siswa: jangan masuk hasil pencarian Google. --}}
        <meta name="robots" content="noindex, nofollow">
        <meta name="application-name" content="{{ $appName }}">
        <meta name="theme-color" content="#2360e8">

        {{-- Pratinjau saat link dibagikan, misalnya di WhatsApp. --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.school_name') }}">
        <meta property="og:title" content="{{ $appName }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:image" content="{{ url('/icon-512.png') }}">
        <meta property="og:locale" content="id_ID">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="{{ route('manifest') }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
