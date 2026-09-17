import test from 'node:test';
import assert from 'node:assert/strict';
import { LaravelControl } from './laravel-control.js';

function response(status, body = {}) {
    return { status: () => status, ok: () => status >= 200 && status < 300, json: async () => body, text: async () => JSON.stringify(body) };
}

test('observation ingestion explicitly expects Laravel 201 Created, including an empty list', async () => {
    const calls = [];
    const request = {
        get: async () => response(200, { csrf_token: 'csrf' }),
        post: async (url, options) => {
            calls.push({ url, options });
            return response(201, { inserted: 0 });
        },
    };
    const control = new LaravelControl(request, 'http://testbed.local');
    await control.bootstrap();

    await assert.doesNotReject(() => control.ingest('run-uuid', []));
    assert.deepEqual(calls[0].options.data, { observations: [] });
});
