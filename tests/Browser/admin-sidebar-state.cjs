const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
// package.json is "type": "module", so load the browser script the way the other browser tests do.
const sandbox = {window: {}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/admin-nav-badges.js'), 'utf8'), sandbox);
const badges = sandbox.window.CfoNavBadges;
assert.ok(badges, 'the script exports window.CfoNavBadges');
const plain = value => JSON.parse(JSON.stringify(value)); // objects from the vm realm have a different prototype

function badge(name) {
    const attrs = {};
    return {
        dataset: {navBadge: name}, hidden: true, textContent: '',
        setAttribute: (key, value) => { attrs[key] = value; },
        removeAttribute: key => { delete attrs[key]; },
        attrs,
    };
}
const rootOf = elements => ({querySelectorAll: selector => { assert.equal(selector, '[data-nav-badge]'); return elements; }});

// format: hidden at zero, capped at 99+, tolerant of bad input.
assert.deepEqual(plain(badges.format(0)), {visible: false, text: '', label: ''});
assert.deepEqual(plain(badges.format(undefined)), {visible: false, text: '', label: ''});
assert.deepEqual(plain(badges.format('abc')), {visible: false, text: '', label: ''});
assert.deepEqual(plain(badges.format(-4)), {visible: false, text: '', label: ''});
assert.deepEqual(plain(badges.format(7)), {visible: true, text: '7', label: '7 waiting'});
assert.equal(badges.format(99).text, '99');
assert.equal(badges.format(100).text, '99+');
assert.equal(badges.format(100).label, '100 waiting');

// apply: shows counts, hides zeros and unknown keys, ignores bad payloads.
const registrations = badge('registrations'), changes = badge('change_requests'), unknown = badge('nothing');
badges.apply(rootOf([registrations, changes, unknown]), {registrations: 3, change_requests: 0});
assert.equal(registrations.hidden, false); assert.equal(registrations.textContent, '3'); assert.equal(registrations.attrs['aria-label'], '3 waiting');
assert.equal(changes.hidden, true); assert.equal(changes.textContent, ''); assert.equal('aria-label' in changes.attrs, false);
assert.equal(unknown.hidden, true);
badges.apply(rootOf([registrations]), null);
assert.equal(registrations.hidden, true, 'a missing payload clears the badge instead of showing stale data');

// mount: initial counts, polling, hidden tab pause, failure tolerance, cleanup.
(async () => {
    const element = badge('accounts');
    const calls = []; let payload = {accounts: 2}; let fail = false;
    const fetchImpl = async (url, options) => {
        calls.push({url, options});
        if (fail) throw new Error('offline');
        return {ok: true, json: async () => payload};
    };
    const listeners = {}; let tick = null; let cleared = false;
    const doc = {hidden: false,
        addEventListener: (name, fn) => { listeners['doc:' + name] = fn; },
        removeEventListener: name => { delete listeners['doc:' + name]; }};
    const win = {
        setInterval: fn => { tick = fn; return 1; }, clearInterval: () => { cleared = true; },
        addEventListener: (name, fn) => { listeners['win:' + name] = fn; },
        removeEventListener: name => { delete listeners['win:' + name]; },
    };
    const mounted = badges.mount({root: rootOf([element]), url: '/api/admin/nav-badges', initial: {accounts: 1}, fetchImpl, document: doc, window: win, interval: 30000});
    assert.equal(element.textContent, '1', 'server-rendered counts show before the first request');
    await mounted.refresh();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(element.textContent, '2'); assert.equal(calls[0].url, '/api/admin/nav-badges');
    assert.equal(calls[0].options.credentials, 'same-origin');

    const before = calls.length;
    doc.hidden = true; tick(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.length, before, 'no polling while the tab is hidden');
    doc.hidden = false; payload = {accounts: 150}; listeners['doc:visibilitychange'](); await new Promise(resolve => setImmediate(resolve));
    assert.equal(element.textContent, '99+', 'returning to the tab refreshes immediately');

    fail = true; await mounted.refresh();
    assert.equal(element.textContent, '99+', 'a failed request keeps the last known count');
    fail = false; payload = {accounts: 0}; listeners['win:hashchange'](); await new Promise(resolve => setImmediate(resolve));
    assert.equal(element.hidden, true, 'navigating refreshes counts');

    mounted.destroy();
    assert.equal(cleared, true); assert.equal(Object.keys(listeners).length, 0);
    const after = calls.length; await mounted.refresh();
    assert.equal(calls.length, after, 'a destroyed poller makes no more requests');

    console.log('PASS: admin sidebar badge formatting, apply, polling, visibility pause, failure handling and cleanup');
})().catch(error => { console.error(error); process.exit(1); });
