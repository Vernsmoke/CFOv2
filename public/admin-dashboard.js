    'use strict';
    window.CFO_BACKEND = window.ADMIN_CONFIG.CFO_BACKEND;


    /* 09. Shared UI helpers */
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const iconPaths = {
      menu:'M4 6h16M4 12h16M4 18h16', close:'m6 6 12 12M18 6 6 18',
      search:'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0', filter:'M3 4h18l-7 8v7l-4 2v-9z', down:'m6 9 6 6 6-6',
      heart:'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z',
      lock:'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4', refresh:'M20 7v5h-5M4 17v-5h5M6 8a7 7 0 0 1 12-2l2 3M18 16a7 7 0 0 1-12 2l-2-3',
      home:'m3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9',
      users:'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.9M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
      'user-check':'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0m3 4 2 2 4-4',
      register:'M8 4H5v17h14V4h-3M9 2h6v4H9zM8 11h8M8 15h6',
      course:'m2 9 10-6 10 6-10 6-10-6M6 12v6l6 3 6-3v-6M22 9v8',
      receipt:'M5 3h14v19l-3-2-4 2-4-2-3 2V3M9 8h6M9 12h6M9 16h3',
      calendar:'M4 5h16v16H4zM8 2v6M16 2v6M4 10h16M8 14h2M14 14h2M8 18h2',
      change:'M4 7h16m-4-4 4 4-4 4M20 17H4m4-4-4 4 4 4',
      check:'m5 12 4 4L19 6', clock:'M12 8v5l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
      award:'M17 8a5 5 0 1 1-10 0 5 5 0 0 1 10 0M8 12l-2 10 6-3 6 3-2-10',
      history:'M3 11a9 9 0 1 1 2 7M3 4v7h7M12 7v6l4 2',
      list:'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
      settings:'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M9 3l-1 3-3 1v3l-2 2 2 2v3l3 1 1 3h6l1-3 3-1v-3l2-2-2-2V7l-3-1-1-3z',
      logout:'M9 3H4v18h5M10 12h11m-4-4 4 4-4 4', bell:'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
      arrow:'M4 12h16m-5-5 5 5-5 5', back:'M20 12H4m5-5-5 5 5 5', plus:'M12 5v14M5 12h14',
      download:'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5', print:'M7 8V3h10v5M6 17H3V9h18v8h-3M7 14h10v8H7zM17 11h1',
      eye:'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
      edit:'m4 16 12-12 4 4L8 20H4zM14 6l4 4', trash:'M3 6h18M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7M14 10v7',
      leaf:'M20 3C9 3 3 7 5 15c5 9 16 2 15-12ZM4 21 16 9', info:'M12 11v6M12 7h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
      file:'M5 2h9l5 5v15H5zM14 2v6h5M9 12h6M9 16h6', upload:'M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5'
    };
    function icon(name) { return `<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="${iconPaths[name] || iconPaths.file}"/></svg>`; }
    function hydrateIcons() { $$('[data-icon]').forEach(element => { element.outerHTML = icon(element.dataset.icon); }); }
    function button(label, action, id = '', tone = '', symbol = '') {
      return `<button type="button" class="btn ${tone}" data-action="${action}" data-id="${escapeHTML(id)}">${symbol ? icon(symbol) : ''}${escapeHTML(label)}</button>`;
    }
    function link(label, route, tone = '', symbol = '') { return `<a class="btn ${tone}" href="#${route}">${symbol ? icon(symbol) : ''}${escapeHTML(label)}</a>`; }
    function badge(value) {
      const text = String(value);
      const tone = /^(Active|Present|Approved|Generated|Printed|Downloaded|Scheduled|Completed|Available)/i.test(text) ? 'success' : /^(Pending|Not Yet|Not Printed|Not Downloaded)/i.test(text) ? 'warning' : /^(Absent|Rejected|Inactive)/i.test(text) ? 'danger' : 'info';
      return `<span class="badge ${tone}">${escapeHTML(text)}</span>`;
    }
    function details(pairs) { return `<dl class="detail-list">${pairs.map(([key,value]) => `<dt>${escapeHTML(key)}</dt><dd>${escapeHTML(value || '—')}</dd>`).join('')}</dl>`; }
    function metric(label, number, caption, symbol, route) {
      return `<article class="metric"><div class="metric-top"><span>${escapeHTML(label)}</span><span class="metric-icon">${icon(symbol)}</span></div><strong>${escapeHTML(number)}</strong>${route ? `<a href="${route.startsWith('/') ? route : '#'+route}">${escapeHTML(caption)} ${icon('arrow')}</a>` : `<p class="muted">${escapeHTML(caption)}</p>`}</article>`;
    }
    function note(message, neutral = false) { return `<div class="notice ${neutral ? 'neutral' : ''}">${escapeHTML(message)}</div>`; }
    function empty(title, description, actions = '') { return `<section class="panel empty">${icon('file')}<h2>${escapeHTML(title)}</h2><p>${escapeHTML(description)}</p><div class="actions" style="justify-content:center">${actions}</div></section>`; }
    let toastTimer;
    function toast(message) { $('#toast').textContent = message; $('#toast').hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 4500); }
    const dialog = $('#dialog');
    let dialogOpener;
    function showDialog(title, body, footer = button('Close','close-dialog'), large = false) {
      if (dialog.open) dialog.close();
      dialogOpener = document.activeElement;
      dialog.classList.toggle('large', large);
      dialog.innerHTML = `<div class="dialog-header"><h2 id="dialog-title">${escapeHTML(title)}</h2><button class="icon-btn" data-action="close-dialog" aria-label="Close dialog">${icon('close')}</button></div><div class="dialog-body">${body}</div><div class="dialog-footer">${footer}</div>`;
      dialog.showModal();
    }
    function closeDialog() { if (dialog.open) dialog.close(); }
    dialog.addEventListener('close', () => { if (dialogOpener?.isConnected) dialogOpener.focus({preventScroll:true}); });
    dialog.addEventListener('click', event => { if(event.target === dialog && (event.clientX < dialog.getBoundingClientRect().left || event.clientX > dialog.getBoundingClientRect().right || event.clientY < dialog.getBoundingClientRect().top || event.clientY > dialog.getBoundingClientRect().bottom)) actions['close-dialog'](); });
    dialog.addEventListener('cancel', event => { event.preventDefault(); actions['close-dialog'](); });
    function previewNotice(title, message) { showDialog(title, `<p>${escapeHTML(message)}</p>${note('Front-end preview only. No server, database or account has been updated.', true)}`); }
    function csvDownload(name, columns, rows) {
      const cell = value => '"' + String(value ?? '').replace(/^[=+@\-\t\r]/, "'$&").replace(/"/g, '""') + '"';
      const csv = [columns, ...rows].map(row => row.map(cell).join(',')).join('\r\n');
      const url = URL.createObjectURL(new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8;'}));
      const anchor = document.createElement('a'); anchor.href = url; anchor.download = name; anchor.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    function printPreview(title, markup) {
      $('#print-area').innerHTML = `<h1>${escapeHTML(title)}</h1>${markup}`;
      window.print();
    }
    const unique = values => [...new Set(values)].sort();
    const options = (values, current = '', all = 'All', label = value => value) => `<option value="">${escapeHTML(all)}</option>${values.map(value => `<option value="${escapeHTML(value)}" ${String(value)===String(current)?'selected':''}>${escapeHTML(label(value))}</option>`).join('')}`;
    const multiSelectField = (label, name, values, selected = []) => `<label class="field">${escapeHTML(label)}<select name="${name}[]" multiple required size="${Math.min(4,Math.max(values.length,2))}">${values.map(value=>`<option value="${escapeHTML(value.id)}" ${selected.map(String).includes(String(value.id))?'selected':''}>${escapeHTML(value.name)}</option>`).join('')}</select><small>Choose one or more options.</small></label>`;
    const groupKey = student => `${student.course}-${String(student.year).match(/\d+/)?.[0] || student.year}`;

    /* 10. Shared data grid: search, exact filters, pagination and selections. */
    const gridState = new Map();
    let activeGrid = null;
    function dataGrid(config) {
      const state = gridState.get(config.key) || {q:'', filters:{...config.initialFilters}, page:1, size:10, selected:new Set()};
      gridState.set(config.key, state);
      if(config.filterQuery !== undefined && state.filterQuery !== config.filterQuery) {
        state.filters={...config.initialFilters}; state.filterQuery=config.filterQuery; state.page=1; state.selected.clear();
      }
      if(config.initialSearch !== undefined) state.q = config.initialSearch;
      const filterRows = config.countFilterOptions ? config.rows() : [];
      function filterOptions(filter) {
        if(!config.countFilterOptions)return options(filter.values,state.filters[filter.key],filter.all || 'All');
        // Totals describe the whole selected schedule, independent of search and other filters.
        const counts=new Map();
        filterRows.forEach(row=>{const value=String(filter.get(row));counts.set(value,(counts.get(value)||0)+1);});
        return options(filter.values,state.filters[filter.key],`${filter.all || 'All'} (${filterRows.length})`,value=>`${value} (${counts.get(String(value))||0})`);
      }
      const fields = (config.filters || []).map(filter => `<label class="field">${escapeHTML(filter.label)}${filter.type === 'date' ? `<input type="date" data-filter="${filter.key}" value="${escapeHTML(state.filters[filter.key] || '')}">` : `<select data-filter="${filter.key}">${filterOptions(filter)}</select>`}</label>`).join('');
      const searchField = `<label class="field search">${config.compactFilters?`<span class="sr-only">${escapeHTML(config.searchLabel || 'Search')}</span>${icon('search')}`:escapeHTML(config.searchLabel || 'Search')}<input type="search" data-search placeholder="${escapeHTML(config.placeholder || 'Search by name or student ID…')}" value="${escapeHTML(state.q)}"></label>`;
      // History opts into separate clear controls; every other grid retains its existing toolbar.
      const filterControls = config.historyControls
        ? `<div class="filters history-filter-controls"><div class="field search"><label for="history-search">Search</label><div class="history-search-input"><input id="history-search" type="search" data-search aria-label="Search recollection history" placeholder="${escapeHTML(config.placeholder)}" value="${escapeHTML(state.q)}"><button type="button" class="btn compact" data-clear-search aria-label="Clear history search" ${state.q?'':'hidden'}>${icon('close')}</button></div></div><div class="history-date-controls">${fields}<button type="button" class="btn" data-clear-date aria-label="Clear recollection schedule date">Clear date</button></div></div>`
        : config.expandableFilters
        ? `<div class="attendance-search-toolbar attendance-health-toolbar">${searchField}<button type="button" class="btn" data-toggle-filters aria-expanded="${Boolean(state.filtersOpen)}" aria-controls="attendance-health-filters">${icon('settings')} <span data-toggle-filter-label>${state.filtersOpen?'Hide Filters':'Show Filters'}</span><span class="attendance-filter-badge" data-active-filters hidden></span></button><button type="button" class="btn" data-reset>${icon('refresh')} Clear Filters</button>${config.filterToolbar || ''}</div><section id="attendance-health-filters" class="attendance-health-filters" ${state.filtersOpen?'':'hidden'}><h3>Student Filters</h3><p>Combine filters to narrow the current schedule.</p><div class="attendance-health-filter-grid">${fields}</div></section>`
        : config.compactFilters
        ? `<div class="attendance-search-toolbar">${searchField}<details class="attendance-filter-menu"><summary>${icon('filter')} Filter ${icon('down')}</summary><div class="attendance-filter-popover">${fields}</div></details><button type="button" class="btn" data-reset>${icon('refresh')} Clear Filters</button>${config.filterToolbar || ''}</div>`
        : `<div class="filters">${searchField}${fields}${config.hideClearFilters?'':'<button class="btn" data-reset>Clear filters</button>'}</div>`;
      const root = document.createElement('section'); root.className = config.historyControls ? 'panel history-grid' : 'panel'; root.id = 'data-grid';
      root.innerHTML = `${filterControls}
        <div class="table-toolbar"><div><h2>${escapeHTML(config.title)}</h2><span class="muted small" data-count role="status"></span></div><div class="actions">${config.toolbarBefore || ''}${config.toolbar || ''}</div></div>
        <div class="table-wrap" role="region" aria-label="${escapeHTML(config.title)} table; scroll horizontally for more columns" tabindex="0"><table class="data-table ${config.wide?'wide':''}"><thead><tr>${config.selectable?'<th><input type="checkbox" data-select-all aria-label="Select all rows on this page"></th>':''}${config.columns.map((column,index)=>`<th scope="col" ${column.sort!==false&&(column.get||column.sort)?'aria-sort="none"':''}>${column.sort!==false&&(column.get||column.sort)?`<button type="button" class="grid-sort" data-sort="${index}" aria-label="Sort by ${escapeHTML(column.label)}">${config.historyControls?escapeHTML(column.label).replaceAll('/','/<wbr>'):escapeHTML(column.label)} <span aria-hidden="true">↕</span></button>`:escapeHTML(column.label)}</th>`).join('')}${config.rowActions?'<th scope="col">Actions</th>':''}</tr></thead><tbody></tbody></table></div>
        <div class="table-footer"><span data-range></span><div class="pagination">${config.compactFilters?'<span>Show</span>':''}<label class="sr-only" for="page-size">Rows per page</label><select id="page-size">${[5,10,25,50].map(n=>`<option ${n===state.size?'selected':''}>${n}</option>`).join('')}</select>${config.compactFilters?'<span>entries</span>':''}<button class="btn compact" data-prev aria-label="Previous page">${icon('back')}</button><span data-page></span><button class="btn compact" data-next aria-label="Next page">${icon('arrow')}</button></div></div>`;
      $('#grid-host').replaceChildren(root);
      if(config.historyControls)$('[data-filter="scheduleDate"]',root).setAttribute('aria-label','Filter by recollection schedule date');
      const idOf = config.id || (row => row.id);
      function filtered() {
        const rows=config.rows().filter(row => (config.searchMatches || window.CfoSearch.matches)(config.search(row),state.q) && (config.filters || []).every(filter=>!state.filters[filter.key] || (filter.matches ? filter.matches(row,state.filters[filter.key]) : String(filter.get(row)) === state.filters[filter.key])));
        return window.CfoSearch.sortRows(rows,config.columns,state.sortIndex,state.sortDirection);
      }
      function draw() {
        const rows = filtered(), pages = Math.max(1, Math.ceil(rows.length/state.size)); state.page = Math.min(state.page,pages);
        const start = (state.page-1)*state.size, visible = rows.slice(start,start+state.size);
        $('tbody',root).innerHTML = visible.map((row,index)=>`<tr data-row-id="${escapeHTML(idOf(row))}">${config.selectable?`<td><input type="checkbox" data-select="${escapeHTML(idOf(row))}" aria-label="Select ${escapeHTML(row.name || idOf(row))}" ${state.selected.has(String(idOf(row)))?'checked':''}></td>`:''}${config.columns.map(column=>`<td${column.nowrap?' class="nowrap"':''}>${column.rowNumber ? start+index+1 : column.html ? column.html(row) : escapeHTML(column.get(row))}</td>`).join('')}${config.rowActions?`<td><div class="actions">${config.rowActions(row)}</div></td>`:''}</tr>`).join('') || `<tr><td class="table-empty" colspan="${config.columns.length+(config.selectable?1:0)+(config.rowActions?1:0)}">No records match your search and filters.</td></tr>`;
        if(window.CFO_LIVE_HIGHLIGHT_ID){const highlighted=$(`[data-row-id="${CSS.escape(String(window.CFO_LIVE_HIGHLIGHT_ID))}"]`,root);if(highlighted){highlighted.classList.add('live-highlight');setTimeout(()=>highlighted.classList.remove('live-highlight'),3200);window.CFO_LIVE_HIGHLIGHT_ID=null;}}
        $('[data-count]',root).textContent = config.resultCount ? config.resultCount(rows.length) : `${rows.length} ${config.noun || 'records'}${config.selectable && state.selected.size ? ` · ${state.selected.size} selected` : ''}`;
        $('[data-range]',root).textContent = `Showing ${rows.length ? start+1 : 0}–${Math.min(start+state.size,rows.length)} of ${rows.length}`;
        $('[data-page]',root).textContent = `${state.page} / ${pages}`;
        $('[data-prev]',root).disabled = state.page === 1; $('[data-next]',root).disabled = state.page === pages;
        if(config.selectable) { const check=$('[data-select-all]',root); check.checked=visible.length>0 && visible.every(row=>state.selected.has(String(idOf(row)))); check.indeterminate=!check.checked && visible.some(row=>state.selected.has(String(idOf(row)))); }
        if(config.expandableFilters){const count=(config.filters||[]).filter(filter=>Boolean(state.filters[filter.key])).length+(state.q.trim()?1:0);const indicator=$('[data-active-filters]',root);indicator.textContent=count;indicator.hidden=count===0;}
        config.afterDraw?.(rows);
        $$('[data-sort]',root).forEach(control=>{const selected=Number(control.dataset.sort)===state.sortIndex;control.parentElement.setAttribute('aria-sort',selected?(state.sortDirection==='desc'?'descending':'ascending'):'none');control.querySelector('span').textContent=selected?(state.sortDirection==='desc'?'↓':'↑'):'↕';});
        if(window.CfoSearch)syncAdminSearch();
      }
      let searchTimer;
      function cancelSearchDraw() { if(searchTimer !== undefined){clearTimeout(searchTimer);searchTimer=undefined;} }
      $('[data-search]',root).addEventListener('input',event=>{
        state.q=event.target.value; state.page=1; state.selected.clear();
        if(config.historyControls)$('[data-clear-search]',root).hidden=!state.q;
        if(config.searchDebounce){cancelSearchDraw();searchTimer=setTimeout(()=>{searchTimer=undefined;if(activeGrid?.config===config)draw();},config.searchDebounce);}
        else draw();
      });
      $$('[data-filter]',root).forEach(control=>{
        const filterEvent=(config.filters || []).find(filter=>filter.key===control.dataset.filter)?.event || 'change';
        control.addEventListener(filterEvent,()=>{cancelSearchDraw();state.filters[control.dataset.filter]=control.value;state.page=1;state.selected.clear();draw();});
      });
      if(config.historyControls){
        $('[data-clear-search]',root).onclick=()=>{cancelSearchDraw();state.q='';state.page=1;state.selected.clear();$('[data-search]',root).value='';$('[data-clear-search]',root).hidden=true;draw();$('[data-search]',root).focus();};
        $('[data-clear-date]',root).onclick=()=>{cancelSearchDraw();state.filters.scheduleDate='';state.page=1;state.selected.clear();$('[data-filter="scheduleDate"]',root).value='';draw();$('[data-filter="scheduleDate"]',root).focus();};
      }else if(!config.hideClearFilters)$('[data-reset]',root).onclick=()=>{state.q='';state.filters={};state.page=1;state.selected.clear();$('[data-search]',root).value='';$$('[data-filter]',root).forEach(control=>control.value='');draw();};
      $('[data-prev]',root).onclick=()=>{state.page--;draw();}; $('[data-next]',root).onclick=()=>{state.page++;draw();};
      $('#page-size',root).onchange=event=>{state.size=Number(event.target.value);state.page=1;draw();};
      $$('[data-sort]',root).forEach(control=>control.onclick=()=>{const index=Number(control.dataset.sort);state.sortDirection=state.sortIndex===index&&state.sortDirection==='asc'?'desc':'asc';state.sortIndex=index;state.page=1;draw();});
      root.addEventListener('change',event=>{
        if(event.target.hasAttribute('data-select')) { const id=event.target.dataset.select; event.target.checked?state.selected.add(id):state.selected.delete(id);draw(); }
        if(event.target.hasAttribute('data-select-all')) { filtered().slice((state.page-1)*state.size,state.page*state.size).forEach(row=>event.target.checked?state.selected.add(String(idOf(row))):state.selected.delete(String(idOf(row))));draw(); }
      });
      if(config.expandableFilters)$('[data-toggle-filters]',root).onclick=()=>{state.filtersOpen=!state.filtersOpen;$('#attendance-health-filters',root).hidden=!state.filtersOpen;$('[data-toggle-filters]',root).setAttribute('aria-expanded',String(state.filtersOpen));$('[data-toggle-filter-label]',root).textContent=state.filtersOpen?'Hide Filters':'Show Filters';};
      if(config.compactFilters&&!config.expandableFilters){
        root.addEventListener('keydown',event=>{const menu=$('.attendance-filter-menu',root);if(event.key==='Escape'&&menu.open){event.preventDefault();menu.open=false;$('summary',menu).focus();}});
        root.addEventListener('click',event=>{const menu=$('.attendance-filter-menu',root);if(menu.open&&!menu.contains(event.target))menu.open=false;});
      }
      activeGrid = {config,state,filtered,draw}; draw();
      return activeGrid;
    }

    /* 11. Routes and shell behavior */
    const pages = {
      'admin-dashboard':['Dashboard','A clear view of your recollection workspace.','Overview','home'],
      'admin-lan-access':['Phone access','Share the student login URL and QR code.','Office','info'],
      'admin-all-student':['Student Accounts','View student accounts and review their approval status.','Students','users'],
      'admin-register-this-year':['Recollection Registrations','Review new registrations and set their schedules.','Students','register'],
      'admin-course-year':['Academic options','Manage the courses, year levels and sections used by accounts and schedules.','Students','course'],
      'admin-student-reciept':['Receipt Verification','Review receipts and payment statuses in one place.','Students','receipt'],
      'admin-recollection-schedule':['Your Recollection Schedules','Browse schedules and open their details.','Recollection','calendar'],
      'admin-add-new-recollection-schedule':['Create New Recollection Schedule','Prepare schedule information and review the student-facing preview.','Recollection','calendar'],
      'admin-schedule-change-requests':['Schedule change requests','Review student requests and assign an eligible recollection schedule.','Recollection','change'],
      'admin-student-attendance':['Student Attendance','Mark attendance and move completed batches to History.','Recollection','check'],
      'admin-certificate':['Student Certificates','View basic student information and print or download certificates for students marked Present.','Documents','award'],
      'admin-certificate-template':['Certificate template','Customize the current design shown on eligible certificates.','Documents','file'],
      'admin-history':['Recollection History','Completed batches and permanently retained yearly records.','Documents','history'],
      'admin-announcements':['Announcements','Publish notices for students.','Office','info'],
      'admin-settings':['Settings','Administrator profile and Christian Formation Office information.','Office','settings'],
      'admin-password-resets':['Password reset requests','Review requests and verify students in person.','Office','lock'],
      'admin-activity':['Activity log','Recorded office actions.','Office','history'],
      'login':['Signed out','No real account session was changed.','Preview','logout']
    };
    // config/admin_navigation.php is the single source of sidebar labels, icons, links and badges.
    const navGroups = Object.entries(window.ADMIN_CONFIG.navigation);
    const navItems = Object.fromEntries(navGroups.flatMap(([, items]) => Object.entries(items)));
    const navLabel = route => navItems[route]?.[0] || pages[route]?.[0] || route;
    const navOptions = route => { const options = navItems[route]?.[2]; return options && !Array.isArray(options) ? options : {}; };
    $('#navigation').innerHTML = navGroups.map(([title,items])=>(title?`<div class="nav-group">${escapeHTML(title)}</div>`:'')+Object.entries(items).map(([route,item])=>{const options=navOptions(route);return `<a class="nav-link" href="${escapeHTML(options.href||'#'+route)}" data-route="${escapeHTML(route)}">${icon(item[1]||pages[route]?.[3])}<span>${escapeHTML(item[0])}</span>${options.badge?`<span class="nav-badge" data-nav-badge="${escapeHTML(options.badge)}" hidden></span>`:''}</a>`;}).join('')).join('');
    window.CfoNavBadges?.autoMount();
    const mobile = matchMedia('(max-width: 1000px)');
    let desktopCollapsed = false;
    function syncNavigation() {
      const open = document.body.classList.contains('nav-open');
      $('#sidebar').inert = mobile.matches ? !open : desktopCollapsed;
      $('#shell').inert = mobile.matches && open;
      $('#nav-backdrop').hidden = !(mobile.matches && open);
      $('#toggle-nav').setAttribute('aria-expanded', String(mobile.matches ? open : !desktopCollapsed));
    }
    function closeNavigation() { document.body.classList.remove('nav-open');syncNavigation(); }
    $('#toggle-nav').onclick=()=>{if(mobile.matches){document.body.classList.toggle('nav-open');syncNavigation();if(document.body.classList.contains('nav-open'))$('#close-nav').focus();}else{desktopCollapsed=!desktopCollapsed;document.body.classList.toggle('nav-collapsed',desktopCollapsed);syncNavigation();}};
    $('#close-nav').onclick=()=>{if(mobile.matches)closeNavigation();else $('#toggle-nav').click();$('#toggle-nav').focus();};
    $('#nav-backdrop').onclick=()=>{closeNavigation();$('#toggle-nav').focus();};
    mobile.addEventListener('change',()=>{closeNavigation();if($('#sidebar').contains(document.activeElement)&&$('#sidebar').inert)$('#toggle-nav').focus();});
    document.addEventListener('keydown',event=>{
      if(dialog.open || !mobile.matches || !document.body.classList.contains('nav-open'))return;
      if(event.key==='Escape'){$('#close-nav').click();}
      if(event.key==='Tab'){const controls=$$('a,button',$('#sidebar'));const first=controls[0],last=controls.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}
    });
    function routeDetails() {
      const [requested,query='']=location.hash.slice(1).split('?');
      const params=new URLSearchParams(query);
      if(!requested && window.CFO_BACKEND?.edit_schedule) {
        params.set('id',String(window.CFO_BACKEND.edit_schedule.id));
        return {route:'admin-add-new-recollection-schedule',params};
      }
      const aliases={'admin-upcoming-recollection':'admin-recollection-schedule','admin-print-student-list':'admin-student-attendance','admin-student-list':'admin-student-attendance'};
      if(requested==='admin-upcoming-recollection')params.set('phase','Upcoming');
      return {route:aliases[requested]||requested||'admin-dashboard',params};
    }
    function go(route) { if(location.hash==='#'+route)renderRoute();else location.hash=route; }
    let currentRoute = 'admin-dashboard';
    function renderRoute() { return renderConnectedAdmin(); }

        /* 12. Page layouts and original preview interactions */
    const panel = (title, body, actions='') => `<section class="panel"><div class="panel-head"><h2>${escapeHTML(title)}</h2><div class="actions">${actions}</div></div><div class="panel-body">${body}</div></section>`;
    const field = (label,name,value='',type='text',extra='') => `<label class="field">${escapeHTML(label)}<input name="${name}" type="${type}" value="${escapeHTML(value)}" ${extra}></label>`;
    const selectField = (label,name,values,value) => `<label class="field">${escapeHTML(label)}<select name="${name}">${values.map(v=>`<option ${v===value?'selected':''}>${escapeHTML(v)}</option>`).join('')}</select></label>`;
    const requiredSelectField = (label,name,values,value) => `<label class="field">${escapeHTML(label)}<select name="${name}" required><option value="" disabled ${value?'':'selected'}>Choose ${escapeHTML(label.toLowerCase())}</option>${values.map(v=>`<option value="${escapeHTML(v)}" ${v===value?'selected':''}>${escapeHTML(v)}</option>`).join('')}</select></label>`;
    const optionalSelectField = (label,name,values,value) => `<label class="field">${escapeHTML(label)}<select name="${name}"><option value="" ${value?'':'selected'}>Not set</option>${values.map(v=>`<option value="${escapeHTML(v)}" ${v===value?'selected':''}>${escapeHTML(v)}</option>`).join('')}</select></label>`;
    const studentName = r => `<div class="name-cell"><strong>${escapeHTML(r.name)}</strong><span>${escapeHTML(r.id)}</span></div>`;
    const objectFilter = (key,label,rows) => ({key,label,values:unique(rows.map(r=>r[key])),get:r=>r[key]});
    const actions = {'close-dialog':closeDialog, 'profile':()=>go('admin-settings')};
    Object.assign(actions,window.CfoHistoryDocuments.actions({parentDialog:dialog,toast}));
    Object.assign(actions,window.CfoAdminProfile.actions({user:()=>window.CFO_BACKEND.user,showDialog,saveConnected,button,$,toast}));
    Object.assign(actions,CfoAnnouncements.actions({$,rows:()=>cfoState.announcements||[],saveConnected,showDialog,toast,button,closeDialog,render:connectedAnnouncements}));
    document.querySelector('.skip-link').addEventListener('click',event=>{event.preventDefault();$('#main-content').focus();$('#main-content').scrollIntoView();});
    document.addEventListener('click',event=>{const control=event.target.closest('[data-action]');if(control){const action=actions[control.dataset.action];if(action)Promise.resolve(action(control.dataset.id)).catch(error=>toast(error.message));}});
    $('#sidebar').addEventListener('click',event=>{const anchor=event.target.closest('a[href]');if(anchor && anchor.pathname===location.pathname && anchor.hash===location.hash){event.preventDefault();renderRoute();}});

    /* Laravel mode: use the same shell, tables, dialogs and inline styles with saved data. */
    let cfoState = null;
    let mySchedulesController = null;
    let schedulePageController = null;
    let cfoRouteVersion = 0;
    let pendingConnectedSave = null;
    let connectedSaveBusy = false;
    function cancelConnectedSave() {
      if(connectedSaveBusy || batchSaveBusy)return;
      const pending=pendingConnectedSave;
      pendingConnectedSave=null;
      if(pending?.previous && pending.route===location.hash) {
        dialog.replaceChildren(...pending.previous.nodes);
        dialog.classList.toggle('large',pending.previous.large);
        dialogOpener=pending.previous.opener;
        if(!dialog.open)dialog.showModal();
        dialog.querySelector('input,select,textarea,button')?.focus({preventScroll:true});
      } else closeDialog();
    }
    actions['close-dialog']=cancelConnectedSave;
    async function backend(url, method = 'GET', body) {
      const multipart=body instanceof FormData; const response = await fetch(url, {method, credentials:'same-origin', headers:{Accept:'application/json',...(!multipart?{'Content-Type':'application/json'}:{}),'X-CSRF-TOKEN':window.CFO_BACKEND.csrf_token}, ...(body?{body:multipart?body:JSON.stringify(body)}:{})});
      const result = await response.json().catch(()=>({}));
      if (!response.ok) {
        if (response.status===401) location.href='/#login';
        const error=Error(response.status===419?'Your session expired. Reload the page.':Object.values(result.errors||{}).flat().join('\n')||result.message||'The request failed.');
        error.fields=result.errors;throw error;
      }
      if (result.csrf_token) Object.assign(window.CFO_BACKEND,result);
      return result;
    }
    async function saveConnected(url, method, values, confirmed = false, onSaved = null) {
      if(connectedSaveBusy)return;
      if (!confirmed) {
        pendingConnectedSave = {url,method,values,onSaved,route:location.hash,form:dialog.open?dialog.querySelector('form'):document.querySelector('#page form'),previous:dialog.open?{nodes:[...dialog.childNodes],large:dialog.classList.contains('large'),opener:dialogOpener}:null};
        const fields = values instanceof FormData ? [...values.keys()] : Object.keys(values || {});
        const summary = url==='/api/admin/settings/certificate'
          ? '<p>Save this design as the active certificate template? It will automatically update the student view of all eligible certificates.</p>'
          : `<p>This will ${escapeHTML(method==='DELETE'?'delete the selected record':method==='POST'?'save the requested record':'save the selected changes')} in the CFO system.</p><p>Review: ${escapeHTML(fields.filter(name=>!name.toLowerCase().includes('password')&&name!=='publication_key').map(name=>({course_ids:'Courses',year_level:'Year level',schedule_id:'Recollection schedule',assigned_schedule_id:'Assigned schedule',review_reason:'Admin note',receipt_status:'Receipt status',receipt_reason:'Receipt review reason'}[name]||name.replaceAll('_',' '))).join(', ') || 'Selected record')}.</p>`;
        showDialog('Confirm changes',summary,button('Cancel','close-dialog')+button('Confirm and save','connected-confirm-save'));
        return;
      }
      connectedSaveBusy=true;
      const controls=[...dialog.querySelectorAll('button')];
      controls.forEach(control=>control.disabled=true);
      try { const result=await backend(url,method,values); if(result.user)window.CFO_BACKEND.user=result.user; pendingConnectedSave=null; closeDialog(); if(onSaved)onSaved(result); await renderConnectedAdmin(); toast(result.message || 'Changes saved to the CFO database.'); }
      catch (error) { const form=pendingConnectedSave?.form;connectedSaveBusy=false; cancelConnectedSave(); window.CfoUsability?.formError(form,error);toast(error.message); }
      finally { connectedSaveBusy=false; controls.forEach(control=>control.disabled=false); }
    }
    const connectedStudent = r => `<div class="name-cell"><strong>${escapeHTML(r.user?.name||r.name)}</strong><span>${escapeHTML(r.user?.student_id||r.student_id)}</span></div>`;
    const connectedSchedule = r => r.schedule ? `${r.schedule.title} · ${r.schedule.date}` : '—';
    const findRegistration = id => [...cfoState.registrations,...(cfoState.history||[])].find(r=>String(r.id)===String(id));
    function connectedGrid(config) {
      $('#page').innerHTML=(config.beforeGrid||'')+'<div id="grid-host"></div>';
      const search=window.CfoSearch.gridOptions(config.key);
      return dataGrid({id:r=>String(r.id),toolbar:button('Export CSV','export-grid','','','download'),...config,...search,...config.searchOptions,columns:config.columns.map(column=>({...column,...(!column.get&&column.label==='Student'?{sort:r=>r.user?.name||r.name}:{}),...(!column.get&&column.label==='Status'?{sort:r=>r.status}:{})}))});
    }
    function syncAdminSearch() {
      const {route,params}=routeDetails();
      if(['admin-dashboard','admin-all-student','admin-student-attendance','admin-schedule-change-requests','admin-password-resets','admin-history','admin-recollection-schedule','admin-add-new-recollection-schedule','admin-activity','admin-certificate','admin-certificate-template','admin-settings','admin-lan-access'].includes(route)){window.CfoSearch.mount($('#cfo-context-search'),null);return;}
      const context=window.CfoSearch.buildContext({role:window.CFO_BACKEND.user.role,user:window.CFO_BACKEND.user,state:cfoState,route,params,rows:activeGrid?.filtered(),recoveryStatus});
      window.CfoSearch.mount($('#cfo-context-search'),context,entry=>{
        const handlers={student:connectedStudentEdit,registration:connectedRegistration,attendance:connectedRegistration,receipt:connectedReceipt,history:connectedHistoryView,request:connectedRequest,recovery:connectedRecoveryReview};
        if(handlers[entry.kind])handlers[entry.kind](String(entry.id));
        else if(entry.kind==='announcement') { const row=cfoState.announcements.find(r=>r.id===entry.id);if(row)showDialog(row.title,`<p style="white-space:pre-wrap">${escapeHTML(row.body)}</p>`); }
        else if(entry.kind==='filter'&&activeGrid){activeGrid.state.q=entry.query;activeGrid.state.page=1;$('#data-grid [data-search]').value=entry.query;activeGrid.draw();$('#data-grid [data-search]').focus();}
      });
    }
    function adminRouteResources(route,params) {
      if(route==='admin-dashboard')return ['users','schedules'];
      if(route==='admin-all-student')return ['users'];
      if(route==='admin-course-year')return ['academic_options'];
      if(['admin-history','admin-student-reciept','admin-student-attendance','admin-certificate'].includes(route))return ['registrations'];
      if(route==='admin-announcements')return ['announcements'];
      if(['admin-recollection-schedule','admin-add-new-recollection-schedule'].includes(route))return ['schedules','academic_options'];
      if(route==='admin-schedule-change-requests')return ['schedule_changes'];
      if(route==='admin-settings')return [params.get('tab')==='admins'?'users':params.get('tab')==='office'?'settings':'notifications'];
      if(route==='admin-certificate-template')return ['settings'];
      if(route==='admin-password-resets')return ['password_resets'];
      if(route==='admin-activity')return ['activity_logs'];
      return ['notifications'];
    }
    function adminRouteStateKeys(route,params) {
      if(route==='admin-dashboard')return ['users','registrations','schedules'];
      if(route==='admin-all-student')return ['users'];
      if(route==='admin-course-year')return ['academic_options','course_year_sections'];
      if(route==='admin-history')return ['history'];
      if(['admin-student-reciept','admin-certificate'].includes(route))return ['registrations'];
      if(route==='admin-student-attendance')return ['registrations','schedules'];
      if(route==='admin-announcements')return ['announcements'];
      if(route==='admin-recollection-schedule')return ['schedules','academic_options'];
      if(route==='admin-add-new-recollection-schedule')return ['schedules','academic_options','course_year_sections'];
      if(route==='admin-schedule-change-requests')return ['requests'];
      if(route==='admin-settings')return params.get('tab')==='admins'?['users']:params.get('tab')==='office'?['settings']:[];
      if(route==='admin-certificate-template')return ['template'];
      if(route==='admin-password-resets')return ['resets'];
      if(route==='admin-activity')return ['logs'];
      return [];
    }
    function renderAdminRouteContent(route,params) {
      if(route==='admin-dashboard') connectedDashboard();
      else if(route==='admin-all-student') connectedDirectory();
      else if(route==='admin-course-year') connectedAcademic();
      else if(['admin-register-this-year','admin-history'].includes(route)) connectedRegistrations(route==='admin-history');
      else if(route==='admin-announcements') connectedAnnouncements();
      else if(route==='admin-recollection-schedule') connectedSchedules();
      else if(route==='admin-add-new-recollection-schedule') connectedScheduleForm(params);
      else if(route==='admin-student-reciept') connectedReceipts();
      else if(route==='admin-student-attendance') connectedAttendance();
      else if(route==='admin-schedule-change-requests') connectedRequests();
      else if(route==='admin-settings') connectedSettings(params);
      else if(route==='login') location.href='/#login';
      else if(route==='admin-certificate') connectedCertificates();
      else if(route==='admin-certificate-template') connectedTemplate();
      else if(route==='admin-password-resets') connectedRecovery();
      else if(route==='admin-activity') connectedActivity();
      else if(route==='admin-lan-access') connectedLanAccess();
      else $('#page').innerHTML=empty('Page not found','Choose a page from the navigation.');
      if(window.CfoSearch)syncAdminSearch();
      hydrateIcons();
    }
    async function renderConnectedAdmin(changedResources = null) {
      const liveRefresh = Array.isArray(changedResources) && changedResources.length > 0;
      const version=++cfoRouteVersion;
      if (!liveRefresh) { pendingConnectedSave=null; closeDialog(); closeNavigation(); activeGrid=null; }
      if (!liveRefresh) { schedulePageController?.destroy?.(); schedulePageController=null; }
      const {route,params}=routeDetails(); currentRoute=route;
      const scheduleWorkspace=['admin-recollection-schedule','admin-add-new-recollection-schedule'].includes(route);
      if(!scheduleWorkspace){mySchedulesController?.destroy();mySchedulesController=null;}
      const creatingSchedule=route==='admin-add-new-recollection-schedule';
      const editingSchedule=creatingSchedule&&params.has('id');
      $('.page-heading').hidden=scheduleWorkspace;
      if(route==='admin-register-this-year'){window.location.replace('/admin/recollection-registrations'+(params.size?'?'+params.toString():''));return;}
      if(!liveRefresh && params.size) {
        const filterGrid={'admin-all-student':'connected-students','admin-recollection-schedule':'connected-schedules','admin-student-reciept':'connected-receipts'}[route];
        if(filterGrid)gridState.delete(filterGrid);
      }
      const meta=pages[route]||['Page not found','','Workspace'];
      const requestedResources=liveRefresh?changedResources:adminRouteResources(route,params);
      const canRenderCached=!liveRefresh&&!scheduleWorkspace&&cfoState!==null&&adminRouteStateKeys(route,params).every(key=>Object.hasOwn(cfoState,key));
      const headerName=editingSchedule?'Edit Recollection Schedule':$$('.nav-link').find(link=>link.dataset.route===route)?.querySelector('span')?.textContent || meta[0];
      const headerAncestors=route==='admin-dashboard'?[]:[{name:'Dashboard',href:'/admin#admin-dashboard'}];
      if(route==='admin-add-new-recollection-schedule')headerAncestors.push({name:navLabel('admin-recollection-schedule'),href:'/admin#admin-recollection-schedule'});
      window.AdminHeader?.setPage(headerName,headerAncestors);
      if (!liveRefresh) { $('#page-title').textContent=meta[0]; $('#page-description').textContent=meta[1]; $('#page-category').textContent=meta[2]; $('#page-actions').innerHTML=''; if(!canRenderCached)$('#page').innerHTML=note('Loading saved records',true); }
      if(window.CfoSearch&&!liveRefresh){if(['admin-dashboard','admin-student-attendance','admin-schedule-change-requests','admin-password-resets','admin-history','admin-activity','admin-certificate','admin-certificate-template','admin-settings','admin-lan-access'].includes(route)||scheduleWorkspace)window.CfoSearch.mount($('#cfo-context-search'),null);else window.CfoSearch.pending($('#cfo-context-search'));}
      try {
        window.CfoAdminSettingsNavigation?.mount()?.sync(route,params);
        const parent={'admin-add-new-recollection-schedule':'admin-recollection-schedule','admin-lan-access':'admin-settings'}[route]||route;
        $$('.nav-link').forEach(a=>{a.classList.toggle('active',a.dataset.route===parent);if(a.dataset.route===parent)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current');});
        if(canRenderCached)renderAdminRouteContent(route,params);
        const url='/api/admin/state?include_history='+(route==='admin-history'?'1':'0')+'&resources='+encodeURIComponent(requestedResources.join(','));
        const state=await backend(url);
        if(version!==cfoRouteVersion)return;
        if(liveRefresh&&isAdminEditing()){changedResources.forEach(resource=>pendingLiveResources.add(resource));return;}
        const oldRegistrations=cfoState?.registrations||[];
        const oldUsers=cfoState?.users||[];
        cfoState={...(cfoState||{}),...state};
        const currentUser=state.users?.find(user=>user.id===window.CFO_BACKEND.user.id);
        if(currentUser)window.CFO_BACKEND.user=currentUser;
        $$('.account:not(.admin-header-account) strong, .sidebar-footer strong, .sidebar-profile-name').forEach(e=>e.textContent=window.CFO_BACKEND.user.name);
        $$('.avatar:not(#admin-header-avatar)').forEach(e=>e.textContent=window.CFO_BACKEND.user.name.slice(0,2).toUpperCase());
        if(liveRefresh&&changedResources.includes('registrations')&&state.registrations){const oldIds=new Set(oldRegistrations.map(row=>String(row.id)));const created=state.registrations.find(row=>!oldIds.has(String(row.id)));if(created){window.CFO_LIVE_HIGHLIGHT_ID=created.id;toast('New registration: '+(created.user?.name||'student'));}}
        if(liveRefresh&&changedResources.includes('users')&&state.users){const oldIds=new Set(oldUsers.map(row=>String(row.id)));const created=state.users.find(row=>!oldIds.has(String(row.id)));if(created){window.CFO_LIVE_HIGHLIGHT_ID=created.id;toast('New '+created.role.toLowerCase()+' account: '+created.name);}}
        renderAdminRouteContent(route,params);
        if(!liveRefresh){if(!mySchedulesController?.isModalOpen())($('#ms-page-title') || $('#attendance-heading') || (route==='admin-history'?($('#history-heading') || $('#main-content')):creatingSchedule?$('#main-content'):$('#page-title'))).focus({preventScroll:true});$('#main-content').scrollTo?.({top:0,behavior:'instant'});}
      } catch(error) { if(liveRefresh)throw error;if(version===cfoRouteVersion) {if(canRenderCached)toast('Unable to refresh records: '+error.message);else{$('#page').innerHTML=empty('Unable to load records',error.message,button('Retry','connected-reload'));if(!['admin-dashboard','admin-student-attendance','admin-schedule-change-requests','admin-password-resets','admin-history','admin-activity','admin-certificate','admin-certificate-template','admin-settings','admin-lan-access'].includes(route))window.CfoSearch?.pending($('#cfo-context-search'),'Search unavailable. Use Retry below to reload this page.',true);}} }
    }
    function connectedDashboard() {
      $('#page-actions').innerHTML='';
      const students=cfoState.users.filter(u=>u.role==='Student');
      const next=cfoState.schedules.filter(s=>s.active && !s.finalized_at && s.year===cfoState.current_year && s.date>=cfoState.server_date).sort((a,b)=>a.date.localeCompare(b.date))[0];
      let summary='<p>No upcoming annual schedule is published.</p>';
      if(next){
        const status=window.CfoMySchedules.statusOf(next);
        const rows=[['Title',escapeHTML(next.title||'—')],['Course(s)',window.CfoMySchedules.courseGroupsHtml(next)],['Date',escapeHTML(window.CfoScheduleCreate.formatDate(next.date))],['Time',escapeHTML(window.CfoMySchedules.calendarTime(next).replace('–',' – '))],['Venue',escapeHTML(next.venue||'Not set')],['Capacity',escapeHTML(next.slot_limit==null||next.slot_limit===''?'No limit':next.slot_limit+' students')],['Status',`<span class="dashboard-schedule-status${status==='Open'?'':' is-closed'}">${escapeHTML(status==='Open'?'Open for Registration':status)}</span>`]];
        summary='<div class="dashboard-schedule-summary"><dl class="dashboard-schedule-list">'+rows.map(([label,value])=>'<div class="dashboard-schedule-row"><dt>'+escapeHTML(label)+'</dt><dd>'+value+'</dd></div>').join('')+'</dl></div>';
      }
      $('#page').innerHTML=`<div class="stack dashboard-overview"><div class="metrics">${metric('Students',students.length,'Student accounts','users','admin-all-student')}${metric('Awaiting approval',students.filter(u=>u.status==='Pending').length,'Approve verified students','register','admin-all-student?status=Pending')}${metric('New registrations',cfoState.registrations.filter(r=>!r.is_archived&&!r.recollection_schedule_id).length,'Awaiting schedule for '+cfoState.current_year,'register','admin-register-this-year')}${metric('Pending receipts',cfoState.registrations.filter(r=>r.has_receipt&&r.receipt_status==='Pending').length,'Review uploaded receipts','receipt','/admin/recollection-registrations?status=Pending')}</div>${panel('Next annual recollection',summary,link('Manage schedules','admin-recollection-schedule'))}</div>`;
    }
    function connectedDirectory() {
      const rows=cfoState.users.filter(u=>u.role==='Student');
      const params=routeDetails().params;
      connectedGrid({key:'connected-students',hideClearFilters:true,filterQuery:params.toString(),initialFilters:{status:params.get('status')||''},title:'Student accounts',rows:()=>rows,filters:['course','year_level','section','status'].map(k=>objectFilter(k,k.replace('_',' '),rows)),columns:[{label:'Student',html:connectedStudent},{label:'Registered Gmail',get:r=>r.original_email||r.email},{label:'Login email',get:r=>r.login_email||r.system_username||'—'},{label:'Course',get:r=>r.course||'—'},{label:'Year / Section',get:r=>[r.year_level,r.section].filter(Boolean).join(' · ')||'—'},{label:'Status',html:r=>badge(r.status)}],rowActions:r=>button('Edit / Approve','connected-student',r.id,'compact')});
    }
    function connectedStudentEdit(id) {
      const r=cfoState.users.find(r=>String(r.id)===id);
      showDialog('Student account',`<form id="connected-student-form"><div class="form-grid">${field('Student ID','student_id',r.student_id,'text','required')}${field('Registered Gmail','email',r.original_email||r.email,'email','required')}${field('First name','first_name',r.first_name,'text','required')}${field('Middle name','middle_name',r.middle_name||'')}${field('Last name','last_name',r.last_name,'text','required')}${selectField('Account status','status',['Pending','Approved','Rejected'],r.status)}</div><div class="form-actions"><button class="btn primary" type="submit">Save account</button></div></form>`,'' );
      window.CfoAccountIds.bind($('#connected-student-form [name=student_id]'),{allowOriginal:true,originalValue:r.student_id});
      $('#connected-student-form').onsubmit=e=>{e.preventDefault();const values=Object.fromEntries(new FormData(e.target));saveConnected('/api/admin/users/'+id,'PATCH',values);};
    }
    // Registration -> schedule batch -> attendance -> permanent history.
let batchSaveBusy = false;
let attendanceView = 'students';
let attendanceUnmarkedFirst = false;
let selectedReview = null;

function lockBatchControls() {
    const controls=[...document.querySelectorAll('#page button,#page input,#page select'),...dialog.querySelectorAll('button,input,select,textarea')];
    const previous=controls.map(control=>[control,control.disabled]);
    controls.forEach(control=>control.disabled=true);
    $('#page').setAttribute('aria-busy','true');
    dialog.setAttribute('aria-busy','true');
    return ()=>{previous.forEach(([control,disabled])=>control.disabled=disabled);$('#page').removeAttribute('aria-busy');dialog.removeAttribute('aria-busy');};
}

async function saveBatchAction(url, payload, title='Saved successfully') {
    if(batchSaveBusy)return;
    batchSaveBusy=true;
    if(!dialog.open)showDialog('Saving attendance','<p role="status">Saving the record to the CFO database…</p>','');
    dialog.querySelector('.dialog-body')?.insertAdjacentHTML('beforeend','<p data-batch-feedback class="batch-feedback" role="status">Saving…</p>');
    const feedback=dialog.querySelector('[data-batch-feedback]');
    const unlock=lockBatchControls();
    try {
        const result=await backend(url,'POST',payload);
        closeDialog();
        await renderConnectedAdmin();
        showDialog(title,`<p role="status">${escapeHTML(result.message)}</p>`,button('Done','close-dialog'));
    } catch(error) { if(feedback)feedback.textContent=error.message;window.CfoUsability?.formError(dialog.querySelector('form'),error);toast(error.message); }
    finally { batchSaveBusy=false;unlock(); }
}

function scheduleUnavailable(schedule, registration) {
    if(schedule.finalized_at)return 'Batch already in History';
    if(!schedule.active)return 'Closed';
    if(schedule.year!==registration.year)return 'Different recollection year';
    if(schedule.date<cfoState.server_date)return 'Past schedule';
    if(schedule.id!==registration.recollection_schedule_id && schedule.registrations_count >= (schedule.slot_limit??schedule.capacity))return 'Full';
    return '';
}

function connectedRegistration(id) {
    const r=findRegistration(id);
    if(!r)return;
    if(r.is_archived){connectedHistoryView(id);return;}
    const schedules=cfoState.schedules||[];
    const receipt=r.has_receipt?`<a class="btn" href="/api/receipts/${r.id}" target="_blank" rel="noopener">View receipt</a><p><img src="/api/receipts/${r.id}" alt="Student receipt" style="max-width:100%;max-height:280px;object-fit:contain"></p>`:'<p class="note">The student must upload a receipt before a schedule can be saved.</p>';
    showDialog(r.recollection_schedule_id?'Edit student schedule':'Review registration and set schedule',
        details([['Student',r.user.name],['ID number',r.user.student_id],['Recollection year',r.year],['Registration',r.status],['Receipt',r.receipt_status],['Schedule',r.schedule?.title||'Not assigned'],['Date',r.schedule?.date||'—'],['Time',r.schedule?.time||'—'],['Venue',r.schedule?.venue||'—'],['Attendance',r.attendance],['Attendance verified',r.attendance_verified_at||'Not verified'],['Certificate',r.certificate?'Available':'Not available'],...Object.entries(r.details||{})])+receipt+(r.has_receipt?'<p>'+button('Review receipt status','connected-receipt',r.id)+'</p>':'')+
        `<form id="review-registration"><label class="field">Recollection schedule<select name="schedule_id" required><option value="">Choose a schedule</option>${schedules.map(s=>{const reason=scheduleUnavailable(s,r);return `<option value="${s.id}" ${s.id===r.recollection_schedule_id?'selected':''} ${reason?'disabled':''}>${escapeHTML(s.title+' · '+s.date+' · '+s.time+(reason?' — '+reason:''))}</option>`;}).join('')}</select></label><p class="muted">Saving confirms that you reviewed the registration and receipt, and moves this student to Attendance.</p>${r.attendance!=='Not Yet Recorded'?'<label class="check-label"><input name="confirm_attendance_reset" type="checkbox" required> I understand that a different schedule clears attendance and withdraws the current certificate until attendance is marked again.</label>':''}<div class="form-actions"><button type="submit" class="btn primary" ${!r.has_receipt?'disabled':''}>Save</button>${button('Cancel','close-dialog')}</div></form>`,'',true);
    $('#review-registration').onsubmit=event=>{event.preventDefault();saveBatchAction('/api/admin/registrations/'+r.id+'/schedule',{schedule_id:Number(event.target.elements.schedule_id.value),confirm_attendance_reset:Boolean(event.target.elements.confirm_attendance_reset?.checked)},'Schedule successfully set');};
}

function connectedAttendance() {
    const rows=cfoState.registrations.filter(r=>!r.is_archived && r.year===cfoState.current_year && r.schedule && !r.schedule.finalized_at && r.status==='Approved');
    const batchId=routeDetails().params.get('batch');
    const schedules=new Map((cfoState.schedules||[]).filter(s=>s.year===cfoState.current_year && !s.finalized_at && (s.active||rows.some(r=>String(r.recollection_schedule_id)===String(s.id)))).map(s=>[String(s.id),s]));
    rows.forEach(r=>{if(!r.schedule.finalized_at)schedules.set(String(r.recollection_schedule_id),r.schedule);});
    const available=[...schedules.values()].sort((a,b)=>a.date.localeCompare(b.date)||a.id-b.id);
    const schedule=schedules.get(batchId);
    const participants=schedule?rows.filter(r=>String(r.recollection_schedule_id)===String(schedule.id)):[];
    $('#page-title').textContent='Recollection Attendance';
    $('#page-description').textContent='Record student attendance for each recollection schedule.';
    $('#page-actions').innerHTML='';
    const groups=new Map();
    participants.forEach(r=>{const group=attendanceAcademicGroup(r),name=[group.course,[group.year_level,group.section].filter(Boolean).join('')].filter(Boolean).join(' · ')||'Academic details not provided';groups.set(name,(groups.get(name)||0)+1);});
    const tabs=`<div id="attendance-tabs" class="attendance-tabs" role="tablist" aria-label="Attendance views">${[['students','Student Attendance','users'],['health','Health & Religion','heart']].map(([view,label,symbol])=>`<button type="button" id="attendance-tab-${view}" role="tab" aria-selected="${attendanceView===view}" aria-controls="grid-host" tabindex="${attendanceView===view?'0':'-1'}" class="attendance-tab ${attendanceView===view?'active':''}" data-action="attendance-view" data-id="${view}">${icon(symbol)}${label}</button>`).join('')}</div>`;
    const header=`<section class="panel attendance-schedule" aria-labelledby="attendance-heading"><div class="attendance-heading"><span class="attendance-heading-icon">${icon('calendar')}</span><div><h1 id="attendance-heading" tabindex="-1">Recollection Attendance</h1><p>Record student attendance for each recollection schedule.</p></div></div>${tabs}<label class="attendance-schedule-label" for="attendance-schedule">Select Recollection Schedule</label><select id="attendance-schedule"><option value="">Choose a recollection schedule</option>${available.map(s=>`<option value="${s.id}" ${String(s.id)===batchId?'selected':''}>${escapeHTML(attendanceDate(s.date)+' · '+attendanceTime(s.start_time)+' – '+attendanceTime(s.end_time)+' · '+s.venue+' · '+s.title)}</option>`).join('')}</select>${schedule?`<div class="attendance-schedule-meta"><span>${icon('calendar')}${escapeHTML(attendanceDate(schedule.date))}</span><span>${icon('clock')}${escapeHTML(attendanceTime(schedule.start_time)+' – '+attendanceTime(schedule.end_time))}</span><span>${icon('home')}${escapeHTML(schedule.venue)}</span></div>${groups.size?`<div class="attendance-groups"><span>Participating Courses:</span>${[...groups].map(([name,count])=>`<span class="attendance-chip">${escapeHTML(name)} <b>${count}</b></span>`).join('')}</div>`:''}`:''}</section>`;
    if(!schedule){
        activeGrid=null;
        $('#page').innerHTML=header+'<div id="grid-host">'+empty(available.length?'Choose a recollection schedule':'No active attendance batches',available.length?(batchId?'The selected batch is no longer active. Select another schedule or open History.':'Select a schedule above to view its students and record attendance.'):'Students appear here after their registration, receipt, and schedule are approved.')+'</div>';
        initializeAttendanceSelector();
        return;
    }
    if(attendanceView==='health'){attendanceInformationGrid(schedule,participants,header);initializeAttendanceSelector();return;}
    const unmarked=attendanceBatchRows(schedule.id).filter(r=>!['Present','Absent'].includes(r.attendance)).length;
    const progress='<section class="panel attendance-progress" aria-label="Attendance progress"><div class="attendance-progress-text"><strong data-attendance-progress-text>0 of 0 marked (0%)</strong><span data-attendance-progress-note></span></div><div class="attendance-progress-track" role="progressbar" aria-label="Students marked" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-attendance-progress-bar><span></span></div></section>';
    const religions=[...new Set(participants.map(r=>attendanceDetail(r,'religion')).filter(Boolean))].sort();
    connectedGrid({key:'batch-attendance-'+schedule.id,title:schedule.title,noun:'students matched',rows:()=>attendanceRosterSort(participants),selectable:true,compactFilters:true,expandableFilters:true,
        filterToolbar:button('Mark All Present','batch-present-all',schedule.id,'attendance-mark-all','check')+`<button type="button" class="btn attendance-unmarked-toggle" data-action="attendance-unmarked-first" aria-pressed="${attendanceUnmarkedFirst}">${icon('clock')} Unmarked first: ${attendanceUnmarkedFirst?'On':'Off'}</button>`,
        toolbarBefore:`<div class="attendance-status-counts" role="status" aria-label="Attendance totals for matching students"><span class="attendance-pill present">Present <b data-attendance-count="Present">0</b></span><span class="attendance-pill absent">Absent <b data-attendance-count="Absent">0</b></span><span class="attendance-pill pending">Not yet <b data-attendance-count="Not Yet Recorded">0</b></span></div><div class="attendance-bulkbar" data-attendance-bulk hidden><b data-bulk-count>0 selected</b>${button('Present','batch-selected-present',schedule.id,'attendance-mark present','check')}${button('Absent','batch-selected-absent',schedule.id,'attendance-mark absent','close')}<button type="button" class="btn compact" data-action="batch-select-matching" data-id="" data-bulk-matching hidden>Select all matching</button>${button('Clear','batch-selected-clear','','compact')}</div>`,
        toolbar:button('Print','connected-print','','','print')+button('Export','attendance-export','','','download'),
        beforeGrid:header+progress,
        filters:[...['course','year_level','section'].map(key=>({key,label:{course:'Course',year_level:'Year Level',section:'Section'}[key],values:[...new Set(participants.map(r=>attendanceAcademicGroup(r)[key]).filter(Boolean))].sort(),get:r=>attendanceAcademicGroup(r)[key]})),{key:'attendance',label:'Attendance',values:['Present','Absent','Not Yet Recorded'],get:r=>r.attendance},{key:'health',label:'Health / Allergies',values:['With Health Condition / Allergy','No Reported Health Condition'],get:attendanceHealthLabel},{key:'religion',label:'Religion',values:religions,get:r=>attendanceDetail(r,'religion')}],
        columns:[...attendanceStudentColumns().filter(column=>column.label!=='ID Number'),{label:'Alerts',get:attendanceAlertText,sort:attendanceAlertSort,html:attendanceAlertCell},{label:'Attendance',get:r=>r.attendance,html:attendanceControls},{label:'Marked at',get:attendanceMarkedAt,sort:r=>r.attendance_verified_at||'',nowrap:true}],
        rowActions:r=>button('View','connected-registration',r.id,'compact attendance-view','eye'),
        afterDraw:matching=>{for(const status of ['Present','Absent','Not Yet Recorded'])$(`[data-attendance-count="${status}"]`).textContent=matching.filter(r=>r.attendance===status).length;updateAttendanceProgress(participants);updateAttendanceBulkBar(matching.length);}});
    $('#data-grid').classList.add('attendance-workspace');
    $('#data-grid').insertAdjacentHTML('beforeend',`<footer class="attendance-completion"><div class="attendance-completion-actions">${button('Complete Attendance','batch-complete',schedule.id,'primary','check')}${button('Move to Recollection History','batch-archive',schedule.id,'attendance-history','history')}</div><p>${participants.length?`${unmarked} student${unmarked===1?'':'s'} remaining to mark. Every student in this batch must be Present or Absent before it can move to History.`:'No eligible students have been assigned to this schedule yet.'}</p></footer>`);
    if(!participants.length)$('#data-grid .table-empty').textContent='No approved students are assigned to this schedule yet.';
    initializeAttendanceSelector();
}

function attendanceStudentName(r) {return r.user?.last_name&&r.user?.first_name?r.user.last_name+', '+r.user.first_name:r.user?.name||'Student';}
function attendanceAcademicGroup(r) {return window.CfoSearch.registrationAcademicGroup(r);}
function attendanceAcademicLabel(r) {
    const group=attendanceAcademicGroup(r);
    return [group.course,[group.year_level,group.section].filter(Boolean).join('')].filter(Boolean).join(' – ')||'—';
}
function attendanceStudentColumns() {
    return [{label:'#',rowNumber:true},{label:'Student Name',get:attendanceStudentName,html:r=>`<strong class="attendance-student">${escapeHTML(attendanceStudentName(r))}</strong><small class="attendance-cell-detail">${escapeHTML(r.user?.student_id||'—')}</small>`},{label:'ID Number',get:r=>r.user?.student_id||'—',nowrap:true},{label:'Course – Year & Section',get:attendanceAcademicLabel,html:r=>{const group=attendanceAcademicGroup(r);return `<strong class="attendance-course">${escapeHTML(group.course||'—')} – ${escapeHTML([group.year_level,group.section].filter(Boolean).join('')||'—')}</strong><small class="attendance-cell-detail">Year ${escapeHTML(group.year_level||'—')} · Section ${escapeHTML(group.section||'—')}</small>`;}}];
}
function attendanceDetail(r,key) {return typeof r.details?.[key]==='string'?r.details[key].trim():'';}
function attendancePayment(r) {return r.receipt_status==='Approved'?'Paid':'Unpaid';}
function attendanceReportedHealth(r) {
    return [['Medical condition','medical'],['Allergies','allergies']].map(([label,key])=>({label,value:attendanceDetail(r,key)}))
        .filter(item=>item.value&&!/^(none|no|n\/?a|nil|not applicable|wala|without|no (known )?(allergies|medical conditions|health conditions))[.!\s]*$/i.test(item.value));
}
function attendanceHealthLabel(r) {return attendanceReportedHealth(r).length?'With Health Condition / Allergy':'No Reported Health Condition';}
// ---- Tab 2 shared renderers (pure functions; the Chrome preview reuses them) ----
const attendanceNoneLike=value=>/^(none|no|n\/?a|nil|not applicable|wala|without|no (known )?(allergies|medical conditions|health conditions|medications?))[.!\s]*$/i.test(String(value||'').trim());
function attendanceIsCatholic(r) {return /catholic/i.test(attendanceDetail(r,'religion'));}
function attendanceShown(r,key) {const value=attendanceDetail(r,key);return value&&!attendanceNoneLike(value)?value:'';}
function attendanceMedications(r) {return attendanceShown(r,'medications');}
function attendanceHasHealthInfo(r) {return attendanceReportedHealth(r).length>0||attendanceMedications(r)!=='';}
function attendanceHealthItems(r) {
    const items=[...attendanceReportedHealth(r)],meds=attendanceMedications(r);
    if(meds)items.push({label:'Medications',value:meds});
    const rank=item=>({'Allergies':0,'Medical condition':1,'Medications':2})[item.label]??3;
    return items.sort((a,b)=>rank(a)-rank(b));
}
function attendanceHealthCell(r) {
    const items=attendanceHealthItems(r);
    if(!items.length)return '<span class="attendance-pill neutral">● None reported</span>';
    const flagged=attendanceReportedHealth(r).length>0;
    return `<div class="attendance-health-values"><span class="attendance-pill ${flagged?'absent':'pending'}">${flagged?'● With Health / Allergy':'● Medication only'}</span>${items.map(item=>`<small><b>${escapeHTML(item.label)}:</b> ${escapeHTML(item.value)}</small>`).join('')}</div>`;
}
function attendanceHealthText(r) {return attendanceHealthItems(r).map(item=>item.label+': '+item.value).join(' · ')||'None reported';}
function attendanceHealthSort(r) {return (attendanceReportedHealth(r).length?'0':attendanceMedications(r)?'1':'2')+attendanceStudentName(r);}
function attendanceFaithCell(r) {
    const religion=attendanceDetail(r,'religion');
    if(!religion)return '<span class="attendance-pill neutral">Not provided</span>';
    const catholic=attendanceIsCatholic(r),stated=!/^prefer not to say$/i.test(religion),parish=attendanceDetail(r,'parish');
    const mark=key=>/^yes$/i.test(attendanceDetail(r,key))?'✓':/^no$/i.test(attendanceDetail(r,key))?'✗':'–';
    const sacraments=catholic?`<small class="attendance-cell-detail">Baptism ${mark('baptism')} · Communion ${mark('communion')} · Confirmation ${mark('confirmation')}</small>`:'';
    return `<span class="attendance-pill ${catholic?'paid':stated?'pending':'neutral'}">${escapeHTML(religion)}</span>${sacraments}${parish?`<small class="attendance-cell-detail">Parish: ${escapeHTML(parish)}</small>`:''}`;
}
function attendanceFaithText(r) {return attendanceDetail(r,'religion')||'Not provided';}
function attendanceEmergencyText(r) {
    const name=attendanceDetail(r,'emergencyName'),relation=attendanceDetail(r,'relationship'),phone=attendanceDetail(r,'emergencyPhone');
    return [name,relation&&'('+relation+')',phone].filter(Boolean).join(' ')||'Not provided';
}
function attendanceEmergencyCell(r) {
    const name=attendanceDetail(r,'emergencyName'),relation=attendanceDetail(r,'relationship'),phone=attendanceDetail(r,'emergencyPhone');
    if(!name&&!phone)return '<small class="attendance-cell-detail">Not provided</small>';
    return `<strong class="attendance-student">${escapeHTML(name||'—')}</strong><small class="attendance-cell-detail">${escapeHTML(relation)}${relation&&phone?' · ':''}${phone?`<a href="tel:${escapeHTML(phone.replace(/[^\d+]/g,''))}">${escapeHTML(phone)}</a>`:''}</small>`;
}
function attendanceHealthColumns() {
    return [...attendanceStudentColumns().filter(column=>column.label!=='ID Number'),
        {label:'Attendance',get:r=>r.attendance,html:r=>`<span class="attendance-pill ${r.attendance==='Present'?'present':r.attendance==='Absent'?'absent':'pending'}">${escapeHTML(r.attendance)}</span>`},
        {label:'Health / Allergies',get:attendanceHealthText,sort:attendanceHealthSort,html:attendanceHealthCell},
        {label:'Religion',get:attendanceFaithText,html:attendanceFaithCell},
        {label:'Emergency contact',get:attendanceEmergencyText,html:attendanceEmergencyCell}];
}
function attendanceHealthRoster(rows) {return [...rows].sort((a,b)=>attendanceHealthSort(a).localeCompare(attendanceHealthSort(b),undefined,{sensitivity:'base'}));}
function attendanceAllergenCounts(rows) {
    const counts=new Map();
    rows.forEach(r=>{
        const value=attendanceShown(r,'allergies');if(!value)return;
        value.split(/[,;\/&\n]+|\band\b/i).map(part=>part.trim().toLowerCase().replace(/[.\s]+$/,'')).filter(Boolean).forEach(part=>counts.set(part,(counts.get(part)||0)+1));
    });
    return [...counts].sort((a,b)=>b[1]-a[1]||a[0].localeCompare(b[0]));
}
function renderAttendanceAllergens(rows) {
    const list=$('[data-allergen-list]');if(!list)return;
    const counts=attendanceAllergenCounts(rows);
    $('[data-allergen-note]').textContent=counts.length?' · counted from what students typed, so spellings may differ':'';
    list.innerHTML=counts.length?counts.slice(0,10).map(([name,count])=>`<span class="attendance-chip">${escapeHTML(name.charAt(0).toUpperCase()+name.slice(1))} <b>${count}</b></span>`).join('')+(counts.length>10?`<span class="muted">+${counts.length-10} more</span>`:''):'<span class="muted">No reported allergies for the students in this view.</span>';
}
function attendanceHealthAfterDraw(matching,filters) {
    const set=(key,test)=>{const element=$(`[data-health-count="${key}"]`);if(element)element.textContent=matching.filter(test).length;};
    set('Present',r=>r.attendance==='Present');
    set('Catholic',attendanceIsCatholic);
    set('Other',r=>{const religion=attendanceDetail(r,'religion');return religion&&!attendanceIsCatholic(r)&&!/^prefer not to say$/i.test(religion);});
    set('health',r=>attendanceReportedHealth(r).length>0);
    renderAttendanceAllergens(matching);
    $('.attendance-present-only')?.setAttribute('aria-pressed',String(filters.attendance==='Present'));
}
function attendanceInformationPairs(r) {
    const value=key=>attendanceDetail(r,key)||'Not provided';
    const group=attendanceAcademicGroup(r);
    return [['Student Name',attendanceStudentName(r)],['Student ID',r.user?.student_id],['Course',group.course],['Year Level',group.year_level],['Section',group.section],
        ['Religion',value('religion')],['Parish',value('parish')],['Baptism',value('baptism')],['Holy Communion',value('communion')],['Confirmation',value('confirmation')],
        ['Allergies',value('allergies')],['Medical Condition',value('medical')],['Medications',value('medications')],
        ['Emergency Contact',value('emergencyName')],['Relationship',value('relationship')],['Emergency Number',value('emergencyPhone')],
        ['Attendance',r.attendance],['Payment',attendancePayment(r)],['Receipt Status',r.receipt_status],['Recollection Schedule',r.schedule?.title],
        ['Date',r.schedule?attendanceDate(r.schedule.date):'—'],['Time',r.schedule?attendanceTime(r.schedule.start_time)+' – '+attendanceTime(r.schedule.end_time):'—'],['Location',r.schedule?.venue]];
}
function attendanceHealthPrintMarkup(rows,scheduleLine) {
    const cell=value=>`<td>${escapeHTML(value||'—')}</td>`;
    return `<p>${escapeHTML(scheduleLine)}</p><table><thead><tr><th>Student</th><th>Course – Year &amp; Section</th><th>Attendance</th><th>Allergies</th><th>Medical condition</th><th>Medications</th><th>Emergency contact</th></tr></thead><tbody>${rows.map(r=>`<tr>${cell(attendanceStudentName(r))}${cell(attendanceAcademicLabel(r))}${cell(r.attendance)}${cell(attendanceShown(r,'allergies'))}${cell(attendanceShown(r,'medical'))}${cell(attendanceMedications(r))}${cell(attendanceEmergencyText(r))}</tr>`).join('')}</tbody></table>`;
}
function attendanceHealthCsv(rows) {
    const value=(r,key)=>attendanceDetail(r,key);
    return {columns:['Student ID','Student Name','Course','Year','Section','Attendance','Religion','Parish','Baptism','Holy Communion','Confirmation','Allergies','Medical condition','Medications','Emergency contact','Relationship','Emergency number'],
        data:rows.map(r=>{const group=attendanceAcademicGroup(r);return [r.user?.student_id||'',attendanceStudentName(r),group.course,group.year_level,group.section,r.attendance,value(r,'religion'),value(r,'parish'),value(r,'baptism'),value(r,'communion'),value(r,'confirmation'),value(r,'allergies'),value(r,'medical'),value(r,'medications'),value(r,'emergencyName'),value(r,'relationship'),value(r,'emergencyPhone')];})};
}
// ---- end Tab 2 shared renderers ----

function attendanceInformationGrid(schedule,participants,header) {
    const religions=[...new Set(participants.map(r=>attendanceDetail(r,'religion')).filter(Boolean))].sort();
    connectedGrid({key:'batch-health-'+schedule.id,title:'Health & Religion',noun:'students matched',rows:()=>attendanceHealthRoster(participants),
        beforeGrid:header,compactFilters:true,expandableFilters:true,countFilterOptions:true,
        filterToolbar:`<button type="button" class="btn attendance-present-only" data-action="health-present-only" aria-pressed="false">${icon('check')} Present only</button>`,
        toolbarBefore:'<div class="attendance-status-counts" role="status" aria-label="Student information totals for matching students"><span class="attendance-pill present"><b data-health-count="Present"></b> Present</span><span class="attendance-pill paid"><b data-health-count="Catholic"></b> Roman Catholic</span><span class="attendance-pill pending"><b data-health-count="Other"></b> Other religion</span><span class="attendance-pill health"><b data-health-count="health"></b> With Health / Allergy</span></div>',
        toolbar:button('Print alert list','health-print',schedule.id,'','print')+button('Export','health-export','','','download'),
        filters:[{key:'attendance',label:'Attendance',values:['Present','Absent','Not Yet Recorded'],get:r=>r.attendance},
            {key:'health',label:'Health / Allergies',values:['With Health Condition / Allergy','No Reported Health Condition'],get:attendanceHealthLabel},
            {key:'religion',label:'Religion',values:religions,get:r=>attendanceDetail(r,'religion')},
            {key:'course',label:'Course',values:[...new Set(participants.map(r=>attendanceAcademicGroup(r).course).filter(Boolean))].sort(),get:r=>attendanceAcademicGroup(r).course}],
        columns:attendanceHealthColumns(),
        rowActions:r=>button('View','attendance-information',r.id,'compact attendance-view','eye'),
        afterDraw:matching=>attendanceHealthAfterDraw(matching,activeGrid?.state.filters||{})});
    $('#data-grid').classList.add('attendance-workspace','attendance-health-workspace');
    if(!participants.length)$('#data-grid .table-empty').textContent='No approved students are assigned to this schedule yet.';
}
function attendanceInformationView(id) {
    const r=findRegistration(id);
    if(!r||r.is_archived||r.year!==cfoState.current_year||r.status!=='Approved'||String(r.recollection_schedule_id)!==routeDetails().params.get('batch'))return;
    showDialog('Student Information',`<p class="muted">${escapeHTML(attendanceStudentName(r))}</p>`+details(attendanceInformationPairs(r)),button('Close','close-dialog'),true);
}
function printHealthAlerts(id) {
    if(!activeGrid)return;
    const rows=activeGrid.filtered().filter(attendanceHasHealthInfo);
    if(!rows.length){toast('No students with reported health or allergy information in this view.');return;}
    const schedule=(cfoState.schedules||[]).find(s=>String(s.id)===String(id));
    printPreview('Health & allergy alert list',attendanceHealthPrintMarkup(rows,schedule?schedule.title+' · '+attendanceDate(schedule.date):''));
}
function exportHealthRegistrations() {
    if(!activeGrid)return;
    const csv=attendanceHealthCsv(activeGrid.filtered());
    csvDownload(activeGrid.config.key+'.csv',csv.columns,csv.data);
}
function toggleHealthPresentOnly() {
    if(!activeGrid)return;
    const state=activeGrid.state;
    state.filters.attendance=state.filters.attendance==='Present'?'':'Present';
    state.page=1;state.selected.clear();
    const select=$('[data-filter="attendance"]');if(select)select.value=state.filters.attendance;
    activeGrid.draw();
}
function switchAttendanceView(view) {
    if(batchSaveBusy||!['students','health'].includes(view)||view===attendanceView)return;
    attendanceView=view;connectedAttendance();hydrateIcons();$('#attendance-tab-'+view).focus({preventScroll:true});
}
function attendanceControls(r) {return `<div class="attendance-row-controls">${['Present','Absent'].map(status=>`<button type="button" class="btn compact attendance-mark ${status.toLowerCase()}" data-action="${status==='Present'?'batch-present':'batch-absent'}" data-id="${escapeHTML(r.id)}" aria-pressed="${r.attendance===status}" aria-label="${escapeHTML('Mark '+attendanceStudentName(r)+' '+status)}">${icon(status==='Present'?'check':'close')}${status}</button>`).join('')}</div>${r.attendance==='Not Yet Recorded'?'<small class="attendance-unrecorded">Not Yet Recorded</small>':''}`;}
function attendanceDate(date) {const day=new Date(date+'T00:00:00Z');return Number.isNaN(day.getTime())?date:new Intl.DateTimeFormat('en-PH',{dateStyle:'long',timeZone:'UTC'}).format(day);}
function attendanceTime(time) {if(!time)return '—';const [hour,minute]=time.split(':');return (Number(hour)%12||12)+':'+minute+' '+(Number(hour)<12?'AM':'PM');}
function initializeAttendanceSelector() {
    $('#attendance-schedule').onchange=event=>go('admin-student-attendance'+(event.target.value?'?batch='+encodeURIComponent(event.target.value):''));
    $('#grid-host').setAttribute('role','tabpanel');$('#grid-host').setAttribute('aria-labelledby','attendance-tab-'+attendanceView);
    $('#attendance-tabs').onkeydown=event=>{if(batchSaveBusy||!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const view=event.key==='Home'?'students':event.key==='End'?'health':attendanceView==='students'?'health':'students';switchAttendanceView(view);$('#attendance-tab-'+view).focus({preventScroll:true});};
}
function attendanceBatchRows(id) {return cfoState.registrations.filter(r=>!r.is_archived&&r.year===cfoState.current_year&&String(r.recollection_schedule_id)===String(id));}

function markBatchAttendance(id, attendance) {
    if(batchSaveBusy)return;
    const r=findRegistration(id);if(!r)return;
    if(r.attendance===attendance){toast(attendanceStudentName(r)+' is already '+attendance+'.');return;}
    if(r.attendance!=='Not Yet Recorded'){
        showDialog('Confirm attendance correction',`<p>Change ${escapeHTML(r.user.name)} from ${escapeHTML(r.attendance)} to ${attendance}? Certificate availability will update automatically.</p>`,button('Cancel','close-dialog')+button('Confirm '+attendance,attendance==='Present'?'batch-confirm-present':'batch-confirm-absent',id,'primary'));
        return;
    }
    saveAttendanceQuick(id,attendance);
}

// First-time marks save without a pop-up so check-in stays fast. Corrections keep their confirmation dialog.
async function saveAttendanceQuick(id, attendance) {
    if(batchSaveBusy)return;
    const r=findRegistration(id),name=r?attendanceStudentName(r):'Student';
    batchSaveBusy=true;
    const unlock=lockBatchControls();
    try {
        await backend('/api/admin/registrations/'+id+'/attendance','POST',{attendance});
        await renderConnectedAdmin();
        toast(name+' marked '+attendance+'.');
    } catch(error) { toast(error.message); }
    finally { batchSaveBusy=false;unlock(); }
}

function attendanceRosterSort(rows) {
    const marked=r=>['Present','Absent'].includes(r.attendance)?1:0;
    return [...rows].sort((a,b)=>(attendanceUnmarkedFirst?marked(a)-marked(b):0)||attendanceStudentName(a).localeCompare(attendanceStudentName(b),undefined,{sensitivity:'base'}));
}
function attendanceAlertCell(r) {
    const health=attendanceReportedHealth(r).length>0,religion=attendanceDetail(r,'religion');
    const faith=!religion?'<small class="attendance-cell-detail">Religion not provided</small>':/catholic|^prefer not to say$/i.test(religion)?`<span class="attendance-pill neutral">${escapeHTML(religion)}</span>`:`<span class="attendance-pill pending">${escapeHTML(religion)}</span>`;
    return `<div class="attendance-alerts">${health?`<span class="attendance-pill health" title="Open View for details">${icon('heart')} Health / Allergy</span>`:''}${faith}</div>`;
}
function attendanceAlertText(r) {return [attendanceReportedHealth(r).length?'Health / Allergy alert':'',attendanceDetail(r,'religion')].filter(Boolean).join(' · ')||'—';}
function attendanceAlertSort(r) {return (attendanceReportedHealth(r).length?'0':'1')+attendanceDetail(r,'religion');}
function attendanceMarkedAt(r) {
    if(!r.attendance_verified_at||!['Present','Absent'].includes(r.attendance))return '—';
    const when=new Date(r.attendance_verified_at);
    return Number.isNaN(when.getTime())?'—':new Intl.DateTimeFormat('en-PH',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit',timeZone:'Asia/Manila'}).format(when);
}
function updateAttendanceProgress(participants) {
    const text=$('[data-attendance-progress-text]');if(!text)return;
    const total=participants.length,marked=participants.filter(r=>['Present','Absent'].includes(r.attendance)).length,percent=total?Math.round(marked/total*100):0;
    text.textContent=`${marked} of ${total} marked (${percent}%)`;
    const bar=$('[data-attendance-progress-bar]');bar.setAttribute('aria-valuenow',String(percent));bar.firstElementChild.style.width=percent+'%';
    $('[data-attendance-progress-note]').textContent=total===0?'':total-marked?`${total-marked} remaining`:'All students marked';
}
function updateAttendanceBulkBar(matchingCount) {
    const bar=$('[data-attendance-bulk]');if(!bar||!activeGrid)return;
    const selected=activeGrid.state.selected.size;
    bar.hidden=selected===0;
    $('[data-bulk-count]').textContent=selected+' selected';
    const more=$('[data-bulk-matching]');more.hidden=selected>=matchingCount;more.textContent='Select all '+matchingCount+' matching';
}
function attendanceExport() {
    if(!activeGrid)return;
    csvDownload(activeGrid.config.key+'.csv',['Student ID','Student Name','Course','Year','Section','Religion','Attendance','Marked at'],activeGrid.filtered().map(r=>{const group=attendanceAcademicGroup(r);return [r.user?.student_id||'',attendanceStudentName(r),group.course,group.year_level,group.section,attendanceDetail(r,'religion'),r.attendance,attendanceMarkedAt(r)];}));
}

function reviewSelectedAttendance(id, status) {
    if(batchSaveBusy||!activeGrid)return;
    const chosen=new Set([...activeGrid.state.selected].map(String));
    const rows=attendanceBatchRows(id).filter(r=>chosen.has(String(r.id)));
    if(!rows.length){toast('Select at least one student first.');return;}
    if(rows.some(r=>r.status!=='Approved'||!r.schedule)){toast('A selected registration still needs approval or a schedule.');return;}
    const targets=rows.filter(r=>r.attendance!==status);
    if(!targets.length){toast('Every selected student is already '+status+'.');return;}
    const corrections=targets.filter(r=>r.attendance!=='Not Yet Recorded').length;
    selectedReview={id,status,states:rows.map(r=>({id:r.id,attendance:r.attendance}))};
    showDialog('Mark selected '+status,`<p>Mark ${targets.length} selected student${targets.length===1?'':'s'} as ${status}?</p>${corrections?`<p><strong>${corrections} recorded result${corrections===1?'':'s'} will be corrected to ${status}.</strong> Certificate availability will update automatically.</p>`:''}${status==='Absent'?'<p>Each student receives a notification. For the current year, the Absent notice tells the student their registration form is open again.</p>':''}`,button('Cancel','close-dialog')+button('Confirm '+status,'batch-selected-confirm',id,'primary'));
}

function reviewRemainingAbsent(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id),pending=rows.filter(r=>!['Present','Absent'].includes(r.attendance));
    if(!pending.length){toast('Every student already has recorded attendance.');return;}
    if(rows.some(r=>r.status!=='Approved'||!r.schedule)){toast('This batch contains registrations that still need approval.');return;}
    selectedReview={id,status:'Absent',states:pending.map(r=>({id:r.id,attendance:r.attendance}))};
    showDialog('Mark remaining as Absent',`<p>Mark the ${pending.length} student${pending.length===1?'':'s'} who are not yet recorded as <strong>Absent</strong>? Students who are already Present or Absent stay unchanged. This includes students hidden by table filters.</p><p>Each student receives a notification. For the current year, the Absent notice tells the student their registration form is open again.</p>`,button('Cancel','close-dialog')+button('Confirm mark remaining Absent','batch-selected-confirm',id,'danger'));
}

async function saveSelectedAttendance(id) {
    if(batchSaveBusy||!selectedReview||String(selectedReview.id)!==String(id))return;
    const review=selectedReview;selectedReview=null;
    batchSaveBusy=true;
    showDialog('Saving attendance','<p id="batch-progress" role="status" aria-live="polite">Checking the latest batch before saving…</p>','');
    const unlock=lockBatchControls();
    let saved=0,total=0,failure=null;
    try {
        cfoState={...cfoState,...await backend('/api/admin/state')};
        const before=new Map(review.states.map(s=>[String(s.id),s.attendance]));
        const rows=attendanceBatchRows(id).filter(r=>before.has(String(r.id)));
        if(rows.length!==before.size||rows.some(r=>before.get(String(r.id))!==r.attendance||r.status!=='Approved'||!r.schedule))throw Error('The batch changed. Review the refreshed list and try again.');
        const list=rows.filter(r=>r.attendance!==review.status);
        total=list.length;
        for(const r of list) {
            $('#batch-progress').textContent=`Saving ${saved+1} of ${total}: ${attendanceStudentName(r)}…`;
            try {await backend('/api/admin/registrations/'+r.id+'/attendance','POST',{attendance:review.status,...(r.attendance!=='Not Yet Recorded'?{confirm_edit:true}:{})});saved++;}
            catch(error){failure={name:attendanceStudentName(r),message:error.message};break;}
        }
    } catch(error){failure={message:error.message};}
    finally {
        try {
            closeDialog();await renderConnectedAdmin();
            if(activeGrid&&!failure){activeGrid.state.selected.clear();activeGrid.draw();}
            const remaining=Math.max(0,total-saved-(failure?.name?1:0));
            const message=failure?`<p role="alert">${saved} of ${total} saves confirmed. ${failure.name?'Failed student: '+escapeHTML(failure.name)+'. ':''}${escapeHTML(failure.message)}</p><p>${remaining} records were not attempted. Previously saved attendance is retained. Review the latest batch before retrying.</p>`:`<p role="status">${saved} attendance record${saved===1?'':'s'} saved as ${escapeHTML(review.status)}.</p>`;
            showDialog(failure?'Attendance needs attention':'Attendance saved',message,button('Done','close-dialog'));
        } finally {batchSaveBusy=false;unlock();}
    }
}

function connectedAttendanceEdit(id) {
    if(batchSaveBusy)return;
    const r=findRegistration(id);if(!r)return;
    showDialog('Correct attendance',`<p>${escapeHTML(r.user.name)} · Currently ${escapeHTML(r.attendance)}. The certificate updates automatically.</p>`,button('Present','batch-confirm-present',id,'primary')+button('Absent','batch-confirm-absent',id,'danger')+button('Cancel','close-dialog'));
}

let archiveReview=null;
function reviewBatchArchive(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id);
    if(!rows.length || rows.some(r=>!['Present','Absent'].includes(r.attendance))){showDialog('Attendance incomplete','<p>Mark every student Present or Absent before moving this batch to History.</p>',button('Close','close-dialog'));return;}
    archiveReview={id,registration_ids:rows.map(r=>r.id)};
    showDialog('Move this Recollection to History','<p><strong>Sigurado ka bang ilipat ang students sa History?</strong></p><p>'+rows.length+' students will leave Attendance. Their complete records and certificates remain available in History.</p>',button('Cancel','close-dialog')+button('Move to History','batch-archive-confirm',id,'primary'));
}

let presentReview=null,completionReview=null;
function reviewBatchPresent(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id);
    if(!rows.length || rows.some(r=>r.status!=='Approved' || !r.schedule)){toast('This batch has no eligible students, or contains registrations that still need approval.');return;}
    const targets=rows.filter(r=>r.attendance!=='Present');
    if(!targets.length){toast('Every student in this batch is already Present.');return;}
    presentReview={id,states:rows.map(r=>({id:r.id,attendance:r.attendance}))};
    const corrections=targets.filter(r=>r.attendance!=='Not Yet Recorded').length;
    showDialog('Mark All Present',`<p>Mark ${targets.length} students in this entire schedule Present? This includes students hidden by table filters. ${rows.length-targets.length} already Present will stay unchanged.</p>${corrections?`<p><strong>${corrections} recorded attendance result${corrections===1?'':'s'} will be corrected to Present.</strong> Certificate availability will update automatically.</p>`:''}<p>Each record is saved to the existing attendance API. If a save fails, processing stops and the result will identify the saved, failed, and unattempted records.</p>`,button('Cancel','close-dialog')+button('Confirm Mark All Present','batch-present-all-confirm',id,'primary'));
}

async function saveBatchPresent(id) {
    if(batchSaveBusy || !presentReview || String(presentReview.id)!==String(id))return;
    const review=presentReview;presentReview=null;
    batchSaveBusy=true;
    showDialog('Saving batch attendance','<p id="batch-progress" role="status" aria-live="polite">Checking the latest batch before saving…</p>','');
    const unlock=lockBatchControls();
    let saved=0,total=review.states.filter(r=>r.attendance!=='Present').length,failure=null;
    try {
        cfoState={...cfoState,...await backend('/api/admin/state')};
        const rows=attendanceBatchRows(id);
        const before=new Map(review.states.map(r=>[String(r.id),r.attendance]));
        if(rows.length!==before.size || rows.some(r=>!before.has(String(r.id)) || before.get(String(r.id))!==r.attendance || r.status!=='Approved' || !r.schedule))throw Error('Batch membership or attendance changed. Review the refreshed batch and confirm again.');
        const targets=rows.filter(r=>r.attendance!=='Present');total=targets.length;
        for(const r of targets) {
            $('#batch-progress').textContent=`Saving ${saved+1} of ${total}: ${attendanceStudentName(r)}…`;
            try {await backend('/api/admin/registrations/'+r.id+'/attendance','POST',{attendance:'Present',...(r.attendance!=='Not Yet Recorded'?{confirm_edit:true}:{})});saved++;}
            catch(error){failure={name:attendanceStudentName(r),message:error.message};break;}
        }
    } catch(error){failure={message:error.message};}
    finally {
        try {
            closeDialog();await renderConnectedAdmin();
            const remaining=Math.max(0,total-saved-(failure?.name?1:0));
            const message=failure?`<p role="alert">${saved} of ${total} saves confirmed. ${failure.name?'Failed student: '+escapeHTML(failure.name)+'. ':''}${escapeHTML(failure.message)}</p><p>${remaining} records were not attempted. Previously saved attendance is retained. Review the latest batch before retrying.</p>`:`<p role="status">${saved} attendance record${saved===1?'':'s'} saved as Present. Review the refreshed batch before completing attendance.</p>`;
            showDialog(failure?'Batch attendance needs attention':'Batch attendance saved',message,button('Done','close-dialog'));
        } finally {batchSaveBusy=false;unlock();}
    }
}

function reviewBatchCompletion(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id);
    if(!rows.length || rows.some(r=>!['Present','Absent'].includes(r.attendance))){
        const pending=rows.filter(r=>!['Present','Absent'].includes(r.attendance));
        showDialog('Attendance incomplete',`<p>${pending.length?pending.length+' student'+(pending.length===1?' is':'s are')+' not marked yet. ':''}Mark every student Present or Absent before completing this batch. Table filters do not limit this check.</p>${pending.length?'<p>If everyone who did not attend should be Absent, you can mark all remaining students Absent now.</p>':''}`,button('Close','close-dialog')+(pending.length?button('Mark remaining as Absent','batch-absent-remaining',id,'danger'):''));
        return;
    }
    completionReview={id,registration_ids:rows.map(r=>r.id)};
    showDialog('Complete Attendance',`<p>Confirm that attendance for all ${rows.length} students in this schedule has been reviewed? We will check the latest saved batch before confirming completion.</p><p>This check keeps the batch in Attendance. Move to Recollection History is a separate confirmed action.</p>`,button('Cancel','close-dialog')+button('Confirm completion','batch-complete-confirm',id,'primary'));
}

async function confirmBatchCompletion(id) {
    if(batchSaveBusy || !completionReview || String(completionReview.id)!==String(id))return;
    const review=completionReview;completionReview=null;
    batchSaveBusy=true;
    showDialog('Checking attendance','<p role="status">Checking every student against the latest saved batch…</p>','');
    const unlock=lockBatchControls();
    try {
        cfoState={...cfoState,...await backend('/api/admin/state')};
        const rows=attendanceBatchRows(id),expected=new Set(review.registration_ids.map(String));
        if(routeDetails().route==='admin-student-attendance'){connectedAttendance();syncAdminSearch();hydrateIcons();}
        if(rows.length!==expected.size || rows.some(r=>!expected.has(String(r.id))))throw Error('Batch membership changed. Review the refreshed batch before completing attendance.');
        if(!rows.length || rows.some(r=>!['Present','Absent'].includes(r.attendance)))throw Error('Attendance is incomplete. Mark every student Present or Absent.');
        showDialog('Attendance complete',`<p role="status">All ${rows.length} students have recorded attendance. Their attendance and certificate results are saved. You may now review moving this batch to History.</p>`,button('Keep in Attendance','close-dialog')+button('Move to Recollection History','batch-archive',id,'primary'));
    } catch(error) {showDialog('Attendance needs review',`<p role="alert">${escapeHTML(error.message)}</p>`,button('Close','close-dialog'));}
    finally {batchSaveBusy=false;unlock();}
}

Object.assign(actions,{
    'attendance-view':switchAttendanceView,
    'attendance-information':attendanceInformationView,
    'batch-present':id=>markBatchAttendance(id,'Present'),
    'batch-absent':id=>markBatchAttendance(id,'Absent'),
    'batch-confirm-present':id=>saveBatchAction('/api/admin/registrations/'+id+'/attendance',{attendance:'Present',confirm_edit:true},'Attendance saved'),
    'batch-confirm-absent':id=>saveBatchAction('/api/admin/registrations/'+id+'/attendance',{attendance:'Absent',confirm_edit:true},'Attendance saved'),
    'attendance-unmarked-first':()=>{if(batchSaveBusy)return;attendanceUnmarkedFirst=!attendanceUnmarkedFirst;if(activeGrid){activeGrid.state.sortIndex=undefined;activeGrid.state.page=1;}connectedAttendance();hydrateIcons();},
    'attendance-export':attendanceExport,
    'batch-selected-present':id=>reviewSelectedAttendance(id,'Present'),
    'batch-selected-absent':id=>reviewSelectedAttendance(id,'Absent'),
    'batch-selected-confirm':saveSelectedAttendance,
    'batch-selected-clear':()=>{if(!activeGrid)return;activeGrid.state.selected.clear();activeGrid.draw();},
    'batch-select-matching':()=>{if(!activeGrid)return;activeGrid.filtered().forEach(r=>activeGrid.state.selected.add(String(r.id)));activeGrid.draw();},
    'batch-absent-remaining':reviewRemainingAbsent,
    'health-present-only':toggleHealthPresentOnly,
    'health-print':printHealthAlerts,
    'health-export':exportHealthRegistrations,
    'batch-present-all':reviewBatchPresent,
    'batch-present-all-confirm':saveBatchPresent,
    'batch-complete':reviewBatchCompletion,
    'batch-complete-confirm':confirmBatchCompletion,
    'batch-archive':reviewBatchArchive,
    'batch-archive-confirm':id=>{if(archiveReview && String(archiveReview.id)===String(id))return saveBatchAction('/api/admin/schedules/'+id+'/archive',{confirmed:true,registration_ids:archiveReview.registration_ids},'Batch moved to History');},
});

    function connectedRegistrations(history=false) {
      const rows=history?[...(cfoState.history||[])].sort((a,b)=>b.year-a.year||b.id-a.id):cfoState.registrations.filter(r=>!r.is_archived && !r.recollection_schedule_id);
      const academic=(r,key)=>r.student_snapshot?.[key]||'Not recorded';
      const certificateStatus=r=>r.certificate?'Issued':r.certificate_record?.revoked_at?'Withdrawn':'Not issued';
      const recollectionDate=r=>{
        if(!r.schedule?.date)return 'Not scheduled';
        const date=new Date(String(r.schedule.date).slice(0,10)+'T00:00:00');
        return Number.isNaN(date.getTime())?'Not scheduled':date.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
      };
      const yearSection=r=>String(r.details?.yearSection??'').trim()||[r.student_snapshot?.year_level,r.student_snapshot?.section].map(value=>String(value??'').trim()).join('')||'Not recorded';
      const columns=history?[
        {label:'Name',get:r=>r.student_snapshot?.name||r.user?.name},
        {label:'ID number',get:r=>r.student_snapshot?.student_id||r.user?.student_id},
        {label:'Date of recollection',get:recollectionDate,sort:r=>r.schedule?.date||''},
        {label:'Course',get:r=>academic(r,'course')},
        {label:'Year/Section',get:yearSection},
        {label:'Attendance',get:r=>r.attendance},
        {label:'Action',get:()=> 'View',sort:false,html:r=>button('View','connected-history-view',r.id,'compact')}
      ]:[{label:'Name',get:r=>r.user?.name},{label:'ID number',get:r=>r.user?.student_id},{label:'Recollection year',get:r=>r.year},{label:'Schedule',get:connectedSchedule},{label:'Registration',get:r=>r.status},{label:'Receipt',get:r=>r.receipt_status},{label:'Attendance',get:r=>r.attendance},{label:'Certificate',get:certificateStatus}];
      $('#page-description').textContent=history?'Completed batches and previous years, permanently retained.':'Unscheduled registrations for '+cfoState.current_year;
      $('#page-actions').innerHTML=history?link('Current-year registrations','admin-register-this-year','','register'):link('Recollection History','admin-history','','history');
      connectedGrid({key:history?'connected-history':'connected-registrations',title:history?'Recollection History':'Registered students · '+cfoState.current_year,rows:()=>rows,
        beforeGrid:history?`<section class="panel"><div class="panel-head"><div><h2 id="history-heading" tabindex="-1">Past recollections</h2><p class="muted">Completed batches appear here after you click Move to History. The previous year automatically appears here. Registration details, receipts, attendance records, and certificates are kept.</p></div></div><div class="panel-body"><div class="metrics" id="history-summary" aria-live="polite" aria-label="Filtered history summary"></div></div></section>`:'',
        ...(history?{historyControls:true,searchDebounce:300,searchOptions:{placeholder:'Search name, ID number, course, or attendance',search:r=>columns.map(column=>column.get(r)).join(' ')},searchMatches:(value,query)=>String(value??'').toLowerCase().includes(query.trim().toLowerCase()),resultCount:count=>`Showing ${count} ${count===1?'student':'students'}`} : {}),
        filters:history?[{key:'scheduleDate',label:'Recollection date',type:'date',event:'input',get:r=>r.schedule?.date||''}]:[],
        columns,
        rowActions:history?null:r=>button('View','connected-registration',r.id,'compact'),
        afterDraw:filtered=>{
          if(!history)return;
          $('#history-summary').innerHTML=metric('Matching records',filtered.length,'Across the selected filters','register')+metric('Recollection years',new Set(filtered.map(r=>r.year)).size,'Years in these results','history')+metric('Present',filtered.filter(r=>r.attendance==='Present').length,'Recorded attendance','check')+metric('Certificates issued',filtered.filter(r=>r.certificate).length,'Available to view or print','certificate');
          if(!filtered.length){
            const date=gridState.get('connected-history')?.filters.scheduleDate;
            $('#data-grid .table-empty').textContent=date?'No students are scheduled on this date.':'No students found.';
          }
        }});
    }
    function connectedSchedules(options = {}) {
      $('#page-actions').innerHTML='';
      mySchedulesController=window.CfoMySchedules.mount({
        root:$('#page'), state:cfoState, request:backend, toast,
        onCreate:()=>go('admin-add-new-recollection-schedule'),
        onEdit:schedule=>{location.href='/admin/schedules/'+encodeURIComponent(schedule.id)+'/edit';},
        refresh:()=>renderConnectedAdmin(['schedules','registrations','schedule_changes','notifications']),
        navigate:()=>go('admin-recollection-schedule'),
        ...options
      });
    }
    function connectedScheduleForm(params) {
      if(params.has('id')) {
        const supplied=window.CFO_BACKEND.edit_schedule;
        if(!supplied||String(supplied.id)!==params.get('id')) {
          location.replace('/admin/schedules/'+encodeURIComponent(params.get('id'))+'/edit');
          return;
        }
        const saved=cfoState.schedules.find(schedule=>String(schedule.id)===params.get('id'));
        if(!saved||saved.finalized_at||saved.status==='Cancelled') {
          toast(!saved?'The schedule was not found.':saved.finalized_at?'Completed schedules are view only.':'Cancelled schedules are view only.');
          go('admin-recollection-schedule');
          return;
        }
        mySchedulesController?.destroy();
        mySchedulesController=null;
        $('#page-actions').innerHTML='';
        schedulePageController=window.CfoScheduleCreate.mount({
          root:$('#page'),state:cfoState,userId:window.CFO_BACKEND.user.id,
          editSchedule:{...saved,assigned_academic_groups:supplied.assigned_academic_groups},
          request:backend,toast,navigate:()=>go('admin-recollection-schedule'),
          save:async(values,onSaved)=>{
            const result=await backend('/api/admin/schedules/'+saved.id,'PUT',values);
            const index=cfoState.schedules.findIndex(schedule=>schedule.id===result.id);
            if(index>=0)cfoState.schedules[index]=result;
            onSaved?.(result);
            toast('Recollection schedule updated.');
          }
        });
        return;
      }
      mySchedulesController?.destroy();
      mySchedulesController=null;
      $('#page-actions').innerHTML='';
      schedulePageController=window.CfoScheduleCreate.mount({
        root:$('#page'),state:cfoState,userId:window.CFO_BACKEND.user.id,
        request:backend,toast,
        navigate:()=>go('admin-recollection-schedule'),
        save:async(values,onSaved)=>{
          const result=await backend('/api/admin/schedules','POST',values);
          const index=cfoState.schedules.findIndex(schedule=>schedule.id===result.id);
          if(index>=0)cfoState.schedules[index]=result;
          else cfoState.schedules.push(result);
          onSaved?.(result);
          toast('Recollection schedule saved.');
        }
      });
    }
    function connectedReceipts() {
      const rows=cfoState.registrations;
      const params=routeDetails().params;
      connectedGrid({key:'connected-receipts',filterQuery:params.toString(),initialFilters:{receipt_status:params.get('receipt_status')||''},title:'Registration receipts',rows:()=>rows,filters:[objectFilter('receipt_status','Status',rows)],columns:[{label:'Student',html:connectedStudent},{label:'Schedule',get:connectedSchedule},{label:'Receipt',get:r=>r.has_receipt?'Uploaded':'Not uploaded'},{label:'Status',get:r=>r.receipt_status}],rowActions:r=>button('Review','connected-receipt',r.id,'compact')});
    }
    function connectedReceipt(id) {
      const r=findRegistration(id);
      showDialog('Receipt review',details([['Student',r.user.name],['Schedule',connectedSchedule(r)]])+(r.has_receipt?`<p><img src="/api/receipts/${r.id}" alt="Uploaded receipt" style="max-width:100%;max-height:65vh;object-fit:contain"></p><p><a class="btn" href="/api/receipts/${r.id}" target="_blank" rel="noopener">Download private receipt</a></p>`:'<p>No receipt has been uploaded.</p>')+`<form id="connected-receipt-form">${selectField('Receipt status','receipt_status',['Pending','Approved','Rejected'],r.receipt_status)}${field('Rejection reason','receipt_reason',r.receipt_reason||'')}<div class="form-actions"><button type="submit" class="btn primary">Save review</button></div></form>`,'');
      $('#connected-receipt-form').onsubmit=e=>{e.preventDefault();saveConnected('/api/admin/registrations/'+id,'PATCH',Object.fromEntries(new FormData(e.target)));};
    }
    function connectedRequests() {
      const rows=cfoState.requests, cr=window.CfoChangeRequests;
      connectedGrid({key:'connected-requests',title:'Schedule change requests',noun:'requests',toolbar:'',hideFilterHelp:true,beforeGrid:cr.statsHtml(rows,metric),rows:()=>rows,
        filters:[objectFilter('status','Status',rows),cr.courseFilter(rows,unique)],columns:cr.columns(),exportColumns:cr.exportColumns(),
        rowActions:r=>button(r.status==='Pending'?'Review':'View','connected-request',r.id,'compact')});
    }
    function connectedRequest(id) {
      const r=cfoState.requests.find(r=>String(r.id)===String(id));
      if(!r)return;
      const pending=r.status==='Pending';
      showDialog(pending?'Review schedule change request':'Schedule change request details',window.CfoChangeRequests.reviewHtml(r),
        pending?button('Cancel','close-dialog')+button('Reject request','cr-reject',id,'danger')+button('Approve & assign schedule','cr-approve',id,'primary'):button('Close','close-dialog'),true);
      const form=$('#connected-request-form');
      if(form)form.onsubmit=e=>{e.preventDefault();decideChangeRequest(id,'Approved');};
    }
    function decideChangeRequest(id,status) {
      const form=$('#connected-request-form');
      if(!form)return;
      const result=window.CfoChangeRequests.decision(form,status);
      if(!result.ok){toast(result.message);form.elements[result.field]?.focus();return;}
      saveConnected('/api/admin/change-requests/'+id,'PATCH',result.values);
    }
    function connectedAnnouncements() {
      $('#page').innerHTML=CfoAnnouncements.pageHtml({rows:cfoState.announcements||[],panel,button});
      CfoAnnouncements.bind($('#page'));
    }
    function connectedSettings(params) {
      if(params.get('tab')==='office'){connectedOffice();return;}
      const r=window.CFO_BACKEND.user;
      if(params.get('tab')==='admins') {
        const rows=cfoState.users.filter(r=>r.role==='Admin');
        connectedGrid({key:'connected-admins',hideClearFilters:true,toolbar:'',title:'Administrator accounts',rows:()=>rows,columns:[{label:'Name',get:r=>r.name},{label:'Employee ID',get:r=>r.student_id||'—'},{label:'Email',get:r=>r.email},{label:'Login email',get:r=>r.login_email||r.system_username||r.email},{label:'Status',get:r=>r.status}],rowActions:r=>r.status==='Pending'&&r.id!==window.CFO_BACKEND.user.id?button('Review / Approve','connected-admin-review',r.id,'compact'):''});
        $('#page').insertAdjacentHTML('afterbegin',note('Public Admin registrations appear here as Pending. Verify the applicant with the CFO before approving administrator access.',true));return;
      }
      $('#page').innerHTML=window.CfoAdminProfile.pageHtml(r);
    }
    function connectedAdminReview(id) {
      const r=cfoState.users.find(row=>String(row.id)===String(id));
      if(!r||r.role!=='Admin'||r.status!=='Pending')return;
      showDialog('Review administrator account',details([['Name',r.name],['Employee ID',r.student_id||'—'],['Email',r.email]])+`<form id="connected-admin-review-form">${selectField('Decision','status',['Approved','Rejected'],'Approved')}${field('Rejection reason','rejection_reason','')}${note('Approve only after verifying this person is authorized to manage CFO records.',true)}<div class="form-actions"><button class="btn primary" type="submit">Save decision</button></div></form>`,'');
      $('#connected-admin-review-form').onsubmit=e=>{e.preventDefault();saveConnected('/api/admin/users/'+id+'/review-admin','PATCH',Object.fromEntries(new FormData(e.target)));};
    }
    if (window.CFO_BACKEND) {
      Object.assign(actions, {
        'connected-admin-review':connectedAdminReview,
        'connected-reload':renderConnectedAdmin,'connected-student':connectedStudentEdit,'connected-receipt':connectedReceipt,'connected-attendance-edit':connectedAttendanceEdit,'connected-request':connectedRequest,'cr-approve':id=>decideChangeRequest(id,'Approved'),'cr-reject':id=>decideChangeRequest(id,'Rejected'),
        'connected-registration':connectedRegistration,
        'connected-print':()=>{if(!activeGrid)return;const rows=activeGrid.filtered();printPreview('Student attendance',`<table><thead><tr><th>Student ID</th><th>Name</th><th>Course</th><th>Year Level</th><th>Section</th><th>Schedule</th><th>Attendance</th></tr></thead><tbody>${rows.map(r=>{const group=attendanceAcademicGroup(r);return `<tr>${[r.user?.student_id,attendanceStudentName(r),group.course,group.year_level,group.section,connectedSchedule(r),r.attendance].map(v=>`<td>${escapeHTML(v)}</td>`).join('')}</tr>`;}).join('')}</tbody></table>`);},
        'export-grid':()=>{if(!activeGrid)return;const custom=activeGrid.config.exportColumns;if(custom){csvDownload(activeGrid.config.key+'.csv',custom.map(c=>c.label),activeGrid.filtered().map(r=>custom.map(c=>c.get(r))));return;}const columns=activeGrid.config.columns;csvDownload(activeGrid.config.key+'.csv',columns.map(c=>c.label),activeGrid.filtered().map((r,index)=>columns.map(c=>c.rowNumber?index+1:c.get?c.get(r):c.label==='Student'?(r.user?.name||r.name):c.label==='Status'?r.status:'')));},
        'logout':()=>showDialog('Log out?','<p>Your current session will end.</p>',button('Cancel','close-dialog')+button('Log out','confirm-logout','','danger')),
        'confirm-logout':async()=>{try{await backend('/api/logout','POST');location.href='/#login';}catch(error){toast(error.message);}},
        'notifications':()=>showDialog('Notifications',cfoState.notifications.map(n=>'<p>'+escapeHTML(n.message)+'<br><small>'+escapeHTML(n.created_at)+'</small></p>').join('')||'<p>No notifications.</p>')
      });
      $$('[data-action=logout]').forEach(e=>e.setAttribute('aria-label','Logout'));
    }
    async function connectedHistoryView(id) {
      const record=await backend('/api/history/'+id);
      const r=record.registration;
      const controls=(r.schedule?button('Correct attendance','connected-attendance-edit',id):'')+button('Close','close-dialog');
      showDialog('Recollection record · '+r.year,window.cfoHistoryDetails(record,{modalDocuments:true}),controls,true);
    }
    actions['connected-history-view']=connectedHistoryView;
    /* cfo-certificates-v2: Student Certificates list (Present students with a ready certificate) */
    function connectedCertificates() {
      $('#page-actions').innerHTML=link('Certificate template','admin-certificate-template','primary');
      Object.assign(actions,{
        'certificate-print':id=>{const w=window.open('/certificates/'+encodeURIComponent(id),'_blank');if(!w){toast('Allow pop-ups to print the certificate.');return;}w.opener=null;w.addEventListener('load',()=>{try{w.focus();w.print();}catch(error){toast('Certificate opened. Use the browser Print command to print or save as PDF.');}},{once:true});},
        'certificate-download':id=>{const a=document.createElement('a');a.href='/certificates/'+encodeURIComponent(id)+'?download=1';a.rel='noopener';document.body.appendChild(a);a.click();a.remove();toast('SVG download requested. Open the file in your browser to print or save as PDF.');}
      });
      const who=r=>{const old=r.year<cfoState.current_year?(r.student_snapshot||{}):{},u=r.user||{},group=window.CfoSearch.registrationAcademicGroup(r);return {name:old.name||u.name||'Student',id:old.student_id||u.student_id||'',course:group.course,year:group.year_level,section:group.section};};
      const distinct=list=>[...new Set(list.filter(Boolean))];
      const classText=r=>{const p=who(r);return [p.course,p.year,p.section].filter(Boolean).join(' · ')||'—';};
      const dateText=r=>{const value=r.schedule?.date;return value?(window.CfoScheduleCreate?.formatDate?.(value)||value):'—';};
      const scheduleLabel=r=>r.schedule?.title?r.schedule.title+(r.schedule.date?' · '+r.schedule.date:''):'—';
      const rows=cfoState.registrations.filter(r=>r.certificate&&r.attendance==='Present'&&r.attendance_verified_at)
        .sort((a,b)=>String(b.schedule?.date||'').localeCompare(String(a.schedule?.date||''))||who(a).name.localeCompare(who(b).name));
      const hasCurrent=rows.some(r=>r.year===cfoState.current_year);
      connectedGrid({key:'certificates',hideClearFilters:true,title:'Certificates ready to print or download',noun:'certificates',rows:()=>rows,
        initialFilters:hasCurrent?{year:String(cfoState.current_year)}:{},
        beforeGrid:'<div class="cert-info" role="note"><span class="cert-info-icon" aria-hidden="true">'+icon('check')+'</span><div>Certificates are generated automatically for students with verified <b>Present</b> attendance. This page lists only students whose certificates are ready. Select <b>Print</b> or <b>Download</b> for a student\'s certificate.</div></div>',
        filters:[
          {key:'schedule',label:'Recollection schedule',values:distinct(rows.map(scheduleLabel)).sort(),get:scheduleLabel},
          {key:'course',label:'Course / program',values:distinct(rows.map(r=>who(r).course)).sort(),get:r=>who(r).course},
          {key:'year',label:'Year',values:distinct(rows.map(r=>String(r.year))).sort().reverse(),get:r=>String(r.year)}
        ],
        columns:[
          {label:'Student',get:r=>who(r).name,html:r=>`<strong class="cert-student">${escapeHTML(who(r).name)}</strong><small class="cert-sub">Student ID: ${escapeHTML(who(r).id||'—')}</small>`},
          {label:'Course / Year / Section',get:classText},
          {label:'Recollection schedule',get:r=>r.schedule?.title||'—'},
          {label:'Recollection date',get:dateText,sort:r=>r.schedule?.date||'',nowrap:true}
        ],
        exportColumns:[
          {label:'Student ID',get:r=>who(r).id},{label:'Student',get:r=>who(r).name},{label:'Course',get:r=>who(r).course},
          {label:'Year level',get:r=>who(r).year},{label:'Section',get:r=>who(r).section},
          {label:'Recollection schedule',get:r=>r.schedule?.title||''},{label:'Recollection date',get:r=>r.schedule?.date||''},
          {label:'Certificate number',get:r=>r.certificate.certificate_number}
        ],
        rowActions:r=>button('Print','certificate-print',r.certificate.id,'compact cert-print','print')+button('Download','certificate-download',r.certificate.id,'compact cert-download','download'),
        afterDraw:()=>{const empty=$('#data-grid .table-empty');if(empty)empty.textContent=rows.length?'No certificates match your search and filters.':'No certificates are ready yet. Students with verified Present attendance will appear here automatically.';}});
    }
    function connectedTemplate() {
      $('#page-actions').innerHTML=link('Certificates','admin-certificate');
      window.mountCertificateEditor({host:$('#page'),template:cfoState.template,request:backend,save:saveConnected});
    }
    function connectedAcademic() {
      connectedGrid({key:'academic',title:'Course, year level and section options',rows:()=>cfoState.academic_options,filters:[objectFilter('type','Type',cfoState.academic_options)],columns:[{label:'Type',get:r=>r.type},{label:'Name',get:r=>r.name}],rowActions:r=>button('Delete unused','connected-academic-delete',r.id,'compact danger')});
      $('#page').insertAdjacentHTML('afterbegin',panel('Add reference option',`<form id="academic-form"><div class="form-grid">${selectField('Type','type',['course','year_level','section'],'course')}${field('Name','name','','text','required maxlength=190')}</div><div class="form-actions"><button class="btn primary" type="submit">Add option</button></div></form>`));
      $('#academic-form').onsubmit=e=>{e.preventDefault();saveConnected('/api/admin/academic-options','POST',Object.fromEntries(new FormData(e.target)));};
      $('#page').insertAdjacentHTML('afterbegin',panel('Sections per course and year level','<div id="course-year-sections-settings"></div>'));
      window.CfoCourseYearSections.mount({root:$('#course-year-sections-settings'),state:cfoState,request:backend,toast,onChanged:()=>renderConnectedAdmin()});
    }
    // Included inside the admin script to share the existing grid, dialogs and live updates.
let recoveryView = 'Pending';
let recoveryDecisionBusy = false;
let recoveryStatusTimer = null;

function recoveryStatus(row) {
    return row.status;
}

function recoveryDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
    });
}

