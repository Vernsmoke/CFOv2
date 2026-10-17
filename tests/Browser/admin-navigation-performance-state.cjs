const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/admin-dashboard.js', 'utf8');

function declaration(name) {
    const start = source.indexOf(`function ${name}(`);
    assert.notEqual(start, -1, `${name} must exist`);
    const body = source.indexOf('{', start);
    let depth = 0;
    for (let index = body; index < source.length; index++) {
        if (source[index] === '{') depth++;
        if (source[index] === '}' && --depth === 0) return source.slice(start, index + 1);
    }
    throw new Error(`Unable to extract ${name}`);
}

const context = {};
vm.createContext(context);
vm.runInContext(`${declaration('adminRouteResources')};${declaration('adminRouteStateKeys')};globalThis.resources=adminRouteResources;globalThis.keys=adminRouteStateKeys;`, context);
const params = value => new URLSearchParams(value);
const plain = value => Array.from(value);

assert.deepEqual(plain(context.resources('admin-dashboard', params())), ['users', 'schedules']);
assert.deepEqual(plain(context.resources('admin-course-year', params())), ['academic_options']);
assert.deepEqual(plain(context.resources('admin-announcements', params())), ['announcements']);
assert.deepEqual(plain(context.resources('admin-student-attendance', params())), ['registrations']);
assert.deepEqual(plain(context.resources('admin-password-resets', params())), ['password_resets']);
assert.deepEqual(plain(context.resources('admin-settings', params('tab=office'))), ['settings']);
assert.deepEqual(plain(context.resources('admin-settings', params('tab=admins'))), ['users']);

assert.deepEqual(plain(context.keys('admin-dashboard', params())), ['users', 'registrations', 'schedules']);
assert.deepEqual(plain(context.keys('admin-history', params())), ['history']);
assert.deepEqual(plain(context.keys('admin-student-attendance', params())), ['registrations', 'schedules']);
assert.deepEqual(plain(context.keys('admin-settings', params())), []);

assert.match(source, /if\(canRenderCached\)renderAdminRouteContent\(route,params\)/, 'cached route data should render before refresh');
assert.match(source, /cfoState=\{\.\.\.\(cfoState\|\|\{\}\),\.\.\.state\}/, 'partial route responses must merge into the cache');
assert.doesNotMatch(source, /adminVersions=await readLiveVersions\(\);await renderConnectedAdmin\(\)/, 'startup must not fetch and render the page twice');

console.log('admin navigation performance tests passed');
