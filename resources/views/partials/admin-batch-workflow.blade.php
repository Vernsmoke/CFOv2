// Registration -> schedule batch -> attendance -> permanent history.
let batchSaveBusy = false;
let attendanceView = 'students';
let attendanceUnmarkedFirst = false;
let selectedReview = null;

function lockBatchControls() {
    const controls=[...document.querySelectorAll('#page button,#page input,#page select'),...dialog.querySelectorAll('button,input,select,textarea')];
    const previous=controls.map(control=>[control,control.disabled]);
    controls.forEach(control=>control.disabled=true);
    $('#page').setAttribute('aria-busy','true');
    dialog.setAttribute('aria-busy','true');
    return ()=>{previous.forEach(([control,disabled])=>control.disabled=disabled);$('#page').removeAttribute('aria-busy');dialog.removeAttribute('aria-busy');};
}

async function saveBatchAction(url, payload, title='Saved successfully') {
    if(batchSaveBusy)return;
    batchSaveBusy=true;
    if(!dialog.open)showDialog('Saving attendance','<p role="status">Saving the record to the CFO database…</p>','');
    dialog.querySelector('.dialog-body')?.insertAdjacentHTML('beforeend','<p data-batch-feedback class="batch-feedback" role="status">Saving…</p>');
    const feedback=dialog.querySelector('[data-batch-feedback]');
    const unlock=lockBatchControls();
    try {
        const result=await backend(url,'POST',payload);
        closeDialog();
        await renderConnectedAdmin();
        showDialog(title,`<p role="status">${escapeHTML(result.message)}</p>`,button('Done','close-dialog'));
    } catch(error) { if(feedback)feedback.textContent=error.message;window.CfoUsability?.formError(dialog.querySelector('form'),error);toast(error.message); }
    finally { batchSaveBusy=false;unlock(); }
}

function scheduleUnavailable(schedule, registration) {
    if(schedule.finalized_at)return 'Batch already in History';
    if(!schedule.active)return 'Closed';
    if(schedule.year!==registration.year)return 'Different recollection year';
    if(schedule.date<cfoState.server_date)return 'Past schedule';
    if(schedule.id!==registration.recollection_schedule_id && schedule.registrations_count >= (schedule.slot_limit??schedule.capacity))return 'Full';
    return '';
}

function connectedRegistration(id) {
    const r=findRegistration(id);
    if(!r)return;
    if(r.is_archived){connectedHistoryView(id);return;}
    const schedules=cfoState.schedules||[];
    const receipt=r.has_receipt?`<a class="btn" href="/api/receipts/${r.id}" target="_blank" rel="noopener">View receipt</a><p><img src="/api/receipts/${r.id}" alt="Student receipt" style="max-width:100%;max-height:280px;object-fit:contain"></p>`:'<p class="note">The student must upload a receipt before a schedule can be saved.</p>';
    showDialog(r.recollection_schedule_id?'Edit student schedule':'Review registration and set schedule',
        details([['Student',r.user.name],['ID number',r.user.student_id],['Recollection year',r.year],['Registration',r.status],['Receipt',r.receipt_status],['Schedule',r.schedule?.title||'Not assigned'],['Date',r.schedule?.date||'—'],['Time',r.schedule?.time||'—'],['Venue',r.schedule?.venue||'—'],['Attendance',r.attendance],['Attendance verified',r.attendance_verified_at||'Not verified'],['Certificate',r.certificate?'Available':'Not available'],...Object.entries(r.details||{})])+receipt+(r.has_receipt?'<p>'+button('Review receipt status','connected-receipt',r.id)+'</p>':'')+
        `<form id="review-registration"><label class="field">Recollection schedule<select name="schedule_id" required><option value="">Choose a schedule</option>${schedules.map(s=>{const reason=scheduleUnavailable(s,r);return `<option value="${s.id}" ${s.id===r.recollection_schedule_id?'selected':''} ${reason?'disabled':''}>${escapeHTML(s.title+' · '+s.date+' · '+s.time+(reason?' — '+reason:''))}</option>`;}).join('')}</select></label><p class="muted">Saving confirms that you reviewed the registration and receipt, and moves this student to Attendance.</p>${r.attendance!=='Not Yet Recorded'?'<label class="check-label"><input name="confirm_attendance_reset" type="checkbox" required> I understand that a different schedule clears attendance and withdraws the current certificate until attendance is marked again.</label>':''}<div class="form-actions"><button type="submit" class="btn primary" ${!r.has_receipt?'disabled':''}>Save</button>${button('Cancel','close-dialog')}</div></form>`,'',true);
    $('#review-registration').onsubmit=event=>{event.preventDefault();saveBatchAction('/api/admin/registrations/'+r.id+'/schedule',{schedule_id:Number(event.target.elements.schedule_id.value),confirm_attendance_reset:Boolean(event.target.elements.confirm_attendance_reset?.checked)},'Schedule successfully set');};
}

