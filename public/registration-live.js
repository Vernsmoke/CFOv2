(() => {
    'use strict';
    const baseline = JSON.parse(document.querySelector('#registration-live-versions').textContent);
    const relevant = ['year', 'registrations', 'users', 'schedules'];
    const form = document.querySelector('#registration-filters');
    const notice = document.querySelector('#registration-live-notice');
    const refresh = document.querySelector('#refresh-registration-records');
    let busy = false, changed = false, dirty = false, stopped = false;
    for (const event of ['input', 'change']) form.addEventListener(event, () => { dirty = true; });
    function safeToRefresh() {
        return !document.hidden && !dirty && !document.querySelector('dialog[open]')
            && !document.querySelector('.row-select:checked')
            && !document.querySelector('[aria-busy="true"]')
            && !document.activeElement?.matches('input,select,textarea');
    }
    refresh.addEventListener('click', () => {
        // Preserve edited filters when refreshing explicitly; untouched filters retain their page.
        if (dirty) form.requestSubmit();
        else window.location.reload();
    });
    async function poll() {
        if (busy || stopped || document.hidden) return;
        busy = true;
        try {
            const response = await fetch('/api/live/versions', {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if ([401, 403, 419].includes(response.status) || response.redirected) {
                window.AdminHeader?.setLiveStatus('Reconnecting');
                stopped = true;
                notice.querySelector('span').textContent = 'Your session needs attention. Refresh to sign in again.';
                notice.hidden = false;
                return;
            }
            if (!response.ok) { window.AdminHeader?.setLiveStatus('Reconnecting'); return; }
            const next = await response.json();
            window.AdminHeader?.setLiveStatus('Live');
            changed ||= relevant.some(key => String(next[key]) !== String(baseline[key]));
            if (!changed) return;
            notice.hidden = false;
            if (safeToRefresh()) { stopped = true; window.location.reload(); }
        } catch (_) {
            window.AdminHeader?.setLiveStatus('Reconnecting');
            // Transient connectivity failures retry on the next poll without discarding edits.
        } finally { busy = false; }
    }
    let timer;
    function resume() {
        if (timer) clearInterval(timer);
        timer = null;
        if (!document.hidden && !stopped) { poll(); timer = setInterval(poll, 5000); }
    }
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('pagehide', () => clearInterval(timer));
    window.addEventListener('pageshow', resume);
    resume();
})();
