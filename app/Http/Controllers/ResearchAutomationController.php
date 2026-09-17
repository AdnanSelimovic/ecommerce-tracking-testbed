<?php

namespace App\Http\Controllers;

use App\Models\BrowserTrackingObservation;
use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Services\ExperimentRunContext;
use App\Services\ExperimentRunManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Session-bound local control API used only by the Playwright research runner.
 */
class ResearchAutomationController extends Controller
{
    private const METADATA_LIMIT = 32_000;

    public function bootstrap(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
            'conditions' => [
                'browser' => 'chromium',
                'blocking_mode' => 'none',
                'privacy_mode' => 'standard',
                'consent_mode' => 'full',
            ],
        ]);
    }

    public function create(Request $request, ExperimentRunManager $runs, ExperimentRunContext $context): JsonResponse
    {
        $attributes = Validator::make($request->all(), [
            'tracking_mode' => ['required', Rule::in(['client_only', 'server_augmented'])],
            'blocking_mode' => ['required', Rule::in(['none', 'controlled'])],
            'privacy_mode' => ['required', Rule::in(['standard', 'javascript_disabled'])],
            'consent_mode' => ['required', Rule::in(['full', 'partial', 'none'])],
            'browser' => ['required', 'string', 'max:255'],
            'browser_version' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ])->validate();
        $this->ensureMetadataIsBounded($attributes['metadata'] ?? null);

        $run = $runs->start($runs->create($attributes));
        $context->bind($run);

        return response()->json(['run' => $this->runPayload($run)], 201);
    }

    public function show(ExperimentRun $run): JsonResponse
    {
        return response()->json(['run' => $this->runPayload($run)]);
    }

    public function events(ExperimentRun $run): JsonResponse
    {
        return response()->json(['events' => $run->groundTruthEvents()->orderBy('id')->get()->map(fn (GroundTruthEvent $event) => [
            'event_id' => $event->event_id,
            'event_name' => $event->event_name->value,
            'occurred_at' => $event->occurred_at?->toISOString(),
        ])->values()]);
    }

    public function observations(Request $request, ExperimentRun $run): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            // An explicit empty collection is valid evidence: no browser
            // observation is still a meaningful baseline outcome.
            'observations' => ['present', 'array', 'max:500'],
            'observations.*.ground_truth_event_id' => ['nullable', 'uuid'],
            'observations.*.provider' => ['required', Rule::in(['ga4', 'meta'])],
            'observations.*.layer' => ['required', Rule::in(['js_invocation', 'network'])],
            'observations.*.resource_kind' => ['required', Rule::in(['script', 'event_transport'])],
            'observations.*.canonical_event_name' => ['nullable', 'string', 'max:100'],
            'observations.*.provider_event_name' => ['nullable', 'string', 'max:100'],
            'observations.*.outcome' => ['required', Rule::in(['issued', 'finished', 'failed', 'response_received_aborted', 'blocked_by_client'])],
            'observations.*.correlation_method' => ['nullable', Rule::in(['embedded_event_uuid'])],
            'observations.*.request_method' => ['nullable', 'string', 'max:16'],
            'observations.*.request_host' => ['nullable', 'string', 'max:255'],
            'observations.*.request_path' => ['nullable', 'string', 'max:2048'],
            'observations.*.request_fingerprint' => ['nullable', 'string', 'size:64'],
            'observations.*.observed_at' => ['required', 'date'],
            'observations.*.finished_at' => ['nullable', 'date'],
            'observations.*.duration_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'observations.*.response_status' => ['nullable', 'integer', 'min:100', 'max:599'],
            'observations.*.failure_text' => ['nullable', 'string', 'max:500'],
            'observations.*.metadata' => ['nullable', 'array'],
        ])->validate();

        $eventIds = collect($validated['observations'])->pluck('ground_truth_event_id')->filter()->unique();
        $events = $run->groundTruthEvents()->whereIn('event_id', $eventIds)->get()->keyBy('event_id');
        if ($events->count() !== $eventIds->count()) {
            return response()->json(['message' => 'Every ground-truth event UUID must belong to this run.'], 422);
        }

        foreach ($validated['observations'] as $observation) {
            $this->ensureMetadataIsBounded($observation['metadata'] ?? null);
            BrowserTrackingObservation::create(array_merge(
                Arr::except($observation, ['ground_truth_event_id']),
                [
                    'experiment_run_id' => $run->id,
                    'ground_truth_event_id' => isset($observation['ground_truth_event_id'])
                        ? $events[$observation['ground_truth_event_id']]->id : null,
                ],
            ));
        }

        return response()->json(['inserted' => count($validated['observations'])], 201);
    }

    public function complete(ExperimentRun $run, ExperimentRunManager $runs): JsonResponse
    {
        return response()->json(['run' => $this->runPayload($runs->complete($run))]);
    }

    public function fail(Request $request, ExperimentRun $run, ExperimentRunManager $runs): JsonResponse
    {
        $reason = Validator::make($request->all(), ['reason' => ['nullable', 'string', 'max:500']])->validate()['reason'] ?? null;

        return response()->json(['run' => $this->runPayload($runs->fail($run, $reason))]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function ensureMetadataIsBounded(?array $metadata): void
    {
        if ($metadata !== null && strlen(json_encode($metadata, JSON_THROW_ON_ERROR)) > self::METADATA_LIMIT) {
            abort(422, 'Metadata exceeds the permitted size.');
        }
    }

    /** @return array<string, mixed> */
    private function runPayload(ExperimentRun $run): array
    {
        return [
            'run_id' => $run->run_id,
            'status' => $run->status->value,
            'tracking_mode' => $run->tracking_mode,
            'blocking_mode' => $run->blocking_mode,
            'privacy_mode' => $run->privacy_mode,
            'consent_mode' => $run->consent_mode,
            'browser' => $run->browser,
            'browser_version' => $run->browser_version,
            'metadata' => $run->metadata,
            'started_at' => $run->started_at?->toISOString(),
            'finished_at' => $run->finished_at?->toISOString(),
        ];
    }
}
