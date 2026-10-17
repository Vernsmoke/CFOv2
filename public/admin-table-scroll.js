(() => {
    'use strict';
    const body = document.querySelector('body.admin-background');
    if (!body) return;
    const selector = '.table-wrap, .table-scroll';
    const excluded = '#print-area, .print-area, .ce-stage, .certificate, .receipt-viewer, #receipt-image-dialog';
    const records = new Map(), hosts = new Set(), containers = new Set(), roots = new Set();
    let pendingFrame = null;
    const resizeObserver = 'ResizeObserver' in window ? new ResizeObserver(schedule) : null;
    function schedule() {
        if (pendingFrame == null) pendingFrame = requestAnimationFrame(refresh);
    }
    function toggle(element, name, enabled) {
        if (element.classList.contains(name) !== enabled) element.classList.toggle(name, enabled);
    }
    function setStyle(element, property, value) {
        if (element.style.getPropertyValue(property) !== value) element.style.setProperty(property, value);
    }
    function sync(from, to) {
        // Matching positions end the paired event without writing back.
        if (Math.abs(from.scrollLeft - to.scrollLeft) > .5) to.scrollLeft = from.scrollLeft;
    }
    function install(wrapper) {
        const frame = document.createElement('div');
        frame.className = 'admin-table-scroll-frame';
        const bar = document.createElement('div');
        bar.className = 'admin-table-scrollbar is-out-of-view';
        bar.hidden = true;
        bar.tabIndex = -1;
        bar.setAttribute('role', 'region');
        bar.setAttribute('aria-label', 'Scroll table columns horizontally');
        const spacer = document.createElement('div');
        spacer.className = 'admin-table-scroll-spacer';
        spacer.setAttribute('aria-hidden', 'true');
        bar.append(spacer);
        wrapper.before(frame);
        frame.append(wrapper, bar);
        wrapper.classList.add('admin-table-scroll-source');
        if (!wrapper.hasAttribute('tabindex')) wrapper.tabIndex = 0;
        const sourceScroll = () => sync(wrapper, bar), barScroll = () => sync(bar, wrapper);
        wrapper.addEventListener('scroll', sourceScroll, {passive: true});
        bar.addEventListener('scroll', barScroll, {passive: true});
        records.set(wrapper, {wrapper, frame, bar, spacer, sourceScroll, barScroll, table: null, root: null});
        resizeObserver?.observe(wrapper);
        resizeObserver?.observe(frame);
    }
    function scrollRoot(frame) {
        for (let parent = frame.parentElement; parent && parent !== body; parent = parent.parentElement) {
            const style = getComputedStyle(parent);
            // Flex/grid items must shrink to the viewport instead of the wide table.
            if (style.minWidth === 'auto' && /flex|grid/.test(getComputedStyle(parent.parentElement).display)) {
                parent.classList.add('admin-table-scroll-container');
                containers.add(parent);
            }
            if (style.overflowX === 'hidden' && style.overflowY === 'hidden' && parent.tagName !== 'DIALOG') {
                parent.classList.add('admin-table-scroll-host');
                hosts.add(parent);
                continue;
            }
            if (/(auto|scroll|overlay)/.test(style.overflowY)) return parent;
        }
        return document.scrollingElement;
    }
    function visibleBounds(root) {
        if (root === document.scrollingElement) return {top: 0, bottom: innerHeight, left: 0, right: innerWidth};
        const rect = root.getBoundingClientRect();
        return {top: Math.max(0, rect.top + root.clientTop),
            bottom: Math.min(innerHeight, rect.top + root.clientTop + root.clientHeight),
            left: Math.max(0, rect.left + root.clientLeft),
            right: Math.min(innerWidth, rect.left + root.clientLeft + root.clientWidth)};
    }
    function footerInset(root, bounds) {
        let inset = 0;
        for (const footer of root.querySelectorAll('.registration-dialog-footer, .dialog-footer, .dialog-foot, [data-sticky-footer]')) {
            const style = getComputedStyle(footer), rect = footer.getBoundingClientRect();
            if (style.position === 'sticky' && style.bottom !== 'auto' && rect.height
                && rect.top < bounds.bottom && rect.bottom > bounds.top) {
                inset = Math.max(inset, bounds.bottom - rect.top + 4);
            }
        }
        return inset;
    }
    function refresh() {
        pendingFrame = null;
        for (const [wrapper, record] of records) {
            if (!wrapper.isConnected || wrapper.parentElement !== record.frame) {
                wrapper.removeEventListener('scroll', record.sourceScroll);
                record.bar.removeEventListener('scroll', record.barScroll);
                resizeObserver?.unobserve(wrapper);
                resizeObserver?.unobserve(record.frame);
                if (record.table) resizeObserver?.unobserve(record.table);
                record.bar.remove();
                if (!record.frame.children.length) record.frame.remove();
                records.delete(wrapper);
            }
        }
        for (const host of hosts) {
            if (!host.isConnected || !host.querySelector('.admin-table-scroll-frame')) {
                host.classList.remove('admin-table-scroll-host');
                hosts.delete(host);
            }
        }
        for (const container of containers) {
            if (!container.isConnected || !container.querySelector('.admin-table-scroll-frame')) {
                container.classList.remove('admin-table-scroll-container');
                containers.delete(container);
            }
        }
        for (const wrapper of body.querySelectorAll(selector)) {
            if (!records.has(wrapper) && !wrapper.closest(excluded) && wrapper.querySelector('table')) install(wrapper);
        }
        for (const record of records.values()) {
            const {wrapper, frame, bar, spacer} = record, table = wrapper.querySelector('table');
            if (table !== record.table) {
                if (record.table) resizeObserver?.unobserve(record.table);
                record.table = table;
                if (table) resizeObserver?.observe(table);
            }
            const root = scrollRoot(frame);
            record.root = root;
            if (!roots.has(root)) { roots.add(root); resizeObserver?.observe(root); }
            const overflow = wrapper.clientWidth > 0 && wrapper.scrollWidth > wrapper.clientWidth + 1;
            toggle(frame, 'is-overflowing', overflow);
            if (bar.hidden === overflow) bar.hidden = !overflow;
            setStyle(bar, 'width', wrapper.clientWidth + 'px');
            setStyle(bar, 'margin-left', wrapper.clientLeft + 'px');
            setStyle(spacer, 'width', wrapper.scrollWidth + 'px');
            const bounds = visibleBounds(root), inset = footerInset(root, bounds);
            setStyle(bar, '--admin-table-scroll-bottom', inset + 'px');
            const rect = wrapper.getBoundingClientRect();
            const visible = overflow && rect.bottom > bounds.top && rect.top < bounds.bottom - inset
                && rect.right > bounds.left && rect.left < bounds.right;
            toggle(bar, 'is-out-of-view', !visible);
            if (bar.tabIndex !== (visible ? 0 : -1)) bar.tabIndex = visible ? 0 : -1;
            const ariaHidden = visible ? 'false' : 'true';
            if (bar.getAttribute('aria-hidden') !== ariaHidden) bar.setAttribute('aria-hidden', ariaHidden);
            sync(wrapper, bar);
        }
        for (const root of roots) {
            if (![...records.values()].some(record => record.root === root)) {
                resizeObserver?.unobserve(root);
                roots.delete(root);
            }
        }
    }
    new MutationObserver(mutations => {
        if (mutations.some(mutation => mutation.type === 'childList'
            || !mutation.target.matches('.admin-table-scroll-frame, .admin-table-scrollbar, .admin-table-scroll-spacer'))) schedule();
    }).observe(body, {childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'hidden', 'open']});
    document.addEventListener('scroll', schedule, {capture: true, passive: true});
    window.addEventListener('resize', schedule);
    window.visualViewport?.addEventListener('resize', schedule);
    document.fonts?.ready.then(schedule);
    schedule();
})();
