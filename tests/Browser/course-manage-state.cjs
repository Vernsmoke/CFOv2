const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const context = {module: {exports: {}}, window: {}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/admin-course-manage.js'), 'utf8'), context);
const api = context.module.exports;
assert.equal(api, context.window.CfoCourseManage);
const plain = value => JSON.parse(JSON.stringify(value));
const fixture = () => ({academic_options: [{id: 11, type: 'course', name: 'BSITT'}, {id: 12, type: 'course', name: 'BSN'}, {id: 21, type: 'section', name: 'A'}], schedules: [{courses: [{id: 11, name: 'BSITT'}]}]});
const state = fixture();
assert.equal(api.courses(state).length, 2);
assert.match(api.build(state), /Existing Courses/);
assert.match(api.build(state), /data-cm-edit="11"/);
assert.match(api.build(state), /data-cm-delete="12"/);
assert.doesNotMatch(api.build(state), /data-cm-edit="21"/);
assert.match(api.validate(state, 11, '  '), /Enter a course name/);
assert.match(api.validate(state, 11, ' bSn '), /already exists/);
assert.equal(api.validate(state, 11, 'bsitt'), '');
assert.match(api.validate(state, 11, 'A'.repeat(191)), /190/);
assert.doesNotMatch(api.build({academic_options: [{id: 11, type: 'course', name: '<script>"x"</script>'}]}), /<script>/);
assert.match(api.build(state, {editing: 11, draft: 'BSITT'}), /value="BSITT"/);
assert.match(api.build(state, {deleting: 12}), /Delete course BSN\?/);
assert.deepEqual(plain(api.reconcileGroups([{course_id: '99', year_level: '3', sections: ['A']}, {course_id: '12', year_level: '4', sections: ['B']}], state)), [{course_id: '', year_level: '', sections: []}, {course_id: '12', year_level: '4', sections: ['B']}]);

// Small DOM adapter exercises the actual delegated actions and rendered controls.
function mounted(request, onChanged) {
    const state = fixture(), listeners = {}, busy = [], messages = [];
    let html = '', controls = [], groups = [{course_id: '11', year_level: '3', sections: ['A']}];
    const decode = text => text.replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
    const matches = (node, selector) => selector.split(',').some(part => {
        const match = part.trim().match(/^\[([\w-]+)(?:="([^"]*)")?\]$/);
        return match && node.attrs[match[1]] !== undefined && (match[2] === undefined || node.attrs[match[1]] === match[2]);
    });
    const root = {
        addEventListener(type, callback) { listeners[type] = callback; }, removeEventListener(type) { delete listeners[type]; },
        querySelector: selector => controls.find(control => matches(control, selector)),
        get innerHTML() { return html; },
        set innerHTML(value) {
            html = value;
            controls = [...value.matchAll(/<(?:button|input)\b([^>]*)>/g)].map(match => {
                const attrs = Object.fromEntries([...match[1].matchAll(/([\w-]+)(?:="([^"]*)")?/g)].map(([, name, value]) => [name, decode(value || '')]));
                const node = {attrs, value: attrs.value || '', disabled: attrs.disabled !== undefined,
                    dataset: Object.fromEntries(Object.entries(attrs).filter(([name]) => name.startsWith('data-')).map(([name, value]) => [name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()), value])),
                    focus() {}, matches(selector) { return matches(this, selector); }, closest(selector) { return matches(this, selector) ? this : null; },
                    click() { return listeners.click({target: this, preventDefault() {}}); },
                };
                return node;
            });
        },
    };
    const manager = api.mount(root, {state, request, toast: message => messages.push(message), onBusy: value => busy.push(value), onChanged() { groups = api.reconcileGroups(groups, state); onChanged?.(state); }});
    return {state, root, manager, busy, messages, listeners, groups: () => groups,
        click: (action, id) => { const node = root.querySelector('[data-cm-' + action + (id == null ? '' : '="' + id + '"') + ']'); assert.ok(node, 'Rendered ' + action + ' control exists'); return node.click(); },
        name(value) { const input = root.querySelector('[data-cm-name]'); input.value = value; listeners.input({target: input}); },
    };
}

async function verifyActions() {
    const calls = [], changed = [];
    const renamed = mounted(async (url, method, body) => {
        calls.push({url, method, body});
        if (method === 'PATCH') return {academic_option: {name: body.name}};
        return {academic_options: fixture().academic_options.map(course => course.id === 11 ? {...course, name: 'BSIT'} : course), course_year_sections: []};
    }, state => changed.push(state.academic_options.find(course => course.id === 11)?.name));
    await renamed.click('edit', 11); assert.equal(renamed.root.querySelector('[data-cm-name]').value, 'BSITT');
    renamed.name('Temporary'); await renamed.click('cancel', 11); assert.equal(calls.length, 0);
    await renamed.click('edit', 11); renamed.name(' '); await renamed.click('save', 11);
    assert.match(renamed.root.innerHTML, /Enter a course name/); assert.equal(calls.length, 0);
    renamed.name('bsn'); await renamed.click('save', 11); assert.match(renamed.root.innerHTML, /already exists/); assert.equal(calls.length, 0);
    renamed.name(' BSIT '); await renamed.click('save', 11);
    assert.equal(calls[0].url, '/api/admin/academic-options/11'); assert.equal(calls[0].method, 'PATCH'); assert.deepEqual(plain(calls[0].body), {name: 'BSIT'});
    assert.equal(calls[1].url, '/api/admin/state?resources=academic_options'); assert.equal(calls.length, 2);
    assert.deepEqual(changed, ['BSIT', 'BSIT']);
    assert.equal(renamed.state.schedules[0].courses[0].name, 'BSIT');
    assert.deepEqual(plain(renamed.groups()), [{course_id: '11', year_level: '3', sections: ['A']}]);
    assert.deepEqual(renamed.busy, [true, false]); assert.equal(renamed.manager.busy(), false);
    assert.deepEqual(renamed.messages, ['Course updated.']); assert.doesNotMatch(renamed.root.innerHTML, /data-cm-name/);

    for (const message of ['This option is used by student profiles.', 'This course is assigned to a Recollection Day.', 'This course has configured sections. Remove its unused sections first.']) {
        let calls = 0;
        const rejected = mounted(async () => { calls++; throw {errors: {request: [message]}}; });
        await rejected.click('delete', 11); assert.match(rejected.root.innerHTML, /Delete course BSITT\?/); assert.equal(calls, 0);
        await rejected.click('cancel', 11); assert.equal(calls, 0);
        await rejected.click('delete', 11); await rejected.click('confirm', 11);
        assert.ok(rejected.root.innerHTML.includes(message)); assert.equal(calls, 1);
        assert.equal(rejected.state.academic_options.length, 3); assert.equal(rejected.manager.busy(), false);
    }
    const deletedCalls = [];
    const deleted = mounted(async (url, method) => {
        deletedCalls.push({url, method});
        return method === 'DELETE' ? {} : {academic_options: fixture().academic_options.filter(course => course.id !== 11)};
    });
    await deleted.click('delete', 11); await deleted.click('confirm', 11);
    assert.deepEqual(deletedCalls, [{url: '/api/admin/academic-options/11', method: 'DELETE'}, {url: '/api/admin/state?resources=academic_options', method: undefined}]);
    assert.doesNotMatch(deleted.root.innerHTML, /BSITT/); assert.deepEqual(plain(deleted.groups()), [{course_id: '', year_level: '', sections: []}]);

    let refreshCalls = 0;
    const offline = mounted(async (url, method) => { refreshCalls++; if (method === 'PATCH') return {}; throw Error('Offline'); });
    await offline.click('edit', 11); offline.name('BSIT'); await offline.click('save', 11);
    assert.equal(offline.state.academic_options[0].name, 'BSIT'); assert.equal(refreshCalls, 2);
    assert.match(offline.root.innerHTML, /latest course list could not be loaded/); assert.doesNotMatch(offline.root.innerHTML, /data-cm-save/);
    const failed = mounted(async () => { throw Error('<img src=x onerror=alert(1)>'); });
    await failed.click('edit', 11); failed.name('BSIT'); await failed.click('save', 11);
    assert.equal(failed.root.querySelector('[data-cm-name]').value, 'BSIT'); assert.equal(failed.state.academic_options[0].name, 'BSITT');
    assert.doesNotMatch(failed.root.innerHTML, /<img/); assert.match(failed.root.innerHTML, /&lt;img/);

    let finish, pendingCalls = 0;
    const pending = mounted((url, method) => { pendingCalls++; return method === 'PATCH' ? new Promise(resolve => { finish = resolve; }) : Promise.resolve({academic_options: fixture().academic_options}); });
    await pending.click('edit', 11); pending.name('BSIT'); const saving = pending.click('save', 11);
    assert.equal(pending.manager.busy(), true); await pending.click('save', 11); assert.equal(pendingCalls, 1);
    finish({}); await saving; assert.equal(pending.manager.busy(), false);
    pending.manager.destroy(); assert.deepEqual(Object.keys(pending.listeners), []);
}
verifyActions().then(() => console.log('PASS: course list, inline edit validation, rename, confirmed delete, backend errors, live references, group reset, busy guards, and failed refresh recovery.')).catch(error => { console.error(error); process.exitCode = 1; });
