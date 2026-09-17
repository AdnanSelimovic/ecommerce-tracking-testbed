<?php

namespace App\Research;

use App\Models\ExperimentRun;
use Illuminate\Support\Facades\Storage;

final class PrivacyConsentExporter
{
    public function __construct(private readonly PrivacyConsentEvidenceValidator $validator) {}

    /** @return array{valid: bool, problems: list<string>, run_count: int, event_count: int} */
    public function export(string $batchId): array
    {
        $root = "research/collections/{$batchId}/export";
        Storage::disk('local')->makeDirectory($root);
        $runs = ExperimentRun::query()->where('metadata->collection_batch_id', $batchId)->where('metadata->experiment_family', 'privacy_consent')->with(['groundTruthEvents', 'browserTrackingObservations'])->orderBy('started_at')->get();
        $runRows = []; $eventRows = []; $problems = [];
        foreach ($runs as $run) {
            $m = $run->metadata ?? []; $v = $this->validator->validate($run); $metrics = $v['metrics']; $profile = $m['consent_profile'] ?? [];
            if (! $v['valid']) foreach ($v['problems'] as $problem) $problems[] = "run {$run->run_id}: {$problem}";
            $gt = $metrics['ground_truth_event_count']; $correlated = $metrics['ga4_network_correlated_event_count'];
            $runRows[] = ['batch_id'=>$batchId, 'condition_label'=>$m['condition_label'] ?? null, 'replication_index'=>$m['replication_index'] ?? null, 'schedule_position'=>$m['schedule_position'] ?? null, 'run_id'=>$run->run_id, 'tracking_mode'=>$run->tracking_mode, 'blocking_mode'=>$run->blocking_mode, 'privacy_mode'=>$run->privacy_mode, 'consent_mode'=>$run->consent_mode, 'javascript_enabled'=>$metrics['javascript_enabled'], 'consent_analytics_storage'=>$profile['analytics_storage'] ?? null, 'consent_ad_storage'=>$profile['ad_storage'] ?? null, 'consent_ad_user_data'=>$profile['ad_user_data'] ?? null, 'consent_ad_personalization'=>$profile['ad_personalization'] ?? null, 'status'=>$run->status->value, 'git_commit'=>$m['git_commit'] ?? null, 'browser'=>$run->browser, 'browser_version'=>$run->browser_version, 'headless'=>($m['headed'] ?? true) === false ? 1 : 0, 'observation_window_ms'=>$m['observation_window_ms'] ?? null, 'routing_enabled'=>$m['routing_enabled'] ?? null, 'service_workers'=>$m['service_workers'] ?? null, 'product_slug'=>$m['product_slug'] ?? null, 'ground_truth_event_count'=>$gt, 'ga4_js_invocation_count'=>$metrics['ga4_js_invocation_count'], 'ga4_loader_finished_count'=>$metrics['ga4_loader_finished_count'], 'ga4_network_correlated_event_count'=>$correlated, 'unmatched_ga4_request_count'=>$metrics['unmatched_ga4_request_count'], 'ga4_controlled_block_count'=>$metrics['ga4_controlled_block_count'], 'client_transport_capture_rate'=>$gt ? $correlated / $gt : null, 'client_transport_loss_rate'=>$gt ? 1 - ($correlated / $gt) : null, 'ga4_server_dispatch_count'=>$metrics['ga4_server_dispatch_count'], 'started_at'=>$run->started_at, 'finished_at'=>$run->finished_at, 'run_duration_ms'=>$run->started_at && $run->finished_at ? $run->started_at->diffInMilliseconds($run->finished_at) : null];
            foreach ($run->groundTruthEvents->sortBy('id') as $event) { $o = $run->browserTrackingObservations->where('ground_truth_event_id', $event->id); $network = $o->first(fn ($x) => $x->provider === 'ga4' && $x->layer === 'network' && $x->resource_kind === 'event_transport'); $eventRows[] = ['batch_id'=>$batchId, 'condition_label'=>$m['condition_label'] ?? null, 'replication_index'=>$m['replication_index'] ?? null, 'schedule_position'=>$m['schedule_position'] ?? null, 'run_id'=>$run->run_id, 'privacy_mode'=>$run->privacy_mode, 'consent_mode'=>$run->consent_mode, 'event_name'=>$event->event_name->value, 'ground_truth_event_uuid'=>$event->event_id, 'occurred_at'=>$event->occurred_at, 'client_js_invoked'=>$o->contains(fn ($x) => $x->provider === 'ga4' && $x->layer === 'js_invocation'), 'client_network_correlated'=>(bool) $network, 'client_network_response_status'=>$network?->response_status, 'client_network_outcome'=>$network?->outcome]; }
        }
        $counts = collect($runRows)->countBy('condition_label');
        if ($runs->count() !== 40) $problems[] = 'expected exactly 40 privacy_consent runs'; foreach (['E','F','G','H'] as $label) if (($counts[$label] ?? 0) !== 10) $problems[] = "expected 10 {$label} runs"; if (count($eventRows) !== 160) $problems[] = 'expected exactly 160 GroundTruthEvents';
        foreach (['git_commit', 'browser_version', 'headless', 'observation_window_ms', 'routing_enabled', 'service_workers', 'product_slug'] as $field) if (collect($runRows)->pluck($field)->unique()->count() !== 1) $problems[] = "runs do not share one {$field}";
        $report = ['batch_id'=>$batchId, 'valid'=>$problems === [], 'problems'=>array_values(array_unique($problems)), 'run_count'=>count($runRows), 'event_count'=>count($eventRows)];
        $this->csv("{$root}/runs.csv", $runRows); $this->csv("{$root}/events.csv", $eventRows); Storage::disk('local')->put("{$root}/dataset.json", json_encode(['runs'=>$runRows, 'events'=>$eventRows], JSON_PRETTY_PRINT)); Storage::disk('local')->put("{$root}/validation-report.json", json_encode($report, JSON_PRETTY_PRINT)); Storage::disk('local')->put("{$root}/summary.md", "# Experiment 2 privacy and consent export\n\nValid: ".($report['valid'] ? 'yes' : 'no')."\n\n| Condition | Runs | Correlated GA4 transports |\n|---|---:|---:|\n".collect($runRows)->groupBy('condition_label')->map(fn ($rows, $label) => "| {$label} | {$rows->count()} | {$rows->sum('ga4_network_correlated_event_count')} |")->implode("\n")."\n");
        return $report;
    }
    private function csv(string $path, array $rows): void { $file = fopen(Storage::disk('local')->path($path), 'w'); if ($rows) { fputcsv($file, array_keys($rows[0])); foreach ($rows as $row) fputcsv($file, $row); } fclose($file); }
}
