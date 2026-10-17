// The recovery secret stays in the server session; passwords stay in page memory.
let recoveryPoll = null, recoveryGeneration = 0;
let recoveryBusy = false, recoveryLastCheck = 0;
let recoveryVerifying = false, recoveryCurrentStatus = 'Pending';
let recoveryRequestCountdown = null;
const recoveryRequestLimits = new Map();

function recoveryTime(seconds) {
    const minutes = Math.floor(seconds / 60), rest = seconds % 60;
    return (minutes >= 60 ? String(Math.floor(minutes / 60)).padStart(2, '0') + ':' : '')
        + String(minutes % 60).padStart(2, '0') + ':' + String(rest).padStart(2, '0');
}

function stopRecoveryRequestCountdown() {
    clearInterval(recoveryRequestCountdown);
    recoveryRequestCountdown = null;
}

function updateRecoveryRequestLimit(limit, email = resetState.email) {
    if (!limit) return;
    recoveryRequestLimits.set(String(email || '').trim().toLowerCase(), {
        ...limit,
        retryAt: Date.now() + limit.retry_after * 1000,
        resetAt: Date.now() + limit.reset_after * 1000,
    });
    startRecoveryRequestCountdown();
}

function syncRecoveryRequestLimit() {
    if (location.hash !== '#forgot-password' || resetState.step > 2) return;
    const email = resetState.step === 1 ? document.querySelector('#reset-email')?.value : resetState.email;
    const limit = recoveryRequestLimits.get(String(email || '').trim().toLowerCase());
    const seconds = limit ? Math.max(0, Math.ceil((limit.retryAt - Date.now()) / 1000)) : 0;
    const label = document.querySelector('#recovery-request-limit');
    if (label) label.textContent = seconds
        ? '5 of 5 requests used. You can send another request in ' + recoveryTime(seconds) + '.'
        : limit && limit.resetAt > Date.now()
            ? limit.remaining_requests + ' of 5 requests remaining. After the fifth request, wait 1 hour before requesting again.'
            : 'You can send up to 5 password reset requests per hour. After the fifth request, wait 1 hour before requesting again.';
    if (resetState.step === 1) {
        const button = document.querySelector('#recovery-form [type=submit]');
        const form = document.querySelector('#recovery-form');
        if (button && !form?.dataset.submitting) button.disabled = seconds > 0;
    }
}

function startRecoveryRequestCountdown() {
    stopRecoveryRequestCountdown();
    syncRecoveryRequestLimit();
    if (recoveryRequestLimits.size && resetState.step <= 2 && location.hash === '#forgot-password') {
        recoveryRequestCountdown = setInterval(syncRecoveryRequestLimit, 1000);
    }
}

function stopRecoveryPolling() {
    clearInterval(recoveryPoll);
    recoveryPoll = null;
    recoveryGeneration++;
    recoveryBusy = false;
    recoveryVerifying = false;
}

function syncRecoveryControls() {
    const available = recoveryCurrentStatus === 'Approved';
    const verified = recoveryCurrentStatus === 'Verified';
    document.querySelector('#recovery-form [type=submit]').disabled = (!available && !verified) || recoveryVerifying;
    document.querySelector('#reset-temporary').disabled = !available || recoveryVerifying;
    document.querySelector('[data-copy="reset-generated"]').disabled = !available || !resetState.temporaryPassword;
    document.querySelector('[data-copy="reset-username"]').disabled = !available || !resetState.loginEmail;
    document.querySelector('#recovery-temporary-field').hidden = verified;
}

function updateRecoveryBanner(status) {
    const approved = status === 'Approved', verified = status === 'Verified';
    document.querySelector('#recovery-stage-title').textContent = verified
        ? 'Temporary Password Verified' : approved ? 'Temporary Password Generated' : 'Awaiting Admin Approval';
    document.querySelector('#recovery-stage-icon').innerHTML = icon(approved || verified ? 'check' : 'info');
}

function clearRecoveryPassword() {
    resetState.temporaryPassword = '';
    for (const id of ['reset-generated', 'reset-temporary']) {
        const input = document.getElementById(id);
        if (input) input.value = '';
    }
    const panel = document.getElementById('recovery-password-panel');
    if (panel) panel.hidden = true;
}

function finishRecoveryStatus(status) {
    stopRecoveryPolling();
    recoveryCurrentStatus = status;
    resetState.verified = false;
    clearRecoveryPassword();
    document.querySelector('#recovery-stage-title').textContent = status === 'Rejected' ? 'Request Rejected' : 'Recovery Request Closed';
    document.querySelector('#recovery-status').textContent = status === 'Rejected'
        ? 'The office rejected this request. Visit the CFO Office, Miraculous Building, near the chapel, for assistance.'
        : status === 'Completed' ? 'Password recovery is complete. Sign in with your new password.'
        : status === 'Cancelled' ? 'This request was automatically cancelled after a successful login. Your current password still works.'
        : 'This request or temporary password has expired. Submit a new request and have it approved in person.';
    document.querySelector('#recovery-countdown').textContent = '';
    document.querySelector('#check-recovery').hidden = true;
    syncRecoveryControls();
    document.querySelector('#reset-back').textContent = 'Start a new request';
}

