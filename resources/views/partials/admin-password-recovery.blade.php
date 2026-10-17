// Included inside the admin script to share the existing grid, dialogs and live updates.
let recoveryView = 'Pending';
let recoveryDecisionBusy = false;
let recoveryStatusTimer = null;

function recoveryStatus(row) {
    return row.status;
}

function recoveryDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
    });
}

function recoveryHelp(status) {
    return {Pending:'Waiting for office verification', Approved:'Temporary password issued',
        Verified:'Creating a new password', Completed:'New password saved',
        Rejected:'Request declined', Expired:'A new request is needed',
        Cancelled:'Automatically closed after successful login'}[status] || status;
}

function recoveryRows() {
    return (cfoState.resets || []).map(row => ({...row, display_status: recoveryStatus(row)}));
}

function connectedRecovery() {
    const rows = recoveryRows();
    const count = status => rows.filter(row => row.display_status === status).length;
    const pending = count('Pending');
    const inProgress = count('Approved') + count('Verified');
    const views = [['Pending', 'Awaiting approval', pending], ['All', 'All requests', rows.length],
        ['History', 'Request history', rows.length - pending]];
    const visible = rows.filter(row => recoveryView === 'All' || (recoveryView === 'Pending'
        ? row.display_status === 'Pending' : row.display_status !== 'Pending'));
    visible.sort((a, b) => {
        const priority = Number(b.display_status === 'Pending') - Number(a.display_status === 'Pending');
        return priority || (a.display_status === 'Pending'
            ? new Date(a.created_at) - new Date(b.created_at) : new Date(b.created_at) - new Date(a.created_at));
    });
    $('#page-description').textContent = 'Verify identity at the office, then approve or reject each password reset request.';
    $('#page-actions').innerHTML = button('Refresh requests', 'recovery-refresh', '', '', 'refresh');
    const beforeGrid = `<div class="recovery-overview">
        <div class="metrics" aria-label="Password reset request counts">
            ${metric('Awaiting approval', pending, 'Verify these requests in person', 'clock')}
            ${metric('Recovery in progress', inProgress, 'Approved or setting a new password', 'lock')}
            ${metric('Completed', count('Completed'), 'Permanent password saved', 'check')}
            ${metric('Rejected / expired', count('Rejected') + count('Expired'), 'Review previous decisions below', 'history')}
        </div>
        <aside class="recovery-office">${icon('info')}<div><h2>Verify in person before approving</h2>
            <p><strong>CFO Office, Miraculous Building, near the chapel.</strong> Match the student's identity to their account and ask them to keep their requesting page open. Approval displays a temporary password on that page, valid for two minutes.</p>
        </div></aside>
        <div class="recovery-tabs" role="group" aria-label="Filter password reset requests">
            ${views.map(([key, label, total]) => `<button type="button" class="btn" data-action="recovery-view" data-id="${key}" aria-pressed="${recoveryView === key}">${label} <span>(${total})</span></button>`).join('')}
        </div>
    </div>`;
    connectedGrid({key:'recovery-'+recoveryView, title:recoveryView === 'Pending' ? 'Students awaiting approval' : 'Password reset requests',
        noun:'requests', rows:()=>visible, beforeGrid,
        placeholder:'Search name, student ID, email or request number',
        search:r=>[r.id, r.user?.name, r.user?.student_id, r.user?.email, r.user?.system_username].join(' '),
        filters:recoveryView === 'Pending' ? [] : [{key:'display_status',label:'Status',values:['Pending','Approved','Verified','Completed','Rejected','Expired','Cancelled'],get:r=>r.display_status}],
        columns:[
            {label:'Student', html:r=>`<div class="name-cell"><strong>${escapeHTML(r.user?.name || 'Account unavailable')}</strong><span>${escapeHTML(r.user?.student_id || 'No student ID')} · Request #${escapeHTML(r.id)}</span><span>${escapeHTML([r.user?.course,r.user?.year_level,r.user?.section].filter(Boolean).join(' · '))}</span></div>`},
            {label:'Account', get:r=>[r.user?.original_email || r.user?.email,r.user?.login_email || r.user?.system_username].filter(Boolean).join(' / '), html:r=>`<div class="name-cell"><strong>${escapeHTML(r.user?.original_email || r.user?.email || '—')}</strong><span>${escapeHTML(r.user?.login_email || r.user?.system_username || '—')}</span></div>`},
            {label:'Requested', get:r=>recoveryDate(r.created_at)},
            {label:'Status', get:r=>r.display_status, html:r=>badge(r.display_status)+`<span class="recovery-status-help">${escapeHTML(recoveryHelp(r.display_status))}</span>`}
        ],
        rowActions:r=>r.display_status === 'Pending'
            ? button('Review request','connected-recovery',r.id,'compact primary','eye')+button('Reject','recovery-reject',r.id,'compact danger','close')
            : button('View details','connected-recovery',r.id,'compact'),
        afterDraw:filtered=>{
            if (!filtered.length) $('#data-grid .table-empty').textContent = visible.length
                ? 'No requests match your search. Try a different name, student ID or email.'
                : recoveryView === 'Pending' ? 'No requests awaiting approval. New student requests appear here automatically.' : 'No password reset requests in this view.';
        }
    });
    $('#grid-host').classList.add('recovery-grid');
    clearTimeout(recoveryStatusTimer);
    const checkExpiry = () => {
        if (currentRoute !== 'admin-password-resets') return;
        if (!isAdminEditing() && rows.some(row => recoveryStatus(row) !== row.display_status)) connectedRecovery();
        else recoveryStatusTimer = setTimeout(checkExpiry, 5000);
    };
    recoveryStatusTimer = setTimeout(checkExpiry, 5000);
}

