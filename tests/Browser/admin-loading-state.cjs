const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/admin-dashboard.js', 'utf8');

function declaration(name) {
    const start = source.indexOf(`async function ${name}(`);
    assert.notEqual(start, -1, `${name} must exist`);
    const body = source.indexOf('{', start);
    let depth = 0;
    for (let index = body; index < source.length; index++) {
        if (source[index] === '{') depth++;
        if (source[index] === '}' && --depth === 0) return source.slice(start, index + 1);
    }
    throw new Error(`Unable to extract ${name}`);
}

async function timeoutFailure() {
    const context = {
        AbortController,
        FormData,
        Error,
        Object,
        JSON,
        window: {CFO_BACKEND: {csrf_token: 'token'}},
        location: {href: ''},
        clearTimeout() {},
        setTimeout(callback) { queueMicrotask(callback); return 1; },
        fetch(url, options) {
            return new Promise((resolve, reject) => {
                options.signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), {name: 'AbortError'})));
            });
        },
    };
    vm.createContext(context);
    vm.runInContext(`const backendTimeoutMs=15000;${declaration('backend')};globalThis.backend=backend;`, context);
    await assert.rejects(context.backend('/api/admin/state'), /server took too long.*database is running/i);
}

async function successfulRequestClearsTimeout() {
    let cleared = false;
    const context = {
        AbortController,
        FormData,
        Error,
        Object,
        JSON,
        window: {CFO_BACKEND: {csrf_token: 'token'}},
        location: {href: ''},
        setTimeout() { return 7; },
        clearTimeout(id) { cleared = id === 7; },
        async fetch() { return {ok: true, status: 200, json: async () => ({users: []})}; },
    };
    vm.createContext(context);
    vm.runInContext(`const backendTimeoutMs=15000;${declaration('backend')};globalThis.backend=backend;`, context);
    assert.deepEqual(await context.backend('/api/admin/state'), {users: []});
    assert.equal(cleared, true);
}

assert.match(source, /CfoAdminSettingsNavigation\?\.mount\(\)\?\.sync/, 'optional setup must not strand the loading state');
Promise.all([timeoutFailure(), successfulRequestClearsTimeout()]).then(() => console.log('admin loading state tests passed'));
