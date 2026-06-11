{{-- Create new collection form — name is required, description is optional --}}
@extends('layouts.app')
@section('title', 'New Collection')
@section('content')
<div class="container">
    <div class="card" style="max-width:500px;">
        <h1>New Collection</h1>

        {{-- POST to collections.store; CSRF token is mandatory for Laravel forms --}}
        <form action="{{ route('collections.store') }}" method="POST">
            @csrf

            {{-- Collection name — required, max 100 chars --}}
            <div class="form-group">
                <label for="name">Name</label>
                {{-- old() repopulates value after validation failure --}}
                <input type="text" id="name" name="name"
                       value="{{ old('name') }}"
                       class="form-input" required maxlength="100">
                @error('name')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Optional description — max 500 chars --}}
            <div class="form-group">
                <label for="description">Description (optional)</label>
                <textarea id="description" name="description"
                          class="form-input" maxlength="500">{{ old('description') }}</textarea>
            </div>

            <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="{{ route('collections.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
