(() => {
    'use strict';
    const form = document.querySelector('#registration-filters');
    const filterToggle = form?.querySelector('[data-toggle-registration-filters]');
    const filterPanel = form?.querySelector('#registration-filter-panel');
    if (filterToggle && filterPanel) {
        function setFiltersExpanded(expanded) {
            filterPanel.hidden = !expanded;
            filterToggle.setAttribute('aria-expanded', String(expanded));
            filterToggle.querySelector('[data-filter-toggle-label]').textContent = expanded ? 'Hide Filters' : 'Show Filters';
        }
        // Keep applied filters visible after server-side searches and pagination.
        setFiltersExpanded([...filterPanel.querySelectorAll('select, input')].some(control => control.value !== ''));
        filterToggle.hidden = false;
        filterToggle.addEventListener('click', () => setFiltersExpanded(filterPanel.hidden));
    }
    const message = document.querySelector('#page-message');
    const dialog = document.querySelector('#registration-dialog');
    const content = document.querySelector('#dialog-content');
    const receiptDialog = document.querySelector('#receipt-image-dialog');
    const fullReceipt = document.querySelector('#full-receipt-image');
    const receiptMessage = document.querySelector('#receipt-image-message');
    const receiptImageContent = document.querySelector('.receipt-image-content');
    let receiptReturnFocus;
    document.querySelector('#close-receipt-image').addEventListener('click', () => receiptDialog.close());
    receiptDialog.addEventListener('cancel', event => {
        event.preventDefault();
        event.stopPropagation();
        receiptDialog.close();
    });
    receiptDialog.addEventListener('close', () => {
        fullReceipt.hidden = true;
        fullReceipt.removeAttribute('src');
        receiptImageContent.setAttribute('aria-busy', 'false');
        if (dialog.open && receiptReturnFocus?.isConnected) receiptReturnFocus.focus({preventScroll: true});
    });
    fullReceipt.addEventListener('load', () => {
        if (!receiptDialog.open) return;
        fullReceipt.hidden = false;
        receiptMessage.hidden = true;
        receiptImageContent.setAttribute('aria-busy', 'false');
    });
    fullReceipt.addEventListener('error', () => {
        if (!receiptDialog.open) return;
        fullReceipt.hidden = true;
        receiptMessage.textContent = 'Unable to load this receipt. Close the preview and try again.';
        receiptMessage.hidden = false;
        receiptImageContent.setAttribute('aria-busy', 'false');
    });
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    function announce(text, kind = 'loading') {
        message.textContent = text;
        message.className = `message ${kind}`;
        message.hidden = false;
    }
    const saved = sessionStorage.getItem('registration-success');
    if (saved) { announce(saved, 'success'); sessionStorage.removeItem('registration-success'); }
    form?.addEventListener('submit', event => {
        if (event.submitter?.hasAttribute('data-export')) {
            announce('Preparing the filtered CSV download. You can continue using this page.');
            return;
        }
        form.setAttribute('aria-busy', 'true');
        announce('Loading registrations…');
    });
    document.querySelector('#per-page')?.addEventListener('change', () => form.requestSubmit());
    const dateFrom = form?.querySelector('[name="date_from"]');
    const dateTo = form?.querySelector('[name="date_to"]');
    function validateDateRange() {
        dateTo?.setCustomValidity(dateFrom?.value && dateTo?.value && dateTo.value < dateFrom.value
            ? 'The end date must be on or after the start date.' : '');
    }
    form?.querySelectorAll('select:not([data-af-select]), input[type="date"]').forEach(filter => {
        filter.addEventListener('change', () => {
            validateDateRange();
            form.requestSubmit();
        });
    });
    dateFrom?.addEventListener('input', validateDateRange);
    dateTo?.addEventListener('input', validateDateRange);
    validateDateRange();
    form?.querySelector('[name="search"]')?.addEventListener('search', () => form.requestSubmit());
    window.addEventListener('pageshow', () => form?.removeAttribute('aria-busy'));
    const rows = [...document.querySelectorAll('.row-select')];
    const selectAll = document.querySelector('#select-all');
    function selection() {
        const count = rows.filter(row => row.checked).length;
        selectAll.checked = rows.length > 0 && count === rows.length;
        selectAll.indeterminate = count > 0 && count < rows.length;
        const status = document.querySelector('#selection-status');
        status.hidden = count === 0;
        status.textContent = `${count} registration${count === 1 ? '' : 's'} selected on this page.`;
    }
    selectAll?.addEventListener('change', () => { rows.forEach(row => row.checked = selectAll.checked); selection(); });
    rows.forEach(row => row.addEventListener('change', selection));
    let loadController;
    let returnFocus;
    let detailsUrl;
    let receiptZoom = 1;
    let needsRefresh = false;
    function sizeReceipt() {
        const img = content.querySelector('.receipt-preview');
        if (!img || img.hidden || !img.naturalWidth || !img.naturalHeight) return;
        const viewport = img.closest('.receipt-viewport');
        const fittedWidth = Math.min(viewport.clientWidth, viewport.clientHeight * img.naturalWidth / img.naturalHeight);
        img.style.width = `${fittedWidth * receiptZoom}px`;
    }
    window.addEventListener('resize', sizeReceipt);
    function initializeDetails() {
        receiptZoom = 1;
        content.querySelectorAll('.receipt-preview').forEach(img => {
            const unavailable = () => {
                img.hidden = true;
                content.querySelector('.receipt-unavailable').hidden = false;
                content.querySelectorAll('[data-receipt-zoom]').forEach(button => button.disabled = true);
            };
            img.addEventListener('error', unavailable);
            img.addEventListener('load', sizeReceipt);
            if (img.complete && img.naturalWidth === 0) unavailable();
            else if (img.complete) sizeReceipt();
        });
    }
    async function getDetails(url, signal) {
        const response = await fetch(url, {headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}, signal});
        if (!response.ok || response.redirected) throw new Error('Unable to load this registration. Refresh the page and check your session.');
        return response.text();
    }
    document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-details]');
        if (!trigger) return;
        returnFocus = trigger;
        detailsUrl = trigger.dataset.details;
        loadController?.abort();
        loadController = new AbortController();
        const currentLoad = loadController;
        content.textContent = 'Loading registration details…';
        content.setAttribute('aria-busy', 'true');
        if (!dialog.open) dialog.showModal();
        try {
            // This fragment is rendered by authenticated Blade views; all record values are escaped there.
            const html = await getDetails(detailsUrl, currentLoad.signal);
            if (currentLoad !== loadController || currentLoad.signal.aborted) return;
            content.innerHTML = html;
            initializeDetails();
            content.scrollTop = 0;
        } catch (error) {
            if (error.name !== 'AbortError' && currentLoad === loadController) content.textContent = error.message;
        } finally { if (currentLoad === loadController) content.removeAttribute('aria-busy'); }
    });
    dialog.addEventListener('click', event => {
        if (event.target.closest('[data-close-dialog]') && !saving) dialog.close();
    });
    dialog.addEventListener('close', () => {
        loadController?.abort();
        returnFocus?.focus();
        if (needsRefresh) window.location.reload();
    });
    content.addEventListener('click', event => {
        const reviewToggle = event.target.closest('[data-receipt-review-toggle]');
        if (reviewToggle) {
            if (saving) return;
            const review = content.querySelector('[data-receipt-review]');
            if (!review) return;
            const expanded = review.hidden;
            review.hidden = !expanded;
            reviewToggle.setAttribute('aria-expanded', String(expanded));
            if (expanded) review.querySelector('[name="receipt_status"]').focus();
            return;
        }
        const receiptTrigger = event.target.closest('[data-receipt-modal]');
        if (receiptTrigger) {
            if (saving || receiptDialog.open) return;
            receiptReturnFocus = receiptTrigger;
            fullReceipt.hidden = true;
            fullReceipt.alt = content.querySelector('.receipt-preview')?.alt || "Student's uploaded receipt";
            receiptMessage.textContent = 'Loading receipt…';
            receiptMessage.hidden = false;
            receiptImageContent.setAttribute('aria-busy', 'true');
            // Keep the registration dialog mounted underneath, preserving its form and scroll state.
            receiptDialog.showModal();
            fullReceipt.src = receiptTrigger.dataset.receiptModal;
            receiptImageContent.scrollTop = 0;
            return;
        }
        const step = event.target.closest('[data-review-section]');
        if (step) {
            content.querySelectorAll('[data-review-section]').forEach(button => {
                button.classList.toggle('active', button === step);
                if (button === step) button.setAttribute('aria-current', 'step');
                else button.removeAttribute('aria-current');
            });
            const section = content.querySelector(`#${step.dataset.reviewSection}`);
            section?.scrollIntoView({block: 'nearest'});
            section?.focus({preventScroll: true});
        }
        const zoomButton = event.target.closest('[data-receipt-zoom]');
        if (!zoomButton) return;
        const img = content.querySelector('.receipt-preview');
        if (!img || img.hidden) return;
        const action = zoomButton.dataset.receiptZoom;
        receiptZoom = action === 'reset' ? 1 : Math.min(3, Math.max(1, receiptZoom + (action === 'in' ? .25 : -.25)));
        sizeReceipt();
        if (action === 'reset') { const viewport = img.closest('.receipt-viewport'); viewport.scrollTop = 0; viewport.scrollLeft = 0; }
    });
    content.addEventListener('change', event => {
        if (event.target.name === 'status') {
            event.target.form.querySelector('[name="rejection_reason"]').required = event.target.value === 'Rejected';
        }
        if (event.target.name === 'receipt_status') {
            const reason = event.target.form.querySelector('[name="receipt_reason"]');
            const rejected = event.target.value === 'Rejected';
            reason.required = rejected;
            reason.disabled = !rejected;
            reason.closest('[data-receipt-rejection-reason]').hidden = !rejected;
        }
        if (event.target.name === 'schedule_id') {
            const selected = event.target.selectedOptions[0];
            for (const key of ['date', 'time', 'location']) content.querySelector(`[data-schedule-${key}]`).textContent = selected?.dataset[key] || '—';
            const status = content.querySelector('[data-schedule-status]');
            const assigned = selected?.dataset.assigned === 'true';
            status.textContent = assigned ? 'Scheduled' : (selected?.value ? 'Not saved yet' : 'No schedule assigned.');
            status.classList.toggle('is-scheduled', assigned);
        }
    });
    let saving = false;
    dialog.addEventListener('cancel', event => { if (saving) event.preventDefault(); });
    content.addEventListener('submit', async event => {
        const workflow = event.target.closest('.workflow-form');
        if (!workflow) return;
        event.preventDefault();
        if (saving) return;
        const payload = Object.fromEntries(new FormData(workflow));
        if (payload.confirm_attendance_reset) payload.confirm_attendance_reset = true;
        const feedback = workflow.querySelector('.form-message');
        const controls = [...dialog.querySelectorAll('button,input,select,textarea')];
        saving = true;
        controls.forEach(control => { control.dataset.previouslyDisabled = String(control.disabled); control.disabled = true; });
        feedback.textContent = 'Saving…'; feedback.hidden = false;
        workflow.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(workflow.action, {method: workflow.dataset.method, headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify(payload)});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Unable to save. Refresh your session and try again.'));
            sessionStorage.setItem('registration-success', data.message || 'Registration updated successfully.');
            if (workflow.hasAttribute('data-receipt-review') || workflow.hasAttribute('data-registration-review')) {
                // Keep the review open so the administrator can assign a schedule immediately.
                needsRefresh = true;
                try {
                    content.innerHTML = await getDetails(detailsUrl);
                    initializeDetails();
                    const notice = content.querySelector('.modal-notice');
                    notice.textContent = data.message || 'Review saved.';
                    notice.hidden = false;
                } catch (error) {
                    feedback.textContent = 'Review was saved. Close this dialog to refresh the registration.';
                }
                return;
            }
            window.location.reload();
        } catch (error) { feedback.textContent = error.message; }
        finally {
            saving = false; workflow.removeAttribute('aria-busy');
            controls.forEach(control => control.disabled = control.dataset.previouslyDisabled === 'true');
        }
    });
    document.querySelector('#registration-logout').addEventListener('submit', async event => {
        event.preventDefault();
        try {
            const response = await fetch(event.target.action, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}});
            if (!response.ok) throw new Error('Unable to log out. Please try again.');
            window.location.assign('/login');
        } catch (error) { announce(error.message, 'error'); }
    });
    const mobile = matchMedia('(max-width: 1000px)');
    const sidebar = document.querySelector('#sidebar');
    const shell = document.querySelector('.shell');
    const toggle = document.querySelector('#toggle-nav');
    const backdrop = document.querySelector('.nav-backdrop');
    function syncNavigation() {
        const open = document.body.classList.contains('nav-open');
        sidebar.inert = mobile.matches ? !open : document.body.classList.contains('nav-collapsed');
        shell.inert = mobile.matches && open;
        backdrop.hidden = !(mobile.matches && open);
        toggle.setAttribute('aria-expanded', String(!sidebar.inert));
    }
    function closeNavigation() { document.body.classList.remove('nav-open'); syncNavigation(); toggle.focus(); }
    toggle.addEventListener('click', () => {
        document.body.classList.toggle(mobile.matches ? 'nav-open' : 'nav-collapsed');
        syncNavigation();
        if (mobile.matches && !sidebar.inert) sidebar.querySelector('.nav-close').focus();
    });
    sidebar.querySelector('.nav-close').addEventListener('click', () => {
        if (mobile.matches) closeNavigation();
        else { toggle.click(); toggle.focus(); }
    });
    backdrop.addEventListener('click', closeNavigation);
    document.addEventListener('keydown', event => {
        if (!mobile.matches || sidebar.inert) return;
        if (event.key === 'Escape') closeNavigation();
        if (event.key === 'Tab') {
            const controls = [...sidebar.querySelectorAll('a,button')];
            const first = controls[0], last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    mobile.addEventListener('change', syncNavigation);
    syncNavigation();
})();
