import { createHash } from 'node:crypto';

const rules = {
    ga4: {
        loaderHosts: ['www.googletagmanager.com'],
        loaderPaths: ['/gtag/js'],
        eventHosts: ['www.google-analytics.com', 'region1.google-analytics.com'],
        eventPaths: ['/g/collect', '/collect', '/mp/collect'],
    },
    meta: {
        loaderHosts: ['connect.facebook.net'],
        loaderPaths: ['/en_US/fbevents.js'],
        eventHosts: ['www.facebook.com', 'facebook.com'],
        eventPaths: ['/tr'],
    },
};

export function classifyRequest(url) {
    const parsed = new URL(url);
    for (const [provider, rule] of Object.entries(rules)) {
        if (rule.loaderHosts.includes(parsed.hostname) && rule.loaderPaths.some((path) => parsed.pathname.startsWith(path))) {
            return { provider, resourceKind: 'script' };
        }
        if (rule.eventHosts.includes(parsed.hostname) && rule.eventPaths.some((path) => parsed.pathname.startsWith(path))) {
            return { provider, resourceKind: 'event_transport' };
        }
    }
    return null;
}

export function findKnownUuids(requestUrl, postData, knownUuids) {
    const haystack = `${requestUrl}\n${decodeURIComponent(requestUrl)}\n${postData ?? ''}`.toLowerCase();
    return [...new Set(knownUuids.filter((id) => haystack.includes(id.toLowerCase())))];
}

export function requestFingerprint(method, url, postData) {
    return createHash('sha256').update(`${method}\n${url}\n${postData ?? ''}`).digest('hex');
}
