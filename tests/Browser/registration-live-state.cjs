// Execute the production polling code with isolated DOM and transport state.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/registration-live.js', 'utf8');
const baseline = {year: 2026, registrations: 1, users: 1, schedules: 1};
const settle = () => new Promise(resolve => setImmediate(resolve));
function setup(options = {}) {
    const state = {modal: false, selected: false, editing: false, saving: false, reloads: 0, submits: 0, requests: 0, fail: false, status: 200, next: {...baseline}, ...options};
    const element = () => ({events: {}, hidden: true, addEventListener(event, fn) { this.events[event] = fn; }});
    const form = element(); form.requestSubmit = () => state.submits++;
    const refresh = element(), notice = element(), text = {};
    notice.querySelector = () => text;
    const document = element(); document.hidden = false;
    document.activeElement = {matches: () => state.editing};
    document.querySelector = selector => ({
        '#registration-live-versions': {textContent: JSON.stringify(baseline)},
        '#registration-filters': form, '#registration-live-notice': notice,
        '#refresh-registration-records': refresh, 'dialog[open]': state.modal,
        '.row-select:checked': state.selected, '[aria-busy="true"]': state.saving,
    })[selector] || null;
    const window = element(); window.location = {reload: () => state.reloads++};
    let callback;
    vm.runInNewContext(source, {document, window,
        setInterval: fn => { callback = fn; return 1; }, clearInterval() {},
        fetch: async url => {
            assert.equal(url, '/api/live/versions'); state.requests++;
            if (state.fail) throw new Error('Offline');
            return {status: state.status, ok: state.status === 200, json: async () => state.next};
        },
    });
    return {state, form, refresh, notice, text, document, poll: async () => { await callback(); await settle(); }};
}
(async () => {
    let env = setup(); await settle();
    assert.equal(env.state.reloads, 0, 'unchanged versions do not reload');
    env.state.next.registrations++; await env.poll();
    assert.equal(env.state.reloads, 1, 'student submission refreshes admin records');

    for (const blocker of ['modal', 'selected', 'editing', 'saving']) {
        env = setup({[blocker]: true}); await settle();
        env.state.next.registrations++; await env.poll();
        assert.equal(env.state.reloads, 0, blocker + ' must be preserved');
        assert.equal(env.notice.hidden, false);
        env.state[blocker] = false; await env.poll();
        assert.equal(env.state.reloads, 1, 'deferred update resumes after ' + blocker);
    }
    env = setup(); await settle(); env.form.events.input();
    env.state.next.users++; await env.poll();
    assert.equal(env.state.reloads, 0, 'unsaved filters are preserved');
    env.refresh.events.click(); assert.equal(env.state.submits, 1, 'explicit refresh submits edited filters');

    env = setup({fail: true}); await settle();
    env.state.fail = false; env.state.next.schedules++; await env.poll();
    assert.equal(env.state.reloads, 1, 'network recovery retries pending schedule changes');

    env = setup(); await settle(); env.document.hidden = true;
    const count = env.state.requests; env.state.next.registrations++; await env.poll();
    assert.equal(env.state.requests, count, 'hidden pages do not poll');
    env.document.hidden = false; env.document.events.visibilitychange(); await settle();
    assert.equal(env.state.reloads, 1, 'visible pages catch up');

    env = setup({status: 403}); await settle();
    assert.match(env.text.textContent, /session needs attention/);
    const stoppedCount = env.state.requests; await env.poll();
    assert.equal(env.state.requests, stoppedCount, 'access failures stop polling');

    env = setup({next: {...baseline, registrations: 2}}); await settle();
    assert.equal(env.state.reloads, 1, 'changes after server render are not missed on the first poll');
    console.log('Admin registration live refresh checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
