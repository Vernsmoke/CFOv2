/* Dock existing admin controls without moving them out of their forms or listeners. */
(function (global) {
  'use strict';
  function layoutGroups(groups, width, gap = 8, padding = 10) {
    const available = Math.max(1, width), rows = [];
    for (const group of groups) {
      const size = Math.min(available, Math.max(1, group.width));
      let row = rows.at(-1);
      if (!row || group.full || row.full || row.width + gap + size > available) {
        row = {items: [], width: 0, height: 0, full: Boolean(group.full)};
        rows.push(row);
      }
      row.items.push({id: group.id, align: group.align, width: size});
      row.width += (row.width ? gap : 0) + size;
      row.height = Math.max(row.height, group.height);
    }
    let bottom = padding;
    const items = [];
    for (const row of [...rows].reverse()) {
      const end = row.items.filter(item => item.align === 'end');
      let startX = 0, endX = available - end.reduce((sum,item) => sum + item.width, 0) - Math.max(0,end.length - 1) * gap;
      for (const {align, ...item} of row.items) {
        const x = align === 'end' ? endX : startX;
        items.push({...item, x, bottom, height: row.height});
        if (align === 'end') endX += item.width + gap; else startX += item.width + gap;
      }
      bottom += row.height + gap;
    }
    return {items, height: rows.length ? bottom - gap + padding : 0};
  }
  const selectors = [
    '#page-actions', '.panel-head > .actions', '.table-toolbar > .actions',
    '.table-footer', '.table-footer > .pagination', '.pagination-tools', '.form-actions', '.filter-actions',
    '.panel.empty > .actions', '.schedule-builder .sb-actions',
    '.ms-calendar-controls', '.ms-table-footer',
    '.filters > button[data-reset]',
    '.attendance-search-toolbar > :is(button[data-reset],button[data-action])'
  ].join(',');
  function mount(doc = global.document) {
    const main = doc?.querySelector('#main-content');
    if (!main) return null;
    if (main.cfoActionBars) return main.cfoActionBars;
    const backdrop = doc.createElement('div');
    backdrop.className = 'cfo-action-bar-background';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.hidden = true;
    main.appendChild(backdrop);
    const originals = new Map();
    let frame = null, destroyed = false;
    const view = doc.defaultView || global;
    const resize = new view.ResizeObserver(() => schedule());
    const excluded = el => el.closest('dialog,.ms-modal,.ce-stage,.certificate,.print-area,[data-course-sections-list],[data-course-sections-confirm]')
      || el.matches('.attendance-workspace .table-toolbar > .actions,.attendance-workspace .attendance-search-toolbar > :is(button[data-reset],button[data-action]),#page:has(> .cr-stats) #data-grid :is(.table-toolbar > .actions,.filters > button[data-reset]),.recovery-grid #data-grid :is(.table-toolbar > .actions,.filters > button[data-reset])')
      || el.matches('.template-editor .ce-toolbar > .form-actions,#page:has(.history-grid,.cert-info) #data-grid > .table-toolbar > .actions')
      || (el.matches('#page #data-grid > .table-toolbar > .actions') && doc.querySelector('.nav-link[data-route="admin-all-student"][aria-current="page"]'))
      || (el.matches('.table-footer') && main.querySelector('#admin-settings-navigation:not([hidden])'))
      || (el.id === 'page-actions' && main.querySelector('#grid-host.recovery-grid,#history-summary,.cert-info,.template-editor,#admin-settings-navigation:not([hidden])'));
    const set = (el, name, value) => {
      if (el.style.getPropertyValue(name) !== value || el.style.getPropertyPriority(name) !== 'important') el.style.setProperty(name, value, 'important');
    };
    const release = el => {
      const original = originals.get(el);
      if (!original) return;
      el.classList.remove('cfo-bottom-action-group');
      if (original.style === null) el.removeAttribute('style'); else el.setAttribute('style', original.style);
      resize.unobserve(el);
      originals.delete(el);
    };
    function update() {
      frame = null;
      if (destroyed || !main.isConnected) return;
      // Restored page actions stay in their heading; other pages use the bottom bar.
      const pageActions = main.querySelector('#page-actions');
      const pageActionsParent = main.querySelector('#grid-host.recovery-grid,#history-summary,.cert-info,.template-editor,#admin-settings-navigation:not([hidden])') ? main.querySelector(':scope > .page-heading') || main : main;
      if (pageActions?.children.length && pageActions.parentElement !== pageActionsParent) pageActionsParent.appendChild(pageActions);
      const settingsNav = main.querySelector('#admin-settings-navigation:not([hidden])');
      const groups = [...main.querySelectorAll(selectors)].filter(el => {
        if (excluded(el) || el.hidden || el.closest('[hidden]')) return false;
        return el.matches('button,a') || Boolean(el.querySelector('button,a[href],select,input[type=submit]'));
      }).filter((el, index, all) => !all.some((parent, other) => other !== index && parent.contains(el)))
        .sort((a,b) => Number(a === settingsNav) - Number(b === settingsNav) || Number(b.id === 'page-actions') - Number(a.id === 'page-actions') || Number(a.matches('.sb-actions,.table-footer,.ms-table-footer,.pagination-tools')) - Number(b.matches('.sb-actions,.table-footer,.ms-table-footer,.pagination-tools')));
      const active = new Set(groups);
      for (const el of originals.keys()) if (!active.has(el)) release(el);
      const left = Math.max(0, doc.querySelector('.shell')?.getBoundingClientRect().left || 0);
      const viewport = doc.documentElement.clientWidth;
      const inset = viewport <= 700 ? 8 : 18;
      const width = Math.max(1, viewport - left - inset * 2);
      const measured = groups.map((el, id) => {
        if (!originals.has(el)) {
          originals.set(el, {style: el.getAttribute('style')});
          el.classList.add('cfo-bottom-action-group');
          resize.observe(el);
        }
        const full = el.matches('.sb-actions,.table-footer,.ms-table-footer,.pagination-tools');
        set(el, 'width', full ? width + 'px' : 'max-content');
        set(el, 'max-width', width + 'px');
        set(el, 'left', left + inset + 'px');
        set(el, 'right', 'auto');
        set(el, 'bottom', '10px');
        set(el, 'position', 'fixed');
        if (!el.matches('button,a')) {
          set(el, 'background', 'transparent'); set(el, 'border', '0px');
          set(el, 'padding', '0px'); set(el, 'margin', '0px');
        }
        const rect = el.getBoundingClientRect();
        return {id, full, align: settingsNav ? (el === settingsNav ? 'start' : 'end') : el.matches('.form-actions,.ms-export,.table-toolbar > .actions,.panel-head > .actions') ? 'end' : 'start', width: rect.width, height: rect.height};
      }).filter(group => group.height > 0);
      const layout = layoutGroups(measured, width);
      const keyboardGap = view.visualViewport ? Math.max(0, view.innerHeight - view.visualViewport.height - view.visualViewport.offsetTop) : 0;
      for (const item of layout.items) {
        const el = groups[item.id];
        set(el, 'left', left + inset + item.x + 'px');
        set(el, 'width', item.width + 'px');
        set(el, 'bottom', item.bottom + keyboardGap + 'px');
      }
      const height = layout.height + (layout.height ? keyboardGap : 0);
      main.style.setProperty('--cfo-action-bar-height', height + 'px');
      doc.body.style.setProperty('--cfo-action-bar-height', height + 'px');
      main.classList.toggle('cfo-has-bottom-actions', height > 0);
      if (backdrop.hidden !== (height === 0)) backdrop.hidden = height === 0;
      backdrop.style.left = left + 'px';
      backdrop.style.height = height + 'px';
    }
    function schedule() {
      if (!destroyed && frame === null) frame = view.requestAnimationFrame(update);
    }
    const mutations = new view.MutationObserver(schedule);
    mutations.observe(main, {subtree: true, childList: true, attributes: true, attributeFilter: ['hidden', 'class']});
    mutations.observe(doc.body, {attributes: true, attributeFilter: ['class']});
    resize.observe(main);
    view.addEventListener('resize', schedule);
    view.visualViewport?.addEventListener('resize', schedule);
    const controller = {refresh() {
      if (frame !== null) view.cancelAnimationFrame(frame);
      update();
    }, destroy() {
      destroyed = true; mutations.disconnect(); resize.disconnect();
      if (frame !== null) view.cancelAnimationFrame(frame);
      view.removeEventListener('resize', schedule); view.visualViewport?.removeEventListener('resize', schedule);
      for (const el of originals.keys()) release(el);
      main.classList.remove('cfo-has-bottom-actions'); main.style.removeProperty('--cfo-action-bar-height');
      doc.body.style.removeProperty('--cfo-action-bar-height');
      backdrop.remove(); delete main.cfoActionBars;
    }};
    main.cfoActionBars = controller;
    schedule();
    return controller;
  }
  const api = {layoutGroups, mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.CfoActionBars = api;
  if (global.document) {
    if (global.document.readyState === 'loading') global.document.addEventListener('DOMContentLoaded', () => mount(), {once: true});
    else mount();
  }
})(typeof window !== 'undefined' ? window : globalThis);
