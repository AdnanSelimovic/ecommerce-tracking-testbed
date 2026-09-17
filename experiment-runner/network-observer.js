import { classifyRequest, findKnownUuids, requestFingerprint } from './provider-classifier.js';

export class NetworkObserver {
    constructor(context, knownUuids) {
        this.knownUuids = knownUuids;
        this.requests = new Map();
        this.records = [];
        context.on('request', (request) => this.request(request));
        context.on('response', (response) => this.response(response));
        context.on('requestfinished', (request) => this.finished(request));
        context.on('requestfailed', (request) => this.failed(request));
    }

    request(request) {
        const classified = classifyRequest(request.url());
        if (!classified) return;
        const url = new URL(request.url());
        this.requests.set(request, { classified, request, observedAt: new Date(), responseStatus: null, url });
    }

    response(response) {
        const item = this.requests.get(response.request());
        if (item) item.responseStatus = response.status();
    }

    finished(request) { this.record(request, 'finished'); }
    failed(request) { this.record(request, 'failed', request.failure()?.errorText ?? 'Browser transport failure'); }

    record(request, outcome, failureText = null) {
        const item = this.requests.get(request);
        if (!item) return;
        this.requests.delete(request);
        const finishedAt = new Date();
        const postData = request.postData();
        const base = {
            provider: item.classified.provider, layer: 'network', resource_kind: item.classified.resourceKind,
            outcome, request_method: request.method(), request_host: item.url.hostname,
            request_path: item.url.pathname, request_fingerprint: requestFingerprint(request.method(), request.url(), postData),
            observed_at: item.observedAt.toISOString(), finished_at: finishedAt.toISOString(),
            duration_ms: finishedAt - item.observedAt, response_status: item.responseStatus,
            failure_text: failureText,
            metadata: null,
        };
        this.records.push({ base, rawUrl: request.url(), rawPostData: postData });
    }

    observations() {
        return this.records.map(({ base, rawUrl, rawPostData }) => {
            const matches = base.resource_kind === 'event_transport'
                ? findKnownUuids(rawUrl, rawPostData, this.knownUuids) : [];
            if (matches.length === 1) return { ...base, ground_truth_event_id: matches[0], correlation_method: 'embedded_event_uuid' };
            return { ...base, metadata: matches.length > 1 ? { matching_event_uuids: matches } : null };
        });
    }
}
