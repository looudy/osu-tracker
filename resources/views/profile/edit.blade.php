@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="container" style="max-width:700px;">
    <h1 style="margin-bottom:1.5rem;">Profile Settings</h1>

    @if(session('status') === 'profile-updated')
        <div class="alert alert-success">Profile updated.</div>
    @endif

    <div class="card">
        @include('profile.partials.update-profile-information-form')
    </div>

    <div class="card">
        @include('profile.partials.delete-user-form')
    </div>
</div>
@endsection
