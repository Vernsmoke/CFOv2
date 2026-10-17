const fs=require('fs');
const vm=require('vm');
const root=require('path').resolve(__dirname,'..');process.chdir(root);
const sPath='resources/views/student.blade.php';
let s=fs.readFileSync(sPath,'utf8');
s=s.replace('@verbatim\n','').replace('@endverbatim','');
s=s.replaceAll('{{','@{{').replace('@{{ json_encode($cfoBackend) }}','{{ json_encode($cfoBackend) }}');
s=s.replace('    dashboard();\n  } else if (location.hash.startsWith', '    dashboard();\n    connectedRegistrationDetails();\n  } else if (location.hash.startsWith');
// Remove obsolete student-side authentication templates/functions; auth lives in its own view.
s=s.replace(/<template id="html-(?:login|registration|forgot|cfologin|cfoforgotpassword|resetactions|resetcredential|roleselect)[^"]*">[\s\S]*?<\/template>/g,'');
s=s.replace(/function roleSelect\([\s\S]*?\n}\n/,'');
fs.writeFileSync(sPath,s);
const aPath='resources/views/admin.blade.php';let a=fs.readFileSync(aPath,'utf8');
a=a.replace('    function connectedSettings(params) {\n',"    function connectedSettings(params) {\n      if(params.get('tab')==='office'){connectedOffice();return;}\n");
a=a.replace("${link('Admin accounts','admin-settings?tab=admins')}","${link('Office information','admin-settings?tab=office')}${link('Admin accounts','admin-settings?tab=admins')}");
a=a.replace("const rows=cfoState.schedules;\n      connectedGrid", "const rows=currentRoute==='admin-upcoming-recollection'?cfoState.schedules.filter(s=>s.active&&s.date>=new Date().toLocaleDateString('en-CA')):cfoState.schedules;\n      connectedGrid");
fs.writeFileSync(aPath,a);
// Restore original embedded login artwork, which the supplied auth view referenced as 'none'.
const p='resources/views/login-create-forgotpassword.blade.php';let auth=fs.readFileSync(p,'utf8');
for(const m of s.matchAll(/<img\b[^>]*data-resource="([^"]+)"[^>]*>/g)){
 const data=m[0].match(/src="([^"]+)"/)?.[1];
 if(data)auth=auth.replace('--'+m[1]+': none','--'+m[1]+': url("'+data+'")');
}
fs.writeFileSync(p,auth);
for(const file of [sPath,aPath,p]){
 const source=fs.readFileSync(file,'utf8').replace(/\{\{ Illuminate\\Support\\Js::from\(\$cfoBackend\) \}\}/g,'{}').replaceAll('@{{','{{');
 let n=0;for(const match of source.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)){new vm.Script(match[1],{filename:file});n++;}
 console.log(file+': '+n+' inline script(s) parsed.');
}
