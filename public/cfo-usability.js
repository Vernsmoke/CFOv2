(function () {
  'use strict';
  function formError(form, error) {
    if (!form?.isConnected) return;
    let summary = form.querySelector('.cfo-form-error');
    if (!summary) { summary = document.createElement('div'); summary.className = 'cfo-form-error'; summary.setAttribute('role', 'alert'); summary.tabIndex = -1; form.prepend(summary); }
    summary.textContent = error.message || 'Unable to save. Please check the form and try again.';
    Object.keys(error.fields || {}).forEach(key => {
      const name = key.replace(/\.([^.]*)/g, '[$1]');
      const field = [...form.elements].find(input => input.name === key || input.name === name);
      field?.setAttribute('aria-invalid', 'true');
    });
    summary.focus({preventScroll: true}); summary.scrollIntoView({block: 'nearest'});
  }
  document.addEventListener('submit', event => {
    event.target.querySelector('.cfo-form-error')?.remove();
    event.target.querySelectorAll('[aria-invalid=true]').forEach(field => field.removeAttribute('aria-invalid'));
  }, true);
  document.addEventListener('input', event => event.target.removeAttribute?.('aria-invalid'));
  window.CfoUsability = {formError};
})();
