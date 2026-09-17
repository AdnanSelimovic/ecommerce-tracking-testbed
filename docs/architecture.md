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
