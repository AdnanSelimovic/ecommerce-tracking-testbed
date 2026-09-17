export class LaravelControl {
    constructor(request, baseUrl) {
        this.request = request;
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.csrfToken = null;
    }

    async bootstrap() {
        const response = await this.request.get(`${this.baseUrl}/research/automation/bootstrap`);
        await this.assertOk(response, 'bootstrap');
        const body = await response.json();
        this.csrfToken = body.csrf_token;
        return body;
    }

    async createRun(attributes) { return this.post('/research/automation/runs', attributes, 201).then((body) => body.run); }
    async getRun(runId) { return this.get(`/research/automation/runs/${runId}`).then((body) => body.run); }
    async events(runId) { return this.get(`/research/automation/runs/${runId}/events`).then((body) => body.events); }
    async ingest(runId, observations) { return this.post(`/research/automation/runs/${runId}/observations`, { observations }); }
    async complete(runId) { return this.post(`/research/automation/runs/${runId}/complete`, {}).then((body) => body.run); }
    async fail(runId, reason) { return this.post(`/research/automation/runs/${runId}/fail`, { reason }).then((body) => body.run); }

    async get(path) {
        const response = await this.request.get(`${this.baseUrl}${path}`, { headers: { Accept: 'application/json' } });
        await this.assertOk(response, path);
        return response.json();
    }

    async post(path, data, expected = 200) {
        const response = await this.request.post(`${this.baseUrl}${path}`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken }, data,
        });
        if (response.status() !== expected) {
            throw new Error(`Laravel control ${path} returned ${response.status()}: ${await response.text()}`);
        }
        return response.json();
    }

    async assertOk(response, name) {
        if (!response.ok()) throw new Error(`Laravel control ${name} returned ${response.status()}: ${await response.text()}`);
    }
}

export async function waitForGroundTruth(control, runId, eventName, timeoutMs = 10000) {
    const deadline = Date.now() + timeoutMs;
    do {
        const events = await control.events(runId);
        if (events.some((event) => event.event_name === eventName)) return events;
        await new Promise((resolve) => setTimeout(resolve, 200));
    } while (Date.now() < deadline);
    throw new Error(`Ground-truth event ${eventName} was not recorded within ${timeoutMs}ms.`);
}