function connectedAttendance() {
    const rows=cfoState.registrations.filter(r=>!r.is_archived && r.year===cfoState.current_year && r.schedule && !r.schedule.finalized_at && r.status==='Approved');
    const batchId=routeDetails().params.get('batch');
    const schedules=new Map((cfoState.schedules||[]).filter(s=>s.year===cfoState.current_year && !s.finalized_at && (s.active||rows.some(r=>String(r.recollection_schedule_id)===String(s.id)))).map(s=>[String(s.id),s]));
    rows.forEach(r=>{if(!r.schedule.finalized_at)schedules.set(String(r.recollection_schedule_id),r.schedule);});
    const available=[...schedules.values()].sort((a,b)=>a.date.localeCompare(b.date)||a.id-b.id);
    const schedule=schedules.get(batchId);
    const participants=schedule?rows.filter(r=>String(r.recollection_schedule_id)===String(schedule.id)):[];
    $('#page-title').textContent='Recollection Attendance';
    $('#page-description').textContent='Record student attendance for each recollection schedule.';
    $('#page-actions').innerHTML=link('Recollection History','admin-history','','history');
    const groups=new Map();
    participants.forEach(r=>{const group=attendanceAcademicGroup(r),name=[group.course,[group.year_level,group.section].filter(Boolean).join('')].filter(Boolean).join(' · ')||'Academic details not provided';groups.set(name,(groups.get(name)||0)+1);});
    const tabs=`<div id="attendance-tabs" class="attendance-tabs" role="tablist" aria-label="Attendance views">${[['students','Student Attendance','users'],['health','Health & Religion','heart']].map(([view,label,symbol])=>`<button type="button" id="attendance-tab-${view}" role="tab" aria-selected="${attendanceView===view}" aria-controls="grid-host" tabindex="${attendanceView===view?'0':'-1'}" class="attendance-tab ${attendanceView===view?'active':''}" data-action="attendance-view" data-id="${view}">${icon(symbol)}${label}</button>`).join('')}</div>`;
    const header=`<section class="panel attendance-schedule" aria-labelledby="attendance-heading"><div class="attendance-heading"><span class="attendance-heading-icon">${icon('calendar')}</span><div><h1 id="attendance-heading" tabindex="-1">Recollection Attendance</h1><p>Record student attendance for each recollection schedule.</p></div></div>${tabs}<label class="attendance-schedule-label" for="attendance-schedule">Select Recollection Schedule</label><select id="attendance-schedule"><option value="">Choose a recollection schedule</option>${available.map(s=>`<option value="${s.id}" ${String(s.id)===batchId?'selected':''}>${escapeHTML(attendanceDate(s.date)+' · '+attendanceTime(s.start_time)+' – '+attendanceTime(s.end_time)+' · '+s.venue+' · '+s.title)}</option>`).join('')}</select>${schedule?`<div class="attendance-schedule-meta"><span>${icon('calendar')}${escapeHTML(attendanceDate(schedule.date))}</span><span>${icon('clock')}${escapeHTML(attendanceTime(schedule.start_time)+' – '+attendanceTime(schedule.end_time))}</span><span>${icon('home')}${escapeHTML(schedule.venue)}</span></div>${groups.size?`<div class="attendance-groups"><span>Participating Courses:</span>${[...groups].map(([name,count])=>`<span class="attendance-chip">${escapeHTML(name)} <b>${count}</b></span>`).join('')}</div>`:''}`:''}</section>`;
    if(!schedule){
        activeGrid=null;
        $('#page').innerHTML=header+'<div id="grid-host">'+empty(available.length?'Choose a recollection schedule':'No active attendance batches',available.length?(batchId?'The selected batch is no longer active. Select another schedule or open History.':'Select a schedule above to view its students and record attendance.'):'Students appear here after their registration, receipt, and schedule are approved.',link('Recollection Registrations','admin-register-this-year','primary'))+'</div>';
        initializeAttendanceSelector();
        return;
    }
    if(attendanceView==='health'){attendanceInformationGrid(schedule,participants,header);initializeAttendanceSelector();return;}
    const unmarked=attendanceBatchRows(schedule.id).filter(r=>!['Present','Absent'].includes(r.attendance)).length;
    const progress='<section class="panel attendance-progress" aria-label="Attendance progress"><div class="attendance-progress-text"><strong data-attendance-progress-text>0 of 0 marked (0%)</strong><span data-attendance-progress-note></span></div><div class="attendance-progress-track" role="progressbar" aria-label="Students marked" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-attendance-progress-bar><span></span></div></section>';
    const religions=[...new Set(participants.map(r=>attendanceDetail(r,'religion')).filter(Boolean))].sort();
    connectedGrid({key:'batch-attendance-'+schedule.id,title:schedule.title,noun:'students matched',rows:()=>attendanceRosterSort(participants),selectable:true,compactFilters:true,expandableFilters:true,
        filterToolbar:button('Mark All Present','batch-present-all',schedule.id,'attendance-mark-all','check')+`<button type="button" class="btn attendance-unmarked-toggle" data-action="attendance-unmarked-first" aria-pressed="${attendanceUnmarkedFirst}">${icon('clock')} Unmarked first: ${attendanceUnmarkedFirst?'On':'Off'}</button>`,
        toolbarBefore:`<div class="attendance-status-counts" role="status" aria-label="Attendance totals for matching students"><span class="attendance-pill present">Present <b data-attendance-count="Present">0</b></span><span class="attendance-pill absent">Absent <b data-attendance-count="Absent">0</b></span><span class="attendance-pill pending">Not yet <b data-attendance-count="Not Yet Recorded">0</b></span></div><div class="attendance-bulkbar" data-attendance-bulk hidden><b data-bulk-count>0 selected</b>${button('Present','batch-selected-present',schedule.id,'attendance-mark present','check')}${button('Absent','batch-selected-absent',schedule.id,'attendance-mark absent','close')}<button type="button" class="btn compact" data-action="batch-select-matching" data-id="" data-bulk-matching hidden>Select all matching</button>${button('Clear','batch-selected-clear','','compact')}</div>`,
        toolbar:button('Print','connected-print','','','print')+button('Export','attendance-export','','','download'),
        beforeGrid:header+progress,
        filters:[...['course','year_level','section'].map(key=>({key,label:{course:'Course',year_level:'Year Level',section:'Section'}[key],values:[...new Set(participants.map(r=>attendanceAcademicGroup(r)[key]).filter(Boolean))].sort(),get:r=>attendanceAcademicGroup(r)[key]})),{key:'attendance',label:'Attendance',values:['Present','Absent','Not Yet Recorded'],get:r=>r.attendance},{key:'health',label:'Health / Allergies',values:['With Health Condition / Allergy','No Reported Health Condition'],get:attendanceHealthLabel},{key:'religion',label:'Religion',values:religions,get:r=>attendanceDetail(r,'religion')}],
        columns:[...attendanceStudentColumns().filter(column=>column.label!=='ID Number'),{label:'Alerts',get:attendanceAlertText,sort:attendanceAlertSort,html:attendanceAlertCell},{label:'Attendance',get:r=>r.attendance,html:attendanceControls},{label:'Marked at',get:attendanceMarkedAt,sort:r=>r.attendance_verified_at||'',nowrap:true}],
        rowActions:r=>button('View','connected-registration',r.id,'compact attendance-view','eye'),
        afterDraw:matching=>{for(const status of ['Present','Absent','Not Yet Recorded'])$(`[data-attendance-count="${status}"]`).textContent=matching.filter(r=>r.attendance===status).length;updateAttendanceProgress(participants);updateAttendanceBulkBar(matching.length);}});
    $('#data-grid').classList.add('attendance-workspace');
    $('#data-grid').insertAdjacentHTML('beforeend',`<footer class="attendance-completion"><div class="attendance-completion-actions">${button('Complete Attendance','batch-complete',schedule.id,'primary','check')}${button('Move to Recollection History','batch-archive',schedule.id,'attendance-history','history')}</div><p>${participants.length?`${unmarked} student${unmarked===1?'':'s'} remaining to mark. Every student in this batch must be Present or Absent before it can move to History.`:'No eligible students have been assigned to this schedule yet.'}</p></footer>`);
    if(!participants.length)$('#data-grid .table-empty').textContent='No approved students are assigned to this schedule yet.';
    initializeAttendanceSelector();
}

