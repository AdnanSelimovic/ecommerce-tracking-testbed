import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { LaravelControl } from './laravel-control.js';
import { NetworkObserver } from './network-observer.js';
import { prepareArtifacts, writeJson } from './artifacts.js';
import { runScenario } from './scenario.js';

const require = createRequire(import.meta.url);
const playwrightVersion = require('@playwright/test/package.json').version;
const options = parseArgs(process.argv.slice(2));
const baseUrl = (process.env.EXPERIMENT_BASE_URL ?? 'http://ecommerce-tracking.test').replace(/\/$/, '');
let browser;
let context;
let control;
let run;
let artifactDirectory;
let observer;
let observationsIngested = false;
const jsObservations = [];
const knownUuids = [];

try {
    browser = await chromium.launch({ headless: !options.headed });
    const browserVersion = browser.version();
    context = await browser.newContext(); // A new, non-persistent context is one ExperimentRun.
    await context.exposeBinding('__testbedCaptureClientTracking', (_source, detail) => {
        jsObservations.push({
            provider: detail.provider, layer: 'js_invocation', resource_kind: detail.resource_kind ?? 'event_transport',
            ground_truth_event_id: detail.ground_truth_event_id, canonical_event_name: detail.canonical_event_name,
            provider_event_name: detail.provider_event_name, outcome: detail.outcome ?? 'issued', observed_at: detail.observed_at,
            metadata: { page_url: detail.page_url },
        });
    });
    await context.addInitScript(() => {
        window.addEventListener('testbed:client-tracking-dispatch', (event) => {
            window.__testbedCaptureClientTracking({ ...event.detail, page_url: window.location.href });
        });
        window.addEventListener('testbed:client-tracking-loader', (event) => {
            window.__testbedCaptureClientTracking({
                ...event.detail, page_url: window.location.href, resource_kind: 'script',
            });
        });
    });
    control = new LaravelControl(context.request, baseUrl);
    await control.bootstrap();
    run = await control.createRun({
        tracking_mode: options.trackingMode,
        blocking_mode: 'none', privacy_mode: 'standard', consent_mode: 'full',
        browser: 'chromium', browser_version: browserVersion,
        metadata: metadata(browserVersion, options),
    });
    artifactDirectory = await prepareArtifacts(run.run_id);
    await context.tracing.start({ screenshots: true, snapshots: true });
    observer = new NetworkObserver(context, knownUuids);
    const page = await context.newPage();
    await runScenario(page, control, run.run_id, options.productSlug, options.observationMs);
    const events = await control.events(run.run_id);
    knownUuids.push(...events.map((event) => event.event_id));
    const observations = collectedObservations();
    await control.ingest(run.run_id, observations);
    observationsIngested = true;
    await writeJson(artifactDirectory, 'browser-observations.json', observations);
    run = await control.complete(run.run_id);
    const summary = summarize(run, browserVersion, events, observations, artifactDirectory, options);
    await writeJson(artifactDirectory, 'run-summary.json', summary);
    console.log(JSON.stringify(summary, null, 2));
} catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    const observations = collectedObservations();
    if (artifactDirectory) {
        await writeJson(artifactDirectory, 'browser-observations.json', observations);
        if (context) {
            const page = context.pages()[0];
            if (page) await page.screenshot({ path: join(artifactDirectory, 'failure.png'), fullPage: true }).catch(() => {});
        }
    }
    if (control && run) {
        if (!observationsIngested) await control.ingest(run.run_id, observations).catch(() => {});
        await control.fail(run.run_id, message).catch(() => {});
    }
    console.error(`Experiment failed: ${message}`);
    process.exitCode = 1;
} finally {
    if (context && artifactDirectory) await context.tracing.stop({ path: join(artifactDirectory, 'trace.zip') }).catch(() => {});
    await context?.close().catch(() => {});
    await browser?.close().catch(() => {});
}

function collectedObservations() {
    return [...jsObservations, ...(observer?.observations() ?? [])];
}

function parseArgs(args) {
    const value = (prefix, fallback) => args.find((arg) => arg.startsWith(prefix))?.slice(prefix.length) ?? fallback;
    const trackingMode = value('--tracking-mode=', 'client_only');
    if (!['client_only', 'server_augmented'].includes(trackingMode)) throw new Error('tracking mode must be client_only or server_augmented');
    const observationMs = Number(value('--observation-ms=', '3000'));
    if (!Number.isInteger(observationMs) || observationMs < 0 || observationMs > 60000) throw new Error('observation-ms must be an integer from 0 to 60000');
    return { trackingMode, observationMs, headed: args.includes('--headed'), productSlug: value('--product-slug=', 'testbed-wireless-headphones') };
}

function metadata(browserVersion, options) {
    return {
        playwright_version: playwrightVersion, node_version: process.version, platform: process.platform,
        architecture: process.arch, headed: options.headed, observation_window_ms: options.observationMs,
        product_slug: options.productSlug, git_commit: git('rev-parse', 'HEAD'), git_dirty: git('status', '--porcelain') !== '',
        base_url: baseUrl, runner_version: '1.0.0', chromium_version: browserVersion,
    };
}

function git(...args) {
    try { return execFileSync('git', args, { encoding: 'utf8' }).trim(); } catch { return 'unavailable'; }
}

function summarize(run, browserVersion, events, observations, artifactDirectory, options) {
    const count = (provider, layer, kind) => observations.filter((item) => item.provider === provider && item.layer === layer && item.resource_kind === kind).length;
    return {
        run_id: run.run_id, status: run.status, browser: `chromium ${browserVersion}`, tracking_mode: options.trackingMode,
        ground_truth_event_count: events.length, ga4_js_invocation_count: count('ga4', 'js_invocation', 'event_transport'),
        meta_js_invocation_count: count('meta', 'js_invocation', 'event_transport'),
        ga4_network_event_request_count: count('ga4', 'network', 'event_transport'),
        meta_network_event_request_count: count('meta', 'network', 'event_transport'),
        unmatched_tracker_request_count: observations.filter((item) => item.layer === 'network' && item.resource_kind === 'event_transport' && !item.ground_truth_event_id).length,
        artifact_directory: artifactDirectory,
    };
}
