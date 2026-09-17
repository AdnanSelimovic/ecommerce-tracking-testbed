<?php

namespace Tests\Feature;

use App\Enums\ExperimentRunStatus;
use App\Enums\GroundTruthEventName;
use App\Http\Middleware\RestrictResearchControlToLocal;
use App\Models\BrowserTrackingObservation;
use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Services\ExperimentRunContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ResearchAutomationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_bootstrap_creates_a_csrf_capable_session(): void
    {
        $this->getJson(route('research.automation.bootstrap'))
            ->assertOk()
            ->assertJsonPath('conditions.browser', 'chromium')
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_automation_route_is_unavailable_outside_local_or_testing(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $this->expectException(HttpException::class);
            app(RestrictResearchControlToLocal::class)->handle(Request::create('/research/automation/bootstrap'), fn () => response('ok'));
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_run_creation_starts_and_binds_the_browser_session_with_safe_metadata(): void
    {
        $this->postJson(route('research.automation.runs.create'), $this->runPayload())
            ->assertCreated()
            ->assertJsonPath('run.status', 'running')
            ->assertJsonPath('run.blocking_mode', 'none');

        $run = ExperimentRun::sole();
        $this->assertSame('runner', $run->metadata['runner']);
        $this->assertSame($run->id, app(ExperimentRunContext::class)->current()?->id);
    }

    public function test_automation_accepts_both_final_blocking_modes_and_rejects_others(): void
    {
        $this->postJson(route('research.automation.runs.create'), $this->runPayload(['blocking_mode' => 'controlled']))
            ->assertCreated()
            ->assertJsonPath('run.blocking_mode', 'controlled');

        $this->postJson(route('research.automation.runs.create'), $this->runPayload(['blocking_mode' => 'real_blocker']))
            ->assertUnprocessable();
    }

    public function test_events_are_scoped_to_the_requested_run(): void
    {
        $run = ExperimentRun::create();
        $other = ExperimentRun::create();
        GroundTruthEvent::create(['experiment_run_id' => $run->id, 'event_name' => GroundTruthEventName::ViewItem]);
        GroundTruthEvent::create(['experiment_run_id' => $other->id, 'event_name' => GroundTruthEventName::Purchase]);

        $this->getJson(route('research.automation.runs.events', $run))
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.event_name', 'view_item');
    }

    public function test_observation_uuid_must_belong_to_run_but_unmatched_and_duplicates_are_allowed(): void
    {
        $run = ExperimentRun::create();
        $event = GroundTruthEvent::create(['experiment_run_id' => $run->id, 'event_name' => GroundTruthEventName::ViewItem]);
        $otherEvent = GroundTruthEvent::create(['experiment_run_id' => ExperimentRun::create()->id, 'event_name' => GroundTruthEventName::Purchase]);
        $matched = $this->observation([
            'ground_truth_event_id' => $event->event_id,
            'outcome' => 'response_received_aborted',
            'response_status' => 204,
            'failure_text' => 'net::ERR_ABORTED',
        ]);

        $this->postJson(route('research.automation.runs.observations', $run), ['observations' => [$matched, $matched, $this->observation()]])
            ->assertCreated()->assertJsonPath('inserted', 3);
        $this->assertSame(3, BrowserTrackingObservation::count());
        $this->assertSame($event->id, BrowserTrackingObservation::first()->ground_truth_event_id);
        $this->assertNull(BrowserTrackingObservation::latest('id')->first()->ground_truth_event_id);

        $this->postJson(route('research.automation.runs.observations', $run), ['observations' => [$this->observation(['ground_truth_event_id' => $otherEvent->event_id])]])
            ->assertUnprocessable();
    }

    public function test_an_explicit_empty_observation_list_is_a_successful_ingestion(): void
    {
        $run = ExperimentRun::create();

        $this->postJson(route('research.automation.runs.observations', $run), ['observations' => []])
            ->assertCreated()
            ->assertJsonPath('inserted', 0);

        $this->assertSame(0, BrowserTrackingObservation::count());
    }

    public function test_controlled_block_observations_are_valid_and_remain_duplicate_evidence(): void
    {
        $run = ExperimentRun::create();
        $blocked = $this->observation([
            'resource_kind' => 'script',
            'outcome' => 'blocked_by_client',
            'failure_text' => 'net::ERR_BLOCKED_BY_CLIENT',
            'metadata' => ['blocking_mode' => 'controlled', 'policy_decision' => 'block'],
        ]);

        $this->postJson(route('research.automation.runs.observations', $run), ['observations' => [$blocked, $blocked]])
            ->assertCreated()
            ->assertJsonPath('inserted', 2);
        $this->assertSame(2, BrowserTrackingObservation::count());
    }

    public function test_completion_and_failure_clear_binding_and_keep_run_records(): void
    {
        $this->postJson(route('research.automation.runs.create'), $this->runPayload());
        $run = ExperimentRun::sole();
        $this->postJson(route('research.automation.runs.complete', $run))->assertOk();
        $this->assertSame(ExperimentRunStatus::Completed, $run->fresh()->status);
        $this->assertNull(app(ExperimentRunContext::class)->current());

        $this->postJson(route('research.automation.runs.create'), $this->runPayload());
        $failed = ExperimentRun::latest('id')->first();
        GroundTruthEvent::create(['experiment_run_id' => $failed->id, 'event_name' => GroundTruthEventName::ViewItem]);
        $this->postJson(route('research.automation.runs.fail', $failed), ['reason' => 'navigation failed'])->assertOk();
        $this->assertSame(ExperimentRunStatus::Failed, $failed->fresh()->status);
        $this->assertNotNull($failed->fresh()->finished_at);
        $this->assertSame('navigation failed', $failed->fresh()->metadata['failure_reason']);
        $this->assertSame(1, $failed->groundTruthEvents()->count());
    }

    /** @return array<string, mixed> */
    private function runPayload(array $overrides = []): array
    {
        return array_merge(['tracking_mode' => 'client_only', 'blocking_mode' => 'none', 'privacy_mode' => 'standard', 'consent_mode' => 'full', 'browser' => 'chromium', 'browser_version' => '153', 'metadata' => ['runner' => 'runner']], $overrides);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function observation(array $overrides = []): array
    {
        return array_merge(['provider' => 'ga4', 'layer' => 'network', 'resource_kind' => 'event_transport', 'outcome' => 'finished', 'request_method' => 'POST', 'request_host' => 'www.google-analytics.com', 'request_path' => '/g/collect', 'request_fingerprint' => str_repeat('a', 64), 'observed_at' => now()->toISOString()], $overrides);
    }
}
