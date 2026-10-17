/* Shared document previews opened above admin or student history details. */
(function (root) {
  'use strict';
  const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function documentSpec(kind, id) {
    if (!['certificate', 'receipt'].includes(kind) || !/^[1-9]\d*$/.test(String(id))) throw new Error('Document not available.');
    return {kind, id:String(id), title:kind === 'certificate' ? 'Recollection certificate' : 'Recollection receipt',
      url:kind === 'certificate' ? '/certificates/' + id : '/api/receipts/' + id};
  }

  function dialogHtml(spec, student = false) {
    return `<div class="${student ? 'modal-head' : 'dialog-header'}"><h2 id="history-document-title">${escape(spec.title)}</h2><button type="button" class="${student ? 'close' : 'icon-btn'}" data-history-preview="close" aria-label="Close document preview">&times;</button></div>
      <div class="${student ? 'modal-body' : 'dialog-body'}"><p role="status" data-preview-status>Loading ${spec.kind}…</p><img data-preview-image hidden alt="${escape(spec.title)}" style="display:block;max-width:100%;max-height:65vh;height:auto;margin:auto;background:white"></div>
      <div class="${student ? 'modal-foot' : 'dialog-footer'}"><button type="button" class="btn" data-history-preview="retry" hidden>Retry</button><button type="button" class="btn" data-history-preview="close">Close</button><button type="button" class="btn" data-history-preview="download" disabled>${spec.kind === 'certificate' ? 'Download SVG' : 'Download receipt'}</button><button type="button" class="btn primary" data-history-preview="print" disabled>Print</button></div>`;
  }

  function controlsHtml(registration) {
    return '<div class="form-actions">' + (registration.has_receipt ? `<button type="button" class="btn outline" data-action="history-receipt-preview" data-id="${escape(registration.id)}">View receipt</button>` : '') +
      (registration.certificate ? `<button type="button" class="btn primary" data-action="history-certificate-preview" data-id="${escape(registration.certificate.id)}">View / Print certificate</button><a class="btn outline" href="${escape(documentSpec('certificate', registration.certificate.id).url)}?download=1">Download SVG</a>` : '') + '</div>';
  }

  function printHtml(spec, imageUrl) {
    return `<!doctype html><html><head><meta charset="utf-8"><title>${escape(spec.title)}</title><style>@page{size:${spec.kind === 'certificate' ? 'landscape' : 'portrait'};margin:10mm}html,body{margin:0}img{display:block;max-width:100%;max-height:95vh;margin:auto}</style></head><body><img src="${escape(imageUrl)}" alt="${escape(spec.title)}"></body></html>`;
  }

  function actions({parentDialog, toast, student = false, isParentOpen = () => parentDialog.open}) {
    let active;
    async function open(kind, id) {
      const spec = documentSpec(kind, id);
      active?.close();
      const doc = root.document, opener = doc.activeElement;
      const dialog = doc.createElement('dialog');
      dialog.id = 'history-document-dialog'; dialog.className = student ? 'cert-view sp' : 'large';
      dialog.setAttribute('aria-labelledby', 'history-document-title');
      dialog.innerHTML = dialogHtml(spec, student);
      doc.body.appendChild(dialog);
      const image = dialog.querySelector('[data-preview-image]'), status = dialog.querySelector('[data-preview-status]');
      const print = dialog.querySelector('[data-history-preview="print"]'), download = dialog.querySelector('[data-history-preview="download"]');
      const retry = dialog.querySelector('[data-history-preview="retry"]');
      let closed = false, controller, imageUrl, imageType, printFrame;
      const cleanup = () => {
        if (closed) return;
        closed = true; controller?.abort(); printFrame?.remove(); parentObserver.disconnect();
        if (imageUrl) root.URL.revokeObjectURL(imageUrl);
        parentDialog.removeEventListener('close', close); dialog.remove();
        if (active?.dialog === dialog) active = null;
        if (isParentOpen() && opener?.isConnected) opener.focus({preventScroll:true});
      };
      const close = () => { if (dialog.open) dialog.close(); cleanup(); };
      const parentObserver = new root.MutationObserver(() => { if (!isParentOpen()) close(); });
      parentObserver.observe(parentDialog, {attributes:true, attributeFilter:['open']});
      if (parentDialog.tagName !== 'DIALOG') parentObserver.observe(doc.body, {childList:true, subtree:true});
      const fail = message => {
        image.hidden = true; status.hidden = false; status.setAttribute('role', 'alert'); status.textContent = message;
        print.disabled = download.disabled = true; retry.hidden = false;
      };
      const load = async () => {
        controller?.abort(); controller = new root.AbortController();
        const request = controller;
        print.disabled = download.disabled = true; image.hidden = true; retry.hidden = true;
        status.hidden = false; status.setAttribute('role', 'status'); status.textContent = 'Loading ' + kind + '…';
        if (imageUrl) { root.URL.revokeObjectURL(imageUrl); imageUrl = null; }
        try {
          const response = await root.fetch(spec.url, {credentials:'same-origin', cache:'no-store', signal:request.signal});
          if (!response.ok) throw new Error(response.status === 403 ? 'You do not have access to this document.' : response.status === 404 ? 'This document is no longer available.' : 'Unable to load this document. Please try again.');
          const blob = await response.blob();
          if (closed || request !== controller) return;
          if (!blob.type.startsWith('image/')) throw new Error('Unable to display this document. Please try again.');
          imageType = blob.type; imageUrl = root.URL.createObjectURL(blob);
          image.onload = () => { if (!closed) { image.hidden = false; status.hidden = true; print.disabled = download.disabled = false; } };
          image.onerror = () => { if (!closed) fail('Unable to display this document. Please try again.'); };
          image.src = imageUrl;
        } catch (error) { if (!closed && request === controller && error.name !== 'AbortError') fail(error.message); }
      };
      dialog.addEventListener('click', event => {
        const action = event.target.closest('[data-history-preview]')?.dataset.historyPreview;
        if (action === 'close') close();
        else if (action === 'retry') load();
        else if (action === 'download' && imageUrl && !download.disabled) {
          const anchor = doc.createElement('a');
          anchor.href = kind === 'certificate' ? spec.url + '?download=1' : imageUrl;
          anchor.download = kind === 'certificate' ? '' : 'recollection-receipt-' + spec.id + '.' + ({'image/jpeg':'jpg','image/png':'png','image/webp':'webp','image/gif':'gif'}[imageType] || 'img');
          doc.body.appendChild(anchor); anchor.click(); anchor.remove();
        } else if (action === 'print' && imageUrl && !print.disabled) {
          printFrame?.remove(); printFrame = doc.createElement('iframe');
          printFrame.title = 'Print ' + spec.title; printFrame.setAttribute('aria-hidden', 'true'); printFrame.tabIndex = -1;
          printFrame.style.cssText = 'position:fixed;width:1px;height:1px;bottom:0;left:0;border:0;';
          printFrame.onload = () => {
            const frame = printFrame, printable = frame?.contentDocument?.querySelector('img');
            const send = () => { if (closed || !frame?.isConnected) return; try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (error) { toast('Unable to print this document. Download it and print the saved file.'); } };
            if (printable?.complete && printable.naturalWidth) send(); else if (printable) printable.onload = send;
          };
          printFrame.srcdoc = printHtml(spec, imageUrl); doc.body.appendChild(printFrame);
        } else if (event.target === dialog) {
          const bounds = dialog.getBoundingClientRect();
          if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
        }
      });
      dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
      dialog.addEventListener('close', cleanup, {once:true});
      parentDialog.addEventListener('close', close);
      active = {dialog, close}; dialog.showModal();
      dialog.querySelector('[data-history-preview="close"]').focus({preventScroll:true});
      await load();
    }
    return {'history-certificate-preview':id => open('certificate', id), 'history-receipt-preview':id => open('receipt', id), closePreview:() => active?.close()};
  }

  root.CfoHistoryDocuments = {documentSpec, controlsHtml, dialogHtml, printHtml, actions};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoHistoryDocuments;
})(typeof window === 'undefined' ? globalThis : window);
