// Decision concurrency and validation using the real admin recovery functions.
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');
const error = {textContent:''}, controls = [{disabled:false}, {disabled:false}];
let requests = [], release, fail = false, refreshes = 0, notices = [];
let grid, renderedDialog;
const form = {elements:{identity_verified:{checked:false}}, querySelector:()=>error};
const context = vm.createContext({
    Date, Number, Boolean, String,
    $:selector=>selector === '#recovery-review' ? form : {classList:{add(){}},textContent:'',innerHTML:''},
    connectedGrid:config=>{grid=config;},
    metric:()=>'', icon:()=>'', details:()=>'', badge:value=>value, escapeHTML:value=>String(value ?? ''),
    showDialog:(title,body,footer)=>{renderedDialog={title,body,footer};},
    clearTimeout(){}, setTimeout(){return 1;},
    cfoState:{resets:[]}, dialog:{querySelectorAll:()=>controls},
    closeDialog(){}, renderConnectedAdmin:async()=>{refreshes++;}, toast:message=>notices.push(message),
    backend:async(url,method,body)=>{
        requests.push({url,method,body});
        if (fail) throw new Error('This recovery request is no longer pending.');
        return new Promise(resolve=>{release=resolve;});
    }
});
const adminSource=fs.readFileSync(path.join(__dirname,'../../public/admin-dashboard.js'),'utf8');
const recoveryStart=adminSource.indexOf('    // Included inside the admin script to share the existing grid, dialogs and live updates.');
const recoveryEnd=adminSource.indexOf('    function connectedActivity()',recoveryStart);
assert.ok(recoveryStart>=0 && recoveryEnd>recoveryStart,'The served dashboard must contain the recovery functions.');
vm.runInContext(adminSource.slice(recoveryStart,recoveryEnd),context);
vm.runInContext(adminSource.slice(adminSource.indexOf('    function button('),adminSource.indexOf('    function link(')),context);

(async()=>{
    context.cfoState.users=[{id:99,name:'Student with no recovery request',role:'Student',status:'Approved'}];
    for(const view of ['Pending','All','History']){
        vm.runInContext("recoveryView = '"+view+"'",context);
        context.connectedRecovery();
        assert.equal(grid.rows().length,0,'Accounts without password reset requests stay out of every recovery view.');
        assert.equal(grid.title,view==='Pending'?'Students awaiting approval':'Password reset requests');
    }
    vm.runInContext("recoveryView = 'Pending'",context);
    context.cfoState.resets=[{id:7,status:'Pending',created_at:new Date(Date.now()-7*86400000).toISOString(),user:{name:'Student'}}];
    context.connectedRecovery();
    assert.deepEqual(Array.from(grid.rows(),row=>row.id),[7],'Only submitted recovery requests appear alongside unrelated student accounts.');
    const rowActions=grid.rowActions(grid.rows()[0]);
    assert.match(rowActions,/Review request/);
    assert.doesNotMatch(rowActions,/Approve/, 'the queue must not duplicate the approval button');
    context.connectedRecoveryReview(7);
    assert.equal((renderedDialog.body.match(/>Approve request<\/button>/g)||[]).length,1);
    assert.equal(renderedDialog.footer,'');
    assert.match(renderedDialog.body,/name="identity_verified" required/);
    assert.equal(typeof form.onsubmit,'function');
    context.connectedRecoveryDecision(7,'Rejected');
    assert.match(renderedDialog.body,/>Reject request<\/button>/);
    context.connectedRecoveryReview(7);
    await context.submitRecoveryDecision(form,7,'Approved');
    assert.equal(requests.length,0);
    assert.match(error.textContent,/identity verification/);
    form.elements.identity_verified.checked=true;
    const approval=context.submitRecoveryDecision(form,7,'Approved');
    assert.equal(controls.every(control=>control.disabled),true);
    await context.submitRecoveryDecision(form,7,'Approved');
    assert.equal(requests.length,1,'double click must not submit a second decision');
    assert.equal(requests[0].body.identity_verified,true);
    assert.equal(requests[0].body.status,'Approved');
    release({});await approval;
    assert.equal(refreshes,1);
    assert.equal(controls.every(control=>!control.disabled),true);

    form.elements={};
    const rejection=context.submitRecoveryDecision(form,8,'Rejected');
    assert.equal(requests[1].body.status,'Rejected');
    assert.equal('identity_verified' in requests[1].body,false);
    release({});await rejection;
    fail=true;
    await context.submitRecoveryDecision(form,8,'Rejected');
    assert.match(error.textContent,/no longer pending/);
    assert.equal(controls.every(control=>!control.disabled),true);
    assert.equal(refreshes,2,'failed decisions leave the dialog available for review');

    const expired={id:9,status:'Expired',created_at:new Date(Date.now()-3601000).toISOString()};
    context.cfoState.resets=[expired];
    assert.equal(context.recoveryStatus(expired),'Expired');
    vm.runInContext("recoveryView = 'History'",context);context.connectedRecovery();
    assert.equal(grid.columns.find(c=>c.label==='Status').get(grid.rows()[0]),'Expired','CSV must match the displayed effective status');
    assert.equal(grid.columns.find(c=>c.label==='Account').get({user:{original_email:'student@gmail.com',login_email:'student@cfo.office.com'}}),'student@gmail.com / student@cfo.office.com');
    vm.runInContext("recoveryView = 'Pending'",context);
    context.connectedRecoveryDecision(9,'Approved');
    assert.match(notices.at(-1),/no longer pending/);
    assert.equal(requests.length,3,'expired requests never reach the decision endpoint');
    const approved={status:'Approved',expires_at:new Date(Date.now()-1000).toISOString()};
    assert.equal(context.recoveryStatus(approved),'Approved');
    assert.equal(context.recoveryStatus({status:'Verified',verified_at:new Date(Date.now()-7*86400000).toISOString()}),'Verified');
    assert.equal(context.recoveryStatus({status:'Completed'}),'Completed');
    const cancelled={id:10,status:'Cancelled',created_at:new Date().toISOString()};
    context.cfoState.resets=[cancelled];
    context.connectedRecovery();
    assert.equal(grid.rows().length,0,'successful login removes the request from the approval queue');
    assert.match(context.recoveryHelp('Cancelled'),/successful login/);
    vm.runInContext("recoveryView = 'History'",context);
    context.connectedRecovery();
    assert.equal(grid.rows()[0].id,10,'cancelled requests retain their audit history');
    assert.doesNotMatch(grid.rowActions(grid.rows()[0]),/Approve|Reject/);
    assert.doesNotMatch(adminSource,/connected-reset-password|admin-temp-password/,'no redundant reset controls in the student editor');
    console.log('PASS: identity confirmation, duplicate-submit guard, reject payload, stale decisions and requests without expiry.');
})().catch(error=>{console.error(error);process.exitCode=1;});
