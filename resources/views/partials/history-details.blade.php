<style>
  #history-detail .detail-list { display:grid; grid-template-columns:160px minmax(0,1fr); gap:12px 20px; font-size:14px; }
  #history-detail dt { font-weight:600; }
  #history-detail dd { margin:0; }
  #history-detail h3 { margin-top:28px; }
  #history-detail .form-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
  @media(max-width:600px) { #history-detail .detail-list { grid-template-columns:1fr; gap:6px; } #history-detail dd { margin-bottom:12px; } }
</style>
<script>
window.cfoHistoryDetails = function ({registration:r, actors={}}, {modalDocuments=false}={}) {
  const escape = value => String(value ?? '—').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const isStudent = window.CFO_BACKEND?.user?.role === 'Student';
  const list = rows => '<dl class="detail-list">'+rows.map(([key,value])=>'<dt>'+escape(key)+'</dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere">'+(isStudent && /email/i.test(key) && window.studentEmailDisplay ? window.studentEmailDisplay(value) : escape(value))+'</dd>').join('')+'</dl>';
  const who = id => actors[id] || (id ? 'Staff #'+id : '—');
  const student = r.student_snapshot || {};
  const cert = r.certificate_record;
  return list([
    ['Student',student.name||r.user?.name],['Student ID',student.student_id||r.user?.student_id],['Recollection year',r.year],['Attempt',r.attempt_no||1],
    ['Course',student.course],['Year level',student.year_level],['Section',student.section],['Submitted year / section',r.details?.yearSection],
    ['Registered at',r.created_at],['Archived at',r.archived_at],['Archive reason',r.archive_reason === 'batch_completed'?'Batch completed':r.archive_reason === 'year_rollover'?'Yearly rollover':r.archive_reason],['Registration status',r.status],['Rejection reason',r.rejection_reason],
    ['Receipt status',r.receipt_status],['Receipt review reason',r.receipt_reason],['Receipt verified by',who(r.receipt_verified_by)],['Receipt verified at',r.receipt_verified_at],
    ['Schedule',r.schedule?.title],['Date',r.schedule?.date],['Time',r.schedule?.time],['Venue',r.schedule?.venue],
    ['Attendance',r.attendance],['Recorded by',who(r.attendance_recorded_by)],['Recorded at',r.attendance_recorded_at],['Verified by',who(r.attendance_verified_by)],['Verified at',r.attendance_verified_at],
    ['Certificate',cert?(cert.revoked_at?'Withdrawn':'Issued'):'Not issued'],['Certificate number',cert?.certificate_number],['Issued by',who(cert?.issued_by)],['Certificate created at',cert?.created_at],['Certificate last updated',cert?.updated_at],['Withdrawal reason',cert?.revocation_reason]
  ])+(student.legacy?'<p class="note">Legacy record: missing academic details were captured from the available profile when history was enabled. See the original submitted information below.</p>':'')+
  (modalDocuments ? window.CfoHistoryDocuments.controlsHtml(r) : '<div class="form-actions">'+(r.has_receipt?'<a class="btn outline" target="_blank" rel="noopener" href="/api/receipts/'+r.id+'">View receipt</a>':'')+(r.certificate?'<a class="btn primary" target="_blank" rel="noopener" href="/certificates/'+r.certificate.id+'">View / Print certificate</a><a class="btn outline" href="/certificates/'+r.certificate.id+'?download=1">Download SVG</a>':'')+'</div>')+
  '<h3>Submitted registration information</h3>'+list(Object.entries(r.details||{}).map(([key,value])=>[key.replace(/([A-Z])/g,' $1').replace(/^./,c=>c.toUpperCase()),typeof value==='boolean'?(value?'Yes':'No'):value]))+
  '<h3>Schedule change requests</h3>'+(r.changes?.length?r.changes.map(c=>list([['Requested at',c.created_at],['Original schedule',c.current_schedule?.title+' · '+c.current_schedule?.date],...(c.requested_schedule?[['Requested schedule (legacy)',c.requested_schedule.title+' · '+c.requested_schedule.date]]:[]),['Assigned schedule',c.assigned_schedule?c.assigned_schedule.title+' · '+c.assigned_schedule.date:'Not assigned'],['Reason',c.reason],['Status',isStudent ? c.student_status || c.status : c.status],['CFO response',isStudent ? c.student_message || c.review_reason : c.review_reason],['Reviewed at',c.reviewed_at]])).join('<hr>'):'<p>No schedule change requests.</p>');
};
</script>
