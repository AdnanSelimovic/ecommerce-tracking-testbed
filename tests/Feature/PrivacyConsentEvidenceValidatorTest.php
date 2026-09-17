<?php

namespace Tests\Feature;

use App\Enums\GroundTruthEventName;
use App\Models\BrowserTrackingObservation;
use App\Models\ExperimentRun;
use App\Models\GroundTruthEvent;
use App\Research\PrivacyConsentEvidenceValidator;
use App\Tracking\Ga4ConsentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyConsentEvidenceValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_consent_is_valid_with_zero_or_four_correlated_transports(): void
    {
        foreach ([0, 4] as $transports) {
            $run = $this->completedRun('G', 'standard', 'partial', true);
            $events = $this->events($run);
            foreach ($events as $event) BrowserTrackingObservation::create($this->observation($run, $event, 'js_invocation', 'event_transport'));
            BrowserTrackingObservation::create($this->observation($run, null, 'network', 'script', 'finished'));
            foreach (array_slice($events, 0, $transports) as $event) BrowserTrackingObservation::create($this->observation($run, $event));
            $this->assertTrue(app(PrivacyConsentEvidenceValidator::class)->validate($run->fresh())['valid']);
        }
    }

    public function test_none_consent_rejects_any_client_invocation(): void
    {
        $run = $this->completedRun('H', 'standard', 'none', true); $event = $this->events($run)[0];
        BrowserTrackingObservation::create($this->observation($run, $event, 'js_invocation', 'event_transport'));
        $this->assertFalse(app(PrivacyConsentEvidenceValidator::class)->validate($run->fresh())['valid']);
    }

    public function test_consent_profile_comparison_ignores_key_order_but_rejects_wrong_missing_and_extra_values(): void
    {
        $profile = ['ad_storage'=>'denied', 'ad_user_data'=>'denied', 'analytics_storage'=>'granted', 'ad_personalization'=>'denied'];
        $run = $this->completedRun('G', 'standard', 'partial', true, $profile);
        foreach ($this->events($run) as $event) BrowserTrackingObservation::create($this->observation($run, $event, 'js_invocation', 'event_transport'));
        BrowserTrackingObservation::create($this->observation($run, null, 'network', 'script', 'finished'));
        $this->assertTrue(app(PrivacyConsentEvidenceValidator::class)->validate($run->fresh())['valid']);

        foreach ([['analytics_storage'=>'denied', 'ad_storage'=>'denied', 'ad_user_data'=>'denied', 'ad_personalization'=>'denied'], ['analytics_storage'=>'granted', 'ad_storage'=>'denied', 'ad_user_data'=>'denied'], ['analytics_storage'=>'granted', 'ad_storage'=>'denied', 'ad_user_data'=>'denied', 'ad_personalization'=>'denied', 'unexpected'=>'denied']] as $invalid) {
            $candidate = $this->completedRun('G', 'standard', 'partial', true, $invalid);
            foreach ($this->events($candidate) as $event) BrowserTrackingObservation::create($this->observation($candidate, $event, 'js_invocation', 'event_transport'));
            BrowserTrackingObservation::create($this->observation($candidate, null, 'network', 'script', 'finished'));
            $this->assertFalse(app(PrivacyConsentEvidenceValidator::class)->validate($candidate->fresh())['valid']);
        }
    }

    private function completedRun(string $label, string $privacy, string $consent, bool $js, ?array $profile = null): ExperimentRun
    {
        return ExperimentRun::create(['tracking_mode'=>'client_only', 'blocking_mode'=>'none', 'privacy_mode'=>$privacy, 'consent_mode'=>$consent, 'status'=>'completed', 'metadata'=>['condition_label'=>$label, 'javascript_enabled'=>$js, 'consent_profile'=>$profile ?? Ga4ConsentProfile::for($consent), 'observation_window_ms'=>10000, 'service_workers'=>'block', 'routing_enabled'=>true]]);
    }
    /** @return list<GroundTruthEvent> */ private function events(ExperimentRun $run): array { return array_map(fn ($name) => GroundTruthEvent::create(['experiment_run_id'=>$run->id, 'event_name'=>$name]), GroundTruthEventName::cases()); }
    private function observation(ExperimentRun $run, ?GroundTruthEvent $event, string $layer = 'network', string $kind = 'event_transport', string $outcome = 'finished'): array { return ['experiment_run_id'=>$run->id, 'ground_truth_event_id'=>$event?->id, 'provider'=>'ga4', 'layer'=>$layer, 'resource_kind'=>$kind, 'outcome'=>$outcome, 'observed_at'=>now()]; }
}
