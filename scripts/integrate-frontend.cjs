const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
process.chdir(root);
const names = ['admin.blade.php', 'student.blade.php', 'login-create-forgotpassword.blade.php'];
for (const name of names) {
  const target = path.join('resources/views', name);
  if (fs.existsSync(name) && !fs.existsSync(target)) fs.renameSync(name, target);
}
let admin = fs.readFileSync('resources/views/admin.blade.php', 'utf8').replace(/\r/g, '');
let student = fs.readFileSync('resources/views/student.blade.php', 'utf8').replace(/\r/g, '');
let auth = fs.readFileSync('resources/views/login-create-forgotpassword.blade.php', 'utf8').replace(/\r/g, '');
const section = (text, start, end, replacement) => {
  const a = text.indexOf(start), b = text.indexOf(end, a + start.length);
  if (a < 0 || b < 0) throw new Error('Missing markers: ' + start + ' / ' + end);
  return text.slice(0,a) + replacement + '\n' + text.slice(b);
};
const replace = (text, from, to) => {
  if (!text.includes(from)) throw new Error('Missing replacement: '+from.slice(0,100));
  return text.replace(from,to);
};
// Use the shared registration artwork in generated authentication views.
const registrationImage = "{{ asset('images/registration-reference.webp') }}";
auth = auth.replace('window.CFO_BACKEND = @json($cfoBackend ?? null);', 'window.CFO_BACKEND = {{ Illuminate\\Support\\Js::from($cfoBackend) }};');
auth = auth.replace("location.href = result.user.role === 'Admin' ? '/admin-panel.html' : '/#dashboard';\n        if (result.user.role === 'Student') route();", 'location.href = result.redirect;');
// Works for either CRLF or LF source.
auth = auth.replace(/location\.href = result\.user\.role === 'Admin' \? '\/admin-panel\.html' : '\/#dashboard';\s*if \(result\.user\.role === 'Student'\) route\(\);/, 'location.href = result.redirect;');
auth = auth.replace(/document\.querySelector\('#google-login'\)\.onclick = \(\) => \{[\s\S]*?\n  \};/, "document.querySelector('#google-login').disabled = true; document.querySelector('#google-login').textContent = 'Google sign-in is not configured';");
auth = section(auth, 'function forgot() {', 'function setResetStep', `function forgot() {
 document.body.classList.add('registration-mode');
 const step=resetState.step;
 const labels=['Verify Account','Enter Temporary Password','Create New Password'];
 let content;
 if(step===1) content=field('reset-email','Registered Email Address','Enter your registered email','email','mail',resetState.email)+reminder(true)+resetActions();
 else if(step===2) content=\`<p>Visit the CFO for identity verification. Keep this browser open. After approval, check your request here.</p><button type="button" class="outline" id="check-recovery">Check approval status</button><p id="recovery-status" role="status"></p>\${resetCredential('reset-generated','Temporary Password',resetState.temporaryPassword,'key',true)}<p id="recovery-countdown" role="status"></p>\${field('reset-temporary','Enter Temporary Password','Enter the password shown after approval','password','key')}\${resetActions()}\`;
 else content=field('reset-new','New Password','8+ characters, upper/lower case, number and symbol','password','lock')+field('reset-confirm','Confirm New Password','Repeat your new password','password','lock')+resetActions(true);
 app.innerHTML=\`<section class="registration-shell forgot-shell"><aside class="brand-panel"><div class="brand-crop"><img src="${registrationImage}" alt="Christian Formation Office logo and campus chapel"></div></aside><section class="registration-main"><nav class="topbar"><a class="back-link" href="#login">\${icon('left')}Back to Login</a></nav><header class="register-heading"><h1 id="page-title" tabindex="-1">Reset Password</h1><p>Office-verified account recovery</p></header><ol class="steps">\${labels.map((label,i)=>\`<li class="\${i+1===step?'active':''}"><span class="step-number">\${i+1}</span>\${label}</li>\`).join('')}</ol><form id="recovery-form">\${content}</form></section></section>\`;
 document.querySelector('#reset-back').onclick=()=>{if(step===1)location.hash='login';else if(step===2)setResetStep(1);else showNotice('Recovery verified','Complete your new password or return to Login.');};
 if(step===2){
  document.querySelector('#check-recovery').onclick=async()=>{try{const result=await cfoRequest('/api/recovery');document.querySelector('#recovery-status').textContent=result.status==='Pending'?'Awaiting office verification.':result.status;if(result.temporary_password)resetState.temporaryPassword=result.temporary_password;document.querySelector('#reset-generated').value=resetState.temporaryPassword;if(result.expires_at){clearInterval(window.recoveryTimer);const tick=()=>{const seconds=Math.max(0,Math.ceil((new Date(result.expires_at)-Date.now())/1000));const label=document.querySelector('#recovery-countdown');if(label)label.textContent=seconds?'Expires in '+seconds+' seconds.':'Temporary password expired. Request a new recovery after the current request window closes.';else clearInterval(window.recoveryTimer);};tick();window.recoveryTimer=setInterval(tick,1000);}}catch(error){showNotice('Recovery',error.message);}};
  document.querySelector('[data-copy]').onclick=async()=>{try{await navigator.clipboard.writeText(document.querySelector('#reset-generated').value);}catch{document.querySelector('#reset-generated').select();}};
 }
 document.querySelector('#recovery-form').onsubmit=event=>{event.preventDefault();cfoSubmit(event.target,async()=>{
  if(step===1){resetState.email=document.querySelector('#reset-email').value;const result=await cfoRequest('/api/forgot-password','POST',{email:resetState.email});setResetStep(2);showNotice('Password recovery',result.message);}
  else if(step===2){await cfoRequest('/api/recovery/verify','POST',{temporary_password:document.querySelector('#reset-temporary').value});resetState.temporaryPassword='';clearInterval(window.recoveryTimer);setResetStep(3);}
  else {const result=await cfoRequest('/api/reset-password','POST',{password:document.querySelector('#reset-new').value,password_confirmation:document.querySelector('#reset-confirm').value});clearReset();location.hash='login';setTimeout(()=>showNotice('Password changed',result.message),0);}
 });};bindReveal();
}
`);
auth = section(auth, 'function cfoForgotPassword()', '// Authentication-only router', '');

