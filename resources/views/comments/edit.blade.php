{{-- Edit comment form — pre-filled with existing content.
     Only reachable by the comment owner or an admin (enforced in CommentController@edit). --}}
@extends('layouts.app')

@section('title', 'Edit Comment')

@section('content')
<div class="container">
    <div class="card" style="max-width: 600px;">
        <h1>Edit Comment</h1>

        {{-- POST tunnel via PUT method spoofing because HTML forms only support GET/POST --}}
        <form action="{{ route('comments.update', $comment->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                {{-- Textarea pre-filled with current content; old() re-populates on validation failure --}}
                <label for="content">Comment</label>
                <textarea
                    id="content"
                    name="content"
                    class="form-input"
                    rows="5"
                    required
                >{{ old('content', $comment->content) }}</textarea>

                {{-- Show validation error if content fails the 1–1000 char rule --}}
                @error('content')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Update Comment</button>
                {{-- Cancel returns the user to the beatmap page without saving --}}
                <a href="{{ route('beatmaps.show', $comment->beatmap_id) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
