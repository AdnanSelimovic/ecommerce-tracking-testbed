# Ecommerce Tracking Testbed

A reproducible ecommerce **tracking research testbed**, built for an MSc Software
Engineering dissertation.

The application is an intentionally small synthetic storefront (Laravel + Blade +
Vite + MySQL). Its purpose is not commerce: it exists so that **the Laravel
backend can independently record what actually happened**, and so that
client-side and server-side measurement systems can later be compared against
that record.

> **Principle:** Laravel backend events are the source of truth.
> GA4, Meta Pixel, GA4 Measurement Protocol and the Meta Conversions API are
> *measurement systems under test*, never the definition of what occurred.

Milestone 3 adds real, correlated **client-side** GA4 and Meta Pixel baseline
instrumentation. Laravel remains the source of truth; browser dispatch is not
treated as evidence that either external platform received an event.

---

## Requirements

- PHP 8.3+ (Laravel 13; developed against PHP 8.4 under WampServer)
  - required extensions: the usual Laravel set, plus `pdo_mysql`
  - `pdo_sqlite` is required **only** to run the automated test suite
- Composer 2
- Node.js 20+ and npm
- MySQL (via WampServer / phpMyAdmin)
- Apache with `mod_rewrite`, document root pointing at `public/`

## Installation

```bash
git clone https://github.com/AdnanSelimovic/ecommerce-tracking-testbed.git
cd ecommerce-tracking-testbed

composer install
npm install

cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
```

`composer.lock` and `package-lock.json` are committed. Use `composer install`
and `npm ci` to reproduce the locked dependency sets.

### Web server

The Apache document root (or virtual host `DocumentRoot`) **must point at the
`public/` directory**, not at the project root:

```
DocumentRoot "C:/wamp64/www/ecommerce-tracking-testbed/public"
```

A `public/.htaccess` is included; `mod_rewrite` must be enabled.

### Database

Create an empty MySQL database in phpMyAdmin, then configure `.env` yourself:

```
APP_URL=http://ecommerce-tracking.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<your database>
DB_USERNAME=<your username>
DB_PASSWORD=<your password>
```

Then run the migrations and the synthetic product seeder:

```bash
php artisan migrate
php artisan db:seed
```

`php artisan migrate:fresh --seed` resets the testbed to a clean state — useful
between experiment batches.

#### If migration fails with "Specified key was too long; max key length is 1000 bytes"

Your MySQL install is creating tables as **MyISAM**, whose index limit is 1000 bytes
(utf8mb4 `varchar(255)` needs 1020). `config/database.php` sets
`'engine' => env('DB_ENGINE', 'InnoDB')` so Laravel emits `ENGINE=InnoDB` explicitly;
`.env.example` also ships `DB_ENGINE=InnoDB`. Make sure your `.env` has it, then drop the
partially created tables and re-run:

```bash
php artisan migrate:fresh
php artisan db:seed
```

### Frontend

```bash
npm run dev      # Vite dev server (hot reload)
npm run build    # production build; required before browsing without `npm run dev`
```

### Tests

```bash
php artisan test
```

The suite runs against an **in-memory SQLite database** (configured in
`phpunit.xml`). This never touches the MySQL database configured in `.env`. If
`pdo_sqlite` is not enabled in your CLI `php.ini`, enable it, or point the test
environment at a separate MySQL schema instead.

---

### Playwright baseline runner (Milestone 5)

The locked baseline uses Playwright **1.63.0** and Chromium **153.0.8010.12**.
Install the exact Node dependency and its Chromium binary on each research
machine:

```bash
npm ci
npx playwright install chromium
```

With the local WAMP site and seeded catalogue available, execute one fresh,
non-persistent Chromium context per run:

```bash
npm run experiment:baseline -- --tracking-mode=client_only --observation-ms=3000
npm run experiment:baseline -- --tracking-mode=server_augmented --observation-ms=3000
```

`--headed` displays Chromium and `--product-slug=testbed-mechanical-keyboard`
selects another deterministic seeded product. `EXPERIMENT_BASE_URL` overrides
the default `http://ecommerce-tracking.test`. Server-augmented runs do not
start workers; run one separately if dispatch delivery is being studied:

```bash
php artisan queue:work --queue=tracking,default
```

Each run stores `trace.zip`, `browser-observations.json`, and `run-summary.json`
under `storage/app/research/playwright/<run-id>/`; a failed run also writes
`failure.png`. These artifacts are git-ignored. Inspect a trace with
`npx playwright show-trace storage/app/research/playwright/<run-id>/trace.zip`.
Traces and artifacts can contain detailed browser and network evidence, so
review them before external publication.

### Final GA4 controlled-browser conditions

The final thesis experiment uses **GA4 only**, Chromium only, and four
conditions: `client_only` or `server_augmented`, each with `none` or
`controlled` GA4 browser blocking. Meta, consent variants, privacy profiles,
multiple browsers, and real blocker extensions are outside this scope.

Both `none` and `controlled` install the same Playwright BrowserContext routing
infrastructure and set `serviceWorkers: 'block'`. Routing disables HTTP cache,
so keeping it in both conditions prevents cache/routing from becoming a
confound. `none` continues every request; `controlled` aborts only requests
the shared GA4 classifier identifies as loader scripts or event transports.
This is controlled browser-side GA4 request blocking, not an emulation of a
specific ad blocker. The earlier passive baseline is engineering-validation
evidence, not final comparative data.

Use the calibrated 10-second pilot window:

```bash
npm run experiment:baseline -- --tracking-mode=client_only --blocking-mode=none --observation-ms=10000
npm run experiment:baseline -- --tracking-mode=client_only --blocking-mode=controlled --observation-ms=10000
npm run experiment:baseline -- --tracking-mode=server_augmented --blocking-mode=controlled --observation-ms=10000
```

`blocked_by_client` is explicit experiment-policy evidence, distinct from a
normal browser failure. Application-side GA4 dispatch invocation does not mean
the Google library loaded or processed an event, and browser transport evidence
does not mean GA4 reporting observed it.

### Final collection and export

The final dataset is ten repetitions per condition (40 sequential runs, 160
expected ground-truth events). It uses the deterministic counterbalanced round
order `A B C D`, `B C D A`, `C D A B`, `D A B C`, repeated through ten rounds.
Inspect the plan without creating runs or sending requests:

```bash
npm run experiment:collect -- --batch-id=final-YYYY-MM-DD --dry-run
```

After committing the tooling and ensuring a clean working tree, run with
`--execute`. The manifest freezes settings and revision, atomically records
each slot, runs the one-shot tracking queue worker after B/D, and stops on an
invalid result. Resume only planned slots on the same revision, then export:

```bash
npm run experiment:collect -- --resume=final-YYYY-MM-DD --execute
php artisan research:export-experiment final-YYYY-MM-DD
```

Exports at `storage/app/research/collections/<batch>/export/` include runs,
events, a dataset, validation report, and evidence-layer summary. GA4 endpoint
acceptance is never described as GA4 reporting or platform receipt.

## Current ecommerce flow

| Step | Route | Ground-truth event |
| --- | --- | --- |
| Product listing | `GET /` | — |
| Product detail | `GET /products/{slug}` | `view_item` |
| Add to cart | `POST /cart` | `add_to_cart` |
| Cart | `GET /cart` | — |
| Checkout | `GET /checkout` | `begin_checkout` |
| Submit synthetic order | `POST /checkout` | `purchase` |
| Order confirmation | `GET /orders/{order_number}` | — (read-only) |
| Research debug | `GET /research/debug` | — |

There is no authentication, no payment provider, no shipping, no tax and no
stock handling. Submitting checkout simply creates a valid completed synthetic
order.

The cart lives in the Laravel session and stores only product ids and
quantities; prices are always re-read from the database.

**Money is never stored or calculated as a floating-point value.** All monetary
columns are integer minor units (`*_minor`, i.e. cents), and `App\Support\Money`
converts to a decimal string only at the presentation boundary. The default test
currency is **EUR** (`config/testbed.php`, overridable with `TESTBED_CURRENCY`).

