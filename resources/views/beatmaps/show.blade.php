{{-- Beatmap detail: metadata, user's scores, tags, comments --}}
@extends('layouts.app')

@section('title', $beatmap->title)

@section('content')
<div class="container">

    {{-- Flash messages from controller redirects --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    {{-- Beatmap metadata card --}}
    <div class="card">
        <h1>{{ $beatmap->artist }} — {{ $beatmap->title }}</h1>
        <h3 class="text-muted">{{ $beatmap->difficulty_name }}</h3>

        {{-- Grid of key difficulty/map stats; only render cells when the value exists --}}
        <div class="metadata-grid">
            <div><strong>Stars:</strong> {{ number_format($beatmap->stars, 2) }}★</div>
            {{-- gmdate('i:s') converts integer seconds to MM:SS format --}}
            <div><strong>Length:</strong> {{ gmdate('i:s', $beatmap->length_seconds) }}</div>
            @if($beatmap->bpm)<div><strong>BPM:</strong> {{ $beatmap->bpm }}</div>@endif
            @if($beatmap->ar)<div><strong>AR:</strong> {{ $beatmap->ar }}</div>@endif
            @if($beatmap->od)<div><strong>OD:</strong> {{ $beatmap->od }}</div>@endif
            @if($beatmap->circle_count)<div><strong>Circles:</strong> {{ $beatmap->circle_count }}</div>@endif
        </div>

        {{-- External link to the official osu! page for this beatmap --}}
        <a href="https://osu.ppy.sh/b/{{ $beatmap->beatmap_id }}" target="_blank" class="btn btn-secondary">
            View on osu!
        </a>
    </div>

    {{-- Tags section: display existing tags, allow adding new ones, allow admin removal --}}
    <div class="card">
        <h2>Tags</h2>
        <div class="tags-list">
            @forelse($beatmap->tags as $tag)
                <span class="tag">#{{ $tag->name }}</span>
                {{-- Only admins can remove individual tags from a beatmap --}}
                @auth
                @if(auth()->user()->isAdmin())
                <form action="{{ route('tags.detach', [$beatmap->id, $tag->id]) }}" method="POST" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-tag-remove" title="Remove tag">×</button>
                </form>
                @endif
                @endauth
            @empty
                <p class="text-muted">No tags yet.</p>
            @endforelse
        </div>

        {{-- Any authenticated user can add tags using #tagname format --}}
        @auth
        <form action="{{ route('tags.attach', $beatmap->id) }}" method="POST" class="inline-form">
            @csrf
            <input type="text" name="tags_input" placeholder="#dt #jumps #technical" class="form-input">
            <button type="submit" class="btn btn-secondary">Add Tags</button>
        </form>
        @endauth
    </div>

    {{-- User's own scores on this beatmap — hidden from guests --}}
    @auth
    <div class="card">
        <h2>Your Scores</h2>
        @if($userScores->isEmpty())
            <p>You have no scores on this beatmap yet. Play it and sync your scores!</p>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Score</th>
                    <th>Accuracy</th>
                    <th>Rank</th>
                    <th>Misses</th>
                </tr>
            </thead>
            <tbody>
                @foreach($userScores as $score)
                <tr>
                    <td>{{ $score->played_at->format('Y-m-d H:i') }}</td>
                    {{-- number_format adds thousands separator for readability --}}
                    <td>{{ number_format($score->score) }}</td>
                    <td>{{ number_format($score->accuracy, 2) }}%</td>
                    {{-- CSS class on rank lets us color-code by letter grade (S=gold, A=green, etc.) --}}
                    <td class="rank rank-{{ strtolower($score->rank) }}">{{ $score->rank }}</td>
                    <td>{{ $score->miss_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endauth

    {{-- Comments section: list + add form --}}
    <div class="card">
        <h2>Comments</h2>

        @forelse($beatmap->comments as $comment)
        <div class="comment">
            <div class="comment-header">
                <strong>{{ $comment->user->name }}</strong>
                {{-- diffForHumans gives friendly relative time like "3 hours ago" --}}
                <span class="text-muted">{{ $comment->created_at->diffForHumans() }}</span>

                {{-- Edit/Delete shown to comment owner or any admin --}}
                @auth
                @if(auth()->id() === $comment->user_id || auth()->user()->isAdmin())
                <div class="comment-actions">
                    <a href="{{ route('comments.edit', $comment->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                    <form action="{{ route('comments.destroy', $comment->id) }}" method="POST" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete this comment?')">Delete</button>
                    </form>
                </div>
                @endif
                @endauth
            </div>
            {{-- Content is escaped by Blade automatically to prevent XSS --}}
            <p>{{ $comment->content }}</p>
        </div>
        @empty
            <p class="text-muted">No comments yet. Be the first!</p>
        @endforelse

        {{-- Add comment form — guests see a login prompt instead --}}
        @auth
        <form action="{{ route('comments.store', $beatmap->id) }}" method="POST" class="comment-form">
            @csrf
            <textarea name="content" class="form-input" placeholder="Write a comment..." rows="3" required></textarea>
            {{-- Show validation error if content fails backend validation --}}
            @error('content')<div class="form-error">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-primary">Post Comment</button>
        </form>
        @else
        <p><a href="{{ route('login') }}">Login</a> to post a comment.</p>
        @endauth
    </div>

</div>
@endsection
