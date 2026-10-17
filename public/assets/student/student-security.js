// Visual privacy for the account owner; email values never leave the existing session/API.
const protectedStudentEmails = new WeakMap();
const emailEscape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function maskStudentEmail(value) {
    const email = String(value || '').trim();
    const at = email.lastIndexOf('@');
    if (at < 1) return email ? '***.***' : '';
    const local = email.slice(0, at);
    const parts = local.split('.');
    const masked = parts.length > 1
        ? parts.map((part, index) => index === 0 ? part.slice(0, Math.min(2, Math.max(0, part.length - 1))) + '**' : '***').join('.')
        : local.slice(0, Math.min(2, Math.max(0, local.length - 1))) + '**.***';
    return masked + email.slice(at);
}
function studentEmailEye(shown) {
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0' + (shown ? 'M3 3l18 18' : '') + '"/></svg>';
}
function studentEmailDisplay(value) {
    if (!value || !String(value).includes('@')) return emailEscape(value);
    return '<span class="student-email-display" data-student-email="' + emailEscape(value) + '"><span data-email-value>' + emailEscape(maskStudentEmail(value)) + '</span><button type="button" class="student-email-toggle" data-email-toggle aria-label="Show email" aria-pressed="false">' + studentEmailEye(false) + '</button></span>';
}
function studentEmailValue(input) {
    const state = protectedStudentEmails.get(input);
    return state && !state.shown ? state.value : input.value;
}
function protectStudentEmailInput(input) {
    if (!input || protectedStudentEmails.has(input)) return;
    const state = {value:input.value, shown:false, readonly:input.readOnly, type:input.type};
    protectedStudentEmails.set(input, state);
    const wrapper = document.createElement('div');
    wrapper.className = 'student-email-input';
    const toggle = document.createElement('button');
    toggle.type = 'button'; toggle.className = 'student-email-toggle';
    toggle.setAttribute('aria-controls', input.id);
    const render = () => {
        input.type = state.shown ? state.type : 'text';
        input.readOnly = state.shown ? state.readonly : true;
        input.value = state.shown ? state.value : maskStudentEmail(state.value);
        toggle.setAttribute('aria-label', state.shown ? 'Hide email' : state.readonly ? 'Show email' : 'Show email to edit');
        toggle.setAttribute('aria-pressed', String(state.shown));
        toggle.innerHTML = studentEmailEye(state.shown);
    };
    toggle.onclick = () => {
        if (state.shown) state.value = input.value;
        state.shown = !state.shown;
        render();
        if (state.shown && !state.readonly) input.focus();
    };
    input.insertAdjacentElement('beforebegin', wrapper);
    wrapper.append(input, toggle);
    render();
}
document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-email-toggle]');
    if (!toggle) return;
    const wrapper = toggle.closest('[data-student-email]');
    const shown = toggle.getAttribute('aria-pressed') !== 'true';
    wrapper.querySelector('[data-email-value]').textContent = shown ? wrapper.dataset.studentEmail : maskStudentEmail(wrapper.dataset.studentEmail);
    toggle.setAttribute('aria-pressed', String(shown));
    toggle.setAttribute('aria-label', shown ? 'Hide email' : 'Show email');
    toggle.innerHTML = studentEmailEye(shown);
});

function studentScheduleRequestCard(request) {
    const status = request.student_status || (['Rejected','Closed'].includes(request.status) ? 'Not Approved' : request.status);
    const tone = status === 'Approved' ? 'approved' : status === 'Not Approved' ? 'rejected' : 'pending';
    // The CFO assigns the new schedule; older requests may still show the schedule the student picked.
    const scheduleLine = request.assigned_schedule ? 'New schedule: ' + request.assigned_schedule.title + ' · ' + request.assigned_schedule.date
        : request.requested_schedule ? 'Requested schedule: ' + request.requested_schedule.title + ' · ' + request.requested_schedule.date
        : status === 'Pending' ? 'Waiting for the CFO to assign your new schedule' : '';
    return '<article class="student-change-card"><div class="student-change-card-head"><strong>' + emailEscape(request.assigned_schedule?.title || 'Schedule change request') + '</strong><span class="student-change-status ' + tone + '">' + emailEscape(status) + '</span></div><p>' + emailEscape(scheduleLine) + '</p><p><strong>Your reason:</strong> ' + emailEscape(request.reason) + '</p><p class="student-change-response">' + emailEscape(request.student_message || '') + '</p></article>';
}
