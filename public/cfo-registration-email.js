/* Gmail is required for public Student and Admin account creation. */
(function (root) {
  'use strict';
  const message = 'Enter a valid Gmail address ending in @gmail.com.';
  const pattern = '[^@\\s]+@[gG][mM][aA][iI][lL]\\.[cC][oO][mM]';
  const valid = value => typeof value === 'string' && value.trim().length <= 190 && /^[^@\s]+@gmail\.com$/i.test(value.trim());
  const bindings = new WeakMap();
  function bind(input) {
    if (!input) return () => {};
    bindings.get(input)?.();
    input.placeholder = 'username@gmail.com'; input.title = message;
    input.setAttribute('pattern',pattern); input.setAttribute('maxlength','190');
    const sync = () => input.setCustomValidity(input.value && !valid(input.value) ? message : '');
    input.addEventListener('input',sync); sync();
    const cleanup = () => { input.removeEventListener('input',sync); bindings.delete(input); };
    bindings.set(input,cleanup);
    return cleanup;
  }
  root.CfoRegistrationEmail = {message,pattern,valid,bind};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoRegistrationEmail;
})(typeof window === 'undefined' ? globalThis : window);
