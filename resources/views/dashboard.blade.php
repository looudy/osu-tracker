{{-- Dashboard: main page for authenticated users showing stats and Almost FC list --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container">

    {{-- Flash messages from sync operations --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    {{-- Profile header with osu! username and sync button --}}
    <div class="card profile-card">
        <div class="profile-info">
            <h1>{{ $user->osu_username ?? $user->name }}</h1>
            @if($user->osu_id)
                <p class="text-muted">osu! ID: {{ $user->osu_id }}</p>
            @endif
            <p class="text-muted">
                Last synced:
                {{ $user->last_sync_at ? $user->last_sync_at->diffForHumans() : 'Never' }}
            </p>
        </div>
        @if($user->osu_id)
        <form action="{{ route('sync.scores') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" id="sync-btn">
                Sync Scores
            </button>
        </form>
        @endif
    </div>

    {{-- Aggregated stats: 4 cards in a grid --}}
    <div class="stats-grid">
        <div class="card stat-card">
            <div class="stat-value">{{ $totalUniqueMaps }}</div>
            <div class="stat-label">Unique Maps</div>
        </div>
        <div class="card stat-card">
            <div class="stat-value">{{ $totalPlays }}</div>
            <div class="stat-label">Total Plays</div>
        </div>
        <div class="card stat-card">
            <div class="stat-value">{{ number_format($bestAccuracy, 2) }}%</div>
            <div class="stat-label">Best Accuracy</div>
        </div>
        <div class="card stat-card">
            <div class="stat-value">{{ $passRate }}%</div>
            <div class="stat-label">Pass Rate</div>
        </div>
    </div>

    {{-- Almost FC table — the core feature of this app --}}
    {{-- Shows beatmaps where user had misses, sorted by fewest misses first --}}
    <div class="card">
        <h2>Almost FC — Closest to Full Combo</h2>
        <p class="text-muted">Beatmaps where you had misses. Sorted by fewest misses first — these are your easiest FCs to achieve.</p>

        @if(count($almostFcMaps) === 0)
            <p>No data yet. Sync your scores to see this list.</p>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Beatmap</th>
                    <th>Difficulty</th>
                    <th>Stars</th>
                    <th>Best Misses</th>
                    <th>Best Accuracy</th>
                </tr>
            </thead>
            <tbody>
                @foreach($almostFcMaps as $map)
                <tr>
                    <td>
                        {{-- Link to local beatmap detail page --}}
                        <a href="{{ route('beatmaps.show', $map->id) }}">
                            {{ $map->artist }} — {{ $map->title }}
                        </a>
                    </td>
                    <td>{{ $map->difficulty_name }}</td>
                    <td>{{ number_format($map->stars, 2) }}★</td>
                    <td class="miss-count">
                        {{ $map->best_miss_count }}
                    </td>
                    <td>{{ number_format($map->best_accuracy, 2) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Recent scores section — last 10 imported plays --}}
    <div class="card">
        <h2>Recent Scores</h2>
        @if($recentScores->isEmpty())
            <p>No scores imported yet. Use the Sync button above.</p>
        @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Beatmap</th>
                    <th>Accuracy</th>
                    <th>Rank</th>
                    <th>Misses</th>
                    <th>Played</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentScores as $score)
                <tr>
                    <td>
                        <a href="{{ route('beatmaps.show', $score->beatmap->id) }}">
                            {{ $score->beatmap->artist }} — {{ $score->beatmap->title }}
                        </a>
                    </td>
                    <td>{{ number_format($score->accuracy, 2) }}%</td>
                    <td class="rank rank-{{ strtolower($score->rank) }}">{{ $score->rank }}</td>
                    <td>{{ $score->miss_count }}</td>
                    <td>{{ $score->played_at->format('Y-m-d H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
