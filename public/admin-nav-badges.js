/* Admin sidebar badges: counts of items waiting for an administrator.
 * Pure helpers (format, apply) plus a small poller (mount). Exported as
 * window.CfoNavBadges and module.exports so tests can load it. */
(function (global) {
    'use strict';

    const MAX_SHOWN = 99;

    function format(count) {
        const value = Math.max(0, Math.floor(Number(count) || 0));
        if (value === 0) return {visible: false, text: '', label: ''};
        return {visible: true, text: value > MAX_SHOWN ? MAX_SHOWN + '+' : String(value), label: value + ' waiting'};
    }

    function apply(root, counts) {
        const values = counts && typeof counts === 'object' ? counts : {};
        root.querySelectorAll('[data-nav-badge]').forEach(element => {
            const shown = format(values[element.dataset.navBadge]);
            element.textContent = shown.text;
            element.hidden = !shown.visible;
            if (shown.visible) element.setAttribute('aria-label', shown.label);
            else element.removeAttribute('aria-label');
        });
    }

    function parse(text) {
        try { return JSON.parse(text); } catch (error) { return null; }
    }

    function mount(options) {
        const root = options.root;
        const url = options.url;
        const fetchImpl = options.fetchImpl || global.fetch?.bind(global);
        const interval = options.interval ?? 30000;
        const doc = options.document || global.document;
        const win = options.window || global;
        let timer = null;
        let busy = false;
        let stopped = false;

        if (options.initial) apply(root, options.initial);

        async function refresh() {
            if (stopped || busy || !fetchImpl || !url) return;
            busy = true;
            try {
                const response = await fetchImpl(url, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}});
                if (response.ok) apply(root, await response.json());
            } catch (error) {
                // Keep the last known counts; the next tick retries.
            } finally {
                busy = false;
            }
        }

        function schedule() {
            if (timer || stopped || !interval) return;
            timer = win.setInterval(() => { if (!doc.hidden) refresh(); }, interval);
        }

        const onVisible = () => { if (!doc.hidden) refresh(); };
        doc.addEventListener?.('visibilitychange', onVisible);
        win.addEventListener?.('hashchange', refresh);
        schedule();
        refresh();

        return {
            refresh,
            destroy() {
                stopped = true;
                if (timer) win.clearInterval(timer);
                timer = null;
                doc.removeEventListener?.('visibilitychange', onVisible);
                win.removeEventListener?.('hashchange', refresh);
            },
        };
    }

    // Server-rendered pages mount on load. The single-page admin builds its links in
    // JavaScript, so it calls autoMount() itself once they exist. Safe to call twice.
    function autoMount() {
        const nav = global.document?.querySelector('[data-nav-badges]');
        if (!nav || nav.dataset.badgesMounted || !nav.querySelector('[data-nav-badge]')) return null;
        nav.dataset.badgesMounted = '1';
        return mount({root: nav, url: nav.dataset.navBadges, initial: parse(nav.dataset.badges || '')});
    }

    const api = {format, apply, mount, autoMount};
    global.CfoNavBadges = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (global.document) {
        if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', autoMount);
        else autoMount();
    }
}(typeof window !== 'undefined' ? window : globalThis));
