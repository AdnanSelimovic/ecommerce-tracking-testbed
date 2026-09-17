@extends('layouts.app')

@section('title', 'Research debug')

@section('content')
    <h1>Research debug</h1>
    <p class="muted">Backend ground truth. Measurement systems are compared against this, never the other way around.</p>

    <h2>Experiment runs ({{ $runs->count() }})</h2>
    <div class="card">
        @if ($runs->isEmpty())
            <p class="muted">No experiment runs yet. The experiment runner arrives in a later milestone.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>run_id</th>
                        <th>tracking</th>
                        <th>blocking</th>
                        <th>privacy</th>
                        <th>consent</th>
                        <th>browser</th>
                        <th>status</th>
                        <th>started</th>
                        <th>finished</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr>
                            <td><code>{{ $run->run_id }}</code></td>
                            <td>{{ $run->tracking_mode ?? '—' }}</td>
                            <td>{{ $run->blocking_mode ?? '—' }}</td>
                            <td>{{ $run->privacy_mode ?? '—' }}</td>
                            <td>{{ $run->consent_mode ?? '—' }}</td>
                            <td>{{ trim(($run->browser ?? '').' '.($run->browser_version ?? '')) ?: '—' }}</td>
                            <td>{{ $run->status }}</td>
                            <td>{{ optional($run->started_at)->toDateTimeString() ?? '—' }}</td>
                            <td>{{ optional($run->finished_at)->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Ground-truth events ({{ $events->count() }} most recent)</h2>
    <div class="card">
        @if ($events->isEmpty())
            <p class="muted">No ground-truth events recorded yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>event_id</th>
                        <th>run</th>
                        <th>event</th>
                        <th>product</th>
                        <th>order</th>
                        <th class="num">qty</th>
                        <th class="num">value</th>
                        <th>occurred_at</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td><code>{{ $event->event_id }}</code></td>
                            <td>{{ $event->experimentRun?->run_id ?? '—' }}</td>
                            <td>{{ $event->event_name->value }}</td>
                            <td>{{ $event->product?->name ?? '—' }}</td>
                            <td>{{ $event->order?->order_number ?? '—' }}</td>
                            <td class="num">{{ $event->quantity ?? '—' }}</td>
                            <td class="num">
                                {{ $event->value_minor === null ? '—' : \App\Support\Money::format($event->value_minor, $event->currency) }}
                            </td>
                            <td>{{ $event->occurred_at->toDateTimeString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
