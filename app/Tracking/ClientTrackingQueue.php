<?php

namespace App\Tracking;

use App\Models\GroundTruthEvent;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * A one-response hand-off for browser dispatch. It stores only event UUIDs,
 * never provider payloads or delivery claims, and consumes them on rendering.
 */
class ClientTrackingQueue
{
    private const SESSION_KEY = 'research.pending_client_tracking_event_ids';

    public function __construct(private readonly Session $session)
    {
    }

    public function queue(GroundTruthEvent $event): void
    {
        $ids = $this->session->get(self::SESSION_KEY, []);
        $ids[] = $event->event_id;

        $this->session->put(self::SESSION_KEY, array_values(array_unique($ids)));
    }

    /** @return Collection<int, GroundTruthEvent> */
    public function pull(): Collection
    {
        $ids = $this->session->pull(self::SESSION_KEY, []);

        if ($ids === []) {
            return collect();
        }

        $events = GroundTruthEvent::with(['experimentRun', 'product', 'order.items'])
            ->whereIn('event_id', $ids)
            ->get()
            ->keyBy('event_id');

        return collect($ids)
            ->map(fn (string $id) => $events->get($id))
            ->filter()
            ->values();
    }
}
