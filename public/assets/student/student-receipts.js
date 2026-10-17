(function (root) {
  'use strict';

  const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[character]));

  function receiptUrl(record) {
    const id = String(record?.id ?? '');
    return record?.has_receipt && /^[1-9]\d*$/.test(id) ? '/api/receipts/' + id : '';
  }

  function preview(record) {
    const url = receiptUrl(record);
    if (!url) return '';
    return '<a class="recollection-receipt-thumb" href="' + url + '" data-student-receipt="' + escape(record.id) + '" aria-label="View uploaded receipt">'
      + '<img src="' + url + '" alt="Uploaded payment receipt" loading="lazy"></a>'
      + '<p class="recollection-file-name"><a href="' + url + '" data-student-receipt="' + escape(record.id) + '">View uploaded receipt</a></p>';
  }

  function markup(record) {
    const image = preview(record);
    if (!image) return '';
    return '<section class="recollection-preview-column" aria-label="Submitted payment receipt"><h2>Payment receipt</h2>'
      + image + '<p class="recollection-receipt-status has-receipt">Receipt status: ' + escape(record.receipt_status || 'Pending') + '</p></section>';
  }

  const mounted = new WeakMap();
  function mount(doc = root.document) {
    if (mounted.has(doc)) return mounted.get(doc);
    let activePreview;
    const closePreview = () => { activePreview?.closePreview(); activePreview = null; };
    const notice = message => root.showNotice?.('Receipt preview', message);
    const click = event => {
      const control = event.target.closest('[data-student-receipt]');
      if (!control || !receiptUrl({id:control.dataset.studentReceipt,has_receipt:true})) return;
      event.preventDefault(); closePreview();
      const parent = control.closest('.recollection-preview-column, #receipt-preview') || control.parentElement;
      activePreview = root.CfoHistoryDocuments.actions({parentDialog:parent,student:true,isParentOpen:()=>parent.isConnected,toast:notice});
      Promise.resolve(activePreview['history-receipt-preview'](control.dataset.studentReceipt)).catch(error=>notice(error.message));
    };
    doc.addEventListener('click', click);
    root.addEventListener?.('hashchange', closePreview);
    const cleanup = () => { closePreview(); doc.removeEventListener('click', click); root.removeEventListener?.('hashchange', closePreview); mounted.delete(doc); };
    mounted.set(doc, cleanup);
    return cleanup;
  }

  const api = {receiptUrl, preview, markup, mount};
  root.CfoStudentReceipt = api;
  if (root.document?.addEventListener) mount(root.document);
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
