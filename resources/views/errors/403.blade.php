@extends('errors.layout')

@section('code', '403')
@section('title', 'Akses ditolak')
@section('message')
{{ $exception->getMessage() && $exception->getMessage() !== 'Forbidden' ? $exception->getMessage() : 'Akun Anda tidak punya akses ke halaman ini. Kalau menurut Anda ini keliru, hubungi admin sekolah.' }}
@endsection
