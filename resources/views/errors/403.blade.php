{{--
    403 Forbidden error page
    Shown by Laravel automatically when an abort(403) is called or a Policy
    returns false and the user is not authorised to perform the action.
    Extends the master layout so the navbar / footer are still visible.
--}}
@extends('layouts.app')

@section('title', '403 — Forbidden')

@section('content')
<div class="container" style="text-align:center; padding:4rem 1rem;">
    {{-- Large status code acts as a visual anchor --}}
    <h1 style="font-size:5rem; color:#e94c6c; margin-bottom:1rem;">403</h1>
    <h2>Access Forbidden</h2>
    <p style="color:#666; margin:1rem 0 2rem;">
        You don&rsquo;t have permission to view this page.
    </p>
    {{-- Send the user back to a safe starting point --}}
    <a href="{{ url('/') }}" class="btn btn-primary">Go Home</a>
</div>
@endsection
