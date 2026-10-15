(function (global) {
    'use strict';

    if (global.DashDWidgetRequests) return;

    const cache = new Map();
    const inFlight = new Map();
    const blockedUntil = new Map();
    const cacheMs = 30000;
    const maxEntries = 80;

    const clone = (value) => JSON.parse(JSON.stringify(value));
    const endpoint = (url) => new URL(url, global.location.href).searchParams.get('action') || 'public';

    async function get(url) {
        const now = Date.now();
        const cached = cache.get(url);
        if (cached && cached.expires > now) return clone(cached.value);
        if (cached) cache.delete(url);

        const bucket = endpoint(url);
        if ((blockedUntil.get(bucket) || 0) > now) {
            const error = new Error('Too many requests. Please try again later.');
            error.status = 429;
            error.retryAfter = Math.ceil((blockedUntil.get(bucket) - now) / 1000);
            throw error;
        }

        if (!inFlight.has(url)) {
            const pending = (async () => {
                const response = await global.fetch(url, { credentials: 'same-origin' });
                if (!response.ok) {
                    const error = new Error(`HTTP Error: ${response.status}`);
                    error.status = response.status;
                    if (response.status === 429) {
                        const seconds = parseInt(response.headers.get('Retry-After') || '10', 10);
                        error.retryAfter = Number.isFinite(seconds) ? Math.max(1, seconds) : 10;
                        blockedUntil.set(bucket, Date.now() + error.retryAfter * 1000);
                    }
                    throw error;
                }

                const json = await response.json();
                if (!json || !json.success) {
                    throw new Error(json && json.data && json.data.msg ? String(json.data.msg) : 'Invalid API response.');
                }

                cache.set(url, { value: json, expires: Date.now() + cacheMs });
                if (cache.size > maxEntries) cache.delete(cache.keys().next().value);
                return json;
            })();
            inFlight.set(url, pending);
            pending.finally(() => inFlight.delete(url)).catch(() => {});
        }

        return clone(await inFlight.get(url));
    }

    global.DashDWidgetRequests = { get };
})(window);
