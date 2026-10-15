const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const code = fs.readFileSync(require('node:path').join(__dirname, '../assets/widget-request-cache.js'), 'utf8');
let calls = 0;
let responseStatus = 200;
const window = {
    location: { href: 'https://example.test/dashboard/' },
    fetch: async () => {
        calls++;
        return {
            ok: responseStatus === 200,
            status: responseStatus,
            headers: { get: () => '2' },
            json: async () => ({ success: true, data: { years: [2026] } }),
        };
    },
};
vm.runInNewContext(code, { window, URL, Date, Map, JSON, Error, parseInt, Number, Math, String });

(async () => {
    const url = 'https://example.test/wp-admin/admin-ajax.php?action=get_dashd_periods_split&key=table1';
    const [first, second] = await Promise.all([
        window.DashDWidgetRequests.get(url),
        window.DashDWidgetRequests.get(url),
    ]);
    assert.equal(calls, 1, 'concurrent identical requests should be shared');
    first.data.years.push(2027);
    assert.deepEqual(Array.from(second.data.years), [2026], 'callers receive independent data');
    await window.DashDWidgetRequests.get(url);
    assert.equal(calls, 1, 'recent responses should be cached');

    responseStatus = 429;
    const limitedUrl = url + '&key=table2';
    await assert.rejects(window.DashDWidgetRequests.get(limitedUrl), (error) => error.status === 429);
    await assert.rejects(window.DashDWidgetRequests.get(limitedUrl), (error) => error.status === 429);
    assert.equal(calls, 2, '429 backoff should avoid a second network request');
    console.log('Widget request cache smoke checks passed.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