function attendanceStudentName(r) {return r.user?.last_name&&r.user?.first_name?r.user.last_name+', '+r.user.first_name:r.user?.name||'Student';}
function attendanceAcademicGroup(r) {return window.CfoSearch.registrationAcademicGroup(r);}
function attendanceAcademicLabel(r) {
    const group=attendanceAcademicGroup(r);
    return [group.course,[group.year_level,group.section].filter(Boolean).join('')].filter(Boolean).join(' – ')||'—';
}
function attendanceStudentColumns() {
    return [{label:'#',rowNumber:true},{label:'Student Name',get:attendanceStudentName,html:r=>`<strong class="attendance-student">${escapeHTML(attendanceStudentName(r))}</strong><small class="attendance-cell-detail">${escapeHTML(r.user?.student_id||'—')}</small>`},{label:'ID Number',get:r=>r.user?.student_id||'—',nowrap:true},{label:'Course – Year & Section',get:attendanceAcademicLabel,html:r=>{const group=attendanceAcademicGroup(r);return `<strong class="attendance-course">${escapeHTML(group.course||'—')} – ${escapeHTML([group.year_level,group.section].filter(Boolean).join('')||'—')}</strong><small class="attendance-cell-detail">Year ${escapeHTML(group.year_level||'—')} · Section ${escapeHTML(group.section||'—')}</small>`;}}];
}
function attendanceDetail(r,key) {return typeof r.details?.[key]==='string'?r.details[key].trim():'';}
function attendancePayment(r) {return r.receipt_status==='Approved'?'Paid':'Unpaid';}
function attendanceReportedHealth(r) {
    return [['Medical condition','medical'],['Allergies','allergies']].map(([label,key])=>({label,value:attendanceDetail(r,key)}))
        .filter(item=>item.value&&!/^(none|no|n\/?a|nil|not applicable|wala|without|no (known )?(allergies|medical conditions|health conditions))[.!\s]*$/i.test(item.value));
}
function attendanceHealthLabel(r) {return attendanceReportedHealth(r).length?'With Health Condition / Allergy':'No Reported Health Condition';}
// ---- Tab 2 shared renderers (pure functions; the Chrome preview reuses them) ----
const attendanceNoneLike=value=>/^(none|no|n\/?a|nil|not applicable|wala|without|no (known )?(allergies|medical conditions|health conditions|medications?))[.!\s]*$/i.test(String(value||'').trim());
function attendanceIsCatholic(r) {return /catholic/i.test(attendanceDetail(r,'religion'));}
function attendanceShown(r,key) {const value=attendanceDetail(r,key);return value&&!attendanceNoneLike(value)?value:'';}
function attendanceMedications(r) {return attendanceShown(r,'medications');}
function attendanceHasHealthInfo(r) {return attendanceReportedHealth(r).length>0||attendanceMedications(r)!=='';}
function attendanceHealthItems(r) {
    const items=[...attendanceReportedHealth(r)],meds=attendanceMedications(r);
    if(meds)items.push({label:'Medications',value:meds});
    const rank=item=>({'Allergies':0,'Medical condition':1,'Medications':2})[item.label]??3;
    return items.sort((a,b)=>rank(a)-rank(b));
}
function attendanceHealthCell(r) {
    const items=attendanceHealthItems(r);
    if(!items.length)return '<span class="attendance-pill neutral">● None reported</span>';
    const flagged=attendanceReportedHealth(r).length>0;
    return `<div class="attendance-health-values"><span class="attendance-pill ${flagged?'absent':'pending'}">${flagged?'● With Health / Allergy':'● Medication only'}</span>${items.map(item=>`<small><b>${escapeHTML(item.label)}:</b> ${escapeHTML(item.value)}</small>`).join('')}</div>`;
}
function attendanceHealthText(r) {return attendanceHealthItems(r).map(item=>item.label+': '+item.value).join(' · ')||'None reported';}
function attendanceHealthSort(r) {return (attendanceReportedHealth(r).length?'0':attendanceMedications(r)?'1':'2')+attendanceStudentName(r);}
function attendanceFaithCell(r) {
    const religion=attendanceDetail(r,'religion');
    if(!religion)return '<span class="attendance-pill neutral">Not provided</span>';
    const catholic=attendanceIsCatholic(r),stated=!/^prefer not to say$/i.test(religion),parish=attendanceDetail(r,'parish');
    const mark=key=>/^yes$/i.test(attendanceDetail(r,key))?'✓':/^no$/i.test(attendanceDetail(r,key))?'✗':'–';
    const sacraments=catholic?`<small class="attendance-cell-detail">Baptism ${mark('baptism')} · Communion ${mark('communion')} · Confirmation ${mark('confirmation')}</small>`:'';
    return `<span class="attendance-pill ${catholic?'paid':stated?'pending':'neutral'}">${escapeHTML(religion)}</span>${sacraments}${parish?`<small class="attendance-cell-detail">Parish: ${escapeHTML(parish)}</small>`:''}`;
}
function attendanceFaithText(r) {return attendanceDetail(r,'religion')||'Not provided';}
function attendanceEmergencyText(r) {
    const name=attendanceDetail(r,'emergencyName'),relation=attendanceDetail(r,'relationship'),phone=attendanceDetail(r,'emergencyPhone');
    return [name,relation&&'('+relation+')',phone].filter(Boolean).join(' ')||'Not provided';
}
function attendanceEmergencyCell(r) {
    const name=attendanceDetail(r,'emergencyName'),relation=attendanceDetail(r,'relationship'),phone=attendanceDetail(r,'emergencyPhone');
    if(!name&&!phone)return '<small class="attendance-cell-detail">Not provided</small>';
    return `<strong class="attendance-student">${escapeHTML(name||'—')}</strong><small class="attendance-cell-detail">${escapeHTML(relation)}${relation&&phone?' · ':''}${phone?`<a href="tel:${escapeHTML(phone.replace(/[^\d+]/g,''))}">${escapeHTML(phone)}</a>`:''}</small>`;
}
function attendanceHealthColumns() {
    return [...attendanceStudentColumns().filter(column=>column.label!=='ID Number'),
        {label:'Attendance',get:r=>r.attendance,html:r=>`<span class="attendance-pill ${r.attendance==='Present'?'present':r.attendance==='Absent'?'absent':'pending'}">${escapeHTML(r.attendance)}</span>`},
        {label:'Health / Allergies',get:attendanceHealthText,sort:attendanceHealthSort,html:attendanceHealthCell},
        {label:'Religion',get:attendanceFaithText,html:attendanceFaithCell},
        {label:'Emergency contact',get:attendanceEmergencyText,html:attendanceEmergencyCell}];
}
function attendanceHealthRoster(rows) {return [...rows].sort((a,b)=>attendanceHealthSort(a).localeCompare(attendanceHealthSort(b),undefined,{sensitivity:'base'}));}
function attendanceAllergenCounts(rows) {
    const counts=new Map();
    rows.forEach(r=>{
        const value=attendanceShown(r,'allergies');if(!value)return;
        value.split(/[,;\/&\n]+|\band\b/i).map(part=>part.trim().toLowerCase().replace(/[.\s]+$/,'')).filter(Boolean).forEach(part=>counts.set(part,(counts.get(part)||0)+1));
    });
    return [...counts].sort((a,b)=>b[1]-a[1]||a[0].localeCompare(b[0]));
}
function renderAttendanceAllergens(rows) {
    const list=$('[data-allergen-list]');if(!list)return;
    const counts=attendanceAllergenCounts(rows);
    $('[data-allergen-note]').textContent=counts.length?' · counted from what students typed, so spellings may differ':'';
    list.innerHTML=counts.length?counts.slice(0,10).map(([name,count])=>`<span class="attendance-chip">${escapeHTML(name.charAt(0).toUpperCase()+name.slice(1))} <b>${count}</b></span>`).join('')+(counts.length>10?`<span class="muted">+${counts.length-10} more</span>`:''):'<span class="muted">No reported allergies for the students in this view.</span>';
}
function attendanceHealthAfterDraw(matching,filters) {
    const set=(key,test)=>{const element=$(`[data-health-count="${key}"]`);if(element)element.textContent=matching.filter(test).length;};
    set('Present',r=>r.attendance==='Present');
    set('Catholic',attendanceIsCatholic);
    set('Other',r=>{const religion=attendanceDetail(r,'religion');return religion&&!attendanceIsCatholic(r)&&!/^prefer not to say$/i.test(religion);});
    set('health',r=>attendanceReportedHealth(r).length>0);
    renderAttendanceAllergens(matching);
    $('.attendance-present-only')?.setAttribute('aria-pressed',String(filters.attendance==='Present'));
}
function attendanceInformationPairs(r) {
    const value=key=>attendanceDetail(r,key)||'Not provided';
    const group=attendanceAcademicGroup(r);
    return [['Student Name',attendanceStudentName(r)],['Student ID',r.user?.student_id],['Course',group.course],['Year Level',group.year_level],['Section',group.section],
        ['Religion',value('religion')],['Parish',value('parish')],['Baptism',value('baptism')],['Holy Communion',value('communion')],['Confirmation',value('confirmation')],
        ['Allergies',value('allergies')],['Medical Condition',value('medical')],['Medications',value('medications')],
        ['Emergency Contact',value('emergencyName')],['Relationship',value('relationship')],['Emergency Number',value('emergencyPhone')],
        ['Attendance',r.attendance],['Payment',attendancePayment(r)],['Receipt Status',r.receipt_status],['Recollection Schedule',r.schedule?.title],
        ['Date',r.schedule?attendanceDate(r.schedule.date):'—'],['Time',r.schedule?attendanceTime(r.schedule.start_time)+' – '+attendanceTime(r.schedule.end_time):'—'],['Location',r.schedule?.venue]];
}
function attendanceHealthPrintMarkup(rows,scheduleLine) {
    const cell=value=>`<td>${escapeHTML(value||'—')}</td>`;
    return `<p>${escapeHTML(scheduleLine)}</p><table><thead><tr><th>Student</th><th>Course – Year &amp; Section</th><th>Attendance</th><th>Allergies</th><th>Medical condition</th><th>Medications</th><th>Emergency contact</th></tr></thead><tbody>${rows.map(r=>`<tr>${cell(attendanceStudentName(r))}${cell(attendanceAcademicLabel(r))}${cell(r.attendance)}${cell(attendanceShown(r,'allergies'))}${cell(attendanceShown(r,'medical'))}${cell(attendanceMedications(r))}${cell(attendanceEmergencyText(r))}</tr>`).join('')}</tbody></table>`;
}
function attendanceHealthCsv(rows) {
    const value=(r,key)=>attendanceDetail(r,key);
    return {columns:['Student ID','Student Name','Course','Year','Section','Attendance','Religion','Parish','Baptism','Holy Communion','Confirmation','Allergies','Medical condition','Medications','Emergency contact','Relationship','Emergency number'],
        data:rows.map(r=>{const group=attendanceAcademicGroup(r);return [r.user?.student_id||'',attendanceStudentName(r),group.course,group.year_level,group.section,r.attendance,value(r,'religion'),value(r,'parish'),value(r,'baptism'),value(r,'communion'),value(r,'confirmation'),value(r,'allergies'),value(r,'medical'),value(r,'medications'),value(r,'emergencyName'),value(r,'relationship'),value(r,'emergencyPhone')];})};
}
// ---- end Tab 2 shared renderers ----