admin = admin.replace("'use strict';", "'use strict';\n    window.CFO_BACKEND = {{ Illuminate\\Support\\Js::from($cfoBackend) }};");
admin = section(admin, '    /* 08. Original sample data', '    /* 09. Shared UI helpers', '');
admin = section(admin, '    function renderRoute() {', '        /* 12. Page layouts', '    function renderRoute() { return renderConnectedAdmin(); }\n');
admin = section(admin, '    function dashboard(){', '    /* Laravel mode:', `    const actions = {'close-dialog':closeDialog, 'profile':()=>go('admin-settings')};
    document.querySelector('.skip-link').addEventListener('click',event=>{event.preventDefault();$('#main-content').focus();$('#main-content').scrollIntoView();});
    document.addEventListener('click',event=>{const control=event.target.closest('[data-action]');if(control){const action=actions[control.dataset.action];if(action)Promise.resolve(action(control.dataset.id)).catch(error=>toast(error.message));}});
    $('#sidebar').addEventListener('click',event=>{const anchor=event.target.closest('a[href]');if(anchor && anchor.hash===location.hash){event.preventDefault();renderRoute();}});
`);
admin = admin.replace(' · SAMPLE / PREVIEW', '');
admin = admin.replace("'admin-student-reciept':['Payments & receipts'", "'admin-student-reciept':['Receipt Verification'");
admin = admin.replace("'login':['Preview closed'", "'admin-password-resets':['Password recovery','Verify identity at the office.','Office','lock'],\n      'admin-activity':['Activity log','Recorded office actions.','Office','history'],\n      'login':['Signed out'");
admin = admin.replace("['Office',['admin-settings']]", "['Office',['admin-password-resets','admin-activity','admin-settings']]");
admin = admin.replace("else if(['admin-all-student','admin-course-year'].includes(route)) connectedDirectory();", "else if(route==='admin-all-student') connectedDirectory();\n        else if(route==='admin-course-year') connectedAcademic();");
admin = admin.replace("else if(['admin-certificate','admin-certificate-template'].includes(route)) $('#page').innerHTML=empty('Certificate issuance is not configured','Attendance is saved. An official certificate template and issuance workflow still need to be supplied by the CFO.');", "else if(route==='admin-certificate') connectedCertificates();\n        else if(route==='admin-certificate-template') connectedTemplate();\n        else if(route==='admin-password-resets') connectedRecovery();\n        else if(route==='admin-activity') connectedActivity();");
admin = admin.replace("const response = await fetch(url, {method, credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':window.CFO_BACKEND.csrf_token}, ...(body?{body:JSON.stringify(body)}:{})});", "const multipart=body instanceof FormData; const response = await fetch(url, {method, credentials:'same-origin', headers:{Accept:'application/json',...(!multipart?{'Content-Type':'application/json'}:{}),'X-CSRF-TOKEN':window.CFO_BACKEND.csrf_token}, ...(body?{body:multipart?body:JSON.stringify(body)}:{})});");
admin = admin.replace("try { await backend(url,method,values);", "try { const result=await backend(url,method,values); if(result.user)window.CFO_BACKEND.user=result.user;");
admin = admin.replace("['Pending','Approved','Inactive']", "['Pending','Approved','Rejected','Inactive']");
admin = admin.replace("r.status)}</div>${note('Approve", "r.status)}${field('Rejection reason (required when rejecting)','rejection_reason',r.rejection_reason||'')}</div>${note('Approve");
admin = admin.replaceAll('String(r.schedule.year)', 'String(r.year)');
admin = admin.replace("${field('Time','time',r?.time||'','text','required')}", "${field('Start time','start_time',r?.start_time||'08:00','time','required')}${field('End time','end_time',r?.end_time||'16:00','time','required')}${field('Maximum capacity','capacity',r?.capacity||100,'number','required min=1')}");
admin = admin.replace('Only one university-wide recollection day can be saved for each calendar year.', 'Students are assigned by the CFO after registration and receipt approval. Capacity is checked on every assignment.');
admin = admin.replace('One recollection day per year', 'Recollection schedules');
admin = admin.replace("get:r=>r.registrations_count", "get:r=>r.registrations_count+'/'+r.capacity");
admin = admin.replace("rowActions:r=>link('Edit','admin-add-new-recollection-schedule?id='+r.id,'compact')", "rowActions:r=>link('Edit','admin-add-new-recollection-schedule?id='+r.id,'compact')+button('Delete','connected-delete-schedule',r.id,'compact danger')");
admin = admin.replace("['Pending','Approved','Rejected'],r.receipt_status)}", "['Pending','Approved','Rejected'],r.receipt_status)}${field('Rejection reason','receipt_reason',r.receipt_reason||'')}");
admin = admin.replace('<p><a class="btn" href="/api/receipts/${r.id}"', '<p><img src="/api/receipts/${r.id}" alt="Uploaded receipt" style="max-width:100%;max-height:65vh;object-fit:contain"></p><p><a class="btn" href="/api/receipts/${r.id}"');
admin = admin.replace("const rows=cfoState.registrations;\n      connectedGrid({key:'connected-attendance'", "const rows=cfoState.registrations.filter(r=>r.schedule);\n      connectedGrid({key:'connected-attendance'");
admin = admin.replace("const rows=cfoState.registrations;\n      connectedGrid({key:'connected-attendance'", "const rows=cfoState.registrations.filter(r=>r.schedule);\n      connectedGrid({key:'connected-attendance'");
admin = admin.replace("key:'connected-attendance',title:'Student attendance and lists',rows:()=>rows,filters:[", "key:'connected-attendance',title:'Student attendance and lists',rows:()=>rows,filters:[{key:'schedule',label:'Schedule date / title',values:unique(rows.map(connectedSchedule)),get:connectedSchedule},");
admin = admin.replace("['Not Yet Recorded','Present','Absent'],r.attendance)}<div", "['Not Yet Recorded','Present','Absent'],r.attendance)}<label class=\"check-label\"><input type=\"checkbox\" name=\"confirm_edit\"> Confirm changes to previously recorded attendance</label><label class=\"check-label\"><input type=\"checkbox\" name=\"verify_attendance\"> Verify this attendance</label><div");
admin = admin.replace("saveConnected('/api/admin/registrations/'+id,'PATCH',Object.fromEntries(new FormData(e.target)));};\n    }\n    function connectedRequests", "const values=Object.fromEntries(new FormData(e.target));values.confirm_edit=e.target.elements.confirm_edit.checked;values.verify_attendance=e.target.elements.verify_attendance.checked;saveConnected('/api/admin/registrations/'+id,'PATCH',values);};\n    }\n    function connectedRequests");
admin = admin.replace("'connected-registration':id=>{const r=cfoState.registrations.find(r=>String(r.id)===id);showDialog('Registration',details([['Student',r.user.name],['Schedule',connectedSchedule(r)],['Receipt',r.receipt_status],['Attendance',r.attendance]]));}", "'connected-registration':connectedRegistration");
admin = admin.replace("'notifications':()=>showDialog('Notifications','<p>Live notification delivery has not been configured.</p>')", "'notifications':()=>showDialog('Notifications',cfoState.notifications.map(n=>'<p>'+escapeHTML(n.message)+'<br><small>'+escapeHTML(n.created_at)+'</small></p>').join('')||'<p>No notifications.</p>')");
admin = admin.replace('    hydrateIcons();syncNavigation();', fs.readFileSync('scripts/admin-extension.txt','utf8')+'\n    hydrateIcons();syncNavigation();');