### Purchase idempotency

Order creation is wrapped in a database transaction that writes the order, its
items and the `purchase` event together. The confirmation page is a plain `GET`
reached by a post/redirect/get; a database unique constraint on
`(event_name, order_id)` ensures a logical order cannot acquire two purchase
ground-truth events. Refreshing confirmation therefore creates neither a new
order nor a new purchase event.

---

## `GroundTruthEvent`

`ground_truth_events` is the backend record of what really happened, written
server-side by `App\Services\GroundTruthRecorder`:

| Column | Purpose |
| --- | --- |
| `event_id` | UUID, unique, assigned automatically |
| `experiment_run_id` | nullable link to an `ExperimentRun` |
| `event_name` | `view_item`, `add_to_cart`, `begin_checkout`, `purchase` |
| `product_id` / `order_id` | nullable links to the subject of the event |
| `quantity`, `value_minor`, `currency` | vendor-neutral measurement values |
| `occurred_at` | when the action happened server-side |
| `payload` | JSON snapshot (item lines, slugs, unit prices) |

This table is deliberately **vendor-neutral**: no GA4 or Meta specific fields
belong here. When measurement systems are added, their payloads and delivery
metadata get their own tables and are joined back to `event_id`.

`ExperimentRun` describes one controlled measurement run (tracking mode,
blocking mode, privacy mode, consent mode, browser, timings, metadata). Its
lifecycle is `pending → running → completed`; a running run may alternatively
end as `failed` or `cancelled`. Terminal runs cannot restart. Starting records
`started_at`; terminal transitions record `finished_at`.

A running run may be bound to one Laravel browser session through
`App\Services\ExperimentRunContext`. `GroundTruthRecorder` reads that context,
so `view_item`, `add_to_cart`, `begin_checkout`, and `purchase` automatically
receive the active run's database id. Browsing without a bound run remains
valid and stores a null `experiment_run_id`.

### Research debug page

`GET /research/debug` is a local/testing-only scientific control surface. It
can create, start, and bind a run; show or clear the current session's run;
finish the active run; list recent runs with event counts; and scope the event
list to a run. It is intentionally not an authentication-protected production
admin interface and returns 404 outside local/testing environments.

For a manual controlled flow, open `/research/debug`, set the desired
conditions, select **Create, start, and bind run**, then perform the normal
storefront journey in that same browser session. Return to the page to inspect
the attributed events and select **Finish active run**. Completion keeps the
run and its records but unbinds the session.

## Client-side tracking baseline

Set the optional public identifiers in local `.env`; leave either blank to
disable that provider independently. Never commit real IDs, API secrets, or
Meta access tokens.

```dotenv
GA4_MEASUREMENT_ID=G-...
META_PIXEL_ID=...
```

The Milestone 3 baseline dispatches only when a browser session has a running
experiment run with `tracking_mode` of `client_only` or `server_augmented` and
`consent_mode` of `full`. This is a temporary full-consent-only research policy,
not consent management. `partial` and `none` dispatch nothing while Laravel
continues to record ground truth.

Each `GroundTruthEvent.event_id` is preserved in a provider-neutral client
payload. GA4 receives it as `testbed_event_id` (with `testbed_run_id` and
`tracking_channel=client`); Meta receives it as Pixel `eventID`. GA4 purchases
use the synthetic order number as `transaction_id`, never the event UUID.

| Laravel event | GA4 event | Meta Pixel event |
| --- | --- | --- |
| `view_item` | `view_item` | `ViewContent` |
| `add_to_cart` | `add_to_cart` | `AddToCart` |
| `begin_checkout` | `begin_checkout` | `InitiateCheckout` |
| `purchase` | `purchase` | `Purchase` |

`view_item` and `begin_checkout` are rendered on the response that created
their ground truth. `add_to_cart` and `purchase` use a dedicated session queue
of event UUIDs; the next response pulls and removes them, so refresh cannot
dispatch them again. Removal means Laravel rendered one browser dispatch
attempt, not that GA4 or Meta received it.

