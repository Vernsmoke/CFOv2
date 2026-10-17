// Run the shared header behavior against an isolated DOM and read-only transport.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/admin-header.js', 'utf8');

class Element {
    constructor(tagName = 'div') {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.attributes = new Map();
        this.events = {};
        this.classes = new Set();
        this.classList = {
            toggle: (name, force) => force ? this.classes.add(name) : this.classes.delete(name),
            contains: name => this.classes.has(name),
        };
    }
    set textContent(value) { this.text = String(value); this.children = []; }
    get textContent() { return (this.text || '') + this.children.map(child => child.textContent).join(''); }
    set className(value) { this.classes = new Set(value.split(/\s+/)); }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.text = ''; this.children = [...children]; }
    addEventListener(name, callback) { this.events[name] = callback; }
    showModal() { this.open = true; }
    close() { this.open = false; }
    querySelector(selector) {
        for (const child of this.children) {
            const matches = selector[0] === '.' ? child.classes.has(selector.slice(1))
                : selector[0] === '#' ? child.id === selector.slice(1)
                : child.tagName === selector.toUpperCase();
            if (matches) return child;
            const descendant = child.querySelector(selector);
            if (descendant) return descendant;
        }
        return null;
    }
}

function setup({registration = false, notifications = []} = {}) {
    const header = new Element('header');
    const title = new Element('strong');
    const breadcrumb = new Element('nav');
    const status = new Element('span');
    const bell = new Element('button');
    header.querySelector = selector => ({
        '#admin-header-title': title,
        '#admin-header-breadcrumb': breadcrumb,
        '#live-status': status,
        '[data-header-notifications]': registration ? bell : null,
    })[selector] || null;
    const body = new Element('body');
    const state = {requests: 0, notifications, ok: true, redirected: false};
    const document = {
        body,
        querySelector: selector => selector === '[data-admin-header]' ? header : null,
        createElement: tagName => new Element(tagName),
    };
    const window = {};
    vm.runInNewContext(source, {document, window, fetch: async (url, options) => {
        assert.equal(url, '/api/admin/state?resources=notifications');
        assert.equal(options.headers.Accept, 'application/json');
        state.requests++;
        return {ok: state.ok, redirected: state.redirected, json: async () => ({notifications: state.notifications})};
    }});
    return {api: window.AdminHeader, title, breadcrumb, status, bell, body, state};
}

(async () => {
    let env = setup();
    env.api.setPage('Dashboard');
    assert.equal(env.title.textContent, 'Dashboard');
    assert.equal(env.breadcrumb.textContent, 'CFO / Admin workspace');
    assert.equal(env.breadcrumb.children.some(child => child.tagName === 'A'), false, 'Dashboard subtitle is plain text');

    env.api.setPage('Student Accounts', [{name: 'Dashboard', href: '/admin#admin-dashboard'}]);
    assert.equal(env.title.textContent, 'Student Accounts');
    let [ancestor, separator, current] = env.breadcrumb.children;
    assert.equal(ancestor.tagName, 'A');
    assert.equal(ancestor.href, '/admin#admin-dashboard');
    assert.equal(ancestor.textContent, 'Dashboard');
    assert.equal(separator.getAttribute('aria-hidden'), 'true');
    assert.equal(current.getAttribute('aria-current'), 'page');
    assert.equal(current.textContent, 'Student Accounts');
    assert.notEqual(current.tagName, 'A', 'the current page is not clickable');

    const unsafe = '<img src=x onerror=alert(1)>';
    env.api.setPage(unsafe, [{name: unsafe, href: '/admin#admin-dashboard'}]);
    assert.equal(env.title.textContent, unsafe);
    assert.equal(env.breadcrumb.children[0].textContent, unsafe);
    assert.equal(env.breadcrumb.children[0].children.length, 0, 'labels remain text instead of executable markup');

    env.api.setPage('Dashboard');
    assert.equal(env.breadcrumb.children.length, 1, 'route changes replace the old breadcrumb');
    env.api.setLiveStatus('Live');
    assert.equal(env.status.textContent, 'Live');
    assert.equal(env.status.classList.contains('live'), true);
    env.api.setLiveStatus('Reconnecting');
    assert.equal(env.status.textContent, 'Reconnecting');
    assert.equal(env.status.classList.contains('live'), false);
    assert.equal(env.state.requests, 0, 'the SPA retains its existing notifications action');

    env = setup({registration: true, notifications: [{message: unsafe, created_at: '2026-10-08 09:00'}]});
    assert.equal(env.state.requests, 0, 'registration notifications load only when requested');
    await env.bell.events.click();
    const dialog = env.body.children[0];
    const content = dialog.querySelector('.admin-header-notification-content');
    assert.equal(dialog.open, true);
    assert.equal(env.state.requests, 1);
    assert.equal(content.getAttribute('aria-busy'), 'false');
    assert.equal(content.children[0].textContent, unsafe + '2026-10-08 09:00');
    assert.equal(content.children[0].children.some(child => child.tagName === 'IMG'), false, 'notification messages remain safe text');

    env.state.notifications = [];
    await env.bell.events.click();
    assert.equal(env.body.children.length, 1, 'reopening notifications reuses the dialog');
    assert.equal(content.textContent, 'No notifications.');
    env.state.ok = false;
    await env.bell.events.click();
    assert.match(content.textContent, /Unable to load notifications/);
    assert.equal(content.getAttribute('aria-busy'), 'false');
    dialog.children[0].querySelector('button').events.click();
    assert.equal(dialog.open, false, 'the close control dismisses the dialog');

    console.log('Shared admin header route, breadcrumb, live-status, and notification checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
