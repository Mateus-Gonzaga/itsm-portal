@extends('layouts.app')
@section('title', $title)
@section('content')
<x-page-header :icon="$icon" :title="$title" />
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi {{ $icon }} text-muted" style="font-size: 3rem"></i>
        <h2 class="h4 mt-3 mb-1">{{ $title }}</h2>
        <p class="text-muted mb-3">{{ $desc }}</p>
        <span class="badge rounded-pill" style="background:var(--fl-green-light);color:var(--fl-green-xdark)">Módulo em construção</span>
    </div>
</div>
@endsection
