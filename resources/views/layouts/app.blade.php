<!DOCTYPE html>
{{-- Master layout for all osu! Tracker pages.
     Uses raw CSS (public/css/style.css) — no Tailwind, no Bootstrap, no framework.
     All pages extend this layout via @extends('layouts.app') and fill @section('content'). --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'osu! Tracker') — osu! Personal Tracker</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>

{{-- Navigation bar — shown on all pages --}}
<nav class="navbar">
    <a href="{{ route('beatmaps.index') }}" class="navbar-brand">osu! Tracker</a>

    <ul class="navbar-links">
        <li><a href="{{ route('beatmaps.index') }}">Beatmaps</a></li>

        @auth
            <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('collections.index') }}">Collections</a></li>

            {{-- Admin link visible only to admin users --}}
            @if(auth()->user()->isAdmin())
                <li><a href="{{ route('admin.users') }}" class="nav-admin">Admin</a></li>
            @endif

            <li>
                {{-- Logout form — must use POST for CSRF protection --}}
                <form action="{{ route('logout') }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="btn-nav-logout">
                        Logout ({{ auth()->user()->osu_username ?? auth()->user()->name }})
                    </button>
                </form>
            </li>
        @else
            <li><a href="{{ route('login') }}">Login</a></li>
            <li><a href="{{ route('register') }}">Register</a></li>
        @endauth
    </ul>
</nav>

{{-- Main content area — each page fills this via @section('content') --}}
<main class="main-content">
    @yield('content')
</main>

<footer class="footer">
    <p>osu! Personal Tracker — built with Laravel 13</p>
</footer>

<script>
    // Sync button: show loading state to prevent double-clicks
    document.addEventListener('DOMContentLoaded', function () {
        var syncBtn = document.getElementById('sync-btn');
        if (syncBtn) {
            syncBtn.closest('form').addEventListener('submit', function () {
                syncBtn.disabled = true;
                syncBtn.textContent = 'Syncing...';
            });
        }
    });
</script>
</body>
</html>
