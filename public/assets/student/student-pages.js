/* Student "Recollection History", "View All Announcements" and "My Profile" pages.
 * Markup and class names follow cfo_student_module_recommended_chrome_preview.html (page content only;
 * the existing dashboard shell stays). student/index.blade.php only calls pageHtml() and mount(). */
(function (root) {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  // Filters and selection survive the live re-render that follows every state refresh.
  const ui = {hq: '', hy: '', ha: '', aq: '', as: 'newest', sel: null, hash: ''};

  const toDate = value => { const s = String(value || ''); return new Date(/^\d{4}-\d{2}-\d{2}$/.test(s) ? s + 'T00:00:00' : s); };
  const dateText = (value, month = 'long') => { const d = toDate(value); return isNaN(d) ? String(value || '—') : d.toLocaleDateString('en-US', {year: 'numeric', month, day: 'numeric'}); };
  const tones = {Present: 'st-green', Absent: 'st-red', Available: 'st-green'};
  const badge = (text, tone) => `<span class="status ${tone || tones[text] || 'st-gray'}">${esc(text)}</span>`;
  const heading = (eyebrow, id, title, text, extra = '') => `<div class="heading"><div><div class="eyebrow">${eyebrow}</div><h1 id="${id}">${title}</h1><p>${text}</p></div>${extra}</div>`;
  const wrap = (name, html) => `<div class="sp" data-student-page="${name}">${html}</div>`;
  const mail = value => (root.studentEmailDisplay || esc)(value);

  /* ---------- modal (one at a time; the existing <dialog> notices/confirmations open on top of it) ---------- */
  let backdrop = null, opener = null, historyDocuments = null;
  function closeModal() {
    if (!backdrop) return;
    historyDocuments?.closePreview(); historyDocuments = null;
    backdrop.remove(); backdrop = null;
    document.body.style.overflow = '';
    opener?.focus?.();
  }
  function openModal(title, body, foot, asForm) {
    closeModal();
    opener = document.activeElement;
    const tag = asForm ? 'form' : 'div';
    backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop sp open';
    backdrop.setAttribute('role', 'dialog'); backdrop.setAttribute('aria-modal', 'true'); backdrop.setAttribute('aria-labelledby', 'sp-modal-title');
    backdrop.innerHTML = `<${tag} class="modal"><div class="modal-head"><h2 id="sp-modal-title">${esc(title)}</h2><button class="close" type="button" data-close aria-label="Close">×</button></div><div class="modal-body">${body}</div><div class="modal-foot">${foot}</div></${tag}>`;
    backdrop.addEventListener('click', event => { if (event.target === backdrop || event.target.closest('[data-close]')) closeModal(); });
    document.body.append(backdrop);
    document.body.style.overflow = 'hidden';
    (backdrop.querySelector('input') || backdrop.querySelector('[data-close]')).focus({preventScroll: true});
    return backdrop;
  }
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && backdrop && !document.querySelector('dialog[open]')) closeModal();
  });

  /* ---------- Recollection History (archived registrations, read-only) ---------- */
  const archived = state => (state.registrations || []).filter(r => r.is_archived);
  const group = r => { const s = r.student_snapshot || {}, d = r.details || {}; return [s.course || d.course, s.year_level || d.yearLevel, s.section || d.section].filter(Boolean).join(' · ') || '—'; };
  const certState = r => r.certificate ? 'Available' : (r.certificate_record?.revoked_at ? 'Withdrawn' : 'Not available');
  const certNo = r => r.certificate_record?.certificate_number || r.certificate?.certificate_number || '';
  const attendance = r => r.attendance === 'Not Yet Recorded' ? 'Not recorded' : (r.attendance || 'Not recorded');
  const when = r => r.schedule ? [dateText(r.schedule.date), r.schedule.time].filter(Boolean).join(' · ') : 'No schedule assigned';
  const matches = r => {
    const q = ui.hq.trim().toLowerCase();
    const hay = [r.schedule?.title, r.year, r.schedule?.date, r.schedule && dateText(r.schedule.date), r.schedule?.venue, group(r), r.status, r.attendance].join(' ').toLowerCase();
    return (!q || hay.includes(q)) && (!ui.hy || String(r.year) === ui.hy) && (!ui.ha || r.attendance === ui.ha);
  };
  const historyRow = r => `<tr><td><div class="main-text">${esc(r.schedule?.title || 'Recollection')}</div><div class="sub-text">${esc(when(r))}</div></td><td>${esc(r.year)}</td><td>${badge(r.status, 'st-blue')}</td><td>${badge(attendance(r), tones[r.attendance])}</td><td>${badge(certState(r), tones[certState(r)])}${certNo(r) ? `<div class="sub-text">${esc(certNo(r))}</div>` : ''}</td><td><button class="btn small soft" type="button" data-history-id="${r.id}">View Details</button></td></tr>`;
  const count = n => `${n} record${n === 1 ? '' : 's'}`;

  function historyHtml(state) {
    const all = archived(state), list = all.filter(matches);
    const years = [...new Set(all.map(r => String(r.year)))].sort().reverse();
    const card = (label, icon, id, value, note) => `<div class="metric"><div class="metric-top"><span>${label}</span><span class="metric-icon">${icon}</span></div><div class="metric-number" id="${id}">${value}</div><div class="metric-note">${note}</div></div>`;
    return wrap('history', `${heading('STUDENT RECORDS', 'history-title', 'Recollection History', 'View your archived recollection attempts, attendance, and certificate availability.')}
    <div class="cards" style="grid-template-columns:repeat(3,minmax(0,1fr))">${card('Total Records', '▤', 'historyTotal', all.length, 'Archived attempts')}${card('Present', '✓', 'historyPresent', all.filter(r => r.attendance === 'Present').length, 'Attendance recorded')}${card('Certificates', '♙', 'historyCerts', all.filter(r => r.certificate).length, 'Available records')}</div>
    <div class="panel"><div class="panel-head"><div><h2>Find a Recollection Record</h2><p>Search your archived record or filter by year and attendance.</p></div></div><div class="panel-body"><div class="filters"><div class="field search"><label for="historySearch">Search records</label><input id="historySearch" type="search" placeholder="Schedule, year, date, venue…" value="${esc(ui.hq)}"></div><div class="field"><label for="historyYear">Academic Year</label><select id="historyYear"><option value="">All years</option>${years.map(y => `<option${y === ui.hy ? ' selected' : ''}>${esc(y)}</option>`).join('')}</select></div><div class="field"><label for="historyAttendance">Attendance</label><select id="historyAttendance"><option value="">All statuses</option>${['Present', 'Absent'].map(a => `<option${a === ui.ha ? ' selected' : ''}>${a}</option>`).join('')}</select></div><button class="btn" type="button" id="clearHistory">Clear Filters</button></div></div>
    <div class="table-summary"><div><strong>Your Recollection Records</strong><p id="historyCount">${count(list.length)}</p></div><span class="status st-blue">Read-only History</span></div><div class="table-wrap"><table><thead><tr><th>Recollection / Date</th><th>Academic Year</th><th>Registration</th><th>Attendance</th><th>Certificate</th><th>Actions</th></tr></thead><tbody id="historyRows">${list.map(historyRow).join('')}</tbody></table><div class="empty" id="historyEmpty"${list.length ? '' : ' style="display:block"'}><strong>No archived recollection records found.</strong>Completed batches appear here after the office moves them to History.</div></div><div class="table-foot"><span id="historyShown">Showing ${list.length} of ${all.length} records</span><span>Archived records are read-only.</span></div></div>`);
  }

  function mountHistory(page, {state, request, showNotice}) {
    const $ = selector => page.querySelector(selector), all = archived(state);
    const render = () => {
      const list = all.filter(matches);
      $('#historyRows').innerHTML = list.map(historyRow).join('');
      $('#historyEmpty').style.display = list.length ? 'none' : 'block';
      $('#historyCount').textContent = count(list.length);
      $('#historyShown').textContent = `Showing ${list.length} of ${all.length} records`;
    };
    $('#historySearch').addEventListener('input', event => { ui.hq = event.target.value; render(); });
    $('#historyYear').addEventListener('change', event => { ui.hy = event.target.value; render(); });
    $('#historyAttendance').addEventListener('change', event => { ui.ha = event.target.value; render(); });
    $('#clearHistory').addEventListener('click', () => {
      ui.hq = ui.hy = ui.ha = '';
      $('#historySearch').value = $('#historyYear').value = $('#historyAttendance').value = '';
      render();
    });
    page.addEventListener('click', event => {
      const r = all.find(x => String(x.id) === event.target.closest('[data-history-id]')?.dataset.historyId);
      if (!r) return;
      const box = (label, value, full) => `<div class="detail-box${full ? ' full' : ''}"><label>${label}</label><strong>${esc(value)}</strong></div>`;
      const links = root.CfoHistoryDocuments.controlsHtml(r);
      const modal = openModal(`Recollection Record · ${r.year}`,
        `<div class="detail-grid">${box('Academic Year', r.year)}${box('Registration', r.status)}${box('Date / Time', when(r))}${box('Venue', r.schedule?.venue || '—')}${box('Course / Year / Section', group(r))}${box('Attendance', attendance(r))}${box('Certificate', certState(r) === 'Available' ? 'Available · ' + certNo(r) : certState(r) === 'Withdrawn' ? 'Withdrawn by the CFO Office' : 'Not available for this archived attempt', true)}</div><div id="history-detail" hidden></div>`,
        `<button class="btn" type="button" data-close>Close</button><button class="btn soft" type="button" data-full>Full Record</button>${links}`);
      const documentActions = root.CfoHistoryDocuments.actions({parentDialog:modal,student:true,isParentOpen:()=>modal.isConnected,toast:message=>showNotice('Document preview',message)});
      historyDocuments = documentActions;
      modal.addEventListener('click', event => {
        const control = event.target.closest('[data-action]'), action = control && documentActions[control.dataset.action];
        if (action) Promise.resolve(action(control.dataset.id)).catch(error=>showNotice('Unable to open document',error.message));
      });
      // The complete archived record (receipt, change requests, submitted form) comes from the existing endpoint.
      modal.querySelector('[data-full]').addEventListener('click', async event => {
        const button = event.currentTarget, target = modal.querySelector('#history-detail');
        button.disabled = true;
        try {
          target.innerHTML = root.cfoHistoryDetails(await request('/api/history/' + r.id),{modalDocuments:true});
          target.hidden = false; button.hidden = true;
        } catch (error) { button.disabled = false; showNotice('Unable to open record', error.message); }
      });
    });
  }

  /* ---------- View All Announcements (published announcements, read-only) ---------- */
  const NEW_DAYS = 7;
  const isNew = a => Date.now() - toDate(a.published_at).getTime() < NEW_DAYS * 86400000;
  const announcementList = state => (state.announcements || [])
    .filter(a => [a.title, a.preview, a.body, dateText(a.published_at, 'short')].join(' ').toLowerCase().includes(ui.aq.trim().toLowerCase()))
    .sort((a, b) => (toDate(b.published_at) - toDate(a.published_at)) * (ui.as === 'oldest' ? -1 : 1));
  const selectedAnnouncement = list => list.find(a => a.id === ui.sel) || (ui.sel === 0 ? null : list[0]);
  const announcementItem = (a, selected, focus) => `<button class="announcement-item${selected ? ' selected' : ''}" type="button" data-announcement="${a.id}"${focus ? ' id="announcement-focus" tabindex="-1"' : ''}><span class="announce-icon">♧</span><span class="announce-main"><div class="announce-title">${esc(a.title)}${isNew(a) ? '<span class="new-tag">NEW</span>' : ''}</div><div class="announce-date">${esc(dateText(a.published_at, 'short'))}</div><div class="announce-preview">${esc(a.preview)}</div><div class="help" style="color:#08614d;font-weight:750;margin-top:8px">▸ Read full announcement</div></span><span>›</span></button>`;
  const announcementDetail = a => a
    ? `<div class="detail-head"><h2>Announcement Details</h2><button class="btn small" type="button" data-announcement-close>Close</button></div><div class="detail-content"><div class="detail-title-row"><div class="announce-icon">♧</div><div><h3>${esc(a.title)}</h3><p>Published on ${esc(dateText(a.published_at, 'short'))}</p><p>From: Christian Formation Office</p></div></div><div class="announcement-body reminder">${esc(a.body)}</div></div>`
    : `<div class="detail-head"><h2>Announcement Details</h2></div><div class="blank-detail"><div class="big-icon">♧</div><strong>Select an announcement</strong><div class="help">Choose an announcement from the list to read the full content.</div></div>`;

  function announcementsHtml(state) {
    // A notification / dashboard link (#announcements?announcement=ID) opens that announcement once per visit.
    if (location.hash !== ui.hash) {
      ui.hash = location.hash;
      const target = (state.announcements || []).find(a => String(a.id) === new URLSearchParams(location.hash.split('?')[1] || '').get('announcement'));
      if (target) ui.sel = target.id;
    }
    const list = announcementList(state), selected = selectedAnnouncement(list), linked = /[?&]announcement=/.test(location.hash);
    return wrap('announcements', `${heading('CHRISTIAN FORMATION OFFICE', 'announcements-title', 'View All Announcements', 'Published updates from the Christian Formation Office.')}
    <div class="announcement-layout"><div class="announcement-list-panel"><div class="search-sort"><input type="search" id="announcementSearch" placeholder="Search announcements…" value="${esc(ui.aq)}"><select id="announcementSort"><option value="newest">Newest First</option><option value="oldest"${ui.as === 'oldest' ? ' selected' : ''}>Oldest First</option></select></div><div class="announcement-list" id="announcementList"${list.length ? '' : ' style="display:none"'}>${list.map(a => announcementItem(a, a === selected, linked && a === selected)).join('')}</div><div class="empty" id="announcementEmpty"${list.length ? '' : ' style="display:block"'}>${(state.announcements || []).length ? 'No published announcements match your search.' : 'No announcements have been published.'}</div></div><div class="announcement-detail" id="announcementDetail">${announcementDetail(selected)}</div></div>`);
  }

  function mountAnnouncements(page, {state}) {
    const $ = selector => page.querySelector(selector);
    const render = () => {
      const list = announcementList(state), selected = selectedAnnouncement(list);
      $('#announcementList').innerHTML = list.map(a => announcementItem(a, a === selected)).join('');
      $('#announcementList').style.display = list.length ? 'grid' : 'none';
      $('#announcementEmpty').style.display = list.length ? 'none' : 'block';
      $('#announcementDetail').innerHTML = announcementDetail(selected);
    };
    $('#announcementSearch').addEventListener('input', event => { ui.aq = event.target.value; render(); });
    $('#announcementSort').addEventListener('change', event => { ui.as = event.target.value; render(); });
    page.addEventListener('click', event => {
      const item = event.target.closest('[data-announcement]');
      if (item) {
        ui.sel = Number(item.dataset.announcement); render();
        if (matchMedia('(max-width:1180px)').matches) $('#announcementDetail').scrollIntoView({behavior: 'smooth', block: 'start'});
      } else if (event.target.closest('[data-announcement-close]')) { ui.sel = 0; render(); }
    });
  }

  /* ---------- My Profile (view + edit basic information through the existing PUT /api/profile) ---------- */
  const initials = user => ((user.first_name || user.name || '').trim().charAt(0) + (user.last_name || '').trim().charAt(0)).toUpperCase() || 'ST';

  function profileHtml(state, user) {
    const line = (label, value) => `<div class="profile-line"><label>${label}</label><strong>${value}</strong></div>`;
    const course = [user.course, user.year_level, user.section].filter(Boolean).join(' · ') || '—';
    return wrap('profile', `${heading('STUDENT ACCOUNT', 'my-profile-title', 'My Profile', 'View your account and academic information.', '<button class="btn" type="button" id="profileEditBtn">Edit Basic Information</button>')}
    <div class="profile-grid"><div class="profile-summary"><div class="big-avatar">${esc(initials(user))}</div><h2>${esc(user.name)}</h2><p>Student ID: ${esc(user.student_id || '—')}</p><p style="margin-top:7px">Account status: ${esc(user.status || '—')}</p></div>
    <div class="panel" style="margin:0"><div class="panel-head"><div><h2>Personal Information</h2><p>Your saved account details.</p></div></div><div class="panel-body"><div class="profile-list">${line('Full Name', esc(user.name))}${line('Registered Gmail', mail(user.email))}${line('Login Email', mail(user.login_email || user.system_username || user.email))}${line('Course / Year / Section', esc(course))}</div></div></div></div>
    `);
  }

  function profileForm(user) {
    const field = (id, label, value, attrs, full) => `<div class="field${full ? ' full' : ''}"><label for="profile-${id}">${label}</label><input id="profile-${id}" name="${id}" value="${esc(value)}" ${attrs}></div>`;
    return `<div class="form-grid">${field('first-name', 'First Name', user.first_name, 'autocomplete="given-name" required')}${field('middle-name', 'Middle Name', user.middle_name, 'autocomplete="additional-name"')}${field('last-name', 'Last Name', user.last_name, 'autocomplete="family-name" required')}${field('email', 'Registered Gmail', user.email, 'type="email" autocomplete="email" required')}${field('current', 'Current Password *', '', 'type="password" autocomplete="current-password" required', true)}${field('password', 'New Password', '', 'type="password" autocomplete="new-password" placeholder="Leave blank to keep your password"')}${field('confirm', 'Confirm New Password', '', 'type="password" autocomplete="new-password"')}</div><div class="help">Enter your current password to save. Academic course, account approval, and assigned schedule are managed by the CFO Office. New passwords need 8+ characters, upper and lower case, a number and a symbol; changing your password signs you out.</div>`;
  }

  function mountProfile(page, {user, request, submit, showNotice, refresh}) {
    page.querySelector('#profileEditBtn').addEventListener('click', () => {
      const form = openModal('Edit Basic Information', profileForm(user), '<button class="btn" type="button" data-close>Cancel</button><button class="btn primary" type="submit">Save Changes</button>', true).querySelector('form');
      const val = id => form.querySelector('#profile-' + id).value, email = form.querySelector('#profile-email');
      root.protectStudentEmailInput?.(email);
      const emailNow = () => root.studentEmailValue ? root.studentEmailValue(email) : email.value;
      form.addEventListener('submit', event => {
        event.preventDefault();
        const review = [['first-name', 'First name', 'first_name'], ['middle-name', 'Middle name', 'middle_name'], ['last-name', 'Last name', 'last_name']]
          .flatMap(([id, label, key]) => val(id) === (user[key] || '') ? [] : [{label, before: user[key] || '', after: val(id)}]);
        if (emailNow() !== (user.email || '')) review.push({label: 'Registered email', before: user.email || '', after: emailNow()});
        if (val('password')) review.push({label: 'Password', after: 'Change password and sign out. You must sign in again with your new password.'});
        if (!review.length) return showNotice('Nothing to save', 'You have not changed any information.');
        submit(form, async () => {
          const result = await request('/api/profile', 'PUT', {first_name: val('first-name'), middle_name: val('middle-name'), last_name: val('last-name'), email: emailNow(), current_password: val('current'), password: val('password'), password_confirmation: val('confirm')});
          if (result.requires_login) { location.href = result.redirect; return; }
          root.CFO_BACKEND.user = result.user;
          closeModal(); refresh();
          showNotice('Profile saved', 'Your account information has been updated.');
        }, false, review);
      });
    });
  }

  /* ---------- public API (same shape as CfoStudentCertificates) ---------- */
  const pages = {history: [historyHtml, mountHistory], announcements: [announcementsHtml, mountAnnouncements], profile: [profileHtml, mountProfile]};
  const pageHtml = (name, state, user) => pages[name][0](state, user || {});
  // Returns a cleanup that closes any open modal when the student leaves the page.
  function mount(name, page, context) { closeModal(); pages[name][1](page, context); return closeModal; }

  root.CfoStudentPages = {pageHtml, mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoStudentPages;
})(typeof window === 'undefined' ? globalThis : window);