No `page_view` or `PageView` is sent. GA4 is configured with
`send_page_view: false`; also disable history-based page changes in the GA4 web
stream's Enhanced Measurement settings. No Meta `PageView` call is made.

Run `npm run dev` or `npm run build` after configuring identifiers. The local
`/research/debug` page shows provider configuration and run eligibility.

---

## Roadmap

1. Blocking, privacy, and consent scenarios
2. Experimental dataset generation and analysis

## Experiment 2: privacy and consent supplementary experiment

Experiment 1 is complete and frozen: its GA4-only controlled-browser blocking comparison used A/B/C/D and 40 completed runs. Experiment 2 is a separate planned 40-run client-only supplement: E standard/full, F JavaScript-disabled/full, G standard/partial, and H standard/none. It uses Chromium headless, routing, blocked service workers, and a 10-second observation window, with ten counterbalanced repetitions per condition.

The synthetic consent states use Google Consent Mode v2-style profiles with basic consent semantics: full grants all four values; partial grants only `analytics_storage`; none denies all four. There is no CMP or banner UX study. Partial consent can initialize GA4 and its 0–4 correlated browser transports are an empirical result, not a validity criterion. `javascript_disabled` is one strong restrictive browser setting, not a generalization to all browsers or privacy technologies.

```bash
npm run experiment:collect-privacy-consent -- --batch-id=expanded-test --dry-run
# After the apparatus is committed and the working tree is clean:
npm run experiment:collect-privacy-consent -- --batch-id=privacy-consent-YYYY-MM-DD --execute
npm run experiment:collect-privacy-consent -- --resume=privacy-consent-YYYY-MM-DD --execute
php artisan research:export-privacy-consent privacy-consent-YYYY-MM-DD
```

## Server-augmented tracking

For `server_augmented` and full-consent runs, Laravel creates durable GA4 MP and
Meta CAPI dispatch records and queues delivery on `tracking`. Calls never delay
or roll back ecommerce ground truth. Configure `GA4_SERVER_MEASUREMENT_ID` and
`GA4_SERVER_API_SECRET` for a **separate** GA4 web stream from
`GA4_MEASUREMENT_ID`; configure `META_CAPI_ACCESS_TOKEN` with the same
`META_PIXEL_ID` used by the browser Pixel. The configurable Meta default is
Graph API `v26.0`.

```bash
php artisan queue:work --queue=tracking,default
php artisan tracking:validate-ga4-server <ground-truth-event-uuid>
```

The validation command uses Google’s debug endpoint and does not change real
dispatch evidence. Server events retain the original UUID and occurrence time;
`endpoint_accepted` is endpoint evidence, never platform observation.

---

## Project layout

```
app/
  Enums/                           canonical events, order status, run lifecycle
  Enums/OrderStatus.php
  Http/Controllers/               thin controllers
  Http/Requests/AddToCartRequest.php
  Models/                          Product, Order, OrderItem,
                                   ExperimentRun, GroundTruthEvent
  Services/Cart.php                session cart
  Services/OrderCreator.php        transactional order + purchase event
  Services/ExperimentRunContext.php session-to-run binding
  Services/ExperimentRunManager.php controlled run creation/transitions
  Services/GroundTruthRecorder.php backend source of truth + run attribution
  Tracking/                         client payload, eligibility, queue, mappers
  Support/Money.php                integer minor-unit helpers
  Support/CartLine.php
config/testbed.php                 currency + canonical event names
config/tracking.php                public GA4/Meta client configuration
config/database.php                published only to force ENGINE=InnoDB
database/migrations/               schema
database/seeders/ProductSeeder.php 4 synthetic products
resources/views/                   Blade storefront + research debug page
tests/                             PHPUnit feature + unit tests
```

Only `config/testbed.php` and `config/database.php` are published; the rest of
Laravel's configuration uses the framework defaults. Publish any file you need to
customise with `php artisan config:publish <name>`.

`config/database.php` is published solely to force `ENGINE=InnoDB` on the `mysql` and
`mariadb` connections — see the migration troubleshooting note above.
