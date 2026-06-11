{{--
    Login page
    Provides two authentication paths:
      1. osu! OAuth — primary method for osu! players; no password required.
      2. Email / password — fallback for users who registered locally.

    Uses the master layout (layouts.app) instead of Breeze's x-guest-layout so
    the navbar and global stylesheet are applied consistently.
--}}
@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="container">
    <div class="card" style="max-width:420px; margin:3rem auto;">
        <h1 style="margin-bottom:1.5rem;">Login</h1>

        {{-- Flash error set by CheckBlocked middleware or OsuAuthController --}}
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        {{-- Validation errors from the login form (wrong credentials, etc.) --}}
        @if($errors->any())
            <div class="alert alert-error">
                <ul style="margin:0; padding-left:1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- osu! OAuth login — placed first because most users will use it --}}
        <a href="{{ route('osu.redirect') }}" class="btn-osu"
           style="display:block; text-align:center; margin-bottom:1rem;">
            Login with osu!
        </a>

        {{-- Visual divider between OAuth and the fallback form --}}
        <div style="text-align:center; color:#999; margin:0.75rem 0;">— or login with email —</div>

        {{-- Standard email / password form — for users who registered locally --}}
        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email"
                       value="{{ old('email') }}"
                       class="form-input" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password"
                       class="form-input" required autocomplete="current-password">
            </div>
            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem;">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember" style="margin:0; font-weight:normal;">Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">
                Login
            </button>
        </form>

        <p style="text-align:center; margin-top:1.5rem; color:#666;">
            Don&rsquo;t have an account? <a href="{{ route('register') }}">Register</a>
        </p>
    </div>
</div>
@endsection