async function checkRecoveryStatus() {
    if (resetState.step !== 2 || location.hash !== '#forgot-password' || document.hidden || recoveryBusy || recoveryVerifying || Date.now() - recoveryLastCheck < 5000) return;
    const generation = recoveryGeneration;
    recoveryBusy = true;
    recoveryLastCheck = Date.now();
    const button = document.querySelector('#check-recovery');
    button.disabled = true;
    try {
        const result = await cfoRequest('/api/recovery');
        if (generation !== recoveryGeneration || recoveryVerifying) return;
        updateRecoveryRequestLimit(result.recovery_limit);
        recoveryCurrentStatus = result.status;
        if (['Expired', 'Rejected', 'Completed', 'Cancelled'].includes(result.status)) {
            finishRecoveryStatus(result.status);
            return;
        }
        if (result.status === 'Verified') {
            const returning = resetState.verified;
            resetState.verified = true;
            clearRecoveryPassword();
            if (!returning) { setResetStep(3); return; }
            updateRecoveryBanner('Verified');
            syncRecoveryControls();
            document.querySelector('#recovery-status').textContent = 'Your temporary password has already been verified. Select Continue to create your new password.';
            return;
        }
        if (result.status !== 'Approved') {
            updateRecoveryBanner(result.status);
            clearRecoveryPassword();
            syncRecoveryControls();
            document.querySelector('#recovery-status').textContent = 'Awaiting in-person verification and admin approval. This page checks automatically every 5 seconds.';
            return;
        }
        resetState.verified = false;
        resetState.loginEmail = result.login_email || resetState.loginEmail || resetState.email;
        document.querySelector('#reset-username').value = resetState.loginEmail;
        if (result.temporary_password) resetState.temporaryPassword = result.temporary_password;
        document.querySelector('#reset-generated').value = resetState.temporaryPassword;
        document.querySelector('#recovery-password-panel').hidden = false;
        updateRecoveryBanner('Approved');
        syncRecoveryControls();
        document.querySelector('#recovery-status').textContent = resetState.temporaryPassword
            ? 'Approved. Copy the temporary password below, paste it into Enter Temporary Password, then select Continue to create your permanent password.'
            : 'Approved. The temporary password has already been displayed once in this browser session. Paste your copied password below. If it was lost, contact the CFO Office for assistance.';
        document.querySelector('#recovery-countdown').textContent = '';
    } catch (error) {
        if (generation === recoveryGeneration) document.querySelector('#recovery-status').textContent = 'Unable to check approval. ' + error.message + ' Automatic checking will retry.';
    } finally {
        if (generation === recoveryGeneration) {
            recoveryBusy = false;
            button.disabled = false;
        }
    }
}

function startRecoveryPolling() {
    stopRecoveryPolling();
    recoveryCurrentStatus = 'Pending';
    syncRecoveryControls();
    checkRecoveryStatus();
    recoveryPoll = setInterval(checkRecoveryStatus, 5000);
}

async function copyRecoveryPassword() {
    if (recoveryCurrentStatus !== 'Approved' || !resetState.temporaryPassword) return;
    const input = document.querySelector('#reset-generated');
    const status = document.querySelector('#recovery-copy-status');
    try {
        await navigator.clipboard.writeText(input.value);
        status.textContent = 'Copied. Paste it into Enter Temporary Password below.';
    } catch {
        input.focus();
        input.select();
        input.setSelectionRange(0, input.value.length);
        status.textContent = 'Password selected. Use your device\'s Copy command, then paste it into Enter Temporary Password below.';
    }
}

async function copyRecoveryUsername() {
    if (recoveryCurrentStatus !== 'Approved' || !resetState.loginEmail) return;
    const input = document.querySelector('#reset-username');
    const status = document.querySelector('#recovery-copy-status');
    try {
        await navigator.clipboard.writeText(input.value);
        status.textContent = 'School email / username copied.';
    } catch {
        input.focus(); input.select(); input.setSelectionRange(0, input.value.length);
        status.textContent = 'School email / username selected. Use your device\'s Copy command.';
    }
}

async function verifyTemporaryPassword() {
    if (recoveryCurrentStatus === 'Verified' && resetState.verified) { setResetStep(3); return; }
    if (recoveryVerifying || recoveryCurrentStatus !== 'Approved') return;
    const generation = recoveryGeneration;
    const password = document.querySelector('#reset-temporary').value;
    const error = document.querySelector('#reset-error');
    if (!password) { error.textContent = 'Paste the temporary password into the second box.'; return; }
    recoveryVerifying = true;
    syncRecoveryControls();
    error.textContent = '';
    try {
        await cfoRequest('/api/recovery/verify', 'POST', {temporary_password: password});
        // Apply verification only while the student is still on the recovery page.
        if (resetState.step !== 2 || location.hash !== '#forgot-password' || !error.isConnected) return;
        resetState.verified = true;
        clearRecoveryPassword();
        setResetStep(3);
    } catch (failure) {
        if (error.isConnected) error.textContent = failure.message;
    } finally {
        if (generation === recoveryGeneration) {
            recoveryVerifying = false;
            syncRecoveryControls();
        }
    }
}

document.addEventListener('visibilitychange', () => { if (!document.hidden && recoveryPoll) checkRecoveryStatus(); });
window.addEventListener('pagehide', () => { stopRecoveryPolling(); stopRecoveryRequestCountdown(); clearRecoveryPassword(); });
window.addEventListener('pageshow', () => {
    if (resetState.step === 2 && location.hash === '#forgot-password' && !recoveryPoll) startRecoveryPolling();
    if (location.hash === '#forgot-password') startRecoveryRequestCountdown();
});
