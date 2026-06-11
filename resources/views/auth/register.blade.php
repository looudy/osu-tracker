{{--
    Registration page
    Allows new users to create a local account with email and password.
    osu! players should use the "Login with osu!" button on the login page
    instead — OAuth automatically creates their account on first login.

    Uses the master layout (layouts.app) instead of Breeze's x-guest-layout
    so the navbar and global stylesheet are applied consistently.
--}}
@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="container">
    <div class="card" style="max-width:420px; margin:3rem auto;">
        <h1 style="margin-bottom:1.5rem;">Create Account</h1>

        {{-- Aggregated validation errors shown at the top of the form --}}
        @if($errors->any())
            <div class="alert alert-error">
                <ul style="margin:0; padding-left:1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('register') }}" method="POST">
            @csrf

            {{-- Display name shown in the UI --}}
            <div class="form-group">
                <label for="name">Name</label>
                <input id="name" type="text" name="name"
                       value="{{ old('name') }}"
                       class="form-input" required autofocus autocomplete="name">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            {{-- Email used as the unique login identifier --}}
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email"
                       value="{{ old('email') }}"
                       class="form-input" required autocomplete="username">
                @error('email')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            {{-- Password must meet Laravel's default validation rules (min 8 chars) --}}
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password"
                       class="form-input" required autocomplete="new-password">
                @error('password')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            {{-- Confirmation must match 'password' field — validated by Laravel automatically --}}
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password"
                       name="password_confirmation"
                       class="form-input" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.5rem;">
                Create Account
            </button>
        </form>

        <p style="text-align:center; margin-top:1.5rem; color:#666;">
            Already have an account? <a href="{{ route('login') }}">Login</a>
        </p>
    </div>
</div>
@endsection
