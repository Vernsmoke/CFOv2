/* Contextual discovery over the existing role-authorized state APIs. No network or storage. */
(function (root) {
  'use strict';
  const text = values => [values].flat(Infinity).filter(v => v !== null && v !== undefined).join(' ');
  const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/\s+/g, ' ');
  const matches = (value, query) => {
    const haystack = normalize(value);
    // A numeric ID/year must match a complete number, not a digit inside an unrelated date.
    return normalize(query).split(' ').every(term => /^\d+$/.test(term)
      ? haystack.split(/\D+/).includes(term) : haystack.includes(term));
  };
  const studentFields = u => [u?.name, u?.student_id, u?.course, u?.year_level, u?.section,
    u?.course && `${u.course}-${u.year_level || ''}${u.section || ''}`];
  function registrationAcademicGroup(r) {
    const value=input=>typeof input==='string'||typeof input==='number'?String(input).trim():'';
    const details=r.details||{},snapshot=r.student_snapshot||{},user=r.user||{};
    const legacy=value(details.yearSection).match(/^([1-4])[-\s]*([A-Z])$/i);
    const snapshotLegacy=value(snapshot.submitted_year_section).match(/^([1-4])[-\/\s]*([A-Z])$/i);
    return {
      course:value(details.course)||value(snapshot.course)||value(user.course),
      year_level:value(details.yearLevel)||legacy?.[1]||snapshotLegacy?.[1]||value(snapshot.year_level)||value(user.year_level),
      section:value(details.section)||legacy?.[2].toUpperCase()||snapshotLegacy?.[2].toUpperCase()||value(snapshot.section)||value(user.section)
    };
  }
  const attendancePerson = r => ({...r.user,...registrationAcademicGroup(r)});
  const changeRequestPerson = r => {
    const details=r.registration?.details||{},user=r.registration?.user;
    return details.course&&details.yearLevel&&details.section
      ? {...user,course:details.course,year_level:details.yearLevel,section:details.section} : user;
  };
  const scheduleFields = s => s ? [s.title, s.date, s.time, s.start_time, s.end_time, s.venue,
    (s.courses || []).map(c => c.name), s.year_levels ?? s.year_level, s.sections ?? s.section, s.active ? 'Open' : 'Inactive'] : [];
  const certificateStatus = r => r.certificate ? 'Issued Available' : r.certificate_record?.revoked_at ? 'Withdrawn' : 'Not issued';
  const registrationFields = (r,person=r.is_archived ? r.student_snapshot || r.user : r.user) => [r.id, studentFields(person),
    r.year, r.created_at?.slice(0, 10), r.status, r.receipt_status, r.attendance,
    scheduleFields(r.schedule), r.certificate?.certificate_number, certificateStatus(r)];

  // Explicit allowlists, shared by record discovery and existing table filtering.
  function gridFields(key, r) {
    if (key === 'connected-students') return text([studentFields(r), r.original_email || r.email, r.login_email || r.system_username, r.status]);
    if (key === 'connected-schedules') return text([r.id, scheduleFields(r)]);
    if (key === 'connected-requests') return text([r.id, studentFields(changeRequestPerson(r)), scheduleFields(r.current_schedule), scheduleFields(r.registration?.schedule), scheduleFields(r.requested_schedule), scheduleFields(r.assigned_schedule), r.reason, r.status, r.created_at?.slice(0, 10), r.created_at ? 'SCR-' + r.created_at.slice(0, 4) + '-' + String(r.id).padStart(3, '0') : '']);
    if (key.startsWith('recovery-')) return text([r.id, studentFields(r.user), r.user?.original_email || r.user?.email, r.user?.login_email || r.user?.system_username, r.display_status, r.created_at?.slice(0, 10)]);
    if (key === 'academic') return text([r.type, r.name]);
    if (key === 'activity') return text([r.created_at, r.actor || 'Public / system', r.action, r.record_type, r.record_id]);
    if (key.startsWith('batch-attendance-')) return text([registrationFields(r,attendancePerson(r)), r.details?.religion]);
    if (key.startsWith('batch-health-')) return text([registrationFields(r,attendancePerson(r)), r.details?.religion, r.details?.medical, r.details?.allergies, r.details?.medications, r.details?.parish, r.details?.emergencyName, r.details?.relationship, r.details?.emergencyPhone]);
    if (key === 'certificates') return text(registrationFields(r,attendancePerson(r)));
    if (['connected-registrations', 'connected-history', 'connected-receipts'].includes(key)) return text(registrationFields(r));
    return ''; // An unknown module never falls back to serializing an entire record.
  }
  function gridOptions(key) {
    const map = {
      'connected-students': 'Name, student ID, email, course or account status',
      'connected-schedules': 'Recollection title, date, venue, course or status',
      'connected-requests': 'Student, request ID, schedule, date or status',
      'connected-registrations': 'Student, registration ID, year, date or status',
      'connected-history': 'Name, ID, year, course, schedule, attendance or certificate',
      'connected-receipts': 'Student, registration ID, schedule or receipt status',
      certificates: 'Student, ID, course, year, section, schedule or certificate number',
      academic: 'Option name or type', activity: 'Actor, action, date or record ID',
    };
    return {searchLabel: 'Filter this table', placeholder: map[key] || (key.startsWith('batch-attendance-')
      ? 'Student, ID, course, year, section or attendance'
      : key.startsWith('batch-health-') ? 'Student, ID, religion, health, medication or emergency contact'
      : key.startsWith('recovery-') ? 'Student, email, request ID, date or status' : 'Search visible records'), search: r => gridFields(key, r)};
  }
  function sortRows(rows, columns, index, direction) {
    const column = columns[index];
    if (!column || !(column.sort || column.get)) return rows;
    const get = column.sort || column.get;
    return [...rows].sort((a, b) => String(get(a) ?? '').localeCompare(String(get(b) ?? ''), undefined, {numeric: true, sensitivity: 'base'}) * (direction === 'desc' ? -1 : 1));
  }

  const adminPages = [
    ['Dashboard', 'admin-dashboard'], ['Student accounts', 'admin-all-student'],
    ['Recollection registrations', 'admin-register-this-year'], ['Attendance', 'admin-student-attendance'],
    ['Recollection schedules', 'admin-recollection-schedule'], ['Schedule change requests', 'admin-schedule-change-requests'],
    ['Receipt verification', 'admin-student-reciept'], ['Recollection History', 'admin-history'],
    ['Certificates', 'admin-certificate'], ['Certificate template', 'admin-certificate-template'],
    ['Academic options', 'admin-course-year'], ['Announcements', 'admin-announcements'],
    ['Password reset requests', 'admin-password-resets'], ['Activity log', 'admin-activity'],
    ['Profile and office settings', 'admin-settings'], ['Phone access', 'admin-lan-access'],
  ];
  const studentPages = [
    ['Dashboard', 'dashboard'], ['Recollection registration', 'recollection-registration'],
    ['Schedule change request', 'schedule-change-request'], ['Recollection History', 'recollection-history'],
    ['Announcements', 'announcements'], ['Certificate', 'certificate'], ['My profile', 'my-profile'],
  ];
  function buildContext({role, user, state = {}, route, params = new URLSearchParams(), rows, recoveryStatus}) {
    const admin = role === 'Admin', student = role === 'Student';
    if (!admin && !student) return null;
    const pages = admin ? adminPages : studentPages;
    let title = pages.find(p => p[1] === route)?.[0];
    if (admin && route === 'admin-add-new-recollection-schedule') title = 'Recollection schedule form';
    if (!title) return null;
    const context = {key: `${role}:${user?.id}:${route}:${params.get('batch') || ''}`, title, entries: [],
      hint: 'Search records in this page. Table filters also narrow these results.', placeholder: '', suggestions: []};
    const add = (group, title, detail, terms, target) => context.entries.push({group, title: String(title || 'Record'), detail: text(detail), terms: text(terms), ...target});
    const nav = page => add('Pages', page[0], 'Open page', page[0], {href: '#' + page[1]});
    const current = (state.registrations || []).filter(r => !r.is_archived && r.year === state.current_year);
    const own = r => !student || r.user_id === user?.id;
    const active = current.filter(own);
    const registration = (r, group, kind) => {
      const studentCertificate = student && group==='Certificates';
      const selectedAcademics = group==='Attendance' || (admin && group==='Certificates');
      const person = selectedAcademics ? attendancePerson(r) : studentCertificate ? {...user,...registrationAcademicGroup({...r,user:r.user || user})} : student ? user : (r.is_archived ? r.student_snapshot || r.user : r.user);
      const certificateSchedule = studentCertificate && r.schedule ? {date:r.schedule.date,time:r.schedule.time,start_time:r.schedule.start_time,end_time:r.schedule.end_time,venue:r.schedule.venue,active:r.schedule.active} : r.schedule;
      const certificateRecord = studentCertificate ? {...r,schedule:certificateSchedule} : r;
      const studentRoute = r.is_archived ? '#recollection-history' : '#dashboard?focus=registration';
      add(group, `${person?.name || 'Student'} · Registration #${r.id}`,
        [selectedAcademics||studentCertificate?[person.course,[person.year_level,person.section].filter(Boolean).join('')]:[],r.year, studentCertificate?[certificateSchedule?.date,certificateSchedule?.time,certificateSchedule?.start_time,certificateSchedule?.end_time,certificateSchedule?.venue]:[r.schedule?.title,r.schedule?.date], r.status, r.attendance, r.receipt_status, r.certificate?.certificate_number],
        [selectedAcademics||studentCertificate?registrationFields(certificateRecord,person):registrationFields(r), studentFields(person)],
        student ? (r.is_archived ? {kind: 'history', id: r.id} : {href: studentRoute, id: r.id}) : {kind, id: r.id});
    };
    const schedules = list => list.forEach(s => add('Recollection Days', s.title,
      [s.date, s.time, s.venue, s.active ? 'Open' : 'Inactive'], [s.id, scheduleFields(s)],
      admin ? {href: '#admin-add-new-recollection-schedule?id=' + s.id} : {href: '#dashboard?focus=registration'}));
    if (route === 'admin-dashboard' || route === 'dashboard' || ['admin-settings', 'my-profile', 'admin-certificate-template', 'admin-lan-access', 'admin-add-new-recollection-schedule'].includes(route)) {
      const related = route.includes('certificate-template') ? ['admin-certificate', 'admin-certificate-template']
        : route.includes('schedule-form') || route === 'admin-add-new-recollection-schedule' ? ['admin-recollection-schedule', 'admin-add-new-recollection-schedule']
        : ['admin-settings', 'admin-lan-access'].includes(route) ? ['admin-settings', 'admin-lan-access']
        : route === 'my-profile' ? ['my-profile', 'dashboard'] : pages.map(p => p[1]);
      pages.filter(p => related.includes(p[1])).forEach(nav);
      context.placeholder = 'Find a page in your workspace';
      context.hint = 'Workspace pages available to your role.';
      if (route === 'dashboard') {
        active.forEach(r => registration(r, 'My registration and attendance'));
        schedules((state.schedules || []).filter(s => s.year === state.current_year));
        context.placeholder = 'Search my registration, attendance, schedule or pages';
        context.hint = 'Your current recollection, eligible schedules and student pages only.';
      }
    } else if (route === 'admin-all-student') {
      (rows || state.users || []).filter(u => u.role === 'Student').forEach(u => add('Students', u.name,
        [u.student_id, u.course, u.year_level, u.section, u.status], gridFields('connected-students', u), {kind: 'student', id: u.id}));
      context.placeholder = gridOptions('connected-students').placeholder;
    } else if (['admin-register-this-year', 'recollection-registration'].includes(route)) {
      (rows || active).filter(r => own(r) && !r.is_archived && r.year === state.current_year && (!admin || !r.recollection_schedule_id)).forEach(r => registration(r, 'Registrations', 'registration'));
      context.placeholder = 'Search name, registration ID, year, date or status';
      context.hint = admin ? 'Unscheduled current-year registrations; table filters apply.' : 'Your saved current-year registration only. Unsaved form details are not searched.';
    } else if (route === 'admin-student-attendance') {
      const batch = params.get('batch');
      const eligible = (rows || active).filter(r => !r.is_archived && r.year === state.current_year && r.schedule && !r.schedule.finalized_at && r.status === 'Approved' && (!batch || String(r.recollection_schedule_id) === batch));
      if (batch) context.title = `Attendance · ${eligible[0]?.schedule?.title || 'Selected batch'}`;
      eligible.forEach(r => registration(r, 'Attendance', 'attendance'));
      context.placeholder = 'Search participant, registration ID, schedule, date or attendance';
      context.hint = batch ? 'Only this attendance batch; table filters apply.' : 'Only active attendance batches. Select a result to open its batch.';
      if (!batch) context.entries.forEach((entry, i) => { entry.href = '#admin-student-attendance?batch=' + eligible[i].recollection_schedule_id; delete entry.kind; });
    } else if (route === 'admin-recollection-schedule') {
      const list = (rows || state.schedules || []).filter(s => s.year === state.current_year && !s.finalized_at);
      schedules(list);
      list.forEach((s, i) => { context.entries[i].terms += s.active && s.date < state.server_date ? ' Past' : s.active ? ' Upcoming' : ''; });
      context.placeholder = gridOptions('connected-schedules').placeholder;
    } else if (['admin-history', 'recollection-history'].includes(route)) {
      (rows || (admin ? state.history : state.registrations) || []).filter(r => r.is_archived && own(r)).forEach(r => registration(r, 'Recollection History', 'history'));
      context.placeholder = 'Search year, name, registration ID, schedule, attendance or certificate';
      context.hint = admin ? '' : 'Only your archived recollections and certificates.';
    } else if (route === 'admin-student-reciept') {
      (rows || active).forEach(r => registration(r, 'Receipts', 'receipt'));
      context.placeholder = gridOptions('connected-receipts').placeholder;
    } else if (['admin-certificate', 'certificate'].includes(route)) {
      (rows || (admin ? active : (state.registrations || []).filter(r => r.year === state.current_year && own(r)))).forEach(r => {
        registration(r, 'Certificates', 'registration');
        if (r.certificate) Object.assign(context.entries.at(-1), {href: '/certificates/' + r.certificate.id, kind: null});
      });
      context.placeholder = gridOptions('certificates').placeholder;
      if (student) context.hint = 'Your current-year certificate. Earlier certificates are in Recollection History.';
    } else if (['admin-schedule-change-requests', 'schedule-change-request'].includes(route)) {
      (rows || state.requests || []).forEach(r => {
        if (student && !active.some(reg => reg.id === r.registration_id)) return;
        const status = student && ['Rejected','Closed'].includes(r.status) ? 'Not Approved' : r.status;
        add('Schedule change requests', `Request #${r.id} · ${admin ? r.registration?.user?.name || 'Student' : 'My request'}`,
          [r.assigned_schedule?.title || r.requested_schedule?.title, r.assigned_schedule?.date || r.requested_schedule?.date, status],
          gridFields('connected-requests', student ? {...r,status} : r),
          admin ? {kind: 'request', id: r.id} : {kind: 'request', id: r.id});
      });
      context.placeholder = 'Search request ID, student, schedule, date or status';
      if (student) { context.placeholder = 'Search my request ID, schedule, date or status'; context.hint = 'Only your current-year schedule change requests.'; }
    } else if (['admin-announcements', 'announcements'].includes(route)) {
      (state.announcements || []).filter(a => a.published_at).forEach(a => add('Announcements', a.title,
        [a.preview, a.published_at?.slice(0, 10)], [a.title, a.preview, a.body, a.published_at, admin ? a.author?.name : ''], {kind: 'announcement', id: a.id}));
      context.placeholder = 'Search published announcements, words or dates';
      context.hint = 'Published office announcements only.';
    } else if (route === 'admin-course-year') {
      (rows || state.academic_options || []).forEach(r => add('Academic options', r.name, r.type.replaceAll('_', ' '), gridFields('academic', r), {kind: 'filter', query: r.name}));
      context.placeholder = gridOptions('academic').placeholder;
    } else if (route === 'admin-activity') {
      (rows || state.logs || []).forEach(r => add('Activity log', r.action, [r.actor || 'Public / system', r.created_at, r.record_type, r.record_id], gridFields('activity', r), {kind: 'filter', query: text([r.action, r.record_type, r.record_id])}));
      context.placeholder = gridOptions('activity').placeholder;
      context.hint = 'Latest 200 activity records loaded by this page; table filters apply.';
    } else if (route === 'admin-password-resets') {
      (rows || []).forEach(r => add('Password reset requests', `Request #${r.id} · ${r.user?.name || 'Student'}`,
        [r.user?.student_id, recoveryStatus ? recoveryStatus(r) : r.display_status, r.created_at?.slice(0, 10)],
        gridFields('recovery-', {...r, display_status: recoveryStatus ? recoveryStatus(r) : r.display_status}), {kind: 'recovery', id: r.id}));
      context.placeholder = gridOptions('recovery-').placeholder;
      context.hint = 'Only the selected request tab and table filters. Recovery credentials are never searched.';
    }
    // Suggestions come from actual visible result categories, never sample records.
    context.suggestions = [...new Set(context.entries.map(e => e.group))];
    return context;
  }
  function find(entries, query, group = '') {
    const q = normalize(query);
    return entries.filter(e => (!group || e.group === group) && matches(text([e.title, e.detail, e.terms]), q))
      .map((e, i) => ({e, i, rank: q && normalize(e.title) === q ? 0 : q && normalize(e.title).startsWith(q) ? 1 : 2}))
      .sort((a, b) => a.rank - b.rank || a.i - b.i).map(item => item.e);
  }

  const drafts = new Map(); // Tab-memory only; no localStorage, history of queries, or network requests.
  let serial = 0;
  function mount(host, context, select) {
    if (!host) return;
    if (!context) { host.replaceChildren(); host._cfoSearch = null; host.onkeydown = host.onfocusout = null; host.hidden = true; return; }
    host.hidden = false;
    if (host._cfoSearch?.key === context.key) { host._cfoSearch.update(context, select); return; }
    const id = 'cfo-search-' + (++serial);
    const categories = !context.key.startsWith('Admin:');
    const draft = drafts.get(context.key) || {query: '', group: ''};
    if (!categories) draft.group = '';
    drafts.set(context.key, draft);
    host.className = 'cfo-search';
    host.setAttribute('role', 'search');
    host.innerHTML = `<div class="cfo-search-heading"><label for="${id}"></label><span class="cfo-search-scope"></span></div><div class="cfo-search-controls"><input id="${id}" type="search" maxlength="160" autocomplete="off" spellcheck="false" aria-describedby="${id}-help" aria-controls="${id}-results" aria-expanded="false"><button type="button" class="cfo-search-clear">Clear search</button></div><p id="${id}-help" class="cfo-search-help"></p><section id="${id}-results" class="cfo-search-results" aria-label="Search suggestions and results" hidden>${categories ? '<div class="cfo-search-tools"><label>Category <select aria-label="Search category"></select></label><button type="button" class="cfo-search-close">Close results</button></div>' : ''}<p class="cfo-search-status" role="status" aria-live="polite"></p><div class="cfo-search-list"></div><button type="button" class="cfo-search-more" hidden>Show more results</button></section>`;
    const input = host.querySelector('input'), results = host.querySelector('.cfo-search-results');
    const group = host.querySelector('select'), list = host.querySelector('.cfo-search-list'), status = host.querySelector('[role=status]');
    const more = host.querySelector('.cfo-search-more');
    let data = context, onSelect = select, limit = 12;
    const el = (tag, value, cls) => { const node = document.createElement(tag); node.textContent = value; if (cls) node.className = cls; return node; };
    function open(value) { results.hidden = !value; input.setAttribute('aria-expanded', String(value)); }
    function draw() {
      const found = find(data.entries, input.value, group?.value || '');
      list.replaceChildren();
      const groups = new Map();
      found.slice(0, limit).forEach(entry => {
        let section = list;
        if (categories) {
          section = groups.get(entry.group);
          if (!section) { section = el('section', ''); section.append(el('h3', entry.group)); groups.set(entry.group, section); list.append(section); }
        }
        const control = el(entry.href ? 'a' : 'button', '', 'cfo-search-result');
        if (entry.href) control.href = entry.href; else control.type = 'button';
        control.append(el('strong', entry.title), el('span', entry.detail));
        control.addEventListener('click', () => {
          if (!entry.href) {
            // Existing dialogs capture their opener; return to the visible input after closing.
            input.focus(); open(false); onSelect?.(entry);
          } else open(false);
        });
        section.append(control);
      });
      status.textContent = !data.entries.length ? 'No records are available in this scope. Check the selected page, batch or table filters.'
        : !found.length ? 'No matches in this scope. Try a name, ID, date or fewer words, or clear the table filters.'
        : `${input.value.trim() ? 'Found' : 'Suggestions:'} ${found.length} ${found.length === 1 ? 'result' : 'results'} · Showing ${Math.min(limit, found.length)}.`;
      more.hidden = found.length <= limit;
      host.querySelector('.cfo-search-clear').disabled = !input.value && !group?.value;
    }
    function update(next, callback) {
      data = next; onSelect = callback;
      host.querySelector('label').textContent = 'Search · ' + data.title;
      host.setAttribute('aria-label', 'Search ' + data.title);
      host.querySelector('.cfo-search-scope').textContent = 'Current page';
      host.querySelector('.cfo-search-help').textContent = data.hint;
      input.placeholder = data.placeholder;
      if (group) {
        const selected = group.value || draft.group;
        group.replaceChildren(new Option('All categories', ''), ...data.suggestions.map(s => new Option(s, s)));
        group.value = data.suggestions.includes(selected) ? selected : '';
      }
      input.disabled = false; host.removeAttribute('aria-busy'); draw();
    }
    input.value = draft.query;
    input.addEventListener('focus', () => { draw(); open(true); });
    input.addEventListener('input', () => { draft.query = input.value; limit = 12; draw(); open(true); });
    group?.addEventListener('change', () => { draft.group = group.value; limit = 12; draw(); });
    host.querySelector('.cfo-search-clear').onclick = () => { input.value = ''; if (group) group.value = ''; draft.query = ''; draft.group = ''; limit = 12; draw(); input.focus(); open(true); };
    const close = host.querySelector('.cfo-search-close'); if (close) close.onclick = () => { input.focus(); open(false); };
    more.onclick = () => { limit += 12; draw(); list.querySelectorAll('.cfo-search-result')[limit - 12]?.focus(); };
    host.onkeydown = event => {
      if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); input.focus(); open(false); }
      if (['ArrowDown', 'ArrowUp'].includes(event.key) && (event.target === input || event.target.closest('.cfo-search-result'))) {
        event.preventDefault(); open(true);
        const controls = [...list.querySelectorAll('.cfo-search-result')], index = controls.indexOf(document.activeElement);
        const next = index < 0 ? (event.key === 'ArrowDown' ? 0 : controls.length - 1)
          : (index + (event.key === 'ArrowDown' ? 1 : -1) + controls.length) % controls.length;
        controls[next]?.focus();
      }
      if (event.key === 'Enter' && event.target === input) { event.preventDefault(); open(true); list.querySelector('.cfo-search-result')?.focus(); }
    };
    host.onfocusout = () => setTimeout(() => { if (!host.contains(document.activeElement)) open(false); }, 0);
    host._cfoSearch = {key: context.key, update};
    update(context, select);
  }
  function pending(host, message = 'Loading this page’s search records…', failed = false) {
    if (!host) return;
    host.replaceChildren(); host._cfoSearch = null; host.onkeydown = host.onfocusout = null; host.hidden = false; host.className = 'cfo-search';
    host.setAttribute('aria-busy', String(!failed));
    const status = document.createElement('p'); status.setAttribute('role', failed ? 'alert' : 'status');
    status.textContent = message; host.append(status);
  }
  const api = {matches, normalize, registrationAcademicGroup, gridFields, gridOptions, sortRows, buildContext, find, mount, pending};
  if (typeof document !== 'undefined') document.addEventListener('pointerdown', event => {
    document.querySelectorAll('.cfo-search').forEach(host => {
      if (!host.contains(event.target)) {
        const results = host.querySelector('.cfo-search-results');
        if (results) results.hidden = true;
        host.querySelector('input')?.setAttribute('aria-expanded', 'false');
      }
    });
  });
  root.CfoSearch = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window === 'undefined' ? globalThis : window);
