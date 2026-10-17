/* Student "My Certificate" page: schedule selector, one certificate at a time, View / Print / Download.
 * The certificate itself is always the server's SVG (/certificates/{id}), so it matches the design the
 * admin saved in the certificate editor. student/index.blade.php only calls pageHtml() and mount(). */
(function (root) {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  let selectedId = '';   // survives the live re-render that follows every state refresh

  const url = (certificate, download) => '/certificates/' + encodeURIComponent(certificate.id) + (download ? '?download=1' : '');
  const issued = state => (state.registrations || []).filter(r => r.certificate && !r.certificate.revoked_at && r.attendance === 'Present' && r.attendance_verified_at);
  const find = (state, id) => issued(state).find(r => String(r.certificate.id) === String(id));
  const longDate = value => {
    const date = new Date(String(value || '') + 'T00:00:00');
    return isNaN(date) ? String(value || '—') : date.toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'});
  };
  const label = r => `${r.year} Recollection`;

  // Same wording the page used before, for a student who has no certificate to show yet.
  function emptyText(state) {
    const current = (state.registrations || []).find(r => r.year === state.current_year);
    if (!current) return ['No certificates available yet', 'No recollection registration for the current year yet.'];
    return current.attendance === 'Present' && current.attendance_verified_at
      ? ['No certificates available yet', 'Your certificate is being prepared automatically.']
      : ['No certificates available yet', 'Not available. Attendance must be marked Present and verified.'];
  }

  function selectedHtml(r, revision) {
    const c = r.certificate, schedule = r.schedule || {};
    const src = url(c) + '?v=' + encodeURIComponent(revision || '');
    return `<div class="cert-selected-head"><div><h2>${esc(label(r))}</h2><p>Certificate No. ${esc(c.certificate_number)}</p></div><span class="cert-status">Available</span></div>
      <div class="cert-stage"><img src="${src}" alt="Issued certificate for ${esc(label(r))}"></div>
      <dl class="cert-meta"><div><dt>Recollection date</dt><dd>${esc(longDate(schedule.date))}</dd></div><div><dt>Venue</dt><dd>${esc(schedule.venue || '—')}</dd></div><div><dt>Academic year</dt><dd>${esc(r.year)}</dd></div></dl>
      <div class="form-actions"><button type="button" class="outline" data-cert-action="view">View</button><button type="button" class="outline" data-cert-action="print">Print</button><button type="button" class="primary" data-cert-action="download">Download</button></div>`;
  }

  function pageHtml(state) {
    const rows = issued(state);
    if (!rows.some(r => String(r.certificate.id) === selectedId)) selectedId = '';
    const options = rows.map(r => `<option value="${esc(r.certificate.id)}"${String(r.certificate.id) === selectedId ? ' selected' : ''}>${esc(label(r))} — ${esc(longDate(r.schedule?.date))} · ${esc(r.certificate.certificate_number)}</option>`).join('');
    const [title, text] = rows.length ? ['Select a recollection schedule', 'Choose a schedule above to view its certificate. Only one certificate is displayed at a time.'] : emptyText(state);
    return `<section class="certificate-page"><div class="cert-heading"><h1 id="certificate-title">My Certificate</h1>
      <p>View, print, or download your automatically generated recollection certificates.</p></div>
      <p class="cert-notice">Certificates appear here automatically when your attendance is verified as <strong>Present</strong> and a certificate record is available. Only one certificate is displayed at a time.</p>
      <div class="cert-selector"><label class="field" for="certificate-schedule"><span class="cert-filter-label">Select Recollection Schedule</span><select id="certificate-schedule"${rows.length ? '' : ' disabled'} aria-describedby="certificate-filter-help"><option value="">Choose a recollection schedule</option>${options}</select></label>
      <p id="certificate-filter-help" class="cert-help">Previous-year certificate records are available in this list too.</p></div>
      <div id="certificate-selected" class="cert-selected" aria-live="polite"${selectedId ? '' : ' hidden'}>${selectedId ? selectedHtml(find(state, selectedId), state.certificate_revision) : ''}</div>
      <div id="certificate-empty" class="cert-empty"${selectedId ? ' hidden' : ''}><strong>${esc(title)}</strong><p>${esc(text)}</p></div></section>`;
  }

  // Print the server SVG exactly as the admin does: open it, then print when it has loaded.
  function print(certificate, showNotice) {
    const w = window.open(url(certificate), '_blank');
    if (!w) return showNotice('Print certificate', 'Allow pop-ups to print the certificate, or download the SVG and open it in your browser to print.');
    w.opener = null;
    w.addEventListener('load', () => { try { w.focus(); w.print(); } catch (error) { showNotice('Print certificate', 'Your certificate opened in a new tab. Use the browser Print command to print or save as PDF.'); } }, {once: true});
  }

  function download(certificate) {
    const a = document.createElement('a');
    a.href = url(certificate, true); a.rel = 'noopener';
    document.body.appendChild(a); a.click(); a.remove();
  }

  function view(r, revision, trigger) {
    const dialog = document.createElement('dialog'); dialog.id = 'certificate-view'; dialog.className = 'cert-view';
    dialog.setAttribute('aria-labelledby', 'certificate-view-title');
    dialog.innerHTML = `<div class="cert-selected-head"><div><h2 id="certificate-view-title">${esc(label(r))}</h2><p>Certificate No. ${esc(r.certificate.certificate_number)}</p></div><button type="button" class="outline" data-cert-action="close" aria-label="Close certificate preview">Close</button></div><div class="cert-stage"><img src="${url(r.certificate)}?v=${encodeURIComponent(revision || '')}" alt="Issued certificate for ${esc(label(r))}"></div>`;
    document.body.appendChild(dialog);
    const overflow = document.body.style.overflow;
    let restored = false;
    const restore = () => {
      if (restored) return;
      restored = true;
      document.body.style.overflow = overflow;
      if (trigger?.isConnected) trigger.focus({preventScroll: true});
    };
    const close = () => {if (dialog.open) dialog.close(); restore(); dialog.remove();};
    dialog.addEventListener('click', event => {
      if (event.target.closest('[data-cert-action="close"]')) return close();
      if (event.target === dialog) {
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
      }
    });
    dialog.addEventListener('cancel', event => {event.preventDefault(); close();});
    dialog.addEventListener('close', restore);
    dialog.showModal();
    document.body.style.overflow = 'hidden';
    dialog.querySelector('[data-cert-action="close"]').focus({preventScroll: true});
    return close;
  }

  function mount(page, {state, showNotice}) {
    let closePreview;
    const select = page.querySelector('#certificate-schedule');
    const box = page.querySelector('#certificate-selected'), empty = page.querySelector('#certificate-empty');
    select.addEventListener('change', () => {
      const r = find(state, select.value);
      selectedId = r ? String(r.certificate.id) : '';
      box.innerHTML = r ? selectedHtml(r, state.certificate_revision) : '';
      box.hidden = !r; empty.hidden = !!r;
    });
    page.addEventListener('click', event => {
      const action = event.target.closest('[data-cert-action]')?.dataset.certAction, r = find(state, selectedId);
      if (!action || !r) return;
      if (action === 'view') {closePreview?.(); closePreview = view(r, state.certificate_revision, event.target.closest('[data-cert-action]'));}
      else if (action === 'print') print(r.certificate, showNotice); else if (action === 'download') download(r.certificate);
    });
    return () => closePreview?.();
  }

  root.CfoStudentCertificates = {url, issued, longDate, emptyText, selectedHtml, pageHtml, mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoStudentCertificates;
})(typeof window === 'undefined' ? globalThis : window);
