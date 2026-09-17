<?php

namespace App\Research;

use App\Models\ExperimentRun;
use App\Models\ServerTrackingDispatch;
use App\Tracking\Ga4ConsentProfile;

/** Authoritative persisted-evidence checks for Experiment 2. */
final class PrivacyConsentEvidenceValidator
{
    public const SEQUENCE = ['view_item', 'add_to_cart', 'begin_checkout', 'purchase'];

    /** @return array{valid: bool, problems: list<string>, metrics: array<string, int|bool|null>} */
    public function validate(ExperimentRun $run): array
    {
        $run->loadMissing(['groundTruthEvents', 'browserTrackingObservations']);
        $m = $run->metadata ?? [];
        $events = $run->groundTruthEvents->sortBy('id')->values();
        $observations = $run->browserTrackingObservations;
        $ga4 = $observations->where('provider', 'ga4');
        $network = $ga4->where('layer', 'network')->where('resource_kind', 'event_transport');
        $metrics = [
            'ground_truth_event_count' => $events->count(),
            'ga4_js_invocation_count' => $ga4->where('layer', 'js_invocation')->where('resource_kind', 'event_transport')->count(),
            'ga4_loader_finished_count' => $ga4->where('resource_kind', 'script')->where('outcome', 'finished')->count(),
            'ga4_network_correlated_event_count' => $network->filter(fn ($o) => $o->ground_truth_event_id)->count(),
            'unmatched_ga4_request_count' => $network->filter(fn ($o) => ! $o->ground_truth_event_id)->count(),
            'ga4_controlled_block_count' => $ga4->where('outcome', 'blocked_by_client')->count(),
            'ga4_server_dispatch_count' => ServerTrackingDispatch::where('experiment_run_id', $run->id)->where('provider', 'ga4')->count(),
            'meta_participation_count' => $observations->where('provider', 'meta')->count() + ServerTrackingDispatch::where('experiment_run_id', $run->id)->where('provider', 'meta')->count(),
            'javascript_enabled' => $m['javascript_enabled'] ?? null,
        ];
        $problems = [];
        $expect = [
            'E' => ['privacy' => 'standard', 'consent' => 'full', 'javascript' => true],
            'F' => ['privacy' => 'javascript_disabled', 'consent' => 'full', 'javascript' => false],
            'G' => ['privacy' => 'standard', 'consent' => 'partial', 'javascript' => true],
            'H' => ['privacy' => 'standard', 'consent' => 'none', 'javascript' => true],
        ][$m['condition_label'] ?? ''] ?? null;
        if (! $expect) $problems[] = 'condition is not E/F/G/H';
        if ($run->status->value !== 'completed') $problems[] = 'run is not completed';
        if ($metrics['ground_truth_event_count'] !== 4) $problems[] = 'expected exactly four ground-truth events';
        if ($events->pluck('event_name')->map->value->all() !== self::SEQUENCE) $problems[] = 'canonical event sequence differs';
        if ($run->tracking_mode !== 'client_only' || $run->blocking_mode !== 'none') $problems[] = 'tracking or blocking condition differs';
        if ($metrics['ga4_server_dispatch_count'] !== 0 || $metrics['meta_participation_count'] !== 0) $problems[] = 'server or Meta participation is present';
        if ($metrics['unmatched_ga4_request_count'] !== 0 || $metrics['ga4_controlled_block_count'] !== 0) $problems[] = 'unexpected GA4 network evidence is present';
        if ($expect && ($run->privacy_mode !== $expect['privacy'] || $run->consent_mode !== $expect['consent'] || $metrics['javascript_enabled'] !== $expect['javascript'])) $problems[] = 'stored condition mapping differs';
        if ($expect && ! $this->sameConsentProfile($m['consent_profile'] ?? null, Ga4ConsentProfile::for($expect['consent']))) $problems[] = 'stored consent profile differs';
        if (($m['observation_window_ms'] ?? null) !== 10000 || ($m['service_workers'] ?? null) !== 'block' || ($m['routing_enabled'] ?? null) !== true) $problems[] = 'fixed browser settings differ';

        if (($m['condition_label'] ?? null) === 'E' && ($metrics['ga4_js_invocation_count'] !== 4 || $metrics['ga4_loader_finished_count'] < 1 || $metrics['ga4_network_correlated_event_count'] !== 4)) $problems[] = 'E delivery manipulation evidence differs';
        if (($m['condition_label'] ?? null) === 'F' && ($metrics['ga4_js_invocation_count'] !== 0 || $metrics['ga4_loader_finished_count'] !== 0 || $metrics['ga4_network_correlated_event_count'] !== 0)) $problems[] = 'F JavaScript-disabled evidence differs';
        // G deliberately has no required correlated-transport count: 0..4 is its outcome.
        if (($m['condition_label'] ?? null) === 'G' && ($metrics['ga4_js_invocation_count'] !== 4 || $metrics['ga4_loader_finished_count'] < 1)) $problems[] = 'G partial-consent initialization evidence differs';
        if (($m['condition_label'] ?? null) === 'H' && ($metrics['ga4_js_invocation_count'] !== 0 || $metrics['ga4_loader_finished_count'] !== 0 || $metrics['ga4_network_correlated_event_count'] !== 0)) $problems[] = 'H denied-consent evidence differs';

        return ['valid' => $problems === [], 'problems' => $problems, 'metrics' => $metrics];
    }

    /**
     * JSON object member order is not semantic. Sorting both copies retains
     * strict checks for array type, the complete key set, and string values.
     */
    private function sameConsentProfile(mixed $actual, array $expected): bool
    {
        if (! is_array($actual)) return false;

        ksort($actual, SORT_STRING);
        ksort($expected, SORT_STRING);

        return $actual === $expected;
    }
}
