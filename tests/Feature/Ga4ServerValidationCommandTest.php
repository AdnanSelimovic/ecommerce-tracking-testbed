<?php

namespace Tests\Feature;

use App\Enums\ExperimentRunStatus;
use App\Enums\GroundTruthEventName;
use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Ga4ServerValidationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_validation_messages_are_successful(): void
    {
        Http::fake(['*' => Http::response(['validationMessages' => []], 200)]);

        $this->artisan('tracking:validate-ga4-server', ['eventId' => $this->event()->event_id])
            ->assertExitCode(0);
    }

    public function test_non_empty_validation_messages_fail_the_command(): void
    {
        Http::fake(['*' => Http::response(['validationMessages' => [['description' => 'Invalid payload']]], 200)]);

        $this->artisan('tracking:validate-ga4-server', ['eventId' => $this->event()->event_id])
            ->assertExitCode(1);
    }

    public function test_http_and_connection_failures_fail_the_command(): void
    {
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
        $this->artisan('tracking:validate-ga4-server', ['eventId' => $this->event()->event_id])
            ->assertExitCode(1);

        Http::fake(['*' => Http::failedConnection()]);
        $this->artisan('tracking:validate-ga4-server', ['eventId' => $this->event()->event_id])
            ->assertExitCode(1);
    }

    private function event(): GroundTruthEvent
    {
        config()->set('tracking.ga4.server_enabled', true);
        config()->set('tracking.ga4.server_measurement_id', 'G-SERVER');
        config()->set('tracking.ga4.server_api_secret', 'secret');
        $run = ExperimentRun::create(['status' => ExperimentRunStatus::Running, 'started_at' => now()]);

        return GroundTruthEvent::create([
            'experiment_run_id' => $run->id,
            'event_name' => GroundTruthEventName::ViewItem,
        ]);
    }
}