function attendanceInformationGrid(schedule,participants,header) {
    const religions=[...new Set(participants.map(r=>attendanceDetail(r,'religion')).filter(Boolean))].sort();
    const allergens='<section class="panel attendance-allergens" aria-label="Reported allergies" aria-live="polite"><div><strong>Reported allergies</strong><span class="attendance-allergen-note" data-allergen-note></span></div><div class="attendance-allergen-list" data-allergen-list></div></section>';
    connectedGrid({key:'batch-health-'+schedule.id,title:'Health & Religion',noun:'students matched',rows:()=>attendanceHealthRoster(participants),
        beforeGrid:header+allergens,compactFilters:true,expandableFilters:true,countFilterOptions:true,
        filterToolbar:`<button type="button" class="btn attendance-present-only" data-action="health-present-only" aria-pressed="false">${icon('check')} Present only</button>`,
        toolbarBefore:'<div class="attendance-status-counts" role="status" aria-label="Student information totals for matching students"><span class="attendance-pill present"><b data-health-count="Present"></b> Present</span><span class="attendance-pill paid"><b data-health-count="Catholic"></b> Roman Catholic</span><span class="attendance-pill pending"><b data-health-count="Other"></b> Other religion</span><span class="attendance-pill health"><b data-health-count="health"></b> With Health / Allergy</span></div>',
        toolbar:button('Print alert list','health-print',schedule.id,'','print')+button('Export','health-export','','','download'),
        filters:[{key:'attendance',label:'Attendance',values:['Present','Absent','Not Yet Recorded'],get:r=>r.attendance},
            {key:'health',label:'Health / Allergies',values:['With Health Condition / Allergy','No Reported Health Condition'],get:attendanceHealthLabel},
            {key:'religion',label:'Religion',values:religions,get:r=>attendanceDetail(r,'religion')},
            {key:'course',label:'Course',values:[...new Set(participants.map(r=>attendanceAcademicGroup(r).course).filter(Boolean))].sort(),get:r=>attendanceAcademicGroup(r).course}],
        columns:attendanceHealthColumns(),
        rowActions:r=>button('View','attendance-information',r.id,'compact attendance-view','eye'),
        afterDraw:matching=>attendanceHealthAfterDraw(matching,activeGrid?.state.filters||{})});
    $('#data-grid').classList.add('attendance-workspace','attendance-health-workspace');
    if(!participants.length)$('#data-grid .table-empty').textContent='No approved students are assigned to this schedule yet.';
}
function attendanceInformationView(id) {
    const r=findRegistration(id);
    if(!r||r.is_archived||r.year!==cfoState.current_year||r.status!=='Approved'||String(r.recollection_schedule_id)!==routeDetails().params.get('batch'))return;
    showDialog('Student Information',`<p class="muted">${escapeHTML(attendanceStudentName(r))}</p>`+details(attendanceInformationPairs(r)),button('Close','close-dialog'),true);
}
function printHealthAlerts(id) {
    if(!activeGrid)return;
    const rows=activeGrid.filtered().filter(attendanceHasHealthInfo);
    if(!rows.length){toast('No students with reported health or allergy information in this view.');return;}
    const schedule=(cfoState.schedules||[]).find(s=>String(s.id)===String(id));
    printPreview('Health & allergy alert list',attendanceHealthPrintMarkup(rows,schedule?schedule.title+' · '+attendanceDate(schedule.date):''));
}
function exportHealthRegistrations() {
    if(!activeGrid)return;
    const csv=attendanceHealthCsv(activeGrid.filtered());
    csvDownload(activeGrid.config.key+'.csv',csv.columns,csv.data);
}
function toggleHealthPresentOnly() {
    if(!activeGrid)return;
    const state=activeGrid.state;
    state.filters.attendance=state.filters.attendance==='Present'?'':'Present';
    state.page=1;state.selected.clear();
    const select=$('[data-filter="attendance"]');if(select)select.value=state.filters.attendance;
    activeGrid.draw();
}
function switchAttendanceView(view) {
    if(batchSaveBusy||!['students','health'].includes(view)||view===attendanceView)return;
    attendanceView=view;connectedAttendance();hydrateIcons();$('#attendance-tab-'+view).focus({preventScroll:true});
}
function attendanceControls(r) {return `<div class="attendance-row-controls">${['Present','Absent'].map(status=>`<button type="button" class="btn compact attendance-mark ${status.toLowerCase()}" data-action="${status==='Present'?'batch-present':'batch-absent'}" data-id="${escapeHTML(r.id)}" aria-pressed="${r.attendance===status}" aria-label="${escapeHTML('Mark '+attendanceStudentName(r)+' '+status)}">${icon(status==='Present'?'check':'close')}${status}</button>`).join('')}</div>${r.attendance==='Not Yet Recorded'?'<small class="attendance-unrecorded">Not Yet Recorded</small>':''}`;}
function attendanceDate(date) {const day=new Date(date+'T00:00:00Z');return Number.isNaN(day.getTime())?date:new Intl.DateTimeFormat('en-PH',{dateStyle:'long',timeZone:'UTC'}).format(day);}
function attendanceTime(time) {if(!time)return '—';const [hour,minute]=time.split(':');return (Number(hour)%12||12)+':'+minute+' '+(Number(hour)<12?'AM':'PM');}
function initializeAttendanceSelector() {
    $('#attendance-schedule').onchange=event=>go('admin-student-attendance'+(event.target.value?'?batch='+encodeURIComponent(event.target.value):''));
    $('#grid-host').setAttribute('role','tabpanel');$('#grid-host').setAttribute('aria-labelledby','attendance-tab-'+attendanceView);
    $('#attendance-tabs').onkeydown=event=>{if(batchSaveBusy||!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const view=event.key==='Home'?'students':event.key==='End'?'health':attendanceView==='students'?'health':'students';switchAttendanceView(view);$('#attendance-tab-'+view).focus({preventScroll:true});};
}
function attendanceBatchRows(id) {return cfoState.registrations.filter(r=>!r.is_archived&&r.year===cfoState.current_year&&String(r.recollection_schedule_id)===String(id));}

function markBatchAttendance(id, attendance) {
    if(batchSaveBusy)return;
    const r=findRegistration(id);if(!r)return;
    if(r.attendance===attendance){toast(attendanceStudentName(r)+' is already '+attendance+'.');return;}
    if(r.attendance!=='Not Yet Recorded'){
        showDialog('Confirm attendance correction',`<p>Change ${escapeHTML(r.user.name)} from ${escapeHTML(r.attendance)} to ${attendance}? Certificate availability will update automatically.</p>`,button('Cancel','close-dialog')+button('Confirm '+attendance,attendance==='Present'?'batch-confirm-present':'batch-confirm-absent',id,'primary'));
        return;
    }
    saveAttendanceQuick(id,attendance);
}

// First-time marks save without a pop-up so check-in stays fast. Corrections keep their confirmation dialog.
async function saveAttendanceQuick(id, attendance) {
    if(batchSaveBusy)return;
    const r=findRegistration(id),name=r?attendanceStudentName(r):'Student';
    batchSaveBusy=true;
    const unlock=lockBatchControls();
    try {
        await backend('/api/admin/registrations/'+id+'/attendance','POST',{attendance});
        await renderConnectedAdmin();
        toast(name+' marked '+attendance+'.');
    } catch(error) { toast(error.message); }
    finally { batchSaveBusy=false;unlock(); }
}

function attendanceRosterSort(rows) {
    const marked=r=>['Present','Absent'].includes(r.attendance)?1:0;
    return [...rows].sort((a,b)=>(attendanceUnmarkedFirst?marked(a)-marked(b):0)||attendanceStudentName(a).localeCompare(attendanceStudentName(b),undefined,{sensitivity:'base'}));
}
function attendanceAlertCell(r) {
    const health=attendanceReportedHealth(r).length>0,religion=attendanceDetail(r,'religion');
    const faith=!religion?'<small class="attendance-cell-detail">Religion not provided</small>':/catholic|^prefer not to say$/i.test(religion)?`<span class="attendance-pill neutral">${escapeHTML(religion)}</span>`:`<span class="attendance-pill pending">${escapeHTML(religion)}</span>`;
    return `<div class="attendance-alerts">${health?`<span class="attendance-pill health" title="Open View for details">${icon('heart')} Health / Allergy</span>`:''}${faith}</div>`;
}
function attendanceAlertText(r) {return [attendanceReportedHealth(r).length?'Health / Allergy alert':'',attendanceDetail(r,'religion')].filter(Boolean).join(' · ')||'—';}
function attendanceAlertSort(r) {return (attendanceReportedHealth(r).length?'0':'1')+attendanceDetail(r,'religion');}
function attendanceMarkedAt(r) {
    if(!r.attendance_verified_at||!['Present','Absent'].includes(r.attendance))return '—';
    const when=new Date(r.attendance_verified_at);
    return Number.isNaN(when.getTime())?'—':new Intl.DateTimeFormat('en-PH',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit',timeZone:'Asia/Manila'}).format(when);
}
function updateAttendanceProgress(participants) {
    const text=$('[data-attendance-progress-text]');if(!text)return;
    const total=participants.length,marked=participants.filter(r=>['Present','Absent'].includes(r.attendance)).length,percent=total?Math.round(marked/total*100):0;
    text.textContent=`${marked} of ${total} marked (${percent}%)`;
    const bar=$('[data-attendance-progress-bar]');bar.setAttribute('aria-valuenow',String(percent));bar.firstElementChild.style.width=percent+'%';
    $('[data-attendance-progress-note]').textContent=total===0?'':total-marked?`${total-marked} remaining`:'All students marked';
}
function updateAttendanceBulkBar(matchingCount) {
    const bar=$('[data-attendance-bulk]');if(!bar||!activeGrid)return;
    const selected=activeGrid.state.selected.size;
    bar.hidden=selected===0;
    $('[data-bulk-count]').textContent=selected+' selected';
    const more=$('[data-bulk-matching]');more.hidden=selected>=matchingCount;more.textContent='Select all '+matchingCount+' matching';
}
function attendanceExport() {
    if(!activeGrid)return;
    csvDownload(activeGrid.config.key+'.csv',['Student ID','Student Name','Course','Year','Section','Religion','Attendance','Marked at'],activeGrid.filtered().map(r=>{const group=attendanceAcademicGroup(r);return [r.user?.student_id||'',attendanceStudentName(r),group.course,group.year_level,group.section,attendanceDetail(r,'religion'),r.attendance,attendanceMarkedAt(r)];}));
}

function reviewSelectedAttendance(id, status) {
    if(batchSaveBusy||!activeGrid)return;
    const chosen=new Set([...activeGrid.state.selected].map(String));
    const rows=attendanceBatchRows(id).filter(r=>chosen.has(String(r.id)));
    if(!rows.length){toast('Select at least one student first.');return;}
    if(rows.some(r=>r.status!=='Approved'||!r.schedule)){toast('A selected registration still needs approval or a schedule.');return;}
    const targets=rows.filter(r=>r.attendance!==status);
    if(!targets.length){toast('Every selected student is already '+status+'.');return;}
    const corrections=targets.filter(r=>r.attendance!=='Not Yet Recorded').length;
    selectedReview={id,status,states:rows.map(r=>({id:r.id,attendance:r.attendance}))};
    showDialog('Mark selected '+status,`<p>Mark ${targets.length} selected student${targets.length===1?'':'s'} as ${status}?</p>${corrections?`<p><strong>${corrections} recorded result${corrections===1?'':'s'} will be corrected to ${status}.</strong> Certificate availability will update automatically.</p>`:''}${status==='Absent'?'<p>Each student receives a notification. For the current year, the Absent notice tells the student their registration form is open again.</p>':''}`,button('Cancel','close-dialog')+button('Confirm '+status,'batch-selected-confirm',id,'primary'));
}

function reviewRemainingAbsent(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id),pending=rows.filter(r=>!['Present','Absent'].includes(r.attendance));
    if(!pending.length){toast('Every student already has recorded attendance.');return;}
    if(rows.some(r=>r.status!=='Approved'||!r.schedule)){toast('This batch contains registrations that still need approval.');return;}
    selectedReview={id,status:'Absent',states:pending.map(r=>({id:r.id,attendance:r.attendance}))};
    showDialog('Mark remaining as Absent',`<p>Mark the ${pending.length} student${pending.length===1?'':'s'} who are not yet recorded as <strong>Absent</strong>? Students who are already Present or Absent stay unchanged. This includes students hidden by table filters.</p><p>Each student receives a notification. For the current year, the Absent notice tells the student their registration form is open again.</p>`,button('Cancel','close-dialog')+button('Confirm mark remaining Absent','batch-selected-confirm',id,'danger'));
}

async function saveSelectedAttendance(id) {
    if(batchSaveBusy||!selectedReview||String(selectedReview.id)!==String(id))return;
    const review=selectedReview;selectedReview=null;
    batchSaveBusy=true;
    showDialog('Saving attendance','<p id="batch-progress" role="status" aria-live="polite">Checking the latest batch before saving…</p>','');
    const unlock=lockBatchControls();
    let saved=0,total=0,failure=null;
    try {
        cfoState={...cfoState,...await backend('/api/admin/state')};
        const before=new Map(review.states.map(s=>[String(s.id),s.attendance]));
        const rows=attendanceBatchRows(id).filter(r=>before.has(String(r.id)));
        if(rows.length!==before.size||rows.some(r=>before.get(String(r.id))!==r.attendance||r.status!=='Approved'||!r.schedule))throw Error('The batch changed. Review the refreshed list and try again.');
        const list=rows.filter(r=>r.attendance!==review.status);
        total=list.length;
        for(const r of list) {
            $('#batch-progress').textContent=`Saving ${saved+1} of ${total}: ${attendanceStudentName(r)}…`;
            try {await backend('/api/admin/registrations/'+r.id+'/attendance','POST',{attendance:review.status,...(r.attendance!=='Not Yet Recorded'?{confirm_edit:true}:{})});saved++;}
            catch(error){failure={name:attendanceStudentName(r),message:error.message};break;}
        }
    } catch(error){failure={message:error.message};}
    finally {
        try {
            closeDialog();await renderConnectedAdmin();
            if(activeGrid&&!failure){activeGrid.state.selected.clear();activeGrid.draw();}
            const remaining=Math.max(0,total-saved-(failure?.name?1:0));
            const message=failure?`<p role="alert">${saved} of ${total} saves confirmed. ${failure.name?'Failed student: '+escapeHTML(failure.name)+'. ':''}${escapeHTML(failure.message)}</p><p>${remaining} records were not attempted. Previously saved attendance is retained. Review the latest batch before retrying.</p>`:`<p role="status">${saved} attendance record${saved===1?'':'s'} saved as ${escapeHTML(review.status)}.</p>`;
            showDialog(failure?'Attendance needs attention':'Attendance saved',message,button('Done','close-dialog'));
        } finally {batchSaveBusy=false;unlock();}
    }
}

function connectedAttendanceEdit(id) {
    if(batchSaveBusy)return;
    const r=findRegistration(id);if(!r)return;
    showDialog('Correct attendance',`<p>${escapeHTML(r.user.name)} · Currently ${escapeHTML(r.attendance)}. The certificate updates automatically.</p>`,button('Present','batch-confirm-present',id,'primary')+button('Absent','batch-confirm-absent',id,'danger')+button('Cancel','close-dialog'));
}

let archiveReview=null;
function reviewBatchArchive(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id);
    if(!rows.length || rows.some(r=>!['Present','Absent'].includes(r.attendance))){showDialog('Attendance incomplete','<p>Mark every student Present or Absent before moving this batch to History.</p>',button('Close','close-dialog'));return;}
    archiveReview={id,registration_ids:rows.map(r=>r.id)};
    showDialog('Move this Recollection to History','<p><strong>Sigurado ka bang ilipat ang students sa History?</strong></p><p>'+rows.length+' students will leave Attendance. Their complete records and certificates remain available in History.</p>',button('Cancel','close-dialog')+button('Move to History','batch-archive-confirm',id,'primary'));
}