// The student file uses numbered {{0}} JavaScript template slots: protect them from Blade.
student = student.replace('<head>', '<head>\n<meta name="cfo-backend" content="{{ json_encode($cfoBackend) }}">\n@verbatim');
student = student.replace('</html>', '</html>\n@endverbatim');
student = section(student, 'function login() {', '/* 7.3.6 — Dashboard', '');
student = student.replaceAll('location.href = location.pathname + "#login";', 'location.href = "/login";');
student = student.replaceAll('location.hash = "login";', 'location.href = "/login";');
student = student.replace('const registration = cfoStudent.registrations[0];', 'const registration = cfoStudent.registrations.find(r=>r.year===new Date().getFullYear());');
student = student.replace(/const schedule =\s*registration\?\.schedule \|\|\s*cfoStudent\.schedules\.find\([^;]+;/, 'const schedule = registration?.schedule;');
student = student.replace('registration ? "Registered" : "Not registered",', 'registration ? registration.status+" · Receipt: "+registration.receipt_status : "Not registered",');
student = student.replace('"Not issued",', 'registration?.certificate ? "Available" : "Not issued",');
student = student.replace('app.querySelector(".dash-notifications span").remove();', 'app.querySelector(".dash-notifications span")?.remove();');
student = student.replace('notices.notifications = ["Notifications", "Live notification delivery has not been configured."];', 'notices.notifications = ["Notifications", cfoStudent.notifications.map(n=>n.message).join("\\n")||"No notifications."];');
student = student.replace('    const today = new Date();', '    const today = new Date();');
student = section(student, '  if (window.CFO_BACKEND) {\n    const today = new Date();', '  form.addEventListener("input"', `  if (window.CFO_BACKEND) {
    root.querySelector('.recollection-local-note').textContent='Your registration is for the current year. The CFO assigns your schedule after approval.';
    if(!second){const previous=cfoStudent.registrations.find(r=>r.year<new Date().getFullYear());if(previous){const reuse=document.createElement('button');reuse.type='button';reuse.className='outline';reuse.textContent='Use Previous Information';form.prepend(reuse);reuse.onclick=()=>{recollectionState.values={...previous.details,participation:false,photos:false};renderRecollectionStep();};}}
  }
`);
student = student.replace('        payload.append("schedule_id", recollectionState.scheduleId);', '');
student = student.replace('const registrations = cfoStudent.registrations.filter((r) => r.attendance !== "Present");', 'const registrations = cfoStudent.registrations.filter((r) => r.schedule && r.attendance === "Not Yet Recorded");');
student = student.replaceAll('escapeHTML(r.schedule.date)', 'escapeHTML(r.schedule?.date || r.year)');
student = student.replaceAll('escapeHTML(r.schedule.time)', 'escapeHTML(r.schedule?.time || "Awaiting assignment")');
student = student.replaceAll('escapeHTML(r.schedule.venue)', 'escapeHTML(r.schedule?.venue || "—")');
student = section(student, '  if (window.CFO_BACKEND) {\n    dashboard({\n      active: "certificate",', '  paths.award =', `  if (window.CFO_BACKEND) { connectedStudentCertificates(); return; }
`);
student = section(student, 'const certificateReferenceImage =', 'function studentMyProfile()', `function studentCertificate() { connectedStudentCertificates(); }

`);
student = student.replace('if (location.hash !== "#forgot-password") clearReset();', '');
student = student.replace('    registration();', '    location.href="/login#register";');
student = student.replace('    forgot();', '    location.href="/login#forgot-password";');
student = student.replace('    login();', '    location.hash="dashboard"; renderedRoute=null; route();');
student = student.replace('if (!location.hash && !window.CFO_BACKEND) history.replaceState(null, "", "#dashboard");', 'if (!location.hash) history.replaceState(null, "", "#dashboard");');
student = student.replace('        await cfoRequest("/api/profile", "PUT", {', '        const result = await cfoRequest("/api/profile", "PUT", {');
student = student.replace('        studentMyProfile();\n        showNotice("Profile saved"', '        window.CFO_BACKEND.user=result.user;\n        studentMyProfile();\n        showNotice("Profile saved"');
student = student.replace('window.addEventListener("hashchange", route);', fs.readFileSync('scripts/student-extension.txt','utf8')+'\nwindow.addEventListener("hashchange", route);');
fs.writeFileSync('resources/views/admin.blade.php', admin);
fs.writeFileSync('resources/views/student.blade.php', student);
fs.writeFileSync('resources/views/login-create-forgotpassword.blade.php', auth);
console.log('Integrated existing Blade views.');