function recoveryHelp(status) {
    return {Pending:'Waiting for office verification', Approved:'Temporary password issued',
        Verified:'Creating a new password', Completed:'New password saved',
        Rejected:'Request declined', Expired:'A new request is needed',
        Cancelled:'Automatically closed after successful login'}[status] || status;
}

function recoveryRows() {
    return (cfoState.resets || []).map(row => ({...row, display_status: recoveryStatus(row)}));
}

function connectedRecovery() {
    const rows = recoveryRows();
    const count = status => rows.filter(row => row.display_status === status).length;
    const pending = count('Pending');
    const inProgress = count('Approved') + count('Verified');
    const views = [['Pending', 'Awaiting approval', pending], ['All', 'All requests', rows.length],
        ['History', 'Request history', rows.length - pending]];
    const visible = rows.filter(row => recoveryView === 'All' || (recoveryView === 'Pending'
        ? row.display_status === 'Pending' : row.display_status !== 'Pending'));
    visible.sort((a, b) => {
        const priority = Number(b.display_status === 'Pending') - Number(a.display_status === 'Pending');
        return priority || (a.display_status === 'Pending'
            ? new Date(a.created_at) - new Date(b.created_at) : new Date(b.created_at) - new Date(a.created_at));
    });
    $('#page-description').textContent = 'Verify identity at the office, then approve or reject each password reset request.';
    $('#page-actions').innerHTML = button('Refresh requests', 'recovery-refresh', '', '', 'refresh');
    const beforeGrid = `<div class="recovery-overview">
        <div class="metrics" aria-label="Password reset request counts">
            ${metric('Awaiting approval', pending, 'Verify these requests in person', 'clock')}
            ${metric('Recovery in progress', inProgress, 'Approved or setting a new password', 'lock')}
            ${metric('Completed', count('Completed'), 'Permanent password saved', 'check')}
            ${metric('Rejected / expired', count('Rejected') + count('Expired'), 'Review previous decisions below', 'history')}
        </div>
        <div class="recovery-tabs" role="group" aria-label="Filter password reset requests">
            ${views.map(([key, label, total]) => `<button type="button" class="btn" data-action="recovery-view" data-id="${key}" aria-pressed="${recoveryView === key}">${label} <span>(${total})</span></button>`).join('')}
        </div>
    </div>`;
    connectedGrid({key:'recovery-'+recoveryView, title:recoveryView === 'Pending' ? 'Students awaiting approval' : 'Password reset requests',
        noun:'requests', rows:()=>visible, beforeGrid, hideFilterHelp:true, toolbar:'',
        placeholder:'Search name, student ID, email or request number',
        search:r=>[r.id, r.user?.name, r.user?.student_id, r.user?.email, r.user?.system_username].join(' '),
        filters:recoveryView === 'Pending' ? [] : [{key:'display_status',label:'Status',values:['Pending','Approved','Verified','Completed','Rejected','Expired','Cancelled'],get:r=>r.display_status}],
        columns:[
            {label:'Student', html:r=>`<div class="name-cell"><strong>${escapeHTML(r.user?.name || 'Account unavailable')}</strong><span>${escapeHTML(r.user?.student_id || 'No student ID')} · Request #${escapeHTML(r.id)}</span><span>${escapeHTML([r.user?.course,r.user?.year_level,r.user?.section].filter(Boolean).join(' · '))}</span></div>`},
            {label:'Account', get:r=>[r.user?.original_email || r.user?.email,r.user?.login_email || r.user?.system_username].filter(Boolean).join(' / '), html:r=>`<div class="name-cell"><strong>${escapeHTML(r.user?.original_email || r.user?.email || '—')}</strong><span>${escapeHTML(r.user?.login_email || r.user?.system_username || '—')}</span></div>`},
            {label:'Requested', get:r=>recoveryDate(r.created_at)},
            {label:'Status', get:r=>r.display_status, html:r=>badge(r.display_status)+`<span class="recovery-status-help">${escapeHTML(recoveryHelp(r.display_status))}</span>`}
        ],
        rowActions:r=>r.display_status === 'Pending'
            ? button('Review request','connected-recovery',r.id,'compact primary','eye')+button('Reject','recovery-reject',r.id,'compact danger','close')
            : button('View details','connected-recovery',r.id,'compact'),
        afterDraw:filtered=>{
            if (!filtered.length) $('#data-grid .table-empty').textContent = visible.length
                ? 'No requests match your search. Try a different name, student ID or email.'
                : recoveryView === 'Pending' ? 'No requests awaiting approval. New student requests appear here automatically.' : 'No password reset requests in this view.';
        }
    });
    $('#grid-host').classList.add('recovery-grid');
    clearTimeout(recoveryStatusTimer);
    const checkExpiry = () => {
        if (currentRoute !== 'admin-password-resets') return;
        if (!isAdminEditing() && rows.some(row => recoveryStatus(row) !== row.display_status)) connectedRecovery();
        else recoveryStatusTimer = setTimeout(checkExpiry, 5000);
    };
    recoveryStatusTimer = setTimeout(checkExpiry, 5000);
}