let presentReview=null,completionReview=null;
function reviewBatchPresent(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id);
    if(!rows.length || rows.some(r=>r.status!=='Approved' || !r.schedule)){toast('This batch has no eligible students, or contains registrations that still need approval.');return;}
    const targets=rows.filter(r=>r.attendance!=='Present');
    if(!targets.length){toast('Every student in this batch is already Present.');return;}
    presentReview={id,states:rows.map(r=>({id:r.id,attendance:r.attendance}))};
    const corrections=targets.filter(r=>r.attendance!=='Not Yet Recorded').length;
    showDialog('Mark All Present',`<p>Mark ${targets.length} students in this entire schedule Present? This includes students hidden by table filters. ${rows.length-targets.length} already Present will stay unchanged.</p>${corrections?`<p><strong>${corrections} recorded attendance result${corrections===1?'':'s'} will be corrected to Present.</strong> Certificate availability will update automatically.</p>`:''}<p>Each record is saved to the existing attendance API. If a save fails, processing stops and the result will identify the saved, failed, and unattempted records.</p>`,button('Cancel','close-dialog')+button('Confirm Mark All Present','batch-present-all-confirm',id,'primary'));
}

async function saveBatchPresent(id) {
    if(batchSaveBusy || !presentReview || String(presentReview.id)!==String(id))return;
    const review=presentReview;presentReview=null;
    batchSaveBusy=true;
    showDialog('Saving batch attendance','<p id="batch-progress" role="status" aria-live="polite">Checking the latest batch before saving…</p>','');
    const unlock=lockBatchControls();
    let saved=0,total=review.states.filter(r=>r.attendance!=='Present').length,failure=null;
    try {
        cfoState={...cfoState,...await backend('/api/admin/state')};
        const rows=attendanceBatchRows(id);
        const before=new Map(review.states.map(r=>[String(r.id),r.attendance]));
        if(rows.length!==before.size || rows.some(r=>!before.has(String(r.id)) || before.get(String(r.id))!==r.attendance || r.status!=='Approved' || !r.schedule))throw Error('Batch membership or attendance changed. Review the refreshed batch and confirm again.');
        const targets=rows.filter(r=>r.attendance!=='Present');total=targets.length;
        for(const r of targets) {
            $('#batch-progress').textContent=`Saving ${saved+1} of ${total}: ${attendanceStudentName(r)}…`;
            try {await backend('/api/admin/registrations/'+r.id+'/attendance','POST',{attendance:'Present',...(r.attendance!=='Not Yet Recorded'?{confirm_edit:true}:{})});saved++;}
            catch(error){failure={name:attendanceStudentName(r),message:error.message};break;}
        }
    } catch(error){failure={message:error.message};}
    finally {
        try {
            closeDialog();await renderConnectedAdmin();
            const remaining=Math.max(0,total-saved-(failure?.name?1:0));
            const message=failure?`<p role="alert">${saved} of ${total} saves confirmed. ${failure.name?'Failed student: '+escapeHTML(failure.name)+'. ':''}${escapeHTML(failure.message)}</p><p>${remaining} records were not attempted. Previously saved attendance is retained. Review the latest batch before retrying.</p>`:`<p role="status">${saved} attendance record${saved===1?'':'s'} saved as Present. Review the refreshed batch before completing attendance.</p>`;
            showDialog(failure?'Batch attendance needs attention':'Batch attendance saved',message,button('Done','close-dialog'));
        } finally {batchSaveBusy=false;unlock();}
    }
}

