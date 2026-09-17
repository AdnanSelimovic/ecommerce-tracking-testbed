function initialiseGa4({ measurement_id: measurementId }) {
    if (!measurementId || window.__testbedGa4Initialised) {
        return;
    }

    window.__testbedGa4Initialised = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag() { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    // The testbed controls only canonical ecommerce events: never page_view.
    window.gtag('config', measurementId, { send_page_view: false });

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    observeProviderLoader('ga4', script);
    document.head.appendChild(script);
}

function initialiseMeta({ pixel_id: pixelId }) {
    if (!pixelId || window.__testbedMetaInitialised) {
        return;
    }

    window.__testbedMetaInitialised = true;
    const existingFbq = window.fbq;
    window.fbq = existingFbq || function fbq() {
        window.fbq.callMethod ? window.fbq.callMethod.apply(window.fbq, arguments) : window.fbq.queue.push(arguments);
    };
    if (!window.fbq.queue) {
        window.fbq.queue = [];
    }
    window.fbq.push = window.fbq;
    window.fbq.loaded = true;
    window.fbq.version = '2.0';
    window.fbq('init', pixelId);

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://connect.facebook.net/en_US/fbevents.js';
    observeProviderLoader('meta', script);
    document.head.appendChild(script);
}

function observeProviderLoader(provider, script) {
    script.addEventListener('load', () => {
        window.dispatchEvent(new CustomEvent('testbed:client-tracking-loader', {
            detail: { provider, outcome: 'finished', observed_at: new Date().toISOString() },
        }));
    });
    script.addEventListener('error', () => {
        window.dispatchEvent(new CustomEvent('testbed:client-tracking-loader', {
            detail: { provider, outcome: 'failed', observed_at: new Date().toISOString() },
        }));
    });
}

function dispatchClientTrackingEvent(event, config) {
    if (config.ga4.enabled && config.ga4.measurement_id) {
        initialiseGa4(config.ga4);
        window.dispatchEvent(new CustomEvent('testbed:client-tracking-dispatch', {
            detail: { provider: 'ga4', ground_truth_event_id: event.ground_truth_event_id, canonical_event_name: event.event_name, provider_event_name: event.ga4.name, observed_at: new Date().toISOString() },
        }));
        window.gtag('event', event.ga4.name, event.ga4.params);
    }

    if (config.meta.enabled && config.meta.pixel_id) {
        initialiseMeta(config.meta);
        window.dispatchEvent(new CustomEvent('testbed:client-tracking-dispatch', {
            detail: { provider: 'meta', ground_truth_event_id: event.ground_truth_event_id, canonical_event_name: event.event_name, provider_event_name: event.meta.name, observed_at: new Date().toISOString() },
        }));
        window.fbq('track', event.meta.name, event.meta.params, event.meta.options);
    }
}

const bootstrap = window.testbedClientTracking;

if (bootstrap?.events?.length) {
    bootstrap.events.forEach((event) => dispatchClientTrackingEvent(event, bootstrap.config));
}
