// Exercise the real notification state functions without a browser or external dependency.
// This checks async races and editing guards; it is not visual browser acceptance.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/student/index.blade.php'),'utf8');
function between(start,end){const a=source.indexOf(start),b=source.indexOf(end,a);assert.ok(a>=0&&b>a);return source.slice(a,b);}
const badge={},button={setAttribute(){}}, state={dirty:true,panelOpen:false,routeCalls:0,requests:[],release:null};
const context=vm.createContext({
  Set, Date, Number, String, Boolean, document:{hidden:false,activeElement:null,querySelectorAll:()=>[],querySelector:selector=>{
    if(selector==='#dashboard-notifications')return button;
    if(selector==='#student-notification-count')return badge;
    if(selector==='form[data-dirty="true"]')return state.dirty?{}:null;
    if(selector==='#student-notification-panel:not([hidden])')return state.panelOpen?{}:null;
    return null;
  }},window:{CFO_BACKEND:{}},location:{hash:'#dashboard'},cfoStudent:{notifications:[]},renderStudentNotifications(){},
  studentRefreshBusy:false,pendingStudentResources:new Set(['notifications','registrations']),
  useLiveStudentState:false,renderedRoute:null,studentRouteLoading:false,routeVersion:0,
  route:async()=>{state.routeCalls++;},
  cfoRequest:async(url,method,data)=>{
    state.requests.push({url,method,data});
    if(method==='POST')return new Promise(resolve=>{state.release=resolve;});
    if(url.endsWith('resources=notifications'))return {notifications:[{id:1,read_at:null}],notification_unviewed_count:1};
    return {registrations:[]};
  },
});
vm.runInContext(between('const viewedStudentNotificationIds =','function closeStudentNotificationPanel'),context);
vm.runInContext(source.match(/^const isStudentEditing=.*$/m)[0],context);
vm.runInContext(between('async function refreshStudentWhenSafe(){',"document.addEventListener('focusout'"),context);
(async()=>{
  await vm.runInContext('refreshStudentWhenSafe()',context);
  assert.equal(badge.textContent,'1');assert.equal(badge.hidden,false);
  assert.equal(state.routeCalls,0);assert.equal(state.requests.length,1);
  assert.ok(context.pendingStudentResources.has('registrations'));
  const viewed=vm.runInContext('markStudentNotificationsViewed()',context);
  assert.deepEqual(Array.from(state.requests.at(-1).data.ids),[1]);
  // A newer message is fetched while the viewed request is in flight.
  context.cfoStudent.notifications.push({id:2,read_at:null});
  state.release({viewed:1});await viewed;
  assert.equal(badge.textContent,'1');assert.ok(context.cfoStudent.notifications[0].read_at);
  assert.equal(context.cfoStudent.notifications[1].read_at,null);
  // An older response must not resurrect an already acknowledged message.
  context.cfoStudent.notifications=[{id:1,read_at:null},{id:2,read_at:null}];
  vm.runInContext('updateStudentNotificationBell()',context);assert.equal(badge.textContent,'1');
  const second=vm.runInContext('markStudentNotificationsViewed()',context);
  assert.deepEqual(Array.from(state.requests.at(-1).data.ids),[2]);
  state.release({viewed:1});await second;
  assert.equal(badge.textContent,'0');assert.equal(badge.hidden,true);
  state.dirty=false;state.panelOpen=true;
  await vm.runInContext('refreshStudentWhenSafe()',context);assert.equal(state.routeCalls,0);
  state.panelOpen=false;
  context.studentRouteLoading=true;
  await vm.runInContext('refreshStudentWhenSafe()',context);assert.equal(state.routeCalls,0,'live updates must wait for navigation');
  context.studentRouteLoading=false;
  await vm.runInContext('refreshStudentWhenSafe()',context);assert.equal(state.routeCalls,1);
  context.pendingStudentResources.add('users');
  let releaseRefresh;
  context.cfoRequest=()=>new Promise(resolve=>{releaseRefresh=resolve;});
  const refreshing=context.refreshStudentWhenSafe();context.routeVersion++;
  releaseRefresh({user:{name:'Stale partial response'}});await refreshing;
  assert.equal(context.window.CFO_BACKEND.user,undefined);
  assert.ok(context.pendingStudentResources.has('users'),'navigation during a poll must retry its resources');

  // Run the actual router with out-of-order HTTP replies.
  const replies=[],renders=[];
  const routing=vm.createContext({
    location:{hash:'#dashboard'},window:{CFO_BACKEND:{user:{role:'Student',name:'Old name'}},scrollTo(){}},
    cfoStudent:{},cfoRequest:()=>new Promise(resolve=>replies.push(resolve)),URLSearchParams,
    document:{querySelectorAll:()=>[],querySelector:()=>null,body:{classList:{toggle(){}}}},
    app:{querySelectorAll:()=>[]},dashboardCleanup(){},clearRecollection(){},
    dashboard:()=>renders.push('dashboard'),connectedRegistrationDetails(){},
    studentMyProfile:()=>renders.push('profile'),showNotice:()=>assert.fail('unexpected request failure'),
  });
  vm.runInContext(between('let renderedRoute = null;','function connectedStudentCertificates()'),routing);
  const first=routing.route();routing.location.hash='#my-profile';const nextRoute=routing.route();
  replies[1]({user:{role:'Student',name:'Updated by admin'},registrations:[{id:2}]});await nextRoute;
  replies[0]({user:{role:'Student',name:'Stale name'},registrations:[{id:1}]});await first;
  assert.equal(routing.cfoStudent.registrations[0].id,2);
  assert.equal(routing.window.CFO_BACKEND.user.name,'Updated by admin','full navigation must update profile data too');
  assert.deepEqual(renders,['profile']);
  routing.location.hash='#dashboard';const abandoned=routing.route();
  routing.location.hash='#my-profile';await routing.route();
  replies[2]({user:{role:'Student',name:'Abandoned route'}});await abandoned;
  assert.equal(routing.window.CFO_BACKEND.user.name,'Updated by admin');
  assert.equal(vm.runInContext('studentRouteLoading',routing),false);
  console.log('PASS: dirty-form badge refresh, displayed-ID acknowledgement, new arrival preservation, stale-read protection and dropdown refresh guard.');
})().catch(error=>{console.error(error);process.exitCode=1;});
