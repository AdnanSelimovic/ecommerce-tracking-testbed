# Research architecture

Laravel is the ground truth for this testbed. It records an ecommerce action
when the action happens inside the application, independently of any future
analytics vendor or network delivery.

```text
Laravel ecommerce action
        ↓
GroundTruthRecorder
        ↓ reads current browser-session context
ExperimentRun attribution (nullable)
        ↓
GroundTruthEvent
```

An `ExperimentRun` captures the controlled conditions for one browser session:
tracking, blocker, privacy, consent, browser, and free-form metadata. The
application starts a run in `pending`, transitions it to `running`, and binds
its database id to the Laravel session with `ExperimentRunContext`. Every
canonical ground-truth event recorded during that session inherits that id.

When the run completes, its `finished_at` timestamp is persisted and the
session binding is removed. The run and all of its events remain immutable
research records. Normal browsing without an active run is permitted; those
events have a null experiment-run id.

This attribution is required for reproducible later comparisons: observations
from GA4, Meta, server augmentation, blockers, privacy settings, and consent
settings can be compared only with the Laravel events generated under the same
controlled run. No analytics observations or synthetic results are produced at
this milestone.

Purchase ground truth is additionally protected by a database unique constraint
on `(event_name, order_id)`. MySQL and SQLite permit multiple null `order_id`
values, so non-order events remain repeatable while one order cannot acquire two
`purchase` records.

## Milestone 3: correlated client baseline

```text
Laravel action
      ↓
GroundTruthEvent (event_id)
      ↓
ClientTrackingPayload (provider-neutral)
      ↓
one-time browser delivery
      ↓
 +-------------------------+-------------------------+
 |                                                   |
GA4                                             Meta Pixel
testbed_event_id = event_id                    eventID = event_id
testbed_run_id, tracking_channel=client
```

`ClientTrackingPayloadFactory` reads a ground-truth record and maps the
canonical ecommerce taxonomy to provider-specific payloads outside the model.
It retains event ID, experiment-run UUID, items, integer `value_minor`, currency,
order number, and timestamp; decimal values are produced only for browser
ecommerce parameters.

The browser dispatcher is one Vite module. It loads the Google tag only for a
configured public `GA4_MEASUREMENT_ID` and an eligible event, using
`send_page_view: false`. It loads Meta Pixel only for a configured public
`META_PIXEL_ID` and an eligible event. It deliberately makes no `page_view` or
`PageView` call.

Eligibility requires a currently bound running experiment run with
`tracking_mode` of `client_only` or `server_augmented` and `consent_mode` of
`full`. This is a temporary baseline rule, not consent enforcement. Ground truth
continues to be recorded for every normal application action.

For post/redirect/get events, `ClientTrackingQueue` stores only the exact
ground-truth UUID in Laravel's session. The destination layout pulls, hydrates,
and removes it before rendering. This prevents refresh-based duplicate browser
dispatch without implying a platform acknowledgement.

The project now distinguishes: **ground truth** (Laravel persisted the action),
**browser dispatch attempt** (one eligible rendered `gtag`/`fbq` invocation),
and **platform observation** (future evidence that GA4 or Meta received it).
Milestone 3 records no platform observation and sends no server-side tracking.

## Milestone 5: browser observation baseline

One fresh, non-persistent Playwright Chromium BrowserContext maps to exactly
one `ExperimentRun`. Its `context.request` API shares that Laravel session to
bootstrap CSRF, create/bind the run, poll ground truth, ingest evidence, then
complete or fail it. The local/testing-only `/research/automation/*` surface
remains behind `RestrictResearchControlToLocal`; CSRF is not disabled globally.

```text
Laravel action → GroundTruthEvent
browser dispatcher → CustomEvent → JS invocation observation
browser request lifecycle → passive network observation
server dispatch endpoint acceptance → server-delivery evidence
platform UI/reporting → platform observation (not yet implemented)
```

These are intentionally distinct claims: ground truth proves the application
recorded an action; JS evidence proves testbed code invoked a provider API;
network evidence proves the browser saw a request lifecycle; endpoint acceptance
proves only an endpoint response. None alone proves GA4 or Meta reporting.

The runner uses an init-script listener for the browser-only
`testbed:client-tracking-dispatch` event; it does not patch `gtag` or `fbq`.
It uses only passive `request`, `response`, `requestfinished`, and
`requestfailed` events—no interception or blocking. A centralized classifier
separates GA4/Meta loader scripts from event transport candidates. Event UUIDs
are correlated only by exact URL or POST evidence; unmatched and duplicates
remain observations. Persistent rows retain method, host, path and a SHA-256
fingerprint, never a full third-party query string.

After each storefront action, the runner bounded-polls expected ground truth
and waits a configurable fixed observation window (3000ms by default), never
`networkidle`. Missing ground truth fails the run; absent tracker traffic is a
result. `browser_tracking_observations` distinguishes `js_invocation` from
`network`, and `script` from `event_transport`, while allowing duplicates.

## Controlled GA4 blocking experiment

The final comparison is GA4-only: Chromium runs `client_only` and
`server_augmented` under `none` and `controlled` browser-network conditions.
Both conditions create a fresh context with `serviceWorkers: 'block'` and
install the same route handler because Playwright routing disables HTTP cache.
The control handler continues every request. The controlled handler uses the
same centralized classifier as the observer and aborts only GA4 loader or event
transport requests with `blockedbyclient`; Laravel, application assets,
unrelated Google traffic, and server-side Measurement Protocol calls are not
browser-routed.

The observer receives the policy decision directly and writes
`blocked_by_client` plus `{ blocking_mode: controlled, policy_decision: block
}`. This preserves the distinction between intentional experimental blocking,
an ordinary network failure, and a response received before a browser abort.
Experiment metadata records routing, policy version, service-worker policy,
and observation window. A controlled block does not fail the ecommerce flow;
it is expected client-layer evidence. No repetitions, statistics, Meta, consent,
privacy-profile, or real-ad-blocker work belongs to this implementation step.

## Milestone 4: queued server augmentation

```text
GroundTruthEvent → TrackingCoordinator → client path + durable server dispatch
                                                    ↓
                                          tracking queue worker
                                            ↙              ↘
                              GA4 Measurement Protocol     Meta CAPI
                              separate GA4 stream          shared Pixel/event ID
```

Each server dispatch is unique per ground-truth event and provider, with durable
attempt history. Retries retain the original UUID and occurrence timestamp;
they cannot alter ecommerce ground truth. `endpoint_accepted` is transport/API
evidence only, never proof of platform reporting observation. GA4 server events
use a distinct stream and deterministic run-derived client ID; Meta reuses the
browser event identity for future deduplication.
