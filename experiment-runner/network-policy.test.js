import test from 'node:test';
import assert from 'node:assert/strict';
import { ControlledNetworkPolicy } from './network-policy.js';

function routeFor(url) {
    const calls = [];
    const request = { url: () => url };
    return {
        request,
        calls,
        route: { request: () => request, continue: async () => calls.push('continue'), abort: async (reason) => calls.push(`abort:${reason}`) },
    };
}

test('none continues GA4 loader, GA4 transport, and unrelated requests', async () => {
    const policy = new ControlledNetworkPolicy('none');
    for (const url of ['https://www.googletagmanager.com/gtag/js?id=G-X', 'https://www.google-analytics.com/g/collect', 'https://example.test/app.js']) {
        const item = routeFor(url);
        assert.equal(await policy.handle(item.route), 'continue');
        assert.deepEqual(item.calls, ['continue']);
    }
});

test('controlled blocks only shared-classifier GA4 loader and transport with blockedbyclient', async () => {
    const policy = new ControlledNetworkPolicy('controlled');
    for (const url of ['https://www.googletagmanager.com/gtag/js?id=G-X', 'https://www.google-analytics.com/g/collect']) {
        const item = routeFor(url);
        assert.equal(await policy.handle(item.route), 'block');
        assert.deepEqual(item.calls, ['abort:blockedbyclient']);
        assert.equal(policy.wasIntentionallyBlocked(item.request), true);
    }
    const unrelated = routeFor('https://example.test/app.js');
    assert.equal(await policy.handle(unrelated.route), 'continue');
    assert.deepEqual(unrelated.calls, ['continue']);
});
