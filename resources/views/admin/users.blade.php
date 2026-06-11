{{--
    Admin panel: user management page.
    Lists all registered users with their status and provides block/unblock actions.
    Only accessible to users where is_admin = true (enforced by AdminMiddleware).
--}}
@extends('layouts.app')

@section('title', 'Admin — Users')

@section('content')
<div class="container">

    {{-- Flash messages: success (e.g. "User blocked") or error --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <h1>Admin Panel — Users</h1>

    <div class="card">
        {{-- User table: one row per registered account --}}
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>osu! Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                {{-- Highlight blocked rows with a CSS class for quick visual identification --}}
                <tr class="{{ $user->is_blocked ? 'row-blocked' : '' }}">
                    <td>{{ $user->id }}</td>
                    <td>
                        {{ $user->name }}
                        {{-- Admin badge so you can see who else has admin privileges --}}
                        @if($user->is_admin)
                            <span class="badge badge-success">admin</span>
                        @endif
                    </td>
                    <td>{{ $user->osu_username ?? '—' }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        {{-- Status badge: red for blocked, green for active --}}
                        @if($user->is_blocked)
                            <span class="badge badge-danger">Blocked</span>
                            @if($user->blocked_at)
                                <br><small class="text-muted">{{ $user->blocked_at->diffForHumans() }}</small>
                            @endif
                        @else
                            <span class="badge badge-success">Active</span>
                        @endif
                    </td>
                    <td>
                        {{-- Guard: don't show block/unblock for the currently logged-in admin --}}
                        @if($user->id !== auth()->id())
                            @if($user->is_blocked)
                                {{-- Unblock form — POST to admin.unblock route --}}
                                <form action="{{ route('admin.unblock', $user->id) }}" method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Unblock</button>
                                </form>
                            @else
                                {{-- Block form with JS confirmation to prevent accidental clicks --}}
                                <form action="{{ route('admin.block', $user->id) }}" method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Block {{ addslashes($user->name) }}? They will be logged out immediately.')">
                                        Block
                                    </button>
                                </form>
                            @endif
                        @else
                            {{-- This is the currently logged-in admin — no self-block allowed --}}
                            <span class="text-muted">You</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Laravel pagination links — navigates through pages of users --}}
        <div class="pagination-wrapper">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
