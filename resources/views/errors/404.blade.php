{{--
    404 Not Found error page
    Shown by Laravel automatically when a model is not found via Route Model
    Binding, or when no route matches the request URI.
    Extends the master layout so the navbar / footer are still visible.
--}}
@extends('layouts.app')

@section('title', '404 — Not Found')

@section('content')
<div class="container" style="text-align:center; padding:4rem 1rem;">
    {{-- Large status code acts as a visual anchor --}}
    <h1 style="font-size:5rem; color:#e94c6c; margin-bottom:1rem;">404</h1>
    <h2>Page Not Found</h2>
    <p style="color:#666; margin:1rem 0 2rem;">
        The page you&rsquo;re looking for doesn&rsquo;t exist or has been removed.
    </p>
    {{-- Send the user back to a safe starting point --}}
    <a href="{{ url('/') }}" class="btn btn-primary">Go Home</a>
</div>
@endsection
