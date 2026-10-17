/* Admin Announcements: single-textbox composer, shared drafts, view and delete.
 * Reuses the portal's panels, dialogs and save helper. No dependencies or new CSS. */
(function (root) {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const TITLE_MAX = 190, PREVIEW_MAX = 140, BODY_MAX = 10000;
  const length = text => Array.from(String(text ?? '')).length;

  function preview(text) {
    const cleaned = String(text || '').replace(/\s+/g, ' ').trim();
    const sentence = cleaned.match(/^.*?[.!?](?:\s|$)/);
    const result = sentence ? sentence[0].trim() : cleaned;
    return length(result) > PREVIEW_MAX ? Array.from(result).slice(0, PREVIEW_MAX - 1).join('').trimEnd() + '…' : result;
  }

  function parse(text) {
    const lines = String(text || '').trim().split(/\r?\n/);
    const title = (lines[0] || '').trim();
    if (!title) return null;
    const body = lines.slice(1).join('\n').trim() || title;
    return {title, preview: preview(body), body};
  }

  function validate(values) {
    if (!values) return 'Enter announcement content first.';
    if (length(values.title) > TITLE_MAX) return 'Keep the title on the first line to 190 characters or fewer.';
    if (length(values.body) > BODY_MAX) return 'Keep the announcement body to 10,000 characters or fewer.';
    return '';
  }

  const compose = row => row.body && row.body !== row.title ? row.title + '\n\n' + row.body : row.title;
  function uuid() {
    const bytes = crypto.getRandomValues(new Uint8Array(16)); bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
  }
  let composer;
  const blank = () => ({content: '', draftId: '', publicationKey: uuid(), revision: '', baseline: ''});
  const state = () => composer || (composer = blank());
  function remember(form) {
    const current = state();
    Object.assign(current, {content: form.elements.content.value, draftId: form.elements.draft_id.value,
      publicationKey: form.elements.publication_key.value, revision: form.elements.draft_revision.value});
    return current;
  }
  function sync(form) {
    const current = remember(form), values = parse(current.content), message = validate(values);
    form.elements.content.setCustomValidity(message);
    form.querySelector('#ann-content-error').textContent = values ? message : '';
    form.querySelectorAll('[data-action="ann-publish"],[data-action="ann-save-draft"]').forEach(control => {control.disabled = Boolean(message);});
    form.dataset.dirty = String(current.content !== current.baseline);
  }
  function restore(form) {
    const current = state();
    form.elements.content.value = current.content;
    form.elements.draft_id.value = current.draftId;
    form.elements.publication_key.value = current.publicationKey;
    form.elements.draft_revision.value = current.revision;
    sync(form);
  }
  const empty = (cols, text) => `<tr><td colspan="${cols}">${esc(text)}</td></tr>`;
  const table = (heads, rows) => `<div class="table-wrap" tabindex="0" role="region" aria-label="Announcement table; scroll horizontally for more columns"><table><thead><tr>${heads.map(h => `<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div>`;

  function pageHtml({rows, panel, button}) {
    const drafts = rows.filter(r => !r.published_at).sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)) || Number(b.id) - Number(a.id));
    const published = rows.filter(r => r.published_at).sort((a, b) => String(b.published_at).localeCompare(String(a.published_at)) || Number(b.id) - Number(a.id));
    const draftRows = drafts.map(d => `<tr><td><strong>${esc(d.title)}</strong><br><span class="muted small">${esc(length(d.body) > 110 ? Array.from(d.body || '').slice(0, 110).join('') + '…' : d.body)}</span></td><td>${esc(d.updated_at)}</td><td><span class="badge warning">Draft</span></td><td><div class="actions">${button('Edit', 'ann-edit-draft', d.id, 'compact')}${button('Publish', 'ann-publish-draft', d.id, 'primary compact')}${button('Delete', 'ann-delete', d.id, 'danger compact')}</div></td></tr>`).join('')
      || empty(4, 'No draft announcements. Use Save draft to keep an announcement here before publishing.');
    const publishedRows = published.map(a => `<tr><td>${esc(a.title)}</td><td>${esc(a.preview)}</td><td>${esc(a.author?.name || 'Admin')}</td><td>${esc(a.published_at)}</td><td><div class="actions">${button('View', 'ann-view', a.id, 'compact')}${button('Delete', 'ann-delete', a.id, 'danger compact')}</div></td></tr>`).join('')
      || empty(5, 'No announcements have been published.');
    const current = state();
    // Native maxlength counts UTF-16 units; semantic limits above count Unicode characters like Laravel.
    const content = `<form id="connected-announcement-form"><input type="hidden" name="publication_key" value="${esc(current.publicationKey)}"><input type="hidden" name="draft_id" value="${esc(current.draftId)}"><input type="hidden" name="draft_revision" value="${esc(current.revision)}">
      <label class="field full" for="ann-content">Announcement content<textarea id="ann-content" name="content" rows="10" maxlength="${2 * (BODY_MAX + TITLE_MAX) + 4}" required aria-describedby="ann-content-help ann-content-error" placeholder="Enter the title on the first line, then write the full announcement below.">${esc(current.content)}</textarea></label>
      <p id="ann-content-help" class="muted small">Use the first line as the title (up to 190 characters), leave one blank line, then write the full announcement (up to 10,000 characters). Publishing sends approved students one notification with the title and a short preview; the full announcement appears on their View All Announcements page.</p>
      <p id="ann-content-error" class="muted small" aria-live="polite"></p>
      <div class="form-actions">${button('Clear', 'ann-clear')}${button('Save draft', 'ann-save-draft')}${button('Publish announcement', 'ann-publish', '', 'primary')}</div></form>`;
    return `<div class="stack">${panel('Publish announcement', content)}${panel('Draft announcements', table(['Draft', 'Last saved', 'Status', 'Actions'], draftRows))}${panel('Published announcements', table(['Title', 'Notification preview', 'Author', 'Published at', 'Actions'], publishedRows))}</div>`;
  }

  function bind(page) {
    const form = page.querySelector('#connected-announcement-form');
    restore(form);
    form.elements.content.addEventListener('input', () => sync(form));
    form.onsubmit = event => {event.preventDefault(); form.reportValidity();};
  }

  function actions({$, rows, saveConnected, showDialog, toast, button, closeDialog, render}) {
    const form = () => $('#connected-announcement-form');
    const row = id => rows().find(r => String(r.id) === String(id));
    let pending, discard, busy = false;
    const active = () => Boolean(form()?.isConnected);
    const payload = f => {
      sync(f);
      const values = parse(f.elements.content.value);
      if (validate(values)) {f.reportValidity(); return null;}
      const saved = row(f.elements.draft_id.value);
      if (saved && f.elements.content.value === compose(saved)) values.preview = saved.preview;
      return values;
    };
    const draftFields = r => r.draft_revision ? {draft_revision: r.draft_revision} : {};
    function ask(url, method, values, message, effect) {
      if (busy || !active()) return;
      remember(form());
      pending = {url, method, values, effect, route: location.hash, composerKey: state().publicationKey};
      showDialog('Confirm announcement changes', `<p>${esc(message)}</p>`, button('Cancel', 'close-dialog') + button('Confirm and save', 'ann-confirm-save'));
      const intent = pending, dialog = $('#dialog');
      dialog.addEventListener('close', () => {if (!dialog.open && !busy && pending === intent) pending = null;}, {once: true});
    }
    function replaceComposer(next) {
      composer = next;
      if (form()) {restore(form()); form().elements.content.focus();}
    }
    function replaceAfterConfirm(next, message) {
      if (busy || !active()) return;
      const current = remember(form());
      if (current.content !== current.baseline) {
        discard = () => replaceComposer(next());
        showDialog('Replace unsaved announcement?', `<p>${esc(message)}</p>`, button('Keep editing', 'close-dialog') + button('Continue', 'ann-confirm-discard', '', 'danger'));
      } else replaceComposer(next());
    }
    return {
      'ann-clear': () => replaceAfterConfirm(blank, 'Clear the unsaved announcement content?'),
      'ann-confirm-discard': () => {if (busy || !discard || !$('#dialog')?.open) return; const intent = discard; discard = null; closeDialog(); intent();},
      'ann-save-draft': () => {
        const f = form(); if (busy || !f) return;
        const values = payload(f); if (!values) return;
        const id = f.elements.draft_id.value;
        ask(id ? `/api/admin/announcements/${encodeURIComponent(id)}` : '/api/admin/announcements/drafts', id ? 'PUT' : 'POST',
          {...values, ...(id ? {draft_revision: f.elements.draft_revision.value} : {publication_key: f.elements.publication_key.value})},
          'Save this announcement as an admin-only draft? Students will not see it or receive a notification.', 'save');
      },
      'ann-publish': () => {
        const f = form(); if (busy || !f) return;
        const values = payload(f); if (!values) return;
        const id = f.elements.draft_id.value;
        ask(id ? `/api/admin/announcements/${encodeURIComponent(id)}/publish` : '/api/admin/announcements', 'POST',
          {...values, ...(id ? {draft_revision: f.elements.draft_revision.value} : {publication_key: f.elements.publication_key.value})},
          'Publish this announcement and notify approved students? Published announcements cannot be edited.', 'publish');
      },
      'ann-edit-draft': id => {
        const r = row(id); if (!r || r.published_at) return;
        replaceAfterConfirm(() => ({content: compose(r), draftId: String(r.id), publicationKey: r.publication_key || uuid(), revision: r.draft_revision || '', baseline: compose(r)}), 'Load this draft and replace the unsaved content?');
      },
      'ann-publish-draft': id => {
        const r = row(id); if (!r || r.published_at) return;
        ask(`/api/admin/announcements/${encodeURIComponent(r.id)}/publish`, 'POST', {title: r.title, preview: r.preview, body: r.body, ...draftFields(r)},
          `Publish the saved draft “${r.title}” and notify approved students? Unsaved composer text will be kept.`, 'publish-row');
      },
      'ann-delete': id => {
        const r = row(id); if (!r) return;
        ask(`/api/admin/announcements/${encodeURIComponent(r.id)}`, 'DELETE', {},
          r.published_at ? `Delete “${r.title}” and its student notifications? This cannot be undone.` : `Delete the draft “${r.title}”? This cannot be undone.`, {deleteId: r.id});
      },
      'ann-view': id => {const r = row(id); if (r && r.published_at) showDialog(r.title, `<p style="white-space:pre-wrap">${esc(r.body)}</p><p class="muted small"><strong>Student notification preview:</strong> ${esc(r.preview)}</p>`);},
      'ann-confirm-save': async () => {
        if (busy || !pending || !$('#dialog')?.open) return;
        const operation = pending; busy = true; let committed = false;
        try {
          await saveConnected(operation.url, operation.method, operation.values, true, result => {
            committed = true; pending = null;
            const records = rows(), saved = result.announcement;
            if (saved) {const index = records.findIndex(r => String(r.id) === String(saved.id)); if (index < 0) records.unshift(saved); else records[index] = saved;}
            if (operation.effect?.deleteId !== undefined) {
              const index = records.findIndex(r => String(r.id) === String(operation.effect.deleteId)); if (index >= 0) records.splice(index, 1);
              if (String(state().draftId) === String(operation.effect.deleteId)) Object.assign(state(), {draftId: '', revision: '', publicationKey: uuid(), baseline: ''});
            } else if (operation.effect === 'save' && saved && state().publicationKey === operation.composerKey) {
              Object.assign(state(), {draftId: String(saved.id), revision: saved.draft_revision || '', publicationKey: saved.publication_key || state().publicationKey, baseline: state().content});
            } else if (operation.effect === 'publish' && state().publicationKey === operation.composerKey) composer = blank();
            else if (operation.effect === 'publish-row' && String(state().draftId) === String(saved?.id)) {
              if (state().content === state().baseline) composer = blank();
              else Object.assign(state(), {draftId: '', revision: '', publicationKey: uuid(), baseline: ''});
            }
          });
          if (committed && operation.route === location.hash) render();
        } finally {busy = false;}
      }
    };
  }

  root.CfoAnnouncements = {preview, parse, validate, compose, pageHtml, bind, actions};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoAnnouncements;
})(typeof window === 'undefined' ? globalThis : window);
