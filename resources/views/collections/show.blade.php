{{-- Collection detail: shows all beatmaps in this collection with remove actions --}}
@extends('layouts.app')
@section('title', $collection->name)
@section('content')
<div class="container">
    {{-- Flash success after add/remove actions --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        {{-- Collection header with name and optional description --}}
        <div style="margin-bottom:1rem;">
            <h1>{{ $collection->name }}</h1>
            @if($collection->description)
                <p class="text-muted">{{ $collection->description }}</p>
            @endif
            <a href="{{ route('collections.index') }}" class="btn btn-secondary btn-sm">← Back to Collections</a>
        </div>

        {{-- Beatmaps table; tags are eager-loaded via with('beatmaps.tags') in controller --}}
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Stars</th>
                    <th>Tags</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($collection->beatmaps as $beatmap)
                <tr>
                    {{-- Link to the beatmap detail page --}}
                    <td><a href="{{ route('beatmaps.show', $beatmap->id) }}">{{ $beatmap->title }}</a></td>
                    <td>{{ $beatmap->artist }}</td>
                    {{-- Stars formatted to 2 decimal places with star symbol --}}
                    <td>{{ number_format($beatmap->stars, 2) }}&#9733;</td>
                    <td>
                        {{-- Display all tags associated with this beatmap --}}
                        @foreach($beatmap->tags as $tag)
                            <span class="tag">#{{ $tag->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        {{-- DELETE via method spoofing; removes only the pivot record, not the beatmap --}}
                        <form action="{{ route('collections.removeBeatmap', [$collection->id, $beatmap->id]) }}"
                              method="POST" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Remove from collection?')">Remove</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">No beatmaps in this collection.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
