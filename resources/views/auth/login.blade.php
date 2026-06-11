@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="container">
    <div class="card" style="max-width:420px; margin:3rem auto;">
        <h1 style="margin-bottom:1.5rem;">Login</h1>

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <a href="{{ route('osu.redirect') }}" class="btn btn-primary" style="display:block; text-align:center; font-size:1.1rem; padding:0.8rem;">
            Login with osu!
        </a>
    </div>
</div>
@endsection
