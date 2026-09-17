import test from 'node:test';
import assert from 'node:assert/strict';
import { classifyRequest, findKnownUuids, requestFingerprint } from './provider-classifier.js';

const first = '11111111-1111-4111-8111-111111111111';
const second = '22222222-2222-4222-8222-222222222222';

test('classifies GA4 and Meta loaders separately from event transports', () => {
    assert.deepEqual(classifyRequest('https://www.googletagmanager.com/gtag/js?id=G-X'), { provider: 'ga4', resourceKind: 'script' });
    assert.deepEqual(classifyRequest('https://connect.facebook.net/en_US/fbevents.js'), { provider: 'meta', resourceKind: 'script' });
    assert.deepEqual(classifyRequest('https://www.google-analytics.com/g/collect?v=2'), { provider: 'ga4', resourceKind: 'event_transport' });
    assert.deepEqual(classifyRequest('https://www.facebook.com/tr/?id=1'), { provider: 'meta', resourceKind: 'event_transport' });
    assert.equal(classifyRequest('https://example.test/asset.js'), null);
});

test('correlation finds exact UUIDs in URL or POST body and preserves multiple matches', () => {
    assert.deepEqual(findKnownUuids(`https://x.test/?id=${first}`, null, [first, second]), [first]);
    assert.deepEqual(findKnownUuids('https://x.test/', `event_id=${second}`, [first, second]), [second]);
    assert.deepEqual(findKnownUuids(`https://x.test/?a=${first}&b=${second}`, null, [first, second]), [first, second]);
    assert.deepEqual(findKnownUuids('https://x.test/', null, [first, second]), []);
});

test('request fingerprints are stable and change with request evidence', () => {
    const original = requestFingerprint('POST', 'https://x.test/collect?a=1', 'payload');
    assert.equal(original, requestFingerprint('POST', 'https://x.test/collect?a=1', 'payload'));
    assert.notEqual(original, requestFingerprint('POST', 'https://x.test/collect?a=2', 'payload'));
});
