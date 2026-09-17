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

Milestone 1 (this version) contains only the clean foundation: the storefront,
the ground-truth event recorder, and the experiment schema. No GA4, no Meta, no
blocking, no consent and no browser automation yet.

---

## Requirements

- PHP 8.2+ (developed against PHP 8.4 under WampServer)
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

There is no `composer.lock` in the first commit, so `composer install` resolves
the latest compatible dependency versions and writes the lock file. Commit the
generated `composer.lock` and `package-lock.json` so the environment is
reproducible from that point on.

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
reached by a post/redirect/get, and the recorder additionally uses
`firstOrCreate` keyed on `(event_name, order_id)`. Refreshing the confirmation
page therefore cannot produce a second order or a second purchase event.

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
blocking mode, privacy mode, consent mode, browser, timings, metadata). The
schema exists now; **the experiment runner is not implemented yet** —
`GroundTruthRecorder::currentExperimentRunId()` is the seam where it will plug
in, and every event is currently recorded with a null run.

### Research debug page

`GET /research/debug` lists the most recent experiment runs and ground-truth
events (event id, run, event name, product/order, value, timestamp). It is an
inspection tool, not a UI.

---

## Roadmap

1. GA4 client-side tracking
2. Meta Pixel
3. GA4 server-side tracking (Measurement Protocol)
4. Meta Conversions API
5. Playwright automation of synthetic sessions
6. Blocking / privacy scenarios
7. Consent scenarios
8. Experimental dataset generation and analysis

---

## Project layout

```
app/
  Enums/GroundTruthEventName.php   canonical, vendor-neutral event names
  Enums/OrderStatus.php
  Http/Controllers/               thin controllers
  Http/Requests/AddToCartRequest.php
  Models/                          Product, Order, OrderItem,
                                   ExperimentRun, GroundTruthEvent
  Services/Cart.php                session cart
  Services/OrderCreator.php        transactional order + purchase event
  Services/GroundTruthRecorder.php backend source of truth
  Support/Money.php                integer minor-unit helpers
  Support/CartLine.php
config/testbed.php                 currency + canonical event names
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
