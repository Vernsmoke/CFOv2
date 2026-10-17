/* Shared downward-opening filter and student menus; the original select owns the value and events. */
(function (root) {
  'use strict';
  const filters = 'select[data-filter],select[data-ms-filter],.filters select,.filter-grid select,.attendance-filter-popover select,.attendance-health-filters select,.attendance-schedule select,.cert-selector select,#student-change-status,#announcementSort';
  function choices(select) {
    return [...select.options].map((option, index) => ({index, label:option.label || option.textContent, disabled:option.disabled || (option.parentElement?.tagName === 'OPTGROUP' && option.parentElement.disabled)}));
  }
  function placement(rect, width, height) {
    const menuWidth = Math.min(rect.width, width - 16);
    return {left:Math.max(8, Math.min(rect.left, width - menuWidth - 8)), top:rect.bottom + 2, width:menuWidth, maxHeight:Math.max(32, Math.min(360, height - rect.bottom - 10))};
  }
  function commit(select, index) {
    const option = select.options[index];
    if (select.disabled || !option || option.disabled || (option.parentElement?.tagName === 'OPTGROUP' && option.parentElement.disabled) || select.selectedIndex === index) return false;
    select.selectedIndex = index;
    const EventType = select.ownerDocument.defaultView.Event;
    select.dispatchEvent(new EventType('input', {bubbles:true}));
    select.dispatchEvent(new EventType('change', {bubbles:true}));
    return true;
  }
  function mount(doc = root.document) {
    if (!doc || doc.cfoFilterDropdowns) return doc?.cfoFilterDropdowns;
    const win = doc.defaultView;
    let current = null, sequence = 0, pointerSelect = null, frame = 0;
    const eligible = select => select?.tagName === 'SELECT' && !select.disabled && !select.multiple && select.size <= 1 && (select.matches(filters) || doc.body.matches('.dashboard-mode:not(.admin-mode)')) && doc.body.matches('.admin-background,.dashboard-mode');
    function close() {
      if (!current) return;
      const {select, menu, observer, attributes} = current;
      current = null; observer.disconnect(); menu.remove();
      attributes.forEach((value, name) => value === null ? select.removeAttribute(name) : select.setAttribute(name, value));
    }
    function position() {
      if (!current) return;
      if (!current.select.isConnected || current.select.disabled || !current.select.getClientRects().length) { close(); return; }
      const rect = current.select.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top >= win.innerHeight) { close(); return; }
      const box = placement(rect, win.innerWidth, win.innerHeight);
      Object.assign(current.menu.style, {left:box.left + 'px', top:box.top + 'px', width:box.width + 'px', maxHeight:box.maxHeight + 'px'});
    }
    function highlight(index, scroll = true) {
      if (!current || !current.items.some(item => item.index === index && !item.disabled)) return;
      current.active = index;
      [...current.menu.children].forEach(node => node.dataset.active = String(Number(node.dataset.index) === index));
      const node = current.menu.querySelector('[data-index="' + index + '"]');
      current.select.setAttribute('aria-activedescendant', node.id);
      if (scroll) {
        const top = node.offsetTop, bottom = top + node.offsetHeight, menu = current.menu;
        if (top < menu.scrollTop) menu.scrollTop = top;
        else if (bottom > menu.scrollTop + menu.clientHeight) menu.scrollTop = bottom - menu.clientHeight;
      }
    }
    function choose(index) {
      if (!current || !current.items.some(item => item.index === index && !item.disabled)) return;
      const select = current.select;
      close(); select.focus({preventScroll:true}); commit(select, index);
    }
    function open(select) {
      if (!eligible(select) || current?.select === select) return;
      close();
      const items = choices(select);
      if (!items.some(item => !item.disabled)) return;
      select.focus({preventScroll:true});
      if (win.innerHeight - select.getBoundingClientRect().bottom < Math.min(120, items.length * 34 + 8)) select.scrollIntoView({block:'center', inline:'nearest', behavior:'instant'});
      const menu = doc.createElement('div');
      menu.id = 'cfo-filter-menu-' + (++sequence); menu.className = 'cfo-filter-dropdown'; menu.setAttribute('role', 'listbox');
      menu.setAttribute('aria-label', select.getAttribute('aria-label') || [...(select.labels || [])].map(label => label.textContent.trim()).join(' ') || 'Filter options');
      items.forEach(item => {
        const node = doc.createElement('div');
        node.id = menu.id + '-' + item.index; node.className = 'cfo-filter-option'; node.dataset.index = item.index;
        node.setAttribute('role', 'option'); node.setAttribute('aria-selected', String(item.index === select.selectedIndex));
        if (item.disabled) node.setAttribute('aria-disabled', 'true');
        node.textContent = item.label; menu.appendChild(node);
      });
      const attributes = new Map(['aria-expanded','aria-controls','aria-activedescendant'].map(name => [name, select.getAttribute(name)]));
      select.setAttribute('aria-expanded', 'true'); select.setAttribute('aria-controls', menu.id);
      const observer = new win.MutationObserver(() => {
        if (!current) return;
        const changed = !select.isConnected || select.disabled || JSON.stringify(choices(select)) !== JSON.stringify(items);
        if (changed) close();
      });
      current = {select, menu, items, attributes, observer, active:select.selectedIndex, query:'', typedAt:0};
      // The top layer keeps menus visible over scrolling panels and dialogs.
      if (typeof menu.showPopover === 'function') menu.setAttribute('popover', 'manual');
      (select.closest('dialog[open]') || doc.body).appendChild(menu);
      if (menu.hasAttribute('popover')) menu.showPopover();
      menu.style.font = win.getComputedStyle(select).font;
      position();
      highlight(items.some(item => item.index === select.selectedIndex && !item.disabled) ? select.selectedIndex : items.find(item => !item.disabled).index);
      observer.observe(doc.body, {childList:true, subtree:true});
      observer.observe(select, {attributes:true, childList:true, subtree:true, characterData:true});
      menu.addEventListener('pointerdown', event => event.preventDefault());
      menu.addEventListener('pointermove', event => {
        const node = event.target.closest('[data-index]'); if (node) highlight(Number(node.dataset.index), false);
      });
      menu.addEventListener('click', event => {
        const node = event.target.closest('[data-index]'); if (node) { event.stopPropagation(); choose(Number(node.dataset.index)); }
      });
    }
    doc.addEventListener('pointerdown', event => {
      if (eligible(event.target) && event.button === 0) {
        event.preventDefault(); pointerSelect = event.target;
        if (current?.select === event.target) close(); else open(event.target);
      } else if (current && !current.menu.contains(event.target)) close();
    }, true);
    doc.addEventListener('click', event => {
      if (!eligible(event.target)) { pointerSelect = null; return; }
      event.preventDefault();
      if (pointerSelect === event.target) { pointerSelect = null; return; }
      open(event.target);
    }, true);
    doc.addEventListener('keydown', event => {
      if (!eligible(event.target) || event.ctrlKey || event.metaKey) return;
      const key = event.key;
      if (!current || current.select !== event.target) {
        if (['ArrowDown','ArrowUp','Enter',' '].includes(key)) { event.preventDefault(); event.stopPropagation(); open(event.target); }
        return;
      }
      if (key === 'Tab') { choose(current.active); return; }
      if (key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); return; }
      if (key === 'Enter' || (key === ' ' && !current.query)) { event.preventDefault(); event.stopPropagation(); choose(current.active); return; }
      const items = current.items.filter(item => !item.disabled), index = items.findIndex(item => item.index === current.active);
      if (['ArrowDown','ArrowUp','Home','End','PageDown','PageUp'].includes(key)) {
        event.preventDefault(); event.stopPropagation(); current.query = '';
        const next = key === 'Home' ? 0 : key === 'End' ? items.length - 1 : index + ({ArrowDown:1,ArrowUp:-1,PageDown:8,PageUp:-8}[key]);
        highlight(items[Math.max(0, Math.min(items.length - 1, next))].index);
      } else if (key.length === 1 && !event.altKey) {
        event.preventDefault();
        const now = Date.now(); current.query = now - current.typedAt > 700 ? key : current.query + key; current.typedAt = now;
        const query = current.query.toLocaleLowerCase(), prefix = [...query].every(char => char === query[0]) ? query[0] : query;
        const rotated = [...items.slice(index + 1), ...items.slice(0, index + 1)];
        const match = rotated.find(item => item.label.trim().toLocaleLowerCase().startsWith(prefix));
        if (match) highlight(match.index);
      }
    }, true);
    doc.addEventListener('focusin', event => { if (current && event.target !== current.select && !current.menu.contains(event.target)) close(); });
    doc.addEventListener('scroll', event => {
      if (!current || current.menu.contains(event.target) || frame) return;
      frame = win.requestAnimationFrame(() => { frame = 0; position(); });
    }, true);
    win.addEventListener('resize', position); win.addEventListener('pagehide', close); win.addEventListener('hashchange', close);
    doc.cfoFilterDropdowns = {open, close};
    return doc.cfoFilterDropdowns;
  }
  root.CfoFilterDropdowns = {choices, placement, commit, mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoFilterDropdowns;
  if (root.document) mount();
})(typeof window === 'undefined' ? globalThis : window);
