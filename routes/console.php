<?php

use App\Models\GroundTruthEvent;
use App\Models\ExperimentRun;
use App\Models\ServerTrackingDispatch;
use App\Models\BrowserTrackingObservation;
use App\Tracking\Ga4ServerPayloadFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

Artisan::command('tracking:validate-ga4-server {eventId}', function (string $eventId, Ga4ServerPayloadFactory $payloads): int {
    if (! config('tracking.ga4.server_enabled')) {
        $this->error('GA4 server Measurement Protocol is not configured.');
        return self::FAILURE;
    }
    $event = GroundTruthEvent::with('experimentRun')->where('event_id', $eventId)->firstOrFail();
    $url = config('tracking.ga4.server_debug_endpoint').'?measurement_id='.rawurlencode(config('tracking.ga4.server_measurement_id')).'&api_secret='.rawurlencode(config('tracking.ga4.server_api_secret'));
    try {
        $response = Http::timeout(10)->post($url, $payloads->make($event)+['validation_behavior'=>'ENFORCE_RECOMMENDATIONS']);
    } catch (ConnectionException) {
        $this->error('GA4 debug endpoint connection failed.');

        return self::FAILURE;
    }

    $this->line($response->body());

    if (! $response->successful()) {
        return self::FAILURE;
    }

    $messages = $response->json('validationMessages');
    if (! is_array($messages) || $messages !== []) {
        $this->error('GA4 payload has validation messages.');

        return self::FAILURE;
    }

    return self::SUCCESS;
})->purpose('Validate one GA4 server payload without changing dispatch evidence');

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('research:export-experiment {batchId}', function (string $batchId): int {
    $root = "research/collections/{$batchId}/export";
    Storage::disk('local')->makeDirectory($root);
    $runs = ExperimentRun::query()->where('metadata->collection_batch_id', $batchId)
        ->with(['groundTruthEvents', 'browserTrackingObservations'])->orderBy('started_at')->get();
    $runRows = [];
    $eventRows = [];
    $problems = [];
    foreach ($runs as $run) {
        $metadata = $run->metadata ?? [];
        $events = $run->groundTruthEvents->sortBy('occurred_at')->values();
        $observations = $run->browserTrackingObservations;
        $dispatches = ServerTrackingDispatch::query()->where('experiment_run_id', $run->id)->where('provider', 'ga4')->with('attempts')->get()->keyBy('ground_truth_event_id');
        $correlated = $observations->where('provider', 'ga4')->where('layer', 'network')->where('resource_kind', 'event_transport')->filter(fn ($o) => $o->ground_truth_event_id)->count();
        $blocks = $observations->where('provider', 'ga4')->where('outcome', 'blocked_by_client')->count();
        $unmatched = $observations->where('provider', 'ga4')->where('layer', 'network')->where('resource_kind', 'event_transport')->filter(fn ($o) => ! $o->ground_truth_event_id)->count();
        $accepted = $dispatches->filter(fn ($d) => $d->status->value === 'endpoint_accepted');
        $latencies = $dispatches->flatMap->attempts->pluck('latency_ms')->filter();
        $groundTruthCount = $events->count();
        $runRows[] = [
            'batch_id'=>$batchId, 'schedule_position'=>$metadata['schedule_position'] ?? null, 'round'=>$metadata['replication_index'] ?? null, 'condition_label'=>$metadata['condition_label'] ?? null, 'replication_index'=>$metadata['replication_index'] ?? null, 'run_id'=>$run->run_id,
            'tracking_mode'=>$run->tracking_mode, 'blocking_mode'=>$run->blocking_mode, 'status'=>$run->status->value, 'git_commit'=>$metadata['git_commit'] ?? null, 'browser'=>$run->browser, 'browser_version'=>$run->browser_version, 'headless'=>$metadata['headed'] === false ? 1 : 0, 'observation_window_ms'=>$metadata['observation_window_ms'] ?? null, 'routing_enabled'=>$metadata['routing_enabled'] ?? null, 'service_workers'=>$metadata['service_workers'] ?? null, 'privacy_mode'=>$run->privacy_mode, 'consent_mode'=>$run->consent_mode, 'product_slug'=>$metadata['product_slug'] ?? null,
            'ground_truth_event_count'=>$groundTruthCount, 'ga4_js_invocation_count'=>$observations->where('provider','ga4')->where('layer','js_invocation')->count(), 'ga4_network_correlated_event_count'=>$correlated, 'ga4_controlled_block_count'=>$blocks, 'unmatched_ga4_request_count'=>$unmatched, 'ga4_server_dispatch_count'=>$dispatches->count(), 'ga4_server_endpoint_accepted_count'=>$accepted->count(), 'client_transport_capture_rate'=>$groundTruthCount ? $correlated / $groundTruthCount : null, 'client_transport_loss_rate'=>$groundTruthCount ? 1 - ($correlated / $groundTruthCount) : null, 'server_endpoint_acceptance_rate'=>$run->tracking_mode === 'server_augmented' && $groundTruthCount ? $accepted->count() / $groundTruthCount : null, 'server_latency_mean_ms'=>$latencies->avg(), 'server_latency_min_ms'=>$latencies->min(), 'server_latency_max_ms'=>$latencies->max(), 'started_at'=>$run->started_at, 'finished_at'=>$run->finished_at, 'run_duration_ms'=>$run->started_at && $run->finished_at ? $run->started_at->diffInMilliseconds($run->finished_at) : null,
        ];
        foreach ($events as $event) {
            $eventObservations = $observations->where('ground_truth_event_id', $event->id);
            $network = $eventObservations->first(fn ($o) => $o->provider === 'ga4' && $o->layer === 'network' && $o->resource_kind === 'event_transport');
            $js = $eventObservations->first(fn ($o) => $o->provider === 'ga4' && $o->layer === 'js_invocation');
            $dispatch = $dispatches->get($event->id);
            $attempt = $dispatch?->attempts->sortByDesc('attempt_number')->first();
            $eventRows[] = ['batch_id'=>$batchId, 'condition_label'=>$metadata['condition_label'] ?? null, 'replication_index'=>$metadata['replication_index'] ?? null, 'run_id'=>$run->run_id, 'event_name'=>$event->event_name->value, 'ground_truth_event_uuid'=>$event->event_id, 'occurred_at'=>$event->occurred_at, 'client_js_invoked'=>(bool) $js, 'client_network_correlated'=>(bool) $network, 'client_network_response_status'=>$network?->response_status, 'client_network_outcome'=>$network?->outcome, 'controlled_block_observed'=>$eventObservations->contains(fn ($o) => $o->outcome === 'blocked_by_client'), 'server_dispatch_expected'=>$run->tracking_mode === 'server_augmented', 'server_dispatch_status'=>$dispatch?->status?->value, 'server_http_status'=>$dispatch?->last_http_status, 'server_attempt_count'=>$dispatch?->attempt_count, 'server_latency_ms'=>$attempt?->latency_ms, 'server_endpoint_accepted'=>$dispatch?->status?->value === 'endpoint_accepted', 'transaction_id'=>$event->event_name->value === 'purchase' ? data_get($event->payload, 'transaction_id') : null, 'delivery_evidence_available'=>(bool) $network || $dispatch?->status?->value === 'endpoint_accepted'];
        }
    }
    $counts = collect($runRows)->countBy('condition_label');
    if ($runs->count() !== 40) $problems[] = 'expected exactly 40 completed final runs';
    foreach (['A','B','C','D'] as $label) if (($counts[$label] ?? 0) !== 10) $problems[] = "expected 10 {$label} runs";
    if (count($eventRows) !== 160) $problems[] = 'expected exactly 160 GroundTruthEvents';
    if (collect($runRows)->pluck('git_commit')->filter()->unique()->count() !== 1) $problems[] = 'runs do not share one Git revision';
    if (collect($runRows)->pluck('browser_version')->filter()->unique()->count() !== 1) $problems[] = 'runs do not share one Chromium version';
    if (collect($runRows)->pluck('headless')->unique()->count() !== 1) $problems[] = 'runs do not share one headless setting';
    if (BrowserTrackingObservation::whereIn('experiment_run_id', $runs->pluck('id'))->where('provider', 'meta')->exists() || ServerTrackingDispatch::whereIn('experiment_run_id', $runs->pluck('id'))->where('provider', 'meta')->exists()) $problems[] = 'Meta evidence or dispatch participation is present';
    foreach ($runRows as $row) {
        if ($row['status'] !== 'completed' || $row['ground_truth_event_count'] !== 4 || $row['observation_window_ms'] != 10000 || ! $row['routing_enabled'] || $row['service_workers'] !== 'block' || $row['privacy_mode'] !== 'standard' || $row['consent_mode'] !== 'full') $problems[] = "run {$row['run_id']} violates fixed conditions";
        if (in_array($row['condition_label'], ['A','B']) && ($row['ga4_network_correlated_event_count'] !== 4 || $row['ga4_controlled_block_count'] !== 0)) $problems[] = "run {$row['run_id']} lacks expected non-blocked client evidence";
        if (in_array($row['condition_label'], ['C','D']) && ($row['ga4_network_correlated_event_count'] !== 0 || $row['ga4_controlled_block_count'] < 4)) $problems[] = "run {$row['run_id']} lacks expected controlled block evidence";
        if (in_array($row['condition_label'], ['A','C']) && $row['ga4_server_dispatch_count'] !== 0) $problems[] = "run {$row['run_id']} has unexpected server dispatches";
        if (in_array($row['condition_label'], ['B','D']) && ($row['ga4_server_dispatch_count'] !== 4 || $row['ga4_server_endpoint_accepted_count'] !== 4)) $problems[] = "run {$row['run_id']} lacks accepted server dispatches";
    }
    $writeCsv = function (string $name, array $rows) use ($root): void { $path = Storage::disk('local')->path("{$root}/{$name}"); $file = fopen($path, 'w'); if ($rows) { fputcsv($file, array_keys($rows[0])); foreach ($rows as $row) fputcsv($file, $row); } fclose($file); };
    $writeCsv('runs.csv', $runRows); $writeCsv('events.csv', $eventRows);
    $report = ['batch_id'=>$batchId, 'valid'=>$problems === [], 'problems'=>array_values(array_unique($problems)), 'run_count'=>count($runRows), 'event_count'=>count($eventRows)];
    Storage::disk('local')->put("{$root}/dataset.json", json_encode(['runs'=>$runRows, 'events'=>$eventRows], JSON_PRETTY_PRINT));
    Storage::disk('local')->put("{$root}/validation-report.json", json_encode($report, JSON_PRETTY_PRINT));
    Storage::disk('local')->put("{$root}/summary.md", "# Final experiment export\n\nValid: ".($report['valid'] ? 'yes' : 'no')."\n\n| Condition | Runs | Ground-truth events | Client correlated transports | Controlled blocks | Server endpoint accepted |\n|---|---:|---:|---:|---:|---:|\n".collect($runRows)->groupBy('condition_label')->map(fn ($rows, $label) => "| {$label} | {$rows->count()} | {$rows->sum('ground_truth_event_count')} | {$rows->sum('ga4_network_correlated_event_count')} | {$rows->sum('ga4_controlled_block_count')} | {$rows->sum('ga4_server_endpoint_accepted_count')} |")->implode("\n")."\n");
    $this->line(json_encode($report));
    return $report['valid'] ? self::SUCCESS : self::FAILURE;
})->purpose('Export one final experiment collection batch from persisted evidence');

