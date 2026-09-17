<?php
namespace App\Tracking;
use App\Models\GroundTruthEvent;
class ServerTrackingEligibility { public function allows(GroundTruthEvent $event): bool { $event->loadMissing('experimentRun'); $r=$event->experimentRun; return $r !== null && $r->tracking_mode==='server_augmented' && $r->consent_mode==='full'; } }
