import { EventEmitter } from 'node:events';
import test from 'node:test';
import assert from 'node:assert/strict';
import { NetworkObserver } from './network-observer.js';

const eventId = '11111111-1111-4111-8111-111111111111';

class FakeRequest {
    constructor(url = `https://www.google-analytics.com/g/collect?testbed_event_id=${eventId}`) {
        this.urlValue = url;
    }

    url() { return this.urlValue; }
    method() { return 'POST'; }
    postData() { return null; }
    failure() { return { errorText: 'net::ERR_ABORTED' }; }
}

function observeLifecycle(callback) {
    const context = new EventEmitter();
    const observer = new NetworkObserver(context, [eventId]);
    const request = new FakeRequest();
    context.emit('request', request);
    callback(context, request);
    return observer.observations()[0];
}

test('preserves a received 204 followed by requestfailed as response_received_aborted', () => {
    const observation = observeLifecycle((context, request) => {
        context.emit('response', { request: () => request, status: () => 204 });
        context.emit('requestfailed', request);
    });

    assert.equal(observation.outcome, 'response_received_aborted');
    assert.equal(observation.response_status, 204);
    assert.equal(observation.failure_text, 'net::ERR_ABORTED');
    assert.equal(observation.ground_truth_event_id, eventId);
});

test('records requestfailed without a response as a true failure', () => {
    const observation = observeLifecycle((context, request) => context.emit('requestfailed', request));

    assert.equal(observation.outcome, 'failed');
    assert.equal(observation.response_status, null);
});

test('records a normal completed request as finished', () => {
    const observation = observeLifecycle((context, request) => {
        context.emit('response', { request: () => request, status: () => 204 });
        context.emit('requestfinished', request);
    });

    assert.equal(observation.outcome, 'finished');
    assert.equal(observation.response_status, 204);
    assert.equal(observation.failure_text, null);
});

test('marks a policy-known request as blocked_by_client instead of a network failure', () => {
    const context = new EventEmitter();
    let blockedRequest;
    const policy = { wasIntentionallyBlocked: (request) => request === blockedRequest };
    const observer = new NetworkObserver(context, [eventId], policy);
    const request = new FakeRequest('https://www.googletagmanager.com/gtag/js?id=G-X');
    blockedRequest = request;
    context.emit('request', request);
    context.emit('requestfailed', request);

    const observation = observer.observations()[0];
    assert.equal(observation.outcome, 'blocked_by_client');
    assert.deepEqual(observation.metadata, { blocking_mode: 'controlled', policy_decision: 'block' });
});
