@extends('layouts.app')

@section('title', 'Research debug')

@section('content')
    <h1>Research debug</h1>
    <p class="muted">Backend ground truth. Measurement systems are compared against this, never the other way around.</p>

    <h2>Client tracking baseline</h2>
    <div class="card">
        <p>GA4 client: <strong>{{ $ga4Configured ? 'configured' : 'disabled (no measurement ID)' }}</strong></p>
        <p>Meta Pixel client: <strong>{{ $metaConfigured ? 'configured' : 'disabled (no Pixel ID)' }}</strong></p>
        <p>Current run eligibility: <strong>{{ $clientTrackingEligible ? 'eligible (full-consent controlled run)' : 'not eligible' }}</strong></p>
        <p class="muted">This milestone dispatches only canonical ecommerce events. It does not claim that either platform received them.</p>
    </div>

    <h2>Server tracking readiness</h2>
    <div class="card">
        <p>GA4 Measurement Protocol: <strong>{{ $ga4ServerConfigured ? 'configured' : 'disabled' }}</strong> @if($ga4ServerConfigured) (target: {{ config('tracking.ga4.server_measurement_id') }}) @endif</p>
        @if($ga4ServerSameStream)<p class="errors">Warning: GA4 server and client measurement IDs are identical. Use separate streams for valid comparison.</p>@endif
        <p>Meta CAPI: <strong>{{ $metaCapiConfigured ? 'configured' : 'disabled' }}</strong> (Graph {{ config('tracking.meta.graph_api_version') }}; token {{ filled(config('tracking.meta.capi_access_token')) ? 'configured' : 'missing' }})</p>
    </div>

    <h2>Recent server dispatches</h2>
    <div class="card"><table><thead><tr><th>event</th><th>run</th><th>provider</th><th>status</th><th>attempts</th><th>HTTP</th><th>queued</th></tr></thead><tbody>@forelse($dispatches as $dispatch)<tr><td><code>{{ $dispatch->groundTruthEvent?->event_id }}</code></td><td>{{ $dispatch->experimentRun?->run_id }}</td><td>{{ $dispatch->provider }}</td><td>{{ $dispatch->status->value }}</td><td>{{ $dispatch->attempt_count }}</td><td>{{ $dispatch->last_http_status ?? '—' }}</td><td>{{ $dispatch->queued_at->toDateTimeString() }}</td></tr>@empty<tr><td colspan="7" class="muted">No server dispatches yet.</td></tr>@endforelse</tbody></table></div>

    <h2>Current browser-session run</h2>
    <div class="card">
        @if ($currentRun)
            <p><code>{{ $currentRun->run_id }}</code> — {{ $currentRun->status->value }}, {{ $currentRun->tracking_mode ?? 'tracking unset' }}, started {{ $currentRun->started_at?->toDateTimeString() ?? '—' }}.</p>
            <form method="post" action="{{ route('research.runs.finish', $currentRun) }}">
                @csrf
                <button type="submit">Finish active run</button>
            </form>
        @else
            <p class="muted">No active run is bound to this browser session.</p>
        @endif
        <form method="post" action="{{ route('research.context.clear') }}">
            @csrf
            <button type="submit">Clear/unbind session run</button>
        </form>
    </div>

    <h2>Start a controlled run</h2>
    <div class="card">
        <form method="post" action="{{ route('research.runs.start') }}">
            @csrf
            <p><label>Tracking <select name="tracking_mode"><option value="">Unset</option><option value="client_only">client_only</option><option value="server_augmented">server_augmented</option></select></label>
            <label>Blocking <select name="blocking_mode"><option value="">Unset</option><option value="none">none</option><option value="controlled">controlled</option></select></label>
            <label>Privacy <select name="privacy_mode"><option value="">Unset</option><option value="standard">standard</option></select></label></p>
            <p><label>Consent <select name="consent_mode"><option value="">Unset</option><option value="full">full</option></select></label>
            <label>Browser <input name="browser" maxlength="255"></label>
            <label>Version <input name="browser_version" maxlength="255"></label></p>
            <button type="submit">Create, start, and bind run</button>
        </form>
    </div>

    <h2>Experiment runs ({{ $runs->count() }})</h2>
    <div class="card">
        @if ($runs->isEmpty())
            <p class="muted">No experiment runs yet.</p>
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
                        <th>events</th>
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
                            <td>{{ $run->status->value }}</td>
                            <td>{{ optional($run->started_at)->toDateTimeString() ?? '—' }}</td>
                            <td>{{ optional($run->finished_at)->toDateTimeString() ?? '—' }}</td>
                            <td><a href="{{ route('research.debug', ['run' => $run->run_id]) }}">{{ $run->ground_truth_events_count }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <h2>Ground-truth events{{ $selectedRun ? ' for '.$selectedRun->run_id : '' }} ({{ $events->count() }} most recent)</h2>
    @if ($selectedRun)
        <p><a href="{{ route('research.debug') }}">Show all runs</a></p>
    @endif
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