function recoveryStudentDetails(row) {
    return `<div class="recovery-review-card">${details([
        ['Student',row.user?.name], ['Student ID',row.user?.student_id],
        ['Registered email',row.user?.original_email || row.user?.email],
        ['Login email',row.user?.login_email || row.user?.system_username],
        ['Request', '#'+row.id], ['Requested',recoveryDate(row.created_at)]
    ])}</div>`;
}

function connectedRecoveryReview(id) {
    const row = recoveryRows().find(row=>String(row.id)===String(id));
    if (!row) { toast('This request is no longer available. Refresh the list.'); return; }
    if (row.display_status === 'Pending') { connectedRecoveryDecision(id, 'Approved'); return; }
    showDialog('Password reset request #'+row.id, recoveryStudentDetails(row)+details([
        ['Status',row.display_status], ['Reviewed by',row.reviewer?.name],
        ['Reviewed',recoveryDate(row.reviewed_at)], ['Password expires',['Approved','Verified'].includes(row.status) ? 'No time limit' : recoveryDate(row.expires_at)],
        ['Verified',recoveryDate(row.verified_at)], ['Completed',recoveryDate(row.used_at)]
    ]), button('Close','close-dialog'));
}

function connectedRecoveryDecision(id, decision) {
    if (recoveryDecisionBusy) return;
    const row = recoveryRows().find(row=>String(row.id)===String(id));
    if (!row || row.display_status !== 'Pending') { toast('This request is no longer pending. Refresh the list to see its current status.'); return; }
    const approve = decision === 'Approved';
    showDialog(approve ? 'Review password reset request' : 'Reject password reset?', recoveryStudentDetails(row)+`
        <form id="recovery-review">
            <p class="notice ${approve ? '' : 'neutral'}">${approve
                ? 'The temporary password will appear on the student\'s requesting page. It has no time limit and can only be used once.'
                : 'This request will be declined. The student will see a rejected status, and no temporary password will be generated. They can contact the office or submit a new request.'}</p>
            ${approve ? '<label class="check-label recovery-confirm"><input type="checkbox" name="identity_verified" required> I verified this student\'s identity in person at the CFO Office, Miraculous Building, near the chapel.</label>' : ''}
            <p id="recovery-decision-error" class="recovery-error" role="alert"></p>
            <div class="form-actions">${button('Cancel','close-dialog')}<button type="submit" class="btn ${approve ? 'primary' : 'danger'}">${approve ? 'Approve request' : 'Reject request'}</button></div>
        </form>`, '');
    const form = $('#recovery-review');
    form.onsubmit = event => { event.preventDefault(); submitRecoveryDecision(form, row.id, decision); };
}