function recoveryStudentDetails(row) {
    return `<div class="recovery-review-card">${details([
        ['Student',row.user?.name], ['Student ID',row.user?.student_id],
        ['Registered email',row.user?.original_email || row.user?.email],
        ['Login email',row.user?.login_email || row.user?.system_username],
        ['Request', '#'+row.id], ['Requested',recoveryDate(row.created_at)]
    ])}</div>`;
}

function connectedRecoveryReview(id) {
    const row = recoveryRows().find(row=>String(row.id)===String(id));
    if (!row) { toast('This request is no longer available. Refresh the list.'); return; }
    if (row.display_status === 'Pending') { connectedRecoveryDecision(id, 'Approved'); return; }
    showDialog('Password reset request #'+row.id, recoveryStudentDetails(row)+details([
        ['Status',row.display_status], ['Reviewed by',row.reviewer?.name],
        ['Reviewed',recoveryDate(row.reviewed_at)], ['Password expires',['Approved','Verified'].includes(row.status) ? 'No time limit' : recoveryDate(row.expires_at)],
        ['Verified',recoveryDate(row.verified_at)], ['Completed',recoveryDate(row.used_at)]
    ]), button('Close','close-dialog'));
}

function connectedRecoveryDecision(id, decision) {
    if (recoveryDecisionBusy) return;
    const row = recoveryRows().find(row=>String(row.id)===String(id));
    if (!row || row.display_status !== 'Pending') { toast('This request is no longer pending. Refresh the list to see its current status.'); return; }
    const approve = decision === 'Approved';
    showDialog(approve ? 'Review password reset request' : 'Reject password reset?', recoveryStudentDetails(row)+`
        <form id="recovery-review">
            <p class="notice ${approve ? '' : 'neutral'}">${approve
                ? 'The temporary password will appear on the student\'s requesting page. It has no time limit and can only be used once.'
                : 'This request will be declined. The student will see a rejected status, and no temporary password will be generated. They can contact the office or submit a new request.'}</p>
            ${approve ? '<label class="check-label recovery-confirm"><input type="checkbox" name="identity_verified" required> I verified this student\'s identity in person at the CFO Office, Miraculous Building, near the chapel.</label>' : ''}
            <p id="recovery-decision-error" class="recovery-error" role="alert"></p>
            <div class="form-actions">${button('Cancel','close-dialog')}<button type="submit" class="btn ${approve ? 'primary' : 'danger'}">${approve ? 'Approve request' : 'Reject request'}</button></div>
        </form>`, '');
    const form = $('#recovery-review');
    form.onsubmit = event => { event.preventDefault(); submitRecoveryDecision(form, row.id, decision); };
}

async function submitRecoveryDecision(form, id, decision) {
    if (recoveryDecisionBusy) return;
    const error = form.querySelector('[role=alert]');
    const verified = Boolean(form.elements.identity_verified?.checked);
    if (decision === 'Approved' && !verified) { error.textContent = 'Confirm in-person identity verification before approving.'; return; }
    recoveryDecisionBusy = true;
    const controls = [...dialog.querySelectorAll('button,input')];
    controls.forEach(control=>control.disabled=true);
    error.textContent = '';
    try {
        await backend('/api/admin/password-resets/'+id, 'PATCH', {status:decision, ...(decision === 'Approved' ? {identity_verified:true} : {})});
        closeDialog();
        await renderConnectedAdmin();
        toast(decision === 'Approved' ? 'Request approved. The temporary password will appear on the student\'s page.' : 'Request rejected. The student can see the decision on their page.');
    } catch (failure) {
        error.textContent = failure.message;
    } finally {
        recoveryDecisionBusy = false;
        controls.forEach(control=>control.disabled=false);
    }
}
