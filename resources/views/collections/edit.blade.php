{{-- Edit collection form — pre-filled with current values, uses PUT method spoofing --}}
@extends('layouts.app')
@section('title', 'Edit Collection')
@section('content')
<div class="container">
    <div class="card" style="max-width:500px;">
        <h1>Edit Collection</h1>

        {{-- PUT request via method spoofing (HTML forms only support GET/POST) --}}
        <form action="{{ route('collections.update', $collection->id) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Name field — old() takes priority after validation failure, otherwise falls back to DB value --}}
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name"
                       value="{{ old('name', $collection->name) }}"
                       class="form-input" required maxlength="100">
                @error('name')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Description field — optional, pre-filled from DB --}}
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"
                          class="form-input" maxlength="500">{{ old('description', $collection->description) }}</textarea>
            </div>

            <div style="display:flex; gap:0.5rem; margin-top:1rem;">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('collections.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