Artisan::command('research:verify-final-server-run {runId}', function (string $runId): int {
    $run = ExperimentRun::where('run_id', $runId)->firstOrFail();
    $expected = $run->tracking_mode === 'server_augmented' ? 4 : 0;
    $dispatches = ServerTrackingDispatch::where('experiment_run_id', $run->id)->where('provider', 'ga4')->get();
    $accepted = $dispatches->filter(fn ($dispatch) => $dispatch->status->value === 'endpoint_accepted')->count();
    $valid = $dispatches->count() === $expected && ($expected === 0 || $accepted === 4);
    $this->line(json_encode(['run_id'=>$runId, 'expected_dispatches'=>$expected, 'dispatch_count'=>$dispatches->count(), 'endpoint_accepted_count'=>$accepted, 'valid'=>$valid]));
    return $valid ? self::SUCCESS : self::FAILURE;
})->purpose('Verify persisted GA4 server dispatch evidence for one final run');

Artisan::command('research:export-privacy-consent {batchId}', function (string $batchId, \App\Research\PrivacyConsentExporter $exporter): int {
    $report = $exporter->export($batchId); $this->line(json_encode($report)); return $report['valid'] ? self::SUCCESS : self::FAILURE;
})->purpose('Export one Experiment 2 privacy/consent collection from persisted evidence');
