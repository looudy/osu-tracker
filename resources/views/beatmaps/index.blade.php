{{-- Beatmap list: shows all known beatmaps with search and filter options --}}
@extends('layouts.app')

@section('title', 'Beatmaps')

@section('content')
<div class="container">
    <h1>Beatmaps</h1>

    {{-- Search and filter form — GET so filters are bookmarkable/shareable --}}
    <form method="GET" action="{{ route('beatmaps.index') }}" class="search-form card">
        <div class="form-row">
            {{-- Free-text search against title and artist --}}
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search by title or artist..." class="form-input">

            {{-- Tag filter dropdown populated from all global tags --}}
            <select name="tag_id" class="form-input">
                <option value="">All Tags</option>
                @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                        #{{ $tag->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="btn btn-primary">Search</button>
            {{-- Clear link resets all filters by navigating to bare index --}}
            <a href="{{ route('beatmaps.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>

    {{-- Beatmap results table --}}
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Difficulty</th>
                    <th>Stars</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($beatmaps as $beatmap)
                <tr>
                    <td>{{ $beatmap->title }}</td>
                    <td>{{ $beatmap->artist }}</td>
                    <td>{{ $beatmap->difficulty_name }}</td>
                    {{-- Format stars to 2 decimal places with the ★ symbol --}}
                    <td>{{ number_format($beatmap->stars, 2) }}★</td>
                    <td>
                        <a href="{{ route('beatmaps.show', $beatmap->id) }}" class="btn btn-sm btn-secondary">
                            View Details
                        </a>
                    </td>
                </tr>
                @empty
                {{-- No results: display a friendly empty-state message --}}
                <tr>
                    <td colspan="5" class="text-center">No beatmaps found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Laravel pagination links — withQueryString() preserves filter params --}}
        <div class="pagination-wrapper">
            {{ $beatmaps->links() }}
        </div>
    </div>
</div>
@endsection
