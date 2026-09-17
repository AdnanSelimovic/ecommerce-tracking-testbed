<?php
namespace App\Tracking;
use App\Models\GroundTruthEvent;
class TrackingCoordinator { public function __construct(private ClientTrackingDelivery $client,private ServerTrackingScheduler $server){} public function handle(GroundTruthEvent $event):void{$this->client->queueIfEligible($event);$this->server->schedule($event);} }