function reviewBatchCompletion(id) {
    if(batchSaveBusy)return;
    const rows=attendanceBatchRows(id);
    if(!rows.length || rows.some(r=>!['Present','Absent'].includes(r.attendance))){
        const pending=rows.filter(r=>!['Present','Absent'].includes(r.attendance));
        showDialog('Attendance incomplete',`<p>${pending.length?pending.length+' student'+(pending.length===1?' is':'s are')+' not marked yet. ':''}Mark every student Present or Absent before completing this batch. Table filters do not limit this check.</p>${pending.length?'<p>If everyone who did not attend should be Absent, you can mark all remaining students Absent now.</p>':''}`,button('Close','close-dialog')+(pending.length?button('Mark remaining as Absent','batch-absent-remaining',id,'danger'):''));
        return;
    }
    completionReview={id,registration_ids:rows.map(r=>r.id)};
    showDialog('Complete Attendance',`<p>Confirm that attendance for all ${rows.length} students in this schedule has been reviewed? We will check the latest saved batch before confirming completion.</p><p>This check keeps the batch in Attendance. Move to Recollection History is a separate confirmed action.</p>`,button('Cancel','close-dialog')+button('Confirm completion','batch-complete-confirm',id,'primary'));
}

async function confirmBatchCompletion(id) {
    if(batchSaveBusy || !completionReview || String(completionReview.id)!==String(id))return;
    const review=completionReview;completionReview=null;
    batchSaveBusy=true;
    showDialog('Checking attendance','<p role="status">Checking every student against the latest saved batch…</p>','');
    const unlock=lockBatchControls();
    try {
        cfoState={...cfoState,...await backend('/api/admin/state')};
        const rows=attendanceBatchRows(id),expected=new Set(review.registration_ids.map(String));
        if(routeDetails().route==='admin-student-attendance'){connectedAttendance();syncAdminSearch();hydrateIcons();}
        if(rows.length!==expected.size || rows.some(r=>!expected.has(String(r.id))))throw Error('Batch membership changed. Review the refreshed batch before completing attendance.');
        if(!rows.length || rows.some(r=>!['Present','Absent'].includes(r.attendance)))throw Error('Attendance is incomplete. Mark every student Present or Absent.');
        showDialog('Attendance complete',`<p role="status">All ${rows.length} students have recorded attendance. Their attendance and certificate results are saved. You may now review moving this batch to History.</p>`,button('Keep in Attendance','close-dialog')+button('Move to Recollection History','batch-archive',id,'primary'));
    } catch(error) {showDialog('Attendance needs review',`<p role="alert">${escapeHTML(error.message)}</p>`,button('Close','close-dialog'));}
    finally {batchSaveBusy=false;unlock();}
}

