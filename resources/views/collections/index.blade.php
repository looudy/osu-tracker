{{-- Collections index: list all of the authenticated user's collections with edit/delete actions --}}
@extends('layouts.app')
@section('title', 'My Collections')
@section('content')
<div class="container">
    {{-- Flash success message from store/update/destroy --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Header row with page title and "New Collection" button --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h1>My Collections</h1>
        <a href="{{ route('collections.create') }}" class="btn btn-primary">New Collection</a>
    </div>

    <div class="card">
        {{-- Iterate each collection; forelse handles empty state gracefully --}}
        @forelse($collections as $collection)
        <div class="collection-item" style="display:flex; justify-content:space-between; align-items:flex-start; padding:0.75rem 0; border-bottom:1px solid #eee;">
            <div>
                {{-- Link to the collection detail page --}}
                <a href="{{ route('collections.show', $collection->id) }}">
                    <strong>{{ $collection->name }}</strong>
                </a>
                {{-- beatmaps_count comes from withCount('beatmaps') in the controller --}}
                <span class="text-muted">&nbsp;({{ $collection->beatmaps_count }} beatmaps)</span>
                @if($collection->description)
                    <p class="text-muted" style="margin:0.25rem 0 0;">{{ $collection->description }}</p>
                @endif
            </div>
            <div class="actions" style="display:flex; gap:0.5rem; flex-shrink:0;">
                <a href="{{ route('collections.edit', $collection->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                {{-- DELETE uses a POST form with method spoofing; JS confirm prevents accidents --}}
                <form action="{{ route('collections.destroy', $collection->id) }}" method="POST" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Delete this collection?')">Delete</button>
                </form>
            </div>
        </div>
        @empty
        <p>No collections yet. <a href="{{ route('collections.create') }}">Create one!</a></p>
        @endforelse
    </div>
</div>
@endsection
