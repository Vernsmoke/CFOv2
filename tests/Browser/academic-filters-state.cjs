const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const window = {CFO_ACADEMIC_CATALOG:{departments:[
    {name:'College A',courses:[{name:'Course A',majors:['Shared','A only']},{name:'No major',majors:[]}]},
    {name:'College B',courses:[{name:'Course B',majors:['Shared']}]}
]}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/academic-filters.js'),'utf8'), {window});
const api = window.CfoAcademicFilters, selection = {department:['College A','College B'],course:['Course A','Course B'],major:[]};
assert.equal(api.options('course',selection).length,3);
assert.equal(api.options('major',selection).length,3);
assert.equal(api.options('course',{department:[]}).length,0);
assert.equal(api.options('major',{department:['College A'],course:[]}).length,0);
assert.equal(api.options('course',{department:['College B']})[0].group,'College B');
assert.equal(api.options('major',{department:['College A'],course:['No major']}).length,0);
assert.equal(api.matches(selection,{department:'College A',course:'Course A',major:'Shared'}),true);
const narrowed = {...selection,major:[JSON.stringify(['Course A','Shared'])]};
assert.equal(api.matches(narrowed,{department:'College B',course:'Course B',major:'Shared'}),false);
assert.equal(api.matches(narrowed,{department:'College A',course:'Course A',major:'Shared'}),true);
assert.equal(api.profile({course:'Course A'}).department,'College A');
assert.equal(api.paths(['Old Course']).at(-1).department,'Other / Legacy Courses');
const population = [{department:'College A',course:'Course A',major:'Shared',year_level:'1',section:'A'}];
assert.equal(api.options('department',{},[],population).map(row=>row.value).join(','),'College A');
assert.equal(api.options('course',{department:['College A']},[],population).map(row=>row.value).join(','),'Course A');
assert.equal(api.options('major',selection,[],population).length,1,'Unrepresented majors are not offered.');
assert.equal(api.options('department',{},[],[]).length,0,'An empty population must not fall back to the catalog.');
// Exercise production schedule filtering, including a schedule open to all majors.
window.document = undefined;
const sandbox = {window,module:{exports:{}}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/my-schedules.js'),'utf8'),sandbox);
const ms = sandbox.module.exports;
const rows = [
    {id:1,courses:[{id:1,name:'Course A'}],course_majors:{1:'Shared'}},
    {id:2,courses:[{id:2,name:'Course B'}],course_majors:{2:'Shared'}},
    {id:3,courses:[{id:1,name:'Course A'}],course_majors:{}}
];
assert.equal(ms.filterSchedules(rows,{academic:narrowed}).map(r=>r.id).join(','),'1,3');
console.log('Academic hierarchy, multi-parent scope, paired majors, and schedule filters passed.');

// Minimal DOM adapter drives the production handlers, including draft-only dialog edits.
const decode = value => value.replace(/&quot;/g,'"').replace(/&#39;/g,"'").replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>');
class Element {
    constructor() { this.nodes = []; this.listeners = {}; }
    set innerHTML(html) {
        this.html = html;
        this.nodes = [...html.matchAll(/<(input|button|select|h2)\b([^>]*)>/g)].map(match => {
            const node = new Element(); node.tag = match[1]; node.dataset = {}; node.attrs = {};
            for (const attr of match[2].matchAll(/([\w-]+)(?:="([^"]*)")?/g)) {
                node.attrs[attr[1]] = decode(attr[2] || '');
                if (attr[1].startsWith('data-')) node.dataset[attr[1].slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = decode(attr[2] || '');
            }
            node.checked = 'checked' in node.attrs;
            return node;
        });
    }
    querySelectorAll(selector) {
        if (selector === 'h2') return this.nodes.filter(node => node.tag === 'h2');
        const match = selector.match(/^\[([^=\]]+)(?:="(.*)")?\]$/);
        return this.nodes.filter(node => match[1] in node.attrs && (match[2] === undefined || node.attrs[match[1]] === match[2]));
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0]; }
    setAttribute(key,value) { this.attrs ||= {}; this.attrs[key] = value; }
    addEventListener(name,fn) { this.listeners[name] = fn; }
    focus() {}
    close() { this.open = false; }
    showModal() { this.open = true; }
    remove() { this.removed = true; }
}
let dialog;
const document = {createElement:() => new Element(), body:{append:node => { dialog = node; }}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/academic-filters.js'),'utf8'), {window,document});
const host = new Element(), changes = [];
const control = window.CfoAcademicFilters.mount(host,{onChange:value=>changes.push(value)});
const change = (key,value) => { const input = host.querySelector(`[data-af-select="${key}"]`); input.value=value; input.onchange(); };
change('department','College A'); change('course','Course A'); change('major',JSON.stringify(['Course A','Shared']));
assert.equal(control.value().major.length,1);
change('course',''); assert.equal(control.value().major.length,0);
change('department',''); assert.equal(control.value().course.length,0);
host.querySelector('[data-af-multiple]').onchange({target:{checked:true}});
host.querySelector('[data-af-open="department"]').onclick();
function check(value) { const input = dialog.querySelectorAll('[data-af-value]').find(node=>node.dataset.afValue===value); input.checked=true; input.onchange(); }
check('College A'); check('College B');
dialog.querySelector('[data-af-cancel]').onclick();
assert.equal(control.value().department.length,0,'Cancel discards draft selections.');
host.querySelector('[data-af-open="department"]').onclick(); check('College A'); check('College B');
dialog.querySelector('[data-af-apply]').onclick();
assert.equal(control.value().department.length,2,'Apply commits both departments.');
host.querySelector('[data-af-open="course"]').onclick();
assert.equal(dialog.querySelectorAll('[data-af-value]').length,3,'Only courses from selected departments appear.');
check('Course A'); check('Course B'); dialog.querySelector('[data-af-apply]').onclick();
host.querySelector('[data-af-open="major"]').onclick();
assert.equal(dialog.querySelectorAll('[data-af-value]').length,3);
check(JSON.stringify(['Course A','Shared'])); dialog.querySelector('[data-af-clear]').onclick();
assert.equal(dialog.querySelectorAll('[data-af-value]').filter(node=>node.checked).length,0);
dialog.querySelector('[data-af-apply]').onclick(); assert.equal(control.value().major.length,0);
control.reset(); assert.equal(control.value().department.length,0);
console.log('Single dropdowns, parent resets, multi-select Apply/Cancel/Clear, and grouped choices passed.');
