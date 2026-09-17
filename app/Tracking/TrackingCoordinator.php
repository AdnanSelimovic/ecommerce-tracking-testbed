<?php
namespace App\Tracking;
use App\Models\GroundTruthEvent;
use Illuminate\Http\Request;

class TrackingCoordinator
{
    public function __construct(
        private ClientTrackingDelivery $client,
        private ServerTrackingScheduler $server,
        private Request $request,
    ) {
    }

    public function handle(GroundTruthEvent $event): void
    {
        $this->client->queueIfEligible($event);
        $this->server->schedule($event, array_filter([
            'client_user_agent' => $this->request->userAgent(),
            'fbp' => $this->request->cookie('_fbp'),
            'fbc' => $this->request->cookie('_fbc'),
            // Use the canonical request URL without query parameters.
            'event_source_url' => $this->request->url(),
            'referrer_url' => $this->request->header('Referer'),
        ]));
    }
}