async function submitRecoveryDecision(form, id, decision) {
    if (recoveryDecisionBusy) return;
    const error = form.querySelector('[role=alert]');
    const verified = Boolean(form.elements.identity_verified?.checked);
    if (decision === 'Approved' && !verified) { error.textContent = 'Confirm in-person identity verification before approving.'; return; }
    recoveryDecisionBusy = true;
    const controls = [...dialog.querySelectorAll('button,input')];
    controls.forEach(control=>control.disabled=true);
    error.textContent = '';
    try {
        await backend('/api/admin/password-resets/'+id, 'PATCH', {status:decision, ...(decision === 'Approved' ? {identity_verified:true} : {})});
        closeDialog();
        await renderConnectedAdmin();
        toast(decision === 'Approved' ? 'Request approved. The temporary password will appear on the student\'s page.' : 'Request rejected. The student can see the decision on their page.');
    } catch (failure) {
        error.textContent = failure.message;
    } finally {
        recoveryDecisionBusy = false;
        controls.forEach(control=>control.disabled=false);
    }
}

    function connectedActivity() {
      connectedGrid({key:'activity',hideClearFilters:true,toolbar:'',title:'Activity log (latest 200)',rows:()=>cfoState.logs,columns:[{label:'Date',get:r=>r.created_at},{label:'Actor',get:r=>r.actor||'Public / system'},{label:'Action',get:r=>r.action},{label:'Record',get:r=>r.record_type+' '+(r.record_id||'')}]});
    }
    function connectedOffice() {
      const r=cfoState.settings;
      $('#page').innerHTML=panel('Office information',`<form id="office-form"><div class="form-grid">${field('Office name','name',r.name,'text','required')}${field('School','school',r.school,'text','required')}${field('Address','address',r.address||'')}${field('Office head','head',r.head||'')}</div><div class="form-actions"><button type="submit" class="btn primary">Save office information</button></div></form>`);
      $('#office-form').onsubmit=e=>{e.preventDefault();saveConnected('/api/admin/settings/office','POST',Object.fromEntries(new FormData(e.target)));};
    }
    function studentAccessUrl() {
      const configured = window.ADMIN_CONFIG.accessUrl;
      try {
        const target = new URL(configured || location.origin);
        if (!['http:', 'https:'].includes(target.protocol) || target.username || target.password) return null;
        return new URL('/login', target.origin).href;
      } catch { return null; }
    }
    function connectedLanAccess() {
      const accessUrl=studentAccessUrl();
      const hostname=accessUrl ? new URL(accessUrl).hostname : '';
      const localOnly=!accessUrl || hostname==='localhost' || hostname.endsWith('.localhost') || hostname.endsWith('.test') || hostname==='[::1]' || hostname.startsWith('127.') || hostname==='0.0.0.0';

      if(localOnly){
        $('#page').innerHTML=panel('Student phone access','<p>This address is available only on the server PC. Set a reachable student URL before sharing a QR code.</p><p>For HTTPS on Android and iPhone, use the configured public HTTPS address. For the same school Wi-Fi, use the server LAN IP and port.</p>');
        return;
      }
      const secure=accessUrl.startsWith('https:');
      $('#page').innerHTML=panel('Student phone access',`<p>Scan this QR code or open the URL in your phone browser.</p><div class="actions"><a id="lan-access-url" style="overflow-wrap:anywhere;min-width:0" href="${escapeHTML(accessUrl)}">${escapeHTML(accessUrl)}</a>${button('Copy access URL','copy-lan-url')}</div><div style="display:grid;place-items:center;padding:20px"><canvas id="lan-access-qr" style="max-width:100%;height:auto" aria-label="QR code for student login"></canvas></div><p class="muted">${secure?'HTTPS address. Keep the server running. Public tunnel access also requires an active internet connection and tunnel.':'Local network address. Connect the phone to the same Wi-Fi/router as the server. This HTTP connection is not encrypted and may show a browser security warning.'}</p>`);
      const draw=()=>{if(window.CFOCreateQr)window.CFOCreateQr($('#lan-access-qr'),accessUrl).catch(error=>toast('Unable to create QR code: '+error.message));};
      if(window.CFOCreateQr)draw();else window.addEventListener('cfo:qr-ready',draw,{once:true});
    }
    Object.assign(actions,{
      'connected-confirm-save':async()=>{const pending=pendingConnectedSave;if(pending)await saveConnected(pending.url,pending.method,pending.values,true,pending.onSaved);},
      'copy-lan-url':async()=>{try{await navigator.clipboard.writeText(studentAccessUrl());toast('Student access URL copied.');}catch{toast('Copy is unavailable; select the URL and copy it.');}},
      'connected-delete-schedule':id=>{showDialog('Delete unused Recollection Day?','<p>This will permanently remove this day. Days linked to registrations or requests cannot be deleted.</p>',button('Cancel','close-dialog')+button('Delete','connected-delete-schedule-confirm',id,'danger'));},
      'connected-delete-schedule-confirm':id=>saveConnected('/api/admin/schedules/'+id,'DELETE',{},true),
      'connected-academic-delete':id=>showDialog('Delete unused option?','<p>Options used by profiles cannot be removed.</p>',button('Cancel','close-dialog')+button('Delete','connected-academic-delete-confirm',id,'danger')),
      'connected-academic-delete-confirm':id=>saveConnected('/api/admin/academic-options/'+id,'DELETE',{},true),
      'connected-recovery':connectedRecoveryReview,
      'recovery-reject':id=>connectedRecoveryDecision(id,'Rejected'),
      'recovery-view':view=>{recoveryView=view;connectedRecovery();},
      'recovery-refresh':()=>renderConnectedAdmin()
    });

    function setLiveStatus(value){const node=$('#live-status');if(node){node.textContent=value;node.classList.toggle('live',value==='Live');}}
    let adminEventSource=null,adminPollTimer=null,adminVersions=null,adminRefreshBusy=false,adminPollBusy=false;
    const pendingLiveResources=new Set();
    const isAdminEditing=()=>batchSaveBusy||dialog.open||Boolean(mySchedulesController?.isModalOpen())||Boolean(document.activeElement?.matches?.('input,textarea,select'))||Boolean(document.querySelector('#page form[data-dirty="true"]'))||Boolean(document.querySelector('.cfo-search-results:not([hidden])'));
    for(const type of ['input','change'])document.addEventListener(type,event=>{const form=event.target.closest?.('#page form');if(form)form.dataset.dirty='true';});
    async function readLiveVersions(){return backend('/api/live/versions');}
    async function applyLiveVersions(next){
      if(!adminVersions){adminVersions=next;Object.keys(next).forEach(resource=>pendingLiveResources.add(resource));await refreshAdminWhenSafe();return;}
      const changed=Object.keys(next).filter(resource=>Number(next[resource])!==Number(adminVersions[resource]));
      adminVersions=next;
      changed.forEach(resource=>pendingLiveResources.add(resource));
      await refreshAdminWhenSafe();
    }
    async function refreshAdminWhenSafe(){
      if(adminRefreshBusy||document.hidden||!pendingLiveResources.size||isAdminEditing())return;
      adminRefreshBusy=true;
      const resources=[...pendingLiveResources];pendingLiveResources.clear();
      try{await renderConnectedAdmin(resources);}catch(error){resources.forEach(resource=>pendingLiveResources.add(resource));setLiveStatus('Reconnecting');}
      finally{adminRefreshBusy=false;if(pendingLiveResources.size)setTimeout(refreshAdminWhenSafe,2000);}
    }
    async function pollAdminVersions(){
      if(adminPollBusy||document.hidden)return;
      adminPollBusy=true;
      try{await applyLiveVersions(await readLiveVersions());setLiveStatus('Live');}
      catch{setLiveStatus('Reconnecting');}
      finally{adminPollBusy=false;}
    }
    function startAdminPolling(){if(!adminPollTimer)adminPollTimer=setInterval(pollAdminVersions,2000);}
    function stopAdminPolling(){if(adminPollTimer){clearInterval(adminPollTimer);adminPollTimer=null;}}
    function connectAdminEvents(){
      if(document.hidden)return;
      if(window.CFO_BACKEND?.live_transport==='polling'){startAdminPolling();return;}
      if(!('EventSource'in window)){setLiveStatus('Reconnecting');startAdminPolling();return;}
      if(adminEventSource)adminEventSource.close();
      adminEventSource=new EventSource('/api/admin/events');
      adminEventSource.onopen=()=>{setLiveStatus('Live');stopAdminPolling();};
      adminEventSource.addEventListener('versions',event=>{try{applyLiveVersions(JSON.parse(event.data)).catch(()=>setLiveStatus('Reconnecting'));}catch{setLiveStatus('Reconnecting');}});
      adminEventSource.onerror=()=>{setLiveStatus('Reconnecting');startAdminPolling();};
    }
    async function startAdminLive(){
      if(!window.CFO_BACKEND?.user||window.CFO_BACKEND.user.role!=='Admin')return;
      try{adminVersions=await readLiveVersions();connectAdminEvents();}catch{setLiveStatus('Reconnecting');startAdminPolling();}
    }
    document.addEventListener('focusout',()=>setTimeout(refreshAdminWhenSafe,200));
    dialog.addEventListener('close',()=>setTimeout(refreshAdminWhenSafe,200));
    document.addEventListener('visibilitychange',()=>{
      if(document.hidden){adminEventSource?.close();adminEventSource=null;stopAdminPolling();}
      else{pollAdminVersions().finally(connectAdminEvents);}
    });
    hydrateIcons();syncNavigation();
    window.addEventListener('hashchange', renderRoute);
    renderRoute().then(()=>{if(window.CFO_BACKEND.notice)toast(window.CFO_BACKEND.notice);startAdminLive();});
