import { classifyRequest } from './provider-classifier.js';

/**
 * Fixed routing infrastructure for both final browser conditions.
 * Playwright routing disables HTTP cache, so the control condition routes and
 * continues every request while the controlled condition aborts GA4 only.
 */
export class ControlledNetworkPolicy {
    constructor(blockingMode) {
        if (!['none', 'controlled'].includes(blockingMode)) {
            throw new Error('blocking mode must be none or controlled');
        }
        this.blockingMode = blockingMode;
        this.blockedRequests = new WeakSet();
    }

    async install(context) {
        await context.route('**/*', (route) => this.handle(route));
    }

    async handle(route) {
        const request = route.request();
        const classification = classifyRequest(request.url());
        if (this.blockingMode === 'controlled' && classification?.provider === 'ga4') {
            this.blockedRequests.add(request);
            await route.abort('blockedbyclient');
            return 'block';
        }

        await route.continue();
        return 'continue';
    }

    wasIntentionallyBlocked(request) {
        return this.blockedRequests.has(request);
    }
}
