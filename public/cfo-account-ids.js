/* Shared Student ID / Employee ID input format. IDs remain strings, including leading zeroes. */
(function (root) {
  'use strict';
  const pattern = '[0-9]{2}-[0-9]{5}';
  const message = 'Use the ID format 24-10723 (two digits, a hyphen, and five digits).';
  const valid = value => typeof value === 'string' && value.length === 8 && /^[0-9]{2}-[0-9]{5}$/.test(value);
  function format(value) {
    const digits = String(value ?? '').replace(/[^0-9]/g,'').slice(0,7);
    return digits.length > 2 ? digits.slice(0,2) + '-' + digits.slice(2) : digits;
  }
  const bindings = new WeakMap();
  function bind(input, {allowOriginal = false, originalValue = ''} = {}) {
    if (!input) return () => {};
    bindings.get(input)?.();
    const original = String(originalValue ?? '');
    const legacy = () => allowOriginal && !valid(original) && input.value === original;
    const sync = change => {
      if (change && !legacy()) {
        const raw = input.value, caret = input.selectionStart;
        const beforeCaret = caret === null ? null : raw.slice(0,caret).replace(/[^0-9]/g,'').length;
        const formatted = format(raw);
        if (formatted !== raw) {
          input.value = formatted;
          if (beforeCaret !== null && input.setSelectionRange) {
            const position = Math.min(formatted.length,beforeCaret + (beforeCaret > 2 ? 1 : 0));
            input.setSelectionRange(position,position);
          }
        }
      }
      input.setAttribute('inputmode','numeric'); input.placeholder = '24-10723'; input.title = message;
      if (legacy()) { input.removeAttribute('pattern'); input.removeAttribute('maxlength'); }
      else { input.setAttribute('pattern',pattern); input.setAttribute('maxlength','8'); }
      input.setCustomValidity(input.value && !valid(input.value) && !legacy() ? message : '');
    };
    const onInput = () => sync(true);
    input.addEventListener('input',onInput); sync(false);
    const cleanup = () => { input.removeEventListener('input',onInput); bindings.delete(input); };
    bindings.set(input,cleanup);
    return cleanup;
  }
  root.CfoAccountIds = {pattern,message,valid,format,bind};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoAccountIds;
})(typeof window === 'undefined' ? globalThis : window);
