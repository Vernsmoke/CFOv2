// Exercise the actual recovery controller with a fake clock, DOM and HTTP transport.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
let now = Date.now(), nextTimer = 0, calls = 0, response = {status: 'Pending'};
const timers = new Map(), listeners = {}, elements = new Map();
function element(key) {
    if (!elements.has(key)) elements.set(key, {value: '', textContent: '', hidden: false, disabled: false, isConnected: true, dataset: {},
        events: {}, attributes: {}, classList: {toggle(name, enabled) { this[name] = enabled; }},
        addEventListener(name, handler) { this.events[name] = handler; }, focus() { this.focused = true; },
        select() { this.selected = true; }, setSelectionRange() {},
        setAttribute(name, value) { this.attributes[name] = value; }, removeAttribute(name) { delete this.attributes[name]; },
        querySelector(selector) { return element(key + ' ' + selector); }});
    return elements.get(key);
}
class Clock extends Date { static now() { return now; } }
const context = vm.createContext({
    Date: Clock, Math, location: {hash: '#forgot-password'},
    resetState: {step: 2, email: 'student@example.test', temporaryPassword: '', loginEmail: '', verified: false, newPassword: '', confirmNewPassword: ''},
    document: {hidden: false, body:{classList:{add(){}}}, getElementById: id => element('#' + id), querySelector: element,
        addEventListener: (name, fn) => { listeners[name] = fn; }},
    window: {addEventListener: (name, fn) => { listeners[name] = fn; }, scrollTo() {}},
    navigator: {clipboard: {writeText: async value => { context.copied = value; }}},
    setInterval: (fn, delay) => { timers.set(++nextTimer, {fn, delay}); return nextTimer; },
    clearInterval: id => timers.delete(id),
    setTimeout: fn => fn(),
    cfoRequest: async (url, method, data) => { calls++; context.lastRequest = {url, method, data}; return typeof response === 'function' ? response() : response; },
    setResetStep: step => { context.stopRecoveryPolling(); context.resetState.step = step; },
    app:{innerHTML:''}, icon:()=>'', escapeHTML:value=>String(value ?? ''), banner:()=>'', bindReveal(){},
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/recovery-polling.js'), 'utf8'), context);
const view=fs.readFileSync(path.join(__dirname,'../../resources/views/login-create-forgotpassword.blade.php'),'latin1');
function loadBetween(start,end){vm.runInContext(view.slice(view.indexOf(start),view.indexOf(end,view.indexOf(start))),context);}
loadBetween('function field(', 'function roleSelect(');
loadBetween('function resetActions(', 'function setResetStep(');
vm.runInContext(view.split(/\r?\n/).find(line => line.startsWith('const rules=')), context);
loadBetween('function resetError(', '// Authentication-only router:');
const flush = () => new Promise(resolve => setImmediate(resolve));
async function poll() { now += 5000; await context.checkRecoveryStatus(); }

(async () => {
    context.forgot(); await flush();
    assert.match(context.app.innerHTML,/id="reset-generated" type="text" readonly/);
    assert.match(context.app.innerHTML,/id="reset-temporary" name="reset-temporary" type="password"/);
    assert.match(context.app.innerHTML,/data-copy="reset-generated"/);
    assert.match(context.app.innerHTML,/Your Temporary Password/);
    assert.match(context.app.innerHTML,/Enter Temporary Password/);
    assert.match(context.app.innerHTML,/id="reset-username" type="text" readonly/);
    assert.match(context.app.innerHTML,/reset-step-2/);
    assert.match(context.app.innerHTML,/aria-current="step"/);
    assert.equal(calls, 1);
    assert.equal(element('#recovery-password-panel').hidden, true);
    assert.equal(element('#recovery-form [type=submit]').disabled, true);
    const beforeApproval = calls;
    element('#recovery-form').onsubmit({preventDefault() {}}); await flush();
    assert.equal(calls, beforeApproval, 'approval is required before verification');
    assert.equal([...timers.values()].filter(timer => timer.delay === 5000).length, 1);
    await context.checkRecoveryStatus(); assert.equal(calls, 1, 'manual checks respect the poll interval');

    response = {status: 'Approved', login_email: 'student@cfo.office.com', temporary_password: 'OneTime12345', expires_at: null, expires_in: null};
    await poll();
    assert.equal(element('#reset-generated').value, 'OneTime12345');
    assert.equal(element('#reset-username').value, 'student@cfo.office.com');
    assert.equal(element('#recovery-stage-title').textContent, 'Temporary Password Generated');
    assert.equal(element('#recovery-password-panel').hidden, false);
    assert.equal(element('#recovery-form [type=submit]').disabled, false);
    assert.equal(element('#recovery-countdown').textContent, '', 'approved passwords have no countdown');
    await context.copyRecoveryPassword(); assert.equal(context.copied, 'OneTime12345');
    await context.copyRecoveryUsername(); assert.equal(context.copied, 'student@cfo.office.com');
    context.navigator.clipboard = undefined;
    await context.copyRecoveryPassword(); assert.equal(element('#reset-generated').selected, true);
    assert.match(element('#recovery-copy-status').textContent, /Copy command/);
    await context.copyRecoveryUsername(); assert.equal(element('#reset-username').selected, true);

    // Later polls contain no secret: preserve both the revealed password and user's input.
    response = {status: 'Approved', expires_at: null, expires_in: null};
    element('#reset-temporary').value = 'OneTime12345';
    await poll();
    assert.equal(element('#reset-generated').value, 'OneTime12345');
    assert.equal(element('#reset-temporary').value, 'OneTime12345');

    // An invalid password can be corrected without losing either box.
    response = () => { throw new Error('The temporary password is invalid.'); };
    await context.verifyTemporaryPassword();
    assert.match(element('#reset-error').textContent, /invalid/);
    assert.equal(element('#recovery-form [type=submit]').disabled, false);
    assert.equal(element('#reset-temporary').value, 'OneTime12345');
    assert.equal([...timers.values()].some(timer => timer.delay === 1000), false);
    context.document.hidden = true;
    const beforeHidden = calls; await poll(); assert.equal(calls, beforeHidden);
    context.document.hidden = false;

    // A temporary network failure preserves typed input and keeps retries running.
    response = () => { throw new Error('Offline'); };
    await poll(); assert.match(element('#recovery-status').textContent, /Automatic checking will retry/);
    assert.equal(element('#reset-temporary').value, 'OneTime12345');
    let rejectVerification;
    response = () => new Promise((resolve, reject) => { rejectVerification = reject; });
    const verification = context.verifyTemporaryPassword();
    const beforeVerification = calls;
    await context.verifyTemporaryPassword();
    assert.equal(calls, beforeVerification, 'verification cannot be submitted twice');
    now += 7 * 24 * 60 * 60 * 1000;
    assert.equal(context.resetState.temporaryPassword, 'OneTime12345');
    assert.equal(element('#reset-temporary').value, 'OneTime12345');
    assert.equal(element('#recovery-password-panel').hidden, false);
    assert.equal(element('#recovery-form [type=submit]').disabled, true, 'only the pending verification disables the form');
    assert.equal([...timers.values()].some(timer => timer.delay === 1000), false);
    rejectVerification(new Error('Offline')); await verification;
    assert.equal(element('#recovery-form [type=submit]').disabled, false, 'verification can be retried after a delay');

    response = {status: 'Rejected'}; await poll();
    assert.match(element('#recovery-status').textContent, /office rejected/);
    assert.equal(element('#reset-generated').value, '');

    // A successful login elsewhere closes recovery and erases both password boxes.
    response = {status: 'Approved', temporary_password: 'CancelMe123', expires_in: null};
    await poll();
    element('#reset-temporary').value = 'CancelMe123';
    response = {status: 'Cancelled'};
    await poll();
    assert.match(element('#recovery-status').textContent, /cancelled after a successful login/);
    assert.equal(element('#reset-generated').value, '');
    assert.equal(element('#reset-temporary').value, '');
    assert.equal(element('#recovery-password-panel').hidden, true);
    assert.equal(element('#recovery-form [type=submit]').disabled, true);
    assert.equal(timers.size, 0);

    // Do not apply a response after leaving the recovery page, or overlap requests.
    let release;
    response = () => new Promise(resolve => { release = resolve; });
    now += 5000;
    const inFlight = context.checkRecoveryStatus();
    const beforeBusy = calls; await poll(); assert.equal(calls, beforeBusy);
    context.stopRecoveryPolling(); context.clearRecoveryPassword();
    context.location.hash = '#login';
    release({status: 'Approved', temporary_password: 'Stale123', expires_at: new Date(now + 60000).toISOString()});
    await inFlight;
    assert.equal(context.resetState.temporaryPassword, '');
    assert.equal(timers.size, 0);

    context.location.hash = '#forgot-password';
    response = {status: 'Verified'}; await poll();
    assert.equal(context.resetState.step, 3);
    assert.equal(timers.size, 0);

    context.resetState.step = 2;
    response = {status:'Approved', temporary_password:'Final12345', expires_in:null};
    await poll();
    assert.equal(element('#recovery-countdown').textContent, '');
    element('#reset-temporary').value = 'Final12345';
    response = {};
    // Use the submit handler bound by the actual student view, not a test-only shortcut.
    element('#recovery-form').onsubmit({preventDefault(){}}); await flush();
    assert.equal(context.resetState.step, 3);
    assert.equal(context.resetState.temporaryPassword, '');
    assert.equal(element('#reset-temporary').value, '');
    assert.equal(timers.size, 0);

    // Exercise the request form and its actual submit helper after confirmation.
    loadBetween('async function cfoSubmit(', '// Consistent button contrast');
    const submit = context.cfoSubmit;
    context.cfoSubmit = (form, action) => submit(form, action, true);
    context.showNotice = (title, message) => { context.notice = message; };
    const form = element('#recovery-form');
    form.id = 'recovery-form';
    form.querySelector = selector => element('#recovery-form ' + selector);
    const submitButton = element('#recovery-form [type=submit]');
    context.resetState.step = 1;
    context.resetState.email = 'student@example.test';
    element('#reset-email').value = 'student@example.test';
    context.forgot();
    assert.match(context.app.innerHTML,/reset-step-1/);
    assert.match(context.app.innerHTML,/Forgot Password\?/);
    assert.match(context.app.innerHTML,/Send to Reset Your Password/);
    assert.match(context.app.innerHTML,/class="reset-login"/);
    assert.match(element('#recovery-request-limit').textContent, /5 password reset requests per hour/);
    const limited = {max_requests: 5, remaining_requests: 0, retry_after: 3600, reset_after: 3600};
    response = {message: 'Recorded', recovery_limit: limited};
    form.onsubmit({preventDefault() {}, target: form}); await flush();
    assert.equal(context.resetState.step, 2, 'the fifth accepted request still proceeds to approval');
    assert.match(element('#recovery-request-limit').textContent, /01:00:00/);

    // Returning to the email screen cannot send a sixth request.
    context.resetState.step = 1;
    context.forgot();
    assert.equal(submitButton.disabled, true);
    const beforeLimit = calls;
    form.onsubmit({preventDefault() {}, target: form}); await flush();
    assert.equal(calls, beforeLimit);
    now += 3599000;
    context.syncRecoveryRequestLimit();
    assert.match(element('#recovery-request-limit').textContent, /00:01/);
    assert.equal(submitButton.disabled, true);
    now += 1000;
    context.syncRecoveryRequestLimit();
    assert.equal(submitButton.disabled, false, 'submission resumes exactly after the cooldown');

    // A server 429 restores the countdown even if local state was absent/stale.
    response = () => { const error = new Error('Limit reached'); error.recoveryLimit = limited; throw error; };
    form.onsubmit({preventDefault() {}, target: form}); await flush();
    assert.equal(submitButton.disabled, true, 'submit cleanup must preserve the cooldown lock');
    assert.match(element('#recovery-request-limit').textContent, /01:00:00/);
    assert.equal(context.notice, 'Limit reached');
    element('#reset-email').value = 'another@example.test';
    context.syncRecoveryRequestLimit();
    assert.equal(submitButton.disabled, false, 'another email has an independent limit');
    element('#reset-email').value = ' STUDENT@EXAMPLE.TEST ';
    context.syncRecoveryRequestLimit();
    assert.equal(submitButton.disabled, true, 'case and whitespace cannot bypass the local cooldown');
    form.dataset.submitting = 'true';
    element('#reset-email').value = 'another@example.test';
    context.syncRecoveryRequestLimit();
    assert.equal(submitButton.disabled, true, 'countdown updates cannot re-enable an in-flight form');
    delete form.dataset.submitting;
    listeners.pagehide();
    assert.equal(timers.size, 0);

    // Use the real step transition and form validation for the new-password screen.
    loadBetween('function clearReset(', 'function resetActions(');
    loadBetween('function setResetStep(', 'function resetError(');
    context.location.hash = '#forgot-password';
    context.resetState.verified = true;
    context.setResetStep(3);
    assert.match(context.app.innerHTML,/reset-step-3/);
    assert.match(context.app.innerHTML,/Password Requirements/);
    assert.match(context.app.innerHTML,/reset-rule-4/);
    element('#reset-new').value = 'weak';
    element('#reset-new').events.input({target: element('#reset-new')});
    const beforeWeak = calls;
    form.onsubmit({preventDefault() {}, target: form}); await flush();
    assert.equal(calls, beforeWeak, 'weak passwords never reach the reset endpoint');
    assert.match(element('#reset-error').textContent, /requirements/);
    assert.equal(element('#reset-new').attributes['aria-invalid'], 'true');
    element('#reset-new').value = 'NewPassword!234';
    element('#reset-new').events.input({target: element('#reset-new')});
    assert.equal(element('#reset-new').attributes['aria-invalid'], undefined);
    for (let i = 0; i < 5; i++) assert.equal(element('#reset-rule-' + i).classList.valid, true);
    element('#reset-confirm').value = 'DifferentPassword!234';
    form.onsubmit({preventDefault() {}, target: form}); await flush();
    assert.equal(calls, beforeWeak, 'mismatched passwords never reach the reset endpoint');
    assert.match(element('#reset-error').textContent, /do not match/);

    // Back and Continue preserve verification without reusing the consumed password.
    response = {status: 'Verified'};
    now += 5000;
    element('#reset-back').onclick(); await flush();
    assert.equal(context.resetState.step, 2);
    assert.equal(element('#recovery-temporary-field').hidden, true);
    assert.equal(element('#recovery-form [type=submit]').disabled, false);
    const beforeContinue = calls;
    form.onsubmit({preventDefault() {}, target: form}); await flush();
    assert.equal(context.resetState.step, 3);
    assert.equal(calls, beforeContinue, 'verified Continue makes no second verification request');
    assert.match(context.app.innerHTML,/value="NewPassword!234"/);
    element('#reset-confirm').value = 'NewPassword!234';
    element('#reset-confirm').events.input({target: element('#reset-confirm')});
    response = {message: 'Password changed.'};
    form.onsubmit({preventDefault() {}, target: form}); await flush();
    assert.equal(context.lastRequest.url, '/api/reset-password');
    assert.equal(context.lastRequest.data.password, 'NewPassword!234');
    assert.equal(context.lastRequest.data.password_confirmation, 'NewPassword!234');
    assert.equal(context.location.hash, 'login');
    assert.equal(context.resetState.newPassword, '');
    assert.equal(context.resetState.temporaryPassword, '');
    assert.equal(timers.size, 0);
    console.log('PASS: three-step recovery, approval, credential copy, password rules, Back/Continue, new-password submission, rejection, retries and session cleanup.');
})().catch(error => { console.error(error); process.exitCode = 1; });