Object.assign(actions,{
    'attendance-view':switchAttendanceView,
    'attendance-information':attendanceInformationView,
    'batch-present':id=>markBatchAttendance(id,'Present'),
    'batch-absent':id=>markBatchAttendance(id,'Absent'),
    'batch-confirm-present':id=>saveBatchAction('/api/admin/registrations/'+id+'/attendance',{attendance:'Present',confirm_edit:true},'Attendance saved'),
    'batch-confirm-absent':id=>saveBatchAction('/api/admin/registrations/'+id+'/attendance',{attendance:'Absent',confirm_edit:true},'Attendance saved'),
    'attendance-unmarked-first':()=>{if(batchSaveBusy)return;attendanceUnmarkedFirst=!attendanceUnmarkedFirst;if(activeGrid){activeGrid.state.sortIndex=undefined;activeGrid.state.page=1;}connectedAttendance();hydrateIcons();},
    'attendance-export':attendanceExport,
    'batch-selected-present':id=>reviewSelectedAttendance(id,'Present'),
    'batch-selected-absent':id=>reviewSelectedAttendance(id,'Absent'),
    'batch-selected-confirm':saveSelectedAttendance,
    'batch-selected-clear':()=>{if(!activeGrid)return;activeGrid.state.selected.clear();activeGrid.draw();},
    'batch-select-matching':()=>{if(!activeGrid)return;activeGrid.filtered().forEach(r=>activeGrid.state.selected.add(String(r.id)));activeGrid.draw();},
    'batch-absent-remaining':reviewRemainingAbsent,
    'health-present-only':toggleHealthPresentOnly,
    'health-print':printHealthAlerts,
    'health-export':exportHealthRegistrations,
    'batch-present-all':reviewBatchPresent,
    'batch-present-all-confirm':saveBatchPresent,
    'batch-complete':reviewBatchCompletion,
    'batch-complete-confirm':confirmBatchCompletion,
    'batch-archive':reviewBatchArchive,
    'batch-archive-confirm':id=>{if(archiveReview && String(archiveReview.id)===String(id))return saveBatchAction('/api/admin/schedules/'+id+'/archive',{confirmed:true,registration_ids:archiveReview.registration_ids},'Batch moved to History');},
});
