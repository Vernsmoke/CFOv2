/* Schedule change requests (admin): markup builders for the table, summary cards and review dialog.
 * Pure functions: admin.blade.php supplies the data (cfoState.requests) and wires the actions. */
(function (root) {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const TONES = {Pending: 'warning', Approved: 'success', Rejected: 'danger', Closed: 'info'};
  const day = value => String(value || '').slice(0, 10);
  const person = r => r.registration?.user || {};
  const requestId = r => 'SCR-' + (day(r.created_at).slice(0, 4) || new Date().getFullYear()) + '-' + String(r.id).padStart(3, '0');

  // Same rule as Registration::academicGroup(): the selected registration fields, then the account.
  function group(r) {
    const details = r.registration?.details || {}, user = person(r);
    return details.course && details.yearLevel && details.section
      ? {course: details.course, year: details.yearLevel, section: details.section}
      : {course: user.course || '', year: user.year_level || '', section: user.section || ''};
  }
  const groupText = r => { const g = group(r); return [g.course, [g.year, g.section].filter(Boolean).join(' ')].filter(Boolean).join(' · '); };
  // The schedule the student held when the request was made (the registration may already be moved).
  const currentSchedule = r => r.current_schedule || r.registration?.schedule || null;
  const scheduleText = s => s ? [s.title, s.date].filter(Boolean).join(' · ') : '—';
  const badge = status => `<span class="badge ${TONES[status] || 'info'}">${esc(status)}</span>`;

  function statsHtml(rows, metric) {
    const count = status => rows.filter(r => r.status === status).length;
    return `<section class="cr-stats" aria-label="Request summary"><div class="metrics">`
      + metric('Total requests', rows.length, 'All requests this year', 'file')
      + metric('Pending review', count('Pending'), 'Waiting for a decision', 'clock')
      + metric('Approved', count('Approved'), 'New schedule assigned', 'check')
      + metric('Rejected', count('Rejected'), 'Not approved', 'close')
      + `</div></section>`;
  }

  const studentCell = r => `<div class="name-cell"><strong>${esc(person(r).name)}</strong><span>${esc([person(r).student_id, groupText(r)].filter(Boolean).join(' · '))}</span><span>Request ID: ${esc(requestId(r))}</span></div>`;
  function scheduleCell(r) {
    const s = currentSchedule(r);
    return s ? `<div class="name-cell"><strong>${esc(s.title)}</strong><span>${esc(s.date)}</span><span>${esc(s.time || '')}</span></div>` : '—';
  }
  function statusCell(r) {
    const assigned = r.assigned_schedule ? `<span class="cr-sub">Assigned: ${esc(scheduleText(r.assigned_schedule))}</span>` : '';
    return `<div class="cr-status">${badge(r.status)}${assigned}</div>`;
  }

  const courseOf = r => group(r).course;
  const columns = () => [
    {label: 'Student', get: r => person(r).name || '', html: studentCell},
    {label: 'Current schedule', get: r => scheduleText(currentSchedule(r)), html: scheduleCell},
    {label: 'Reason', get: r => r.reason || '', html: r => `<div class="cr-reason" title="${esc(r.reason)}">${esc(r.reason)}</div>`},
    {label: 'Request date', get: r => day(r.created_at), nowrap: true},
    {label: 'Status', get: r => r.status, html: statusCell}
  ];
  const courseFilter = (rows, unique) => ({key: 'course', label: 'Course', values: unique(rows.map(courseOf).filter(Boolean)), get: courseOf});
  const exportColumns = () => [
    {label: 'Request ID', get: requestId},
    {label: 'Student ID', get: r => person(r).student_id},
    {label: 'Student', get: r => person(r).name},
    {label: 'Course', get: r => group(r).course},
    {label: 'Year', get: r => group(r).year},
    {label: 'Section', get: r => group(r).section},
    {label: 'Current schedule', get: r => currentSchedule(r)?.title},
    {label: 'Current date', get: r => currentSchedule(r)?.date},
    {label: 'Reason', get: r => r.reason},
    {label: 'Request date', get: r => day(r.created_at)},
    {label: 'Status', get: r => r.status},
    {label: 'Assigned schedule', get: r => r.assigned_schedule?.title},
    {label: 'Assigned date', get: r => r.assigned_schedule?.date},
    {label: 'Decision note', get: r => r.review_reason}
  ];

  const pairs = list => `<dl class="detail-list">${list.map(([key, value]) => `<dt>${esc(key)}</dt><dd>${value?.html ?? esc(value || '—')}</dd>`).join('')}</dl>`;
  const scheduleDetail = s => s ? [s.title, s.date, s.time, s.venue].filter(Boolean).join(' · ') : '—';
  const section = (title, body) => `<section class="cr-section"><h3>${esc(title)}</h3>${body}</section>`;

  // Review dialog body. Pending requests get the assignment form; decided ones are read-only.
  function reviewHtml(r) {
    const user = person(r);
    let html = section('1. Student and current assignment', pairs([
      ['Student', user.name], ['Student ID', user.student_id], ['Course / Year / Section', groupText(r)],
      ['Current schedule', scheduleDetail(currentSchedule(r))], ['Request date', day(r.created_at)], ['Status', {html: badge(r.status)}]
    ]));
    html += section("2. Student's reason", `<div class="cr-reason-box">${esc(r.reason)}</div>`);

    if (r.status === 'Pending') {
      const eligible = r.eligible_schedules || [];
      const asked = r.requested_schedule && eligible.some(s => String(s.id) === String(r.requested_schedule.id)) ? String(r.requested_schedule.id) : '';
      const options = eligible.map(s => `<option value="${esc(s.id)}" ${String(s.id) === asked ? 'selected' : ''}>${esc(`${s.title} — ${s.date} · ${s.time} (${s.slots_left} slot${s.slots_left === 1 ? '' : 's'} left)`)}</option>`).join('');
      const picker = eligible.length
        ? `<label class="field">Available schedule *<select name="assigned_schedule_id" required><option value="">Select a schedule</option>${options}</select></label>`
        : `<div class="notice">No Open upcoming schedule is available for this student's course, year level and section. You can still reject the request, or open a matching schedule under Schedules first.</div>`;
      const legacy = r.requested_schedule ? `<p class="cr-help">This older request asked for ${esc(scheduleText(r.requested_schedule))}${asked ? '; it is preselected.' : ', which is no longer available.'}</p>` : '';
      html += section('3. Assign a new recollection schedule', `<form id="connected-request-form" class="cr-form" novalidate>${picker}${legacy}`
        + `<label class="field">Admin note<textarea name="review_reason" maxlength="2000" rows="3" aria-describedby="cr-note-help"></textarea><small id="cr-note-help">Optional when approving (kept in the CFO records). When rejecting, enter a reason that the student will see.</small></label>`
        + `<p class="cr-help">The student does not choose a new schedule. After approval the assigned schedule replaces the current one and appears in the student's attendance list.</p></form>`);
    } else {
      if (r.assigned_schedule) html += section('Assigned schedule', pairs([['New schedule', scheduleDetail(r.assigned_schedule)]]));
      if (r.review_reason) html += section(r.status === 'Rejected' ? 'Reason for not approving' : 'Admin note', `<div class="cr-reason-box">${esc(r.review_reason)}</div>`);
      if (r.status === 'Closed') html += `<p class="cr-help">This request was closed automatically because the student's schedule changed.</p>`;
    }
    return html;
  }

  // Validates the dialog form for a decision and returns the payload for PATCH /api/admin/change-requests/{id}.
  function decision(form, status) {
    if (!['Approved', 'Rejected'].includes(status)) return {ok: false, message: 'Choose whether to approve or reject this request.'};
    const note = String(form.elements.review_reason?.value || '').trim();
    if (note.length > 2000) return {ok: false, field: 'review_reason', message: 'Keep the admin note within 2,000 characters.'};
    if (status === 'Rejected') {
      return note ? {ok: true, values: {status, review_reason: note}}
        : {ok: false, field: 'review_reason', message: 'Enter the reason for rejecting this request in the admin note.'};
    }
    const picker = form.elements.assigned_schedule_id;
    const schedule = String(picker?.value || '');
    const available = /^\d+$/.test(schedule) && Number(schedule) > 0
      && (!picker.options || [...picker.options].some(option => option.value === schedule && !option.disabled));
    return available ? {ok: true, values: {status, assigned_schedule_id: schedule, review_reason: note}}
      : {ok: false, field: 'assigned_schedule_id', message: 'Select a new schedule before approving.'};
  }

  root.CfoChangeRequests = {requestId, statsHtml, columns, courseFilter, exportColumns, reviewHtml, decision};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoChangeRequests;
})(typeof window === 'undefined' ? globalThis : window);
