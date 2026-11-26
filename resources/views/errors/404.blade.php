@extends('errors.layout')
@section('code', '404')
@section('title', 'Halaman Tidak Ditemukan')
@section('message', 'Tautan yang dibuka mungkin sudah berubah atau halaman tersebut tidak lagi tersedia.')
@section('extra_action')
    <a href="{{ url('/program') }}" class="btn btn-ghost">Jelajahi Program</a>
@endsection
