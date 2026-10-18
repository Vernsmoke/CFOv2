const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {spawnSync} = require('node:child_process');
const modulePath = path.join(__dirname, '../../public/admin-schedule-create.js');
const source = fs.readFileSync(modulePath, 'utf8');
const courseManagerSource = fs.readFileSync(path.join(__dirname, '../../public/admin-course-manage.js'), 'utf8');
const context = vm.createContext({window: {}}); vm.runInContext(courseManagerSource, context); vm.runInContext(source, context, {filename: modulePath});
const api = context.window.CfoScheduleCreate;
const accountOnly = {academic_options:[{id:1,type:'course',name:'Criminology'},{id:2,type:'course',name:'Nursing'}], academic_population:[{course:'Criminology',major:'',year_level:'1',section:'A'}]};
assert.equal(api.createOptions(accountOnly,'course').map(row=>row.name).join(','),'Criminology');
assert.equal(api.createOptions(accountOnly,'year_level').map(row=>row.name).join(','),'1');
assert.equal(api.createSectionOptions(accountOnly,'1','1').join(','),'A');
assert.equal(api.createSectionOptions(accountOnly,'1','2').length,0);
assert.equal(api.createOptions({...accountOnly,academic_population:[]},'course').length,0);
const majorPopulation={...accountOnly,academic_population:[{course:'Criminology',major:'One',year_level:'1',section:'A'},{course:'Criminology',major:'Two',year_level:'1',section:'B'}]};
assert.equal(api.createSectionOptions(majorPopulation,'1','1','One').join(','),'A');
const plain = value => JSON.parse(JSON.stringify(value));
const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
const state = {academic_options: [
    {id: 11, type: 'course', name: 'BSIT'}, {id: 12, type: 'course', name: 'BSN'}, {id: 13, type: 'course', name: 'BSCS'},
    ...['1', '2', '3', '4'].map((name, index) => ({id: 21 + index, type: 'year_level', name})),
    {id: 31, type: 'section', name: 'A'}, {id: 32, type: 'section', name: 'B'},
], course_year_sections: [], schedules: []};
const configuredState = {...state, course_year_sections: [
    // Reference sections remain independent from account-driven choices.
    ...['A', 'B'].map((section, index) => ({id: index + 1, course_id: 11, year_level: '3', section})),
    ...['A', 'B', 'C'].map((section, index) => ({id: index + 3, course_id: 12, year_level: '3', section})),
]};
const catalogState = {
    ...configuredState,
    academic_options: [...configuredState.academic_options, {id: 50, type: 'course', name: 'Bachelor of Science in Business Administration'}],
    academic_catalog: {departments: [{name: 'College of Business Education', courses: [{name: 'Bachelor of Science in Business Administration', majors: ['Marketing Management', 'Financial Management']}]}]},
};
// Legacy helper inputs stay supported while Create and Edit share the course-card renderer.
const valid = {course_ids: ['11'], year_levels: ['3'], sections: ['A'], date: '2026-10-20', start_time: '08:00', end_time: '16:00', venue: 'University Gymnasium', slot_limit: '', status: 'Open for Registration'};
const check = (changes = {}, schedules = [], references = state) => api.evaluate({...valid, ...changes}, {...references, schedules});
const save = (changes = {}, publish = false, references = state) => api.payload({...valid, ...changes}, references, publish);
const existing = {id: 50, title: 'Existing schedule', courses: [{id: 11, name: 'BSIT'}], year_level: '3', section: 'A', date: '2026-10-20', start_time: '10:00', end_time: '12:00', venue: 'Other Hall', active: true};
const groupedValid = {course_groups: [{course_id: '11', year_level: '3', sections: ['A']}], date: valid.date, start_time: valid.start_time, end_time: valid.end_time, venue: valid.venue, slot_limit: '', status: 'Open for Registration'};
const createCheck = (changes = {}, schedules = [], references = configuredState) => api.evaluate({...groupedValid, ...changes}, {...references, schedules});
const createBody = (changes = {}, action = 'another', references = configuredState) => plain(api.payload({...groupedValid, ...changes}, references, action));
assert.equal(typeof api.mount, 'function');
assert.deepEqual(plain(api.optionNames('year_level')), ['1', '2', '3', '4'], 'Edit legacy options stay compatible');
assert.deepEqual(plain(api.optionNames('section')), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split(''));
assert.deepEqual(plain(api.createOptions(state, 'year_level').map(option => option.name)), ['1', '2', '3', '4']);
assert.deepEqual(plain(api.createOptions(state, 'section').map(option => option.name)), alphabet);
assert.deepEqual(plain(api.createSectionOptions(configuredState, 11, '3')), ['A', 'B']);
assert.deepEqual(plain(api.createSectionOptions(configuredState, 12, '3')), ['A', 'B', 'C']);
const customReferences = {...configuredState, academic_options: [...configuredState.academic_options, {id: 90, type: 'section', name: 'Legacy'}], course_year_sections: [...configuredState.course_year_sections, {course_id: 11, year_level: '3', section: 'AA'}]};
assert.deepEqual(plain(api.createOptions(customReferences, 'section').map(option => option.name)), alphabet, 'The displayed baseline is A–Z even when old global references have custom sections');
assert.deepEqual(plain(api.createSectionOptions(customReferences, 11, '3')), ['A', 'B', 'AA'], 'A configured custom section remains available after A–Z');
assert.deepEqual(plain(api.createSectionOptions(configuredState, 11, '2')), alphabet, 'An unconfigured course/year uses the A–Z fallback');
const emptyReferences = {...state, academic_options: state.academic_options.filter(option => option.type === 'course')};
assert.deepEqual(plain(api.createOptions(emptyReferences, 'year_level').map(option => option.name)), ['1', '2', '3', '4']);
assert.deepEqual(plain(api.createSectionOptions(emptyReferences, 11, '2')), alphabet);
assert.equal(api.evaluate(groupedValid, emptyReferences).canSave, true, 'The baseline years and A–Z fallback work even when the old global references are absent');
assert.equal(createCheck({course_groups: [{course_id: '11', year_level: '2', sections: ['Z']}]}, [], emptyReferences).canSave, true);
assert.deepEqual(createBody({course_groups: [{course_id: '11', year_level: '2', sections: ['Z']}]}, 'publish', emptyReferences).course_sections_by_year, {'11': {'2': ['Z']}});
assert.equal(createCheck().canSave, true);
const majorSchedule = {...groupedValid, course_groups: [{department: 'College of Business Education', course_id: '50', major: 'Marketing Management', year_level: '3', sections: ['A']}]} ;
assert.equal(api.evaluate(majorSchedule, catalogState).canSave, true, 'A catalog course is valid after selecting its required major');
assert.equal(api.evaluate({...majorSchedule, course_groups: [{...majorSchedule.course_groups[0], major: ''}]}, catalogState).majorValid, false, 'A course that offers majors remains locked until a major is selected');
assert.deepEqual(plain(api.payload(majorSchedule, catalogState).course_majors), {'50': 'Marketing Management'});
assert.equal(createCheck({status: 'Closed'}).canSave, true);
assert.equal(createBody({status: 'Closed'}, 'publish').status, 'Closed');
assert.equal(createBody({status: 'Closed'}, 'publish').active, false);
assert.equal(createBody({}, 'publish').status, 'Open');
assert.equal(createBody({status: 'Draft'}, 'another').status, 'Draft');
assert.equal(createBody({}, 'draft').status, 'Draft');
assert.throws(() => createBody({status: 'Draft'}, 'publish'), /Draft|draft|status|publish/);
for (const changes of [
    {course_groups: []}, {course_groups: null}, {course_groups: 'BSIT'}, {course_groups: [{}]},
    {course_groups: [{course_id: '999', year_level: '3', sections: ['A']}]},
    {course_groups: [{course_id: '11', year_level: '', sections: ['A']}]},
    {course_groups: [{course_id: '11', year_level: 'Deleted year', sections: ['A']}]},
    {course_groups: [{course_id: '11', year_level: '3', sections: []}]},
    {course_groups: [{course_id: '11', year_level: '3', sections: ['A', 'A']}]},
    {course_groups: [{course_id: '11', year_level: '3', sections: ['C']}]},
    {course_groups: [{course_id: '11', year_level: '3', sections: ['a']}]},
    {course_groups: [{course_id: '11', year_level: '3', sections: ['A']}, {course_id: '11', year_level: '3', sections: ['B']}]},
    {course_groups: [{course_id: '11', year_level: '3', sections: ['A']}, {course_id: '', year_level: '', sections: []}]},
    {date: ''}, {venue: '   '}, {venue: 'x'.repeat(191)}, {status: 'Unknown'},
]) { assert.equal(createCheck(changes).canSave, false, JSON.stringify(changes)); assert.throws(() => createBody(changes)); }
const twoCards = {...groupedValid, course_groups: [{course_id: '11', year_level: '2', sections: ['A', 'B']}, {course_id: '12', year_level: '3', sections: ['B', 'C']}]};
const allCards = api.evaluate(twoCards, configuredState);
assert.equal(allCards.canSave, true);
assert.deepEqual(plain(allCards.groups.map(group => group.label)), ['BSIT - 2A', 'BSIT - 2B', 'BSN - 3B', 'BSN - 3C']);
assert.deepEqual(plain(allCards.year_levels), ['2', '3']);
const twoCardBody = plain(api.payload(twoCards, configuredState, 'publish'));
assert.deepEqual(twoCardBody.course_ids, [11, 12]);
assert.deepEqual(twoCardBody.course_sections_by_year, {'11': {'2': ['A', 'B']}, '12': {'3': ['B', 'C']}});
assert.deepEqual(twoCardBody.year_levels, ['2', '3']); assert.deepEqual(twoCardBody.sections, ['A', 'B', 'C']);
assert.equal(twoCardBody.save_action, 'publish');
assert.equal(Object.hasOwn(twoCardBody, 'course_sections'), false);
for (const name of ['title', 'theme', 'facilitator', 'description']) assert.equal(Object.hasOwn(twoCardBody, name), false, name + ' is not sent by Create');
const sameCourseYears = {...groupedValid, course_groups: [{course_id: '11', year_level: '2', sections: ['A']}, {course_id: '11', year_level: '3', sections: ['B']}]};
assert.equal(api.evaluate(sameCourseYears, configuredState).canSave, true);
assert.deepEqual(plain(api.payload(sameCourseYears, configuredState).course_sections_by_year), {'11': {'2': ['A'], '3': ['B']}});
assert.deepEqual(plain(api.payload(sameCourseYears, configuredState).course_ids), [11]);
const manyReferences = {...state, academic_options: [...state.academic_options.filter(option => option.type !== 'course'), ...Array.from({length: 30}, (_, index) => ({id: index + 100, type: 'course', name: 'Course ' + index}))]};
const manyCards = {...groupedValid, course_groups: manyReferences.academic_options.filter(option => option.type === 'course').map(course => ({course_id: String(course.id), year_level: '2', sections: ['A', 'B']}))};
assert.equal(api.evaluate(manyCards, manyReferences).canSave, true, 'No arbitrary course card limit');
assert.equal(api.evaluate(manyCards, manyReferences).groups.length, 60);
assert.equal(api.payload(manyCards, manyReferences).course_ids.length, 30);
const extendedYears = {...configuredState, academic_options: [...configuredState.academic_options, {id: 41, type: 'year_level', name: '5'}, {id: 42, type: 'year_level', name: '6'}]};
const sixYearCards = {...groupedValid, course_groups: ['1', '2', '3', '4', '5', '6'].map(year_level => ({course_id: '11', year_level, sections: ['A']}))};
assert.deepEqual(plain(api.createOptions(extendedYears, 'year_level').map(option => option.name)), ['1', '2', '3', '4'], 'Fresh schedule choices stay at years 1–4 even if old references contain other years');
assert.equal(api.evaluate(sixYearCards, extendedYears).canSave, false, 'A fresh schedule cannot add an out-of-range year');
assert.throws(() => api.payload(sixYearCards, extendedYears), /year/i);
const fourYearCards = {...sixYearCards, course_groups: sixYearCards.course_groups.slice(0, 4)};
assert.equal(api.evaluate(fourYearCards, extendedYears).canSave, true, 'All four years can use independent cards without a course or section count cap');
assert.deepEqual(plain(api.payload(fourYearCards, extendedYears).year_levels), ['1', '2', '3', '4']);
assert.throws(() => api.payload({...groupedValid, course_groups: [...groupedValid.course_groups, {course_id: '12', year_level: '3', sections: []}]}, configuredState, 'draft'), /section/);
const longReferences = {...configuredState, academic_options: configuredState.academic_options.map(option => option.id === 11 ? {...option, name: 'Long course name '.repeat(12)} : option)};
assert.equal(Object.hasOwn(api.payload(groupedValid, longReferences), 'title'), false, 'The server generates titles for long course names');
assert.equal(Object.hasOwn(api.payload(manyCards, manyReferences), 'title'), false);
assert.equal(createBody().slot_limit, null); assert.equal(createBody({slot_limit: '   '}).slot_limit, null);
assert.equal(createBody({slot_limit: '1'}).slot_limit, 1); assert.equal(createBody({slot_limit: '100000'}).slot_limit, 100000);
for (const slot_limit of ['0', '-1', '1.5', '100001', '1e2', 'NaN', 'abc']) { assert.equal(createCheck({slot_limit}).settingsValid, false); assert.throws(() => createBody({slot_limit})); }
for (const changes of [{start_time: ''}, {end_time: ''}, {start_time: '8:00'}, {start_time: '24:00'}, {end_time: '12:60'}, {end_time: '08:00'}, {end_time: '07:59'}]) { assert.equal(createCheck(changes).timeValid, false); assert.equal(createCheck(changes).canSave, false); }
assert.equal(createCheck({start_time: '00:00', end_time: '00:01'}).timeValid, true);
assert.equal(createCheck({start_time: '23:58', end_time: '23:59'}).timeValid, true);
assert.equal(createCheck({}, [existing]).studentConflicts.length, 1);
assert.equal(createCheck({start_time: '12:00'}, [existing]).canSave, true);
assert.equal(createCheck({end_time: '10:00'}, [existing]).canSave, true);
assert.equal(createCheck({}, [{...existing, status: 'Cancelled'}]).canSave, true);
const sharedVenue = {...existing, courses: [{id: 12, name: 'BSN'}], venue: ' university gymnasium '};
for (const status of ['Open', 'Draft', 'Closed', 'Completed']) {
    const result = createCheck({}, [{...sharedVenue, status}]);
    assert.equal(result.venueConflicts.length, 1, status + ' reserves the venue'); assert.equal(result.studentConflicts.length, 0); assert.equal(result.canSave, false);
    assert.throws(() => api.payload(groupedValid, {...configuredState, schedules: [{...sharedVenue, status}]}));
}
assert.equal(createCheck({}, [{...sharedVenue, status: 'Cancelled'}]).venueConflicts.length, 0);
assert.equal(createCheck({start_time: '12:00'}, [sharedVenue]).venueConflicts.length, 0);
const mappedSchedule = {...existing, year_levels: ['2', '3'], sections: ['A', 'C'], course_sections_by_year: {'11': {'2': ['A']}, '12': {'3': ['C']}}, courses: [{id: 11, name: 'BSIT', pivot: {sections: {'2': ['A']}}}, {id: 12, name: 'BSN', pivot: {sections: {'3': ['C']}}}]};
assert.equal(createCheck({}, [mappedSchedule]).canSave, true, 'BSIT 3A must not match BSIT 2A');
assert.equal(api.evaluate(twoCards, {...configuredState, schedules: [mappedSchedule]}).studentConflicts.length, 1, 'All cards participate in exact course/year/section conflict checks');
assert.equal(api.evaluate({...twoCards, course_groups: [{course_id: '11', year_level: '2', sections: ['B']}, {course_id: '12', year_level: '3', sections: ['A']}]}, {...configuredState, schedules: [mappedSchedule]}).studentConflicts.length, 0);
const restoredNew = plain(api.restoreDraft({...twoCards, draft_version: 2, ignored: 'unsaved'}, configuredState));
assert.deepEqual(restoredNew.course_groups, [
    {department: 'Other / Legacy Courses', course_id: '11', major: '', year_level: '2', sections: ['A', 'B']},
    {department: 'Other / Legacy Courses', course_id: '12', major: '', year_level: '3', sections: ['B', 'C']},
]); assert.equal(Object.hasOwn(restoredNew, 'ignored'), false);
const restoredLegacy = plain(api.restoreDraft({...valid, course_ids: [11, 12], year_levels: ['2', '3'], course_sections: {'11': ['A', 'B', 'C'], '12': ['B', 'C']}, status: 'Closed'}, configuredState));
assert.deepEqual(restoredLegacy.course_groups, [
    {department: 'Other / Legacy Courses', course_id: '11', major: '', year_level: '2', sections: ['A', 'B', 'C']},
    {department: 'Other / Legacy Courses', course_id: '11', major: '', year_level: '3', sections: ['A', 'B']},
    {department: 'Other / Legacy Courses', course_id: '12', major: '', year_level: '2', sections: ['B', 'C']},
    {department: 'Other / Legacy Courses', course_id: '12', major: '', year_level: '3', sections: ['B', 'C']},
]);
assert.equal(restoredLegacy.status, 'Closed', 'Closed draft values are retained instead of converted to Draft');
assert.equal(api.restoreDraft({status: 'Open'}, state).status, 'Open for Registration');
assert.equal(api.restoreDraft({status: 'Unknown'}, state).status, 'Open for Registration');
for (const broken of [null, 'broken draft', {course_groups: []}]) assert.deepEqual(plain(api.restoreDraft(broken, state).course_groups), [{department: '', course_id: '', major: '', year_level: '', sections: []}]);
const filteredDraft = plain(api.restoreDraft({...groupedValid, course_groups: [{course_id: '11', year_level: '3', sections: ['A', 'A', 'C']}, {course_id: 'deleted', year_level: '3', sections: ['A']}], slot_limit: '75'}, configuredState));
assert.deepEqual(filteredDraft.course_groups[0], {department: 'Other / Legacy Courses', course_id: '11', major: '', year_level: '3', sections: ['A']}); assert.equal(filteredDraft.slot_limit, '75');
const copyBody = plain(api.payload(twoCards, configuredState)); copyBody.course_sections_by_year['11']['2'].push('C'); assert.deepEqual(twoCards.course_groups[0].sections, ['A', 'B']);
assert.equal(createCheck({date: '2028-02-29'}).dateValid, true);
for (const date of ['2026-02-29', '2026-04-31', '2026-13-01', '2026-00-20', '2026-10-00', '2026-1-20', 'not a date']) { assert.equal(createCheck({date}).dateValid, false); assert.equal(api.formatDate(date), 'Not set'); }
assert.equal(api.formatDate('2026-10-20'), 'October 20, 2026');
for (const [time, label] of [['00:00', '12:00 AM'], ['08:00', '8:00 AM'], ['12:00', '12:00 PM'], ['16:05', '4:05 PM'], ['23:59', '11:59 PM'], ['24:00', 'Not set']]) assert.equal(api.formatTime(time), label);
const dateCheck = "const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');const context=vm.createContext({window:{}});vm.runInContext(fs.readFileSync(" + JSON.stringify(modulePath) + ",'utf8'),context);const format=context.window.CfoScheduleCreate.formatDate;assert.equal(format('2026-03-08'),'March 8, 2026');assert.equal(format('2026-11-01'),'November 1, 2026');assert.equal(format('2028-02-29'),'February 29, 2028');";
for (const timezone of ['Asia/Manila', 'America/New_York', 'Australia/Sydney']) { const child = spawnSync(process.execPath, ['-e', dateCheck], {encoding: 'utf8', env: {...process.env, TZ: timezone}}); assert.equal(child.status, 0, timezone + ': ' + child.stderr); }

// The actual Create handlers run against a small DOM adapter. No extra package is needed.
function mountedForm(draft = twoCards, blockedStorage = false, references = configuredState, request, editSchedule) {
    const node = properties => ({listeners: {}, dataset: {}, hidden: false, disabled: false, value: '', textContent: '', innerHTML: '', open: false,
        addEventListener(type, handler) { this.listeners[type] = handler; }, removeEventListener(type) { delete this.listeners[type]; }, querySelectorAll() { return []; }, focus() {}, scrollIntoView() {},
        showModal() { this.open = true; }, close() { this.open = false; }, setCustomValidity() {}, reportValidity() {}, ...properties});
    const inputs = Object.fromEntries(['date', 'start_time', 'end_time', 'venue', 'slot_limit', 'status'].map(name => [name, node({name})]));
    const cards = new Map(), rendered = new Map();
    const attrsOf = text => Object.fromEntries([...text.matchAll(/([\w-]+)="([^"]*)"/g)].map(match => [match[1], match[2]]));
    const datasetOf = attrs => Object.fromEntries(Object.entries(attrs).filter(([name]) => name.startsWith('data-')).map(([name, value]) => [name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()), value]));
    const matches = (entry, selector) => {
        if (selector.includes(',')) return selector.split(',').some(part => matches(entry, part.trim()));
        if (selector.includes(':checked') && !entry.checked) return false;
        if (selector.includes(':not(:disabled)') && entry.disabled) return false;
        const attributes = [...selector.matchAll(/\[([\w-]+)(?:="([^"]*)")?\]/g)];
        if (!attributes.length) return selector.split(',').some(tag => tag.trim() === entry.tagName?.toLowerCase());
        return attributes.every(([, name, value]) => {
            const key = name.startsWith('data-') ? name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()) : name;
            const found = name.startsWith('data-') ? entry.dataset[key] : entry[key];
            return found !== undefined && (value === undefined || String(found) === value);
        });
    };
    const cardContainer = node({querySelector(selector) { const index = selector.match(/^\[data-sb-course-group="([0-9]+)"\]$/)?.[1]; return index != null ? cards.get(index) : [...cards.values()].flatMap(card => card.entries).find(entry => matches(entry, selector)) || get(selector); }, querySelectorAll(selector) { return selector === '[data-sb-course-group]' ? [...cards.values()] : [...cards.values()].flatMap(card => card.entries).filter(entry => matches(entry, selector)); }}); let cardHtml = '';
    Object.defineProperty(cardContainer, 'innerHTML', {get: () => cardHtml, set(html) {
        cardHtml = html; cards.clear();
        const parts = html.split(/<article\b[^>]*data-sb-course-group="([0-9]+)"[^>]*>/);
        for (let index = 1; index < parts.length; index += 2) {
            const htmlPart = parts[index + 1];
            const entries = [...htmlPart.matchAll(/<input\b([^>]*)>/g)].map(match => {
                const attrs = attrsOf(match[1]);
                return node({...attrs, tagName: 'INPUT', dataset: datasetOf(attrs), checked: /\schecked(?:\s|$)/.test(match[1]), disabled: /\sdisabled(?:\s|$)/.test(match[1])});
            });
            for (const match of htmlPart.matchAll(/<select\b([^>]*)>([\s\S]*?)<\/select>/g)) {
                const attrs = attrsOf(match[1]), options = [...match[2].matchAll(/<option\b([^>]*)>(.*?)<\/option>/g)].map(option => ({...attrsOf(option[1]), selected: /\sselected(?:\s|$)/.test(option[1]), textContent: option[2]}));
                entries.push(node({...attrs, tagName: 'SELECT', dataset: datasetOf(attrs), options, disabled: /\sdisabled(?:\s|$)/.test(match[1]), value: (options.find(option => option.selected) || options[0])?.value || ''}));
            }
            for (const match of htmlPart.matchAll(/<button\b([^>]*)>/g)) {
                const attrs = attrsOf(match[1]);
                entries.push(node({...attrs, tagName: 'BUTTON', dataset: datasetOf(attrs), disabled: /\sdisabled(?:\s|$)/.test(match[1])}));
            }
            for (const entry of entries) entry.matches = selector => matches(entry, selector);
            const local = new Map();
            const card = node({dataset: {sbCourseGroup: parts[index]}, entries, html: htmlPart,
                querySelector(selector) { const input = entries.find(entry => matches(entry, selector)); if (input) return input; if (!local.has(selector)) local.set(selector, node()); return local.get(selector); },
                querySelectorAll: selector => entries.filter(entry => matches(entry, selector)),
            });
            for (const entry of entries) entry.closest = selector => selector === '[data-sb-course-group]' ? card : null;
            cards.set(parts[index], card);
        }
    }});
    const get = selector => {
        if (selector === '[data-sb-course-groups]') return cardContainer;
        const index = selector.match(/^\[data-sb-course-group="([0-9]+)"\]$/)?.[1]; if (index != null) return cards.get(index);
        if (!rendered.has(selector)) rendered.set(selector, node({querySelector: get, querySelectorAll() { return []; }})); return rendered.get(selector);
    };
    const error = node({hidden: true}), form = node({elements: inputs,
        querySelector(selector) { if (selector === '.sb-form-error') return error; return Object.values(inputs).find(input => selector.includes('name="' + input.name + '"')) || get(selector); },
        querySelectorAll(selector) { if (selector === '[data-sb-course-group]') return [...cards.values()]; return [...Object.values(inputs), ...[...cards.values()].flatMap(card => card.entries)].filter(entry => matches(entry, selector)); },
        checkValidity() { return ['date', 'start_time', 'end_time', 'venue'].every(name => this.elements[name].value !== ''); }, reportValidity() {},
    });
    const courseInput = node(), courseForm = node({elements: {name: courseInput}, reset() { this.elements.name.value = ''; }});
    const courseError = node({hidden: true});
    const courseDialog = node({querySelector: selector => selector === 'form' ? courseForm : selector === '[data-sb-course-error]' ? courseError : selector === '[data-sb-course-manage]' ? get(selector) : courseInput, querySelectorAll: () => []});
    const sectionInput = node(), sectionForm = node({elements: {section: sectionInput}, reset() { this.elements.section.value = ''; }}), sectionError = node({hidden: true});
    const sectionDialog = node({querySelector: selector => selector === 'form' ? sectionForm : selector === '[data-sb-section-error]' ? sectionError : sectionInput, querySelectorAll: () => []});
    const confirmDialog = node({querySelector: get, querySelectorAll: () => []});
    const builder = node({querySelector(selector) { if (selector === '.schedule-course-dialog' || selector === '[data-sb-course-dialog]') return courseDialog; if (selector === '[data-sb-section-dialog]') return sectionDialog; if (selector === '[data-sb-create-confirm]') return confirmDialog; return get(selector); },
        querySelectorAll(selector) { return selector === '[data-sb-course-group]' ? [...cards.values()] : form.querySelectorAll(selector); }});
    const root = node({querySelector: selector => selector === '.schedule-builder' ? builder : form});
    let rootHtml = '';
    Object.defineProperty(root, 'innerHTML', {get: () => rootHtml, set(html) {
        rootHtml = html;
        for (const match of html.matchAll(/<(?:input|select|textarea)\b([^>]*)>/g)) {
            const attrs = attrsOf(match[1]), input = inputs[attrs.name];
            if (!input) continue;
            input.disabled = /\sdisabled(?:\s|$)/.test(match[1]);
            if (attrs.min !== undefined) input.min = attrs.min;
            if (attrs.max !== undefined) input.max = attrs.max;
        }
    }});
    const key = 'cfo-admin-schedule-draft-v1:1', entries = new Map(draft ? [[key, JSON.stringify(draft)]] : []);
    const storageCalls = [];
    const storage = {getItem(key) { storageCalls.push('read'); if (editSchedule) throw Error('Edit must not use create drafts'); return entries.get(key); }, setItem(key, value) { storageCalls.push('write'); if (editSchedule) throw Error('Edit must not use create drafts'); if (blockedStorage) throw Error('Storage unavailable'); entries.set(key, value); }, removeItem(key) { storageCalls.push('remove'); if (editSchedule) throw Error('Edit must not use create drafts'); entries.delete(key); }};
    const saves = []; let navigations = 0;
    const mountedContext = vm.createContext({window: {addEventListener() {}, removeEventListener() {}}, document: {activeElement: null}}); vm.runInContext(courseManagerSource, mountedContext); vm.runInContext(source, mountedContext);
    const instance = mountedContext.window.CfoScheduleCreate.mount({root, state: plain(references), editSchedule: editSchedule ? plain(editSchedule) : undefined, userId: 1, storage, request, toast() {}, save(body, onSaved, onError) { saves.push({body: plain(body), onSaved, onError}); }, navigate() { navigations++; }});
    return {root, form, error, inputs, cards, entries, key, saves, storageCalls, instance, get, courseDialog, courseForm, courseError, sectionDialog, sectionForm, sectionError, confirmDialog, navigations: () => navigations,
        click(selector, dataset = {}, properties = {}) { const event = {prevented: false, preventDefault() { this.prevented = true; }, target: {closest: name => name === selector ? {dataset, ...properties} : null}}; builder.listeners.click(event); return event; },
        submit(mode) { return form.listeners.submit({preventDefault() {}, submitter: {value: mode}}); },
        change(name, value) { inputs[name].value = value; return form.listeners.input({target: inputs[name]}); },
        select(index, type, value) {
            const card = cards.get(String(index)), entries = card.entries.filter(input => input.dataset[type] !== undefined);
            const target = entries.find(input => input.tagName === 'SELECT') || entries.find(input => input.value === value);
            assert.ok(target, 'Rendered control exists for ' + type + ' ' + value);
            if (target.tagName === 'SELECT') target.value = value;
            else if (target.type === 'radio') for (const input of entries) input.checked = input === target;
            else target.checked = !target.checked;
            return form.listeners.change({target});
        },
    };
}
function summaryCourseGroups(mounted) {
    const html = mounted.get('[data-sb-summary]').innerHTML;
    assert.match(html, /<ul\b[^>]*class="sb-summary-courses"/);
    return [...html.matchAll(/<li\b[^>]*class="sb-summary-course"[^>]*>([\s\S]*?)<\/li>/g)].map(([, group]) => ({
        label: group.match(/<strong\b[^>]*class="sb-summary-course-name"[^>]*>([\s\S]*?)<\/strong>/)?.[1],
        chips: [...group.matchAll(/<span\b[^>]*class="sb-summary-section"[^>]*>([\s\S]*?)<\/span>/g)].map(([, label]) => label),
    }));
}
function summaryRowLabels(mounted) {
    return [...mounted.get('[data-sb-summary]').innerHTML.matchAll(/<dt\b[^>]*>([\s\S]*?)<\/dt>/g)].map(([, label]) => label);
}
async function verifyCreateHandlers() {
    const mounted = mountedForm();
    assert.match(mounted.root.innerHTML, /data-sb-course-groups/); assert.match(mounted.root.innerHTML, /Add Another Course Group/);
    assert.match(mounted.root.innerHTML, /<option[^>]*>Closed<\/option>/);
    assert.equal(mounted.cards.size, 2); assert.deepEqual(plain(mounted.instance.read().course_groups), [
        {department: 'Other / Legacy Courses', course_id: '11', major: '', year_level: '2', sections: ['A', 'B']},
        {department: 'Other / Legacy Courses', course_id: '12', major: '', year_level: '3', sections: ['B', 'C']},
    ]);
    assert.match(mounted.get('[data-sb-summary]').innerHTML, /BSIT/); assert.match(mounted.get('[data-sb-summary]').innerHTML, /BSN/);
    assert.match(mounted.get('[data-sb-summary]').innerHTML, /2nd Year/); assert.match(mounted.get('[data-sb-summary]').innerHTML, /3rd Year/);
    assert.deepEqual(summaryCourseGroups(mounted), [
        {label: 'BSIT \u00b7 2nd Year', chips: ['2A', '2B']}, {label: 'BSN \u00b7 3rd Year', chips: ['3B', '3C']},
    ], 'Create summary keeps each course/year heading paired with its own section chips');
    assert.deepEqual(summaryRowLabels(mounted), ['Course(s)', 'Date', 'Time', 'Venue', 'Capacity', 'Status']);
    assert.match(mounted.get('[data-sb-summary]').innerHTML, /<span class="sb-status-pill">Open for Registration<\/span>/);
    mounted.click('[data-sb-remove-section]', {sbRemoveSection: '0', sbSection: 'A'});
    assert.deepEqual(plain(mounted.instance.read().course_groups[0].sections), ['B']); assert.deepEqual(plain(mounted.instance.read().course_groups[1].sections), ['B', 'C']);
    assert.deepEqual(summaryCourseGroups(mounted), [
        {label: 'BSIT \u00b7 2nd Year', chips: ['2B']}, {label: 'BSN \u00b7 3rd Year', chips: ['3B', '3C']},
    ], 'Removing a section updates only the matching course in the live summary');
    mounted.select(0, 'sbGroupSelectAll', ''); assert.deepEqual(plain(mounted.instance.read().course_groups[0].sections), alphabet, 'Unconfigured course/year Select All selects A–Z');
    mounted.select(0, 'sbGroupSelectAll', ''); assert.deepEqual(plain(mounted.instance.read().course_groups[0].sections), [], 'Select All toggles off');
    mounted.select(0, 'sbGroupSelectAll', '');
    mounted.change('venue', 'New venue');
    assert.equal(JSON.parse(mounted.entries.get(mounted.key)).venue, 'New venue');
    assert.equal(JSON.parse(mounted.entries.get(mounted.key)).draft_version, 3);
    mounted.click('[data-sb-draft]'); assert.equal(mounted.saves.length, 1); assert.equal(mounted.saves[0].body.status, 'Draft'); assert.equal(mounted.saves[0].body.active, false);
    assert.deepEqual(mounted.saves[0].body.course_sections_by_year, {'11': {'2': alphabet}, '12': {'3': ['B', 'C']}});
    assert.equal(mounted.entries.has(mounted.key), true); assert.equal(mounted.navigations(), 0);
    mounted.saves[0].onSaved(); assert.equal(mounted.entries.has(mounted.key), false); assert.equal(mounted.navigations(), 1);
    const incomplete = mountedForm(null); assert.equal(incomplete.cards.size, 1); incomplete.click('[data-sb-draft]'); assert.equal(incomplete.saves.length, 0); assert.equal(incomplete.error.hidden, false);
    assert.match(incomplete.cards.get('0').html, /data-sb-remove-group="0" disabled/);
    const catalogMounted = mountedForm(null, false, catalogState);
    assert.equal(cardControl(catalogMounted, 0, 'sbGroupCourse').disabled, true, 'Course starts locked until a department is selected');
    assert.equal(cardControl(catalogMounted, 0, 'sbGroupMajor').disabled, true, 'Major starts locked until a course is selected');
    catalogMounted.select(0, 'sbGroupDepartment', 'College of Business Education');
    assert.equal(cardControl(catalogMounted, 0, 'sbGroupCourse').disabled, false); assert.match(catalogMounted.cards.get('0').html, /Bachelor of Science in Business Administration/);
    catalogMounted.select(0, 'sbGroupCourse', '50');
    assert.equal(cardControl(catalogMounted, 0, 'sbGroupMajor').disabled, false, 'Major unlocks for a course that offers majors');
    assert.ok(catalogMounted.cards.get('0').entries.filter(input => input.dataset.sbGroupYear !== undefined).every(input => input.disabled), 'Year stays locked until the required major is selected');
    catalogMounted.select(0, 'sbGroupMajor', 'Marketing Management');
    assert.ok(catalogMounted.cards.get('0').entries.filter(input => input.dataset.sbGroupYear !== undefined).every(input => !input.disabled), 'Year unlocks after the academic path is complete');
    incomplete.click('[data-sb-remove-group]', {sbRemoveGroup: '0'}, {disabled: true}); assert.equal(incomplete.confirmDialog.open, false); assert.equal(incomplete.instance.read().course_groups.length, 1, 'At least one card remains');
    assertCardRanges(incomplete, 0);
    assert.ok(incomplete.cards.get('0').entries.filter(input => input.dataset.sbGroupSection !== undefined).every(input => input.disabled), 'A–Z is visible but unavailable until course/year are chosen');
    const configured = mountedForm(groupedValid);
    assertCardRanges(configured, 0);
    assert.equal(cardControl(configured, 0, 'sbGroupSection', 'A').disabled, false);
    assert.equal(cardControl(configured, 0, 'sbGroupSection', 'B').disabled, false);
    for (const section of alphabet.slice(2)) assert.equal(cardControl(configured, 0, 'sbGroupSection', section).disabled, true, section + ' is displayed but not configured for BSIT 3');
    assert.match(configured.cards.get('0').html, /not configured|configured sections|configured for/i, 'The disabled choices have a clear explanation');
    configured.select(0, 'sbGroupSelectAll', ''); assert.deepEqual(plain(configured.instance.read().course_groups[0].sections), ['A', 'B'], 'Select All selects only configured sections');
    assert.ok(configured.cards.get('0').entries.filter(input => input.dataset.sbGroupSection !== undefined && input.disabled).every(input => !input.checked), 'Select All never checks disabled unconfigured sections');
    configured.select(0, 'sbGroupSelectAll', ''); assert.deepEqual(plain(configured.instance.read().course_groups[0].sections), []);
    const configuredCustom = mountedForm(groupedValid, false, customReferences);
    assertCardRanges(configuredCustom, 0, ['1', '2', '3', '4'], [...alphabet, 'AA']);
    assert.equal(cardControl(configuredCustom, 0, 'sbGroupSection', 'AA').disabled, false);
    configuredCustom.select(0, 'sbGroupSelectAll', ''); assert.deepEqual(plain(configuredCustom.instance.read().course_groups[0].sections), ['A', 'B', 'AA']);
    const fallback = mountedForm(null, false, emptyReferences);
    fallback.select(0, 'sbGroupCourse', '11'); fallback.select(0, 'sbGroupYear', '2'); fallback.select(0, 'sbGroupSection', 'Z'); assertCardRanges(fallback, 0);
    for (const section of alphabet) assert.equal(cardControl(fallback, 0, 'sbGroupSection', section).disabled, false, section + ' is eligible under the unconfigured fallback');
    for (const [name, value] of [['date', valid.date], ['venue', valid.venue]]) fallback.change(name, value);
    await fallback.submit('publish'); assert.equal(fallback.saves.length, 1);
    assert.deepEqual(fallback.saves[0].body.course_sections_by_year, {'11': {'2': ['Z']}});
    assert.deepEqual(fallback.saves[0].body.year_levels, ['2']);
    const removing = mountedForm(); removing.click('[data-sb-remove-group]', {sbRemoveGroup: '1'}); assert.equal(removing.confirmDialog.open, true); assert.equal(removing.instance.read().course_groups.length, 2);
    removing.click('[data-sb-close-create-confirm]'); assert.equal(removing.instance.read().course_groups.length, 2);
    removing.click('[data-sb-remove-group]', {sbRemoveGroup: '1'}); removing.click('[data-sb-create-confirm-action]'); assert.equal(removing.instance.read().course_groups.length, 1);
    const duplicateCard = mountedForm(groupedValid); duplicateCard.click('[data-sb-add-group]'); duplicateCard.select(1, 'sbGroupCourse', '11');
    assert.equal(duplicateCard.cards.get('1').entries.find(input => input.dataset.sbGroupYear !== undefined && input.value === '3').disabled, true, 'A repeated course/year pair is unavailable');
    assert.equal(duplicateCard.cards.get('1').entries.find(input => input.dataset.sbGroupYear !== undefined && input.value === '2').disabled, false, 'The same course can select another year');
    const adding = mountedForm(groupedValid); adding.click('[data-sb-add-group]');
    assert.deepEqual(plain(adding.instance.read().course_groups[1]), {department: '', course_id: '', major: '', year_level: '', sections: []});
    adding.select(1, 'sbGroupCourse', '12'); adding.select(1, 'sbGroupYear', '3'); adding.select(1, 'sbGroupSection', 'C');
    assert.deepEqual(plain(adding.instance.read().course_groups[1]), {department: 'Other / Legacy Courses', course_id: '12', major: '', year_level: '3', sections: ['C']});
    adding.select(0, 'sbGroupYear', '2'); assert.deepEqual(plain(adding.instance.read().course_groups[1]), {department: 'Other / Legacy Courses', course_id: '12', major: '', year_level: '3', sections: ['C']}, 'Each year control changes only its card');
    const publishingDraft = mountedForm({...twoCards, status: 'Draft'}); await publishingDraft.submit('publish'); assert.equal(publishingDraft.saves.length, 0); assert.match(publishingDraft.error.textContent, /Draft|draft|status|publish/);
    const publishingClosed = mountedForm({...twoCards, status: 'Closed'}); await publishingClosed.submit('publish'); assert.equal(publishingClosed.saves[0].body.status, 'Closed'); publishingClosed.saves[0].onSaved(); assert.equal(publishingClosed.navigations(), 1);
    const another = mountedForm({...twoCards, status: 'Draft'}); await another.submit('another'); assert.equal(another.saves[0].body.status, 'Draft'); assert.equal(another.instance.read().course_groups.length, 2);
    another.saves[0].onSaved(); assert.deepEqual(plain(another.instance.read().course_groups), [{department: '', course_id: '', major: '', year_level: '', sections: []}]); assert.equal(another.instance.read().date, ''); assert.equal(another.form.dataset.dirty, 'false'); assert.equal(another.navigations(), 0);
    const reset = mountedForm(); reset.click('[data-sb-reset]'); assert.equal(reset.confirmDialog.open, true); assert.equal(reset.instance.read().course_groups.length, 2);
    reset.click('[data-sb-close-create-confirm]'); assert.equal(reset.confirmDialog.open, false); assert.equal(reset.instance.read().course_groups.length, 2);
    reset.click('[data-sb-reset]'); reset.confirmDialog.close(); assert.equal(reset.instance.read().course_groups.length, 2, 'Native Escape/backdrop dismissal retains the unfinished entry');
    reset.click('[data-sb-reset]'); reset.click('[data-sb-create-confirm-action]'); assert.deepEqual(plain(reset.instance.read().course_groups), [{department: '', course_id: '', major: '', year_level: '', sections: []}]); assert.equal(reset.instance.read().date, '');
    const back = mountedForm(); back.change('venue', 'Unsaved venue'); back.click('[data-sb-create-back]'); assert.equal(back.confirmDialog.open, true); assert.equal(back.navigations(), 0); back.click('[data-sb-create-confirm-action]'); assert.equal(back.navigations(), 1);
    const serverRejected = mountedForm(); await serverRejected.submit('publish'); serverRejected.saves[0].onError({message: 'Rejected', errors: {venue: ['This venue is already reserved.']}}); assert.equal(serverRejected.error.hidden, false); assert.match(serverRejected.error.textContent, /venue is already reserved/); assert.equal(serverRejected.entries.has(serverRejected.key), true);
    const noStorage = mountedForm(twoCards, true); await noStorage.submit('publish'); assert.equal(noStorage.saves.length, 1);
    const restored = mountedForm({...valid, course_ids: [11, 12], year_levels: ['2', '3']}); assert.equal(restored.cards.size, 4, 'Legacy browser drafts restore every course/year card');
    const requests = [], newCourse = {id: 99, type: 'course', name: 'Fourth Newly Created Course'};
    const addCourse = mountedForm(twoCards, false, configuredState, async (url, method, body) => {
        requests.push({url, method, body}); return url === '/api/admin/academic-options' ? {message: 'Course added.'} : {academic_options: [...configuredState.academic_options, newCourse], course_year_sections: configuredState.course_year_sections};
    });
    addCourse.click('[data-sb-add-group]'); addCourse.click('[data-sb-add-course]', {sbAddCourse: '2'}); assert.equal(addCourse.courseDialog.open, true);
    addCourse.courseForm.elements.name.value = newCourse.name; await addCourse.courseForm.listeners.submit({preventDefault() {}, target: addCourse.courseForm});
    assert.equal(addCourse.courseDialog.open, false); assert.deepEqual(plain(requests[0]), {url: '/api/admin/academic-options', method: 'POST', body: {type: 'course', name: newCourse.name}});
    assert.equal(addCourse.instance.read().course_groups[2].course_id, '99', 'The real Add Course endpoint refreshes the chosen card');
    assert.deepEqual(plain(addCourse.instance.read().course_groups.slice(0, 2)), twoCards.course_groups.map(group => ({department: 'Other / Legacy Courses', major: '', ...group})), 'Adding a database course retains other cards');
}

async function verifyCourseManagement() {
    const unused = {id: 99, type: 'course', name: 'Unused Course'};
    let options = [...configuredState.academic_options, unused], releaseRename, rejectDelete = true;
    const requests = [], draft = {...twoCards, course_groups: [...twoCards.course_groups, {course_id: '99', year_level: '', sections: []}]};
    const ui = mountedForm(draft, false, {...configuredState, academic_options: options}, async (url, method, body) => {
        requests.push({url, method, body});
        if (method === 'PATCH') {
            options = options.map(course => course.id === 11 ? {...course, name: body.name} : course);
            return new Promise(resolve => { releaseRename = resolve; });
        }
        if (method === 'DELETE') {
            if (rejectDelete) throw {message: 'This course has configured sections. Remove its unused sections first.'};
            options = options.filter(course => course.id !== 99); return {};
        }
        return {academic_options: options, course_year_sections: configuredState.course_year_sections};
    });
    const manager = ui.get('[data-sb-course-manage]'), originalDraft = plain(ui.instance.read());
    const click = (key, id) => manager.listeners.click({preventDefault() {}, target: {closest: () => ({dataset: {[key]: String(id)}})}});
    assert.match(ui.root.innerHTML, /data-sb-course-manage/);
    ui.click('[data-sb-add-course]', {sbAddCourse: '0'});
    await click('cmEdit', 11); assert.match(manager.innerHTML, /value="BSIT"/);
    ui.get('[data-cm-name]').value = 'Information Technology'; const renaming = click('cmSave', 11);
    ui.click('[data-sb-close-course]'); assert.equal(ui.courseDialog.open, true, 'Do not dismiss a pending write');
    let prevented = false; ui.courseDialog.listeners.cancel({preventDefault() { prevented = true; }}); assert.equal(prevented, true);
    ui.courseForm.elements.name.value = 'Another Course'; await ui.courseForm.listeners.submit({preventDefault() {}, target: ui.courseForm});
    assert.equal(requests.length, 1, 'Add cannot race a course rename');
    releaseRename({academic_option: {name: 'Information Technology'}}); await renaming;
    assert.match(ui.cards.get('0').html, /Information Technology/);
    assert.match(ui.cards.get('1').html, /Information Technology/);
    assert.deepEqual(plain(ui.instance.read()), originalDraft, 'Rename preserves all selected IDs, sections, and unfinished schedule fields');
    assert.match(manager.innerHTML, /Unused Course/);
    await click('cmDelete', 99); assert.match(manager.innerHTML, /Delete course Unused Course\?/);
    const count = requests.length; await click('cmCancel', 99); assert.equal(requests.length, count, 'Cancel does not delete');
    await click('cmDelete', 99); await click('cmConfirm', 99);
    assert.match(manager.innerHTML, /configured sections/); assert.equal(ui.instance.read().course_groups[2].course_id, '99');
    rejectDelete = false; await click('cmConfirm', 99);
    assert.equal(ui.courseDialog.open, true, 'Management keeps the dialog open');
    assert.deepEqual(plain(ui.instance.read().course_groups), [
        ...twoCards.course_groups.map(group => ({department: 'Other / Legacy Courses', major: '', ...group})),
        {department: 'Other / Legacy Courses', course_id: '', major: '', year_level: '', sections: []},
    ]);
    assert.equal(ui.instance.read().venue, draft.venue); assert.equal(ui.instance.read().date, draft.date);
    assert.doesNotMatch(ui.cards.get('0').html, /Unused Course/); assert.doesNotMatch(ui.cards.get('1').html, /Unused Course/);
    assert.equal(JSON.parse(ui.entries.get(ui.key)).course_groups[2].course_id, '', 'The local draft drops the deleted selection too');
    ui.instance.destroy(); assert.equal(manager.listeners.click, undefined);
}

const editSchedule = {...existing, id: 90, title: 'A manually edited schedule name', date: valid.date, start_time: valid.start_time, end_time: valid.end_time, venue: valid.venue, status: 'Open', active: true, slot_limit: 25,
    year_levels: ['3'], sections: ['A'], course_sections: {'11': ['A']}, courses: [{id: 11, name: 'BSIT', pivot: {sections: ['A']}}],
    registrations_count: 1, registrations_total_count: 2, registrations_recorded_attendance_count: 1,
    assigned_academic_groups: [{course_id: 11, year_level: '3', section: 'A', count: 2}], theme: 'Faith', facilitator: 'Fr. John', description: 'Keep these existing details.'};
const editReferences = {...configuredState, schedules: [editSchedule], course_year_sections: [...configuredState.course_year_sections,
    {course_id: 11, year_level: '4', section: 'Z'}, {course_id: 12, year_level: '4', section: 'C'}]};
const restoredEditing = plain(api.restoreEdit(editSchedule));
assert.deepEqual(restoredEditing.course_groups, [{department: '', course_id: '11', major: '', year_level: '3', sections: ['A']}], 'Edit prefills the same card shape as Create');
const editing = {...restoredEditing, year_blocks: [{year_level: '3', course_ids: ['11'], course_sections: {'11': ['A']}}]};
delete editing.course_groups;
assert.deepEqual(editing.year_blocks, [{year_level: '3', course_ids: ['11'], course_sections: {'11': ['A']}}]);
assert.equal(Object.hasOwn(editing, 'title'), false); assert.equal(editing.slot_limit, '25'); assert.equal(editing.status, 'Open');
for (const name of ['theme', 'facilitator', 'description']) assert.equal(Object.hasOwn(editing, name), false, 'Edit ignores saved optional details');
assert.deepEqual(plain(api.editStatuses(editSchedule)), ['Open', 'Closed']);
assert.deepEqual(plain(api.editStatuses({...editSchedule, status: 'Closed'})), ['Closed', 'Open']);
assert.deepEqual(plain(api.editStatuses({...editSchedule, status: 'Draft'})), ['Draft', 'Open']);
assert.equal(api.evaluateEdit(editing, editReferences, editSchedule).canSave, true, 'The edited schedule itself is excluded from student and venue conflicts');
assert.equal(api.evaluateEdit(editing, editReferences, editSchedule).venueConflicts.length, 0);
const withSection = {...editing, year_blocks: [{year_level: '3', course_ids: ['11'], course_sections: {'11': ['A', 'B']}}]};
const addingSection = api.evaluateEdit(withSection, editReferences, editSchedule);
assert.equal(addingSection.canSave, true); assert.equal(addingSection.attendanceValid, true);
assert.deepEqual(plain(addingSection.changes.added.map(group => group.label)), ['BSIT 3B']);
assert.equal(addingSection.changes.pureAddition, true);
const secondBlock = {...withSection, year_blocks: [...withSection.year_blocks, {year_level: '4', course_ids: ['11', '12'], course_sections: {'11': ['Z'], '12': ['C']}}]};
const addingYear = api.evaluateEdit(secondBlock, editReferences, editSchedule);
assert.equal(addingYear.canSave, true);
assert.deepEqual(plain(addingYear.groups.map(group => group.label)), ['BSIT 3A', 'BSIT 3B', 'BSIT 4Z', 'BSN 4C']);
const editBody = plain(api.editPayload(secondBlock, editReferences, editSchedule));
assert.deepEqual(editBody.course_sections_by_year, {'11': {'3': ['A', 'B'], '4': ['Z']}, '12': {'4': ['C']}});
assert.deepEqual(editBody.year_levels, ['3', '4']); assert.deepEqual(editBody.sections, ['A', 'B', 'Z', 'C']);
assert.equal(editBody.slot_limit, 25); assert.equal(editBody.status, 'Open'); for (const name of ['title', 'theme', 'facilitator', 'description']) assert.equal(Object.hasOwn(editBody, name), false, name + ' is not sent by Edit');
assert.equal(Object.hasOwn(editBody, 'course_sections'), false);
const threeCourses = {...editing, year_blocks: [{year_level: '3', course_ids: ['11', '12', '13'], course_sections: {'11': ['A'], '12': ['A'], '13': ['A']}}]};
assert.equal(api.evaluateEdit(threeCourses, editReferences, editSchedule).canSave, true, 'Editing no longer imposes a course-count cap');
assert.deepEqual(plain(api.editPayload(threeCourses, editReferences, editSchedule).course_ids), [11, 12, 13]);
const unlimitedCourses = [...state.academic_options.filter(option => option.type === 'course'), ...Array.from({length: 3}, (_, index) => ({id: index + 14, type: 'course', name: 'Additional Course ' + (index + 1)}))];
const unlimitedYears = ['1', '2', '3', '4'];
const unlimitedSections = [...'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'AA', 'AB', 'AC', 'AD'];
const unlimitedReferences = {...editReferences, academic_options: [...editReferences.academic_options,
    ...unlimitedCourses.filter(course => course.id >= 14),
    ...['5', '6'].map((name, index) => ({id: index + 41, type: 'year_level', name})),
    ...unlimitedSections.slice(26).map((name, index) => ({id: index + 51, type: 'section', name})),
], course_year_sections: unlimitedCourses.flatMap(course => unlimitedYears.flatMap(year_level => unlimitedSections.map(section => ({course_id: course.id, year_level, section}))))};
const unlimitedEdit = {...editing, year_blocks: unlimitedYears.map(year_level => ({year_level, course_ids: unlimitedCourses.map(course => String(course.id)), course_sections: Object.fromEntries(unlimitedCourses.map(course => [String(course.id), [...unlimitedSections]]))}))};
const unlimitedEvaluation = api.evaluateEdit(unlimitedEdit, unlimitedReferences, editSchedule);
assert.equal(unlimitedEvaluation.canSave, true, 'Any number of configured courses, distinct year blocks, and configured sections may be added');
assert.equal(unlimitedEvaluation.groups.length, 6 * 4 * 30);
assert.equal(unlimitedEvaluation.assignedValid, true); assert.equal(unlimitedEvaluation.attendanceValid, true);
const unlimitedBody = plain(api.editPayload(unlimitedEdit, unlimitedReferences, editSchedule));
assert.deepEqual(unlimitedBody.course_ids, unlimitedCourses.map(course => course.id)); assert.deepEqual(unlimitedBody.year_levels, unlimitedYears);
for (const course of unlimitedCourses) for (const year of unlimitedYears) assert.deepEqual(unlimitedBody.course_sections_by_year[course.id][year], unlimitedSections);
assert.equal(unlimitedBody.slot_limit, 25, 'Unlimited group selection leaves capacity unchanged');
const nestedSchedule = {...editSchedule, year_levels: ['3', '4'], courses: [{id: 11, name: 'BSIT', pivot: {sections: {'3': ['A'], '4': ['Z']}}}, {id: 12, name: 'BSN', pivot: {sections: {'4': ['C']}}}],
    course_sections_by_year: {'11': {'3': ['A'], '4': ['Z']}, '12': {'4': ['C']}}, course_sections: {'11': ['A', 'Z'], '12': ['C']}, sections: ['A', 'Z', 'C']};
assert.deepEqual(plain(api.restoreEdit(nestedSchedule).course_groups), [{department: '', course_id: '11', major: '', year_level: '3', sections: ['A']}, {department: '', course_id: '11', major: '', year_level: '4', sections: ['Z']}, {department: '', course_id: '12', major: '', year_level: '4', sections: ['C']}]);
assert.deepEqual(plain(api.scheduleSections(nestedSchedule, 12, '3')), []);
assert.deepEqual(plain(api.scheduleSections({...nestedSchedule, course_sections_by_year: undefined}, 11, '4')), ['Z']);
assert.equal(check({course_ids: ['12'], course_sections: {'12': ['C']}}, [nestedSchedule], configuredState).studentConflicts.length, 0, 'Create conflicts respect exact nested year scopes');
assert.equal(check({course_ids: ['12'], year_levels: ['4'], course_sections: {'12': ['C']}}, [nestedSchedule], editReferences).studentConflicts.length, 1);
for (const bad of [
    {...secondBlock, year_blocks: [...secondBlock.year_blocks, secondBlock.year_blocks[0]]},
    {...secondBlock, year_blocks: [...withSection.year_blocks, {year_level: '4', course_ids: ['11'], course_sections: {'11': ['A']}}]},
    {...secondBlock, year_blocks: [...withSection.year_blocks, {year_level: '4', course_ids: ['11'], course_sections: {'11': []}}]},
    {...editing, year_blocks: [{year_level: '3', course_ids: ['11', '999'], course_sections: {'11': ['A'], '999': ['A']}}]},
    {...editing, year_blocks: [{year_level: '3', course_ids: ['11', '11'], course_sections: {'11': ['A']}}]},
    {...editing, slot_limit: '1'}, {...editing, date: '2026-10-21'}, {...editing, start_time: '09:00'}, {...editing, end_time: '15:00'}, {...editing, venue: 'Changed venue'},
    {...editing, status: 'Draft'}, {...editing, year_blocks: [{year_level: '3', course_ids: ['11'], course_sections: {'11': ['B']}}]},
]) { assert.equal(api.evaluateEdit(bad, editReferences, editSchedule).canSave, false, JSON.stringify(bad)); assert.throws(() => api.editPayload(bad, editReferences, editSchedule)); }
for (const status of ['Cancelled', 'Completed']) assert.equal(api.evaluateEdit(secondBlock, editReferences, {...editSchedule, status}).canSave, false);
assert.equal(api.editPayload(editing, editReferences, {...editSchedule, status: 'Draft'}, true).status, 'Open');
assert.equal(api.editPayload({...editing, status: 'Closed'}, editReferences, editSchedule).status, 'Closed');
const conflict = {...nestedSchedule, id: 91, title: 'Conflicting year four', courses: [{id: 11, name: 'BSIT', pivot: {sections: {'4': ['Z']}}}], course_sections_by_year: {'11': {'4': ['Z']}}, year_levels: ['4']};
assert.equal(api.evaluateEdit(secondBlock, {...editReferences, schedules: [editSchedule, conflict]}, editSchedule).studentConflicts.length, 1);
assert.equal(api.evaluateEdit(secondBlock, {...editReferences, schedules: [editSchedule, {...conflict, course_sections_by_year: {'11': {'4': ['A']}}}]}, editSchedule).studentConflicts.length, 0);
const previousConflict = {...editSchedule, id: 92};
assert.equal(api.evaluateEdit(withSection, {...editReferences, schedules: [editSchedule, previousConflict]}, editSchedule).canSave, true, 'Pure additions validate newly added groups without treating an old group as a new conflict');
const legacyEdit = {...editSchedule, courses: [{id: 11, name: 'BSIT'}, {id: 12, name: 'BSN'}], course_sections: {}, year_levels: ['3', '4'], sections: ['A', 'B'], registrations_recorded_attendance_count: 0};
assert.deepEqual(plain(api.restoreEdit(legacyEdit).course_groups), ['3', '4'].flatMap(year_level => ['11', '12'].map(course_id => ({department: '', course_id, major: '', year_level, sections: ['A', 'B']}))));
assert.equal(api.evaluateEdit(api.restoreEdit(legacyEdit), {...editReferences, schedules: [legacyEdit]}, legacyEdit).canSave, true, 'Existing legacy groups are retained even if later course configuration differs');
assert.equal(api.evaluateEdit(withSection, editReferences, {...editSchedule, assigned_academic_groups: [{course_id: 11, year_level: '4', section: 'Z', count: 1}]}).canSave, true, 'Pure additions do not exclude students whose profiles changed after assignment');
const changedProfileSchedule = {...editSchedule, assigned_academic_groups: [{course_id: 11, year_level: '4', section: 'Z', count: 1}]};
assert.equal(api.evaluateEdit({...editing, slot_limit: '26'}, editReferences, changedProfileSchedule).canSave, true, 'An unchanged group scope permits capacity edits when an assigned student profile has changed');
const removingFromChangedProfile = {...changedProfileSchedule, course_sections: {'11': ['A', 'B']}, sections: ['A', 'B'], courses: [{id: 11, name: 'BSIT', pivot: {sections: ['A', 'B']}}]};
assert.equal(api.evaluateEdit(editing, {...editReferences, schedules: [removingFromChangedProfile]}, removingFromChangedProfile).canSave, false, 'Removing a group retains the assigned-student exclusion check');
const cardEditAddition = {...restoredEditing, course_groups: [{course_id: '11', year_level: '3', sections: ['A', 'B']}, {course_id: '11', year_level: '4', sections: ['Z']}, {course_id: '12', year_level: '3', sections: ['C']}]};
assert.equal(api.evaluateEdit(cardEditAddition, editReferences, editSchedule).canSave, true, 'Different courses may share a year and the same course may have a different year card');
assert.deepEqual(plain(api.editPayload(cardEditAddition, editReferences, editSchedule).course_sections_by_year), {'11': {'3': ['A', 'B'], '4': ['Z']}, '12': {'3': ['C']}});
assert.equal(api.evaluateEdit({...cardEditAddition, course_groups: [...cardEditAddition.course_groups, {course_id: '11', year_level: '4', sections: ['Z']}]}, editReferences, editSchedule).canSave, false, 'A duplicate course/year pair is rejected');
assert.equal(api.evaluateEdit({...restoredEditing, course_groups: [{course_id: '11', year_level: '3', sections: ['A', 'C']}]}, editReferences, editSchedule).canSave, false, 'New unconfigured sections are rejected');
assert.equal(api.evaluateEdit({...restoredEditing, course_groups: [...restoredEditing.course_groups, {course_id: '11', year_level: '5', sections: ['A']}]}, extendedYears, editSchedule).canSave, false, 'Edit cannot introduce a year outside 1–4 merely because it is a global reference');
const savedLegacyYear = {...editSchedule, year_levels: ['5'], year_level: '5', sections: ['Legacy'], section: 'Legacy', course_sections_by_year: {'11': {'5': ['Legacy']}}, course_sections: {'11': ['Legacy']}, courses: [{id: 11, name: 'BSIT', pivot: {sections: {'5': ['Legacy']}}}], registrations_count: 0, registrations_total_count: 0, registrations_recorded_attendance_count: 0, assigned_academic_groups: []};
assert.equal(api.evaluateEdit(api.restoreEdit(savedLegacyYear), {...extendedYears, schedules: [savedLegacyYear]}, savedLegacyYear).canSave, true, 'An old saved year and custom section remain valid without rewriting that schedule');
assert.deepEqual(plain(api.editPayload(api.restoreEdit(savedLegacyYear), {...extendedYears, schedules: [savedLegacyYear]}, savedLegacyYear).course_sections_by_year), {'11': {'5': ['Legacy']}});
const unlimitedCardValues = {...restoredEditing, course_groups: unlimitedYears.flatMap(year_level => unlimitedCourses.map(course => ({course_id: String(course.id), year_level, sections: [...unlimitedSections]})))};
assert.equal(api.evaluateEdit(unlimitedCardValues, unlimitedReferences, editSchedule).canSave, true);
assert.equal(api.evaluateEdit(unlimitedCardValues, unlimitedReferences, editSchedule).groups.length, 720, 'The shared card form has no course or configured section count cap within years 1–4');
assert.deepEqual(plain(api.editPayload(unlimitedCardValues, unlimitedReferences, editSchedule).course_sections_by_year), unlimitedBody.course_sections_by_year);
const noAttendanceSchedule = {...editSchedule, registrations_recorded_attendance_count: 0};
assert.equal(api.evaluateEdit({...restoredEditing, date: '2027-10-20'}, {...editReferences, schedules: [noAttendanceSchedule]}, noAttendanceSchedule).calendarYearValid, false);
assert.throws(() => api.editPayload({...restoredEditing, date: '2027-10-20'}, {...editReferences, schedules: [noAttendanceSchedule]}, noAttendanceSchedule), /recollection year|assigned/i);
assert.equal(api.evaluateEdit({...restoredEditing, date: '2026-10-21'}, {...editReferences, schedules: [noAttendanceSchedule]}, noAttendanceSchedule).canSave, true, 'Before attendance, an assigned schedule may change date within its recollection year');
const earlierCoLocated = {...noAttendanceSchedule, id: 93, courses: [{id: 12, name: 'BSN', pivot: {sections: ['C']}}], course_sections: {'12': ['C']}, sections: ['C']};
const venueReferences = {...editReferences, schedules: [noAttendanceSchedule, earlierCoLocated]};
assert.equal(api.evaluateEdit(restoredEditing, venueReferences, noAttendanceSchedule).venueConflicts.length, 1);
assert.equal(api.evaluateEdit(restoredEditing, venueReferences, noAttendanceSchedule).canSave, true, 'Unchanged legacy co-location is retained');
assert.equal(api.evaluateEdit({...restoredEditing, start_time: '09:00'}, venueReferences, noAttendanceSchedule).canSave, false, 'A changed booking is blocked by another non-cancelled schedule in the venue');
assert.throws(() => api.editPayload({...restoredEditing, start_time: '09:00'}, venueReferences, noAttendanceSchedule), /venue/i);
function mountedEdit(schedule = editSchedule, references = editReferences, request) {
    return mountedForm(twoCards, false, references, request, schedule);
}
function cardControl(mounted, index, name, value) {
    return mounted.cards.get(String(index)).entries.find(input => input.dataset[name] !== undefined && (value === undefined || input.value === value));
}
function assertCardRanges(mounted, index, years = ['1', '2', '3', '4'], sections = alphabet) {
    const card = mounted.cards.get(String(index));
    assert.deepEqual(card.entries.filter(input => input.dataset.sbGroupYear !== undefined).map(input => input.value), years, 'The year choices are 1–4, with only saved legacy values appended');
    assert.deepEqual(card.entries.filter(input => input.dataset.sbGroupSection !== undefined).map(input => input.value), sections, 'All A–Z sections appear in order, with configured or saved extras appended');
    for (const year of ['1', '2', '3', '4']) assert.match(card.html, new RegExp('data-sb-group-year="' + index + '"[^>]*><span>' + year + '</span>'), 'Year ' + year + ' has a numeric label');
}
function cardAddition(mounted, courseId, year, section) {
    mounted.click('[data-sb-add-group]');
    const index = mounted.instance.read().course_groups.length - 1;
    mounted.select(index, 'sbGroupCourse', String(courseId)); mounted.select(index, 'sbGroupYear', String(year));
    if (section) mounted.select(index, 'sbGroupSection', section);
    return index;
}
async function verifyEditSharedHandlers() {
    const editMounted = mountedEdit();
    assert.deepEqual(summaryCourseGroups(editMounted), [{label: 'BSIT \u00b7 3rd Year', chips: ['3A']}],
        'Edit prefills the same organized summary course/year headings and section chips');
    assert.deepEqual(summaryRowLabels(editMounted), ['Course(s)', 'New groups', 'Date', 'Time', 'Venue', 'Capacity', 'Status']);
    assert.match(editMounted.get('[data-sb-summary]').innerHTML, /<span class="sb-status-pill">Open for Registration<\/span>/,
        'An existing Open schedule uses the same visible registration label as Create');
    assert.equal(editMounted.get('.sb-summary-status').textContent, 'Open');
    assert.deepEqual(editMounted.storageCalls, [], 'Edit never reads or writes the unfinished Create entry');
    assert.equal(editMounted.inputs.venue.value, editSchedule.venue); assert.doesNotMatch(editMounted.root.innerHTML, /Schedule name and optional details|Recollection name|name="(?:title|theme|facilitator|description)"/);
    assert.equal(editMounted.inputs.slot_limit.value, '25');
    assert.equal(editMounted.form.dataset.dirty, 'false'); assert.match(editMounted.root.innerHTML, /sb-grouped/);
    assert.match(editMounted.root.innerHTML, /data-sb-course-groups/); assert.match(editMounted.root.innerHTML, /Add Another Course Group/);
    assert.doesNotMatch(editMounted.root.innerHTML, /data-sb-year-blocks|data-sb-add-year|sb-year-block/);
    assert.match(editMounted.root.innerHTML, /data-sb-save-changes/); assert.doesNotMatch(editMounted.root.innerHTML, /Save &amp; Create Another|data-sb-draft>/);
    assert.match(editMounted.root.innerHTML, /Date, time and venue are locked because attendance has been recorded/);
    for (const name of ['date', 'start_time', 'end_time', 'venue']) assert.equal(editMounted.inputs[name].disabled, true, name + ' remains locked');
    assert.equal(editMounted.inputs.slot_limit.min, '2'); assert.match(editMounted.root.innerHTML, /Cannot be lower than the 2 students already assigned/);
    assert.deepEqual(plain(editMounted.instance.read().course_groups), restoredEditing.course_groups.map(group => ({...group, department: 'Other / Legacy Courses'})), 'Disabled checked assigned groups are included in the update');
    assert.equal(cardControl(editMounted, 0, 'sbGroupCourse').disabled, true);
    assert.ok(editMounted.cards.get('0').entries.filter(input => input.dataset.sbGroupYear !== undefined).every(input => input.disabled));
    assert.equal(cardControl(editMounted, 0, 'sbGroupSection', 'A').disabled, true); assert.equal(cardControl(editMounted, 0, 'sbRemoveGroup').disabled, true);
    assertCardRanges(editMounted, 0);
    for (const section of alphabet.slice(2)) assert.equal(cardControl(editMounted, 0, 'sbGroupSection', section).disabled, true, 'Edit shows unconfigured ' + section + ' without allowing it');
    assert.match(editMounted.cards.get('0').html, /Already included/); assert.match(editMounted.cards.get('0').html, /Students are already assigned to this group/);
    editMounted.select(0, 'sbGroupSection', 'B');
    assert.match(editMounted.get('[data-sb-added-summary]').textContent, /You are adding: BSIT 3B[.] Capacity stays at 25[.]/);
    assert.match(editMounted.cards.get('0').html, /New/); assert.match(editMounted.get('[data-sb-checks]').innerHTML, /One year level per (?:course )?(?:group|card)/);
    await editMounted.submit('changes'); assert.equal(editMounted.saves.length, 0); assert.equal(editMounted.confirmDialog.open, true);
    assert.match(editMounted.get('[data-sb-create-confirm-text]').textContent, /BSIT 3B/);
    editMounted.click('[data-sb-create-confirm-action]'); assert.equal(editMounted.saves.length, 1);
    assert.deepEqual(editMounted.saves[0].body.course_sections_by_year, {'11': {'3': ['A', 'B']}});
    for (const name of ['title', 'theme', 'facilitator', 'description']) assert.equal(Object.hasOwn(editMounted.saves[0].body, name), false);
    editMounted.saves[0].onSaved({message: 'Saved'}); assert.equal(editMounted.navigations(), 1); assert.equal(editMounted.form.dataset.dirty, 'false');
    assert.deepEqual(editMounted.storageCalls, []); assert.deepEqual(JSON.parse(editMounted.entries.get(editMounted.key)), twoCards, 'Saving Edit leaves the independent Create draft untouched');

    const editNewYear = mountedEdit(); cardAddition(editNewYear, 11, '4', 'Z');
    assert.deepEqual(plain(editNewYear.instance.read().course_groups), [
        ...restoredEditing.course_groups.map(group => ({...group, department: 'Other / Legacy Courses'})),
        {department: 'Other / Legacy Courses', course_id: '11', major: '', year_level: '4', sections: ['Z']},
    ]);
    assert.equal(cardControl(editNewYear, 1, 'sbGroupYear', '3').disabled, true, 'Repeated course/year pair cannot be chosen');
    assert.match(editNewYear.get('[data-sb-added-summary]').textContent, /BSIT 4Z/);
    cardAddition(editNewYear, 12, '3', 'C');
    assert.deepEqual(plain(api.editPayload(editNewYear.instance.read(), editReferences, editSchedule).course_sections_by_year), {'11': {'3': ['A'], '4': ['Z']}, '12': {'3': ['C']}});
    assert.match(editNewYear.get('[data-sb-summary]').innerHTML, /BSN/); assert.match(editNewYear.get('[data-sb-summary]').innerHTML, /New groups/);
    assert.deepEqual(summaryCourseGroups(editNewYear), [
        {label: 'BSIT \u00b7 3rd Year', chips: ['3A']}, {label: 'BSIT \u00b7 4th Year', chips: ['4Z']},
        {label: 'BSN \u00b7 3rd Year', chips: ['3C']},
    ], 'Edit summary renders newly added groups independently without inventing course/year combinations');
    editNewYear.click('[data-sb-remove-section]', {sbRemoveSection: '1', sbSection: 'Z'});
    assert.deepEqual(plain(editNewYear.instance.read().course_groups[1].sections), []); assert.deepEqual(plain(editNewYear.instance.read().course_groups[2].sections), ['C'], 'Chip removal affects its own card only');
    editNewYear.select(0, 'sbGroupSelectAll', ''); editNewYear.select(0, 'sbGroupSelectAll', '');
    assert.deepEqual(plain(editNewYear.instance.read().course_groups[0].sections), ['A'], 'Select All deselection preserves assigned sections');
    const nestedMounted = mountedEdit(nestedSchedule);
    assert.deepEqual(plain(nestedMounted.instance.read().course_groups), plain(api.restoreEdit(nestedSchedule).course_groups.map(group => ({...group, department: 'Other / Legacy Courses'}))), 'Each saved nested pair has its own prefilled card'); assert.equal(nestedMounted.cards.size, 3, 'No phantom Cartesian groups');
    assert.deepEqual(summaryCourseGroups(nestedMounted), [
        {label: 'BSIT \u00b7 3rd Year', chips: ['3A']}, {label: 'BSIT \u00b7 4th Year', chips: ['4Z']},
        {label: 'BSN \u00b7 4th Year', chips: ['4C']},
    ], 'The Edit preview keeps saved nested sections under their exact course/year headings');
    const legacyMounted = mountedEdit(legacyEdit);
    assert.deepEqual(plain(legacyMounted.instance.read().course_groups), plain(api.restoreEdit(legacyEdit).course_groups.map(group => ({...group, department: 'Other / Legacy Courses'}))), 'Old flat and legacy selections are preserved');
    const savedCustom = {...editSchedule, registrations_count: 0, registrations_total_count: 0, registrations_recorded_attendance_count: 0, assigned_academic_groups: [], sections: ['Legacy'], course_sections: {'11': ['Legacy']}, courses: [{id: 11, name: 'BSIT', pivot: {sections: ['Legacy']}}]};
    const customMounted = mountedEdit(savedCustom);
    assert.ok(cardControl(customMounted, 0, 'sbGroupSection', 'Legacy').checked, 'An old saved section remains available if the current configuration no longer includes it');
    assertCardRanges(customMounted, 0, ['1', '2', '3', '4'], [...alphabet, 'Legacy']);
    customMounted.select(0, 'sbGroupSection', 'Legacy'); assert.deepEqual(plain(customMounted.instance.read().course_groups[0].sections), []);
    assert.ok(cardControl(customMounted, 0, 'sbGroupSection', 'Legacy'), 'Unchecking a grandfathered section does not remove the option');
    customMounted.select(0, 'sbGroupSection', 'Legacy'); assert.deepEqual(plain(customMounted.instance.read().course_groups[0].sections), ['Legacy']);
    assert.equal(api.evaluateEdit(customMounted.instance.read(), editReferences, savedCustom).canSave, true);
    const legacyYearMounted = mountedEdit(savedLegacyYear, {...extendedYears, schedules: [savedLegacyYear]});
    assertCardRanges(legacyYearMounted, 0, ['1', '2', '3', '4', '5'], [...alphabet, 'Legacy']);
    assert.ok(cardControl(legacyYearMounted, 0, 'sbGroupYear', '5').checked, 'The saved legacy year is prefilled instead of discarded');
    legacyYearMounted.click('[data-sb-add-group]'); legacyYearMounted.select(1, 'sbGroupCourse', '12');
    assertCardRanges(legacyYearMounted, 1);
    assert.equal(cardControl(legacyYearMounted, 1, 'sbGroupYear', '5'), undefined, 'A saved legacy year is not offered for a different course');
    assert.deepEqual(plain(legacyYearMounted.instance.read().course_groups[0]), {department: 'Other / Legacy Courses', course_id: '11', major: '', year_level: '5', sections: ['Legacy']});
    const lockedLegacy = mountedEdit({...savedCustom, registrations_count: 1, registrations_total_count: 1, assigned_academic_groups: [{course_id: 11, year_level: '3', section: 'Legacy', count: 1}]});
    assert.equal(cardControl(lockedLegacy, 0, 'sbGroupSection', 'Legacy').disabled, true);
    lockedLegacy.select(0, 'sbGroupSelectAll', ''); assert.deepEqual(plain(lockedLegacy.instance.read().course_groups[0].sections), ['A', 'B', 'Legacy'], 'Select All adds only configured sections while retaining a checked assigned legacy section');
    lockedLegacy.select(0, 'sbGroupSelectAll', ''); assert.deepEqual(plain(lockedLegacy.instance.read().course_groups[0].sections), ['Legacy'], 'Deselecting Select All keeps the assigned legacy section');
    assert.ok(lockedLegacy.cards.get('0').entries.filter(input => input.dataset.sbGroupSection !== undefined && input.disabled && input.value !== 'Legacy').every(input => !input.checked));
    const unlockedDate = mountedEdit(noAttendanceSchedule);
    assert.equal(unlockedDate.inputs.date.disabled, false); assert.equal(unlockedDate.inputs.date.min, '2026-01-01'); assert.equal(unlockedDate.inputs.date.max, '2026-12-31');
    assert.match(unlockedDate.root.innerHTML, /The recollection year cannot change because students are already assigned/);

    const unlimitedMounted = mountedEdit(editSchedule, unlimitedReferences);
    for (const id of [12, 13, 14, 15, 16]) { const index = cardAddition(unlimitedMounted, id, '3'); unlimitedMounted.select(index, 'sbGroupSelectAll', ''); }
    for (const year of unlimitedYears.filter(year => year !== '3')) { const index = cardAddition(unlimitedMounted, 11, year); unlimitedMounted.select(index, 'sbGroupSelectAll', ''); }
    const mountedUnlimitedValues = plain(unlimitedMounted.instance.read()); assert.equal(mountedUnlimitedValues.course_groups.length, 9);
    for (const card of mountedUnlimitedValues.course_groups.slice(1)) assert.deepEqual(card.sections, unlimitedSections, 'Select All retains every configured section without a count limit');
    await unlimitedMounted.submit('changes'); assert.equal(unlimitedMounted.saves.length, 0); assert.equal(unlimitedMounted.confirmDialog.open, true);
    unlimitedMounted.click('[data-sb-create-confirm-action]'); assert.equal(unlimitedMounted.saves.length, 1);
    assert.deepEqual(unlimitedMounted.saves[0].body.course_ids, [11, 12, 13, 14, 15, 16]); assert.equal(unlimitedMounted.saves[0].body.year_levels.length, 4); assert.equal(unlimitedMounted.saves[0].body.slot_limit, 25);
    const failedEdit = mountedEdit(); await failedEdit.submit('changes'); assert.equal(failedEdit.saves.length, 1);
    failedEdit.saves[0].onError({message: 'Rejected', errors: {course_sections_by_year: ['BSIT 4C is not configured.']}}); assert.equal(failedEdit.error.hidden, false); assert.equal(failedEdit.error.textContent, 'BSIT 4C is not configured.');
    assert.deepEqual(plain(failedEdit.instance.read().course_groups), restoredEditing.course_groups.map(group => ({...group, department: 'Other / Legacy Courses'})));
    const draftMounted = mountedEdit({...editSchedule, status: 'Draft'}); assert.match(draftMounted.root.innerHTML, /data-sb-draft/); assert.match(draftMounted.root.innerHTML, /Publish Schedule/); assert.doesNotMatch(draftMounted.root.innerHTML, /Save &amp; Create Another/);
    await draftMounted.submit('publish'); assert.equal(draftMounted.saves[0].body.status, 'Open');
    const closedMounted = mountedEdit({...editSchedule, status: 'Closed'}); assert.match(closedMounted.root.innerHTML, /data-sb-save-changes/); assert.doesNotMatch(closedMounted.root.innerHTML, /Publish Schedule|Save &amp; Create Another/);
    for (const status of ['Completed', 'Cancelled']) { const terminal = mountedEdit({...editSchedule, status}); assert.match(terminal.root.innerHTML, new RegExp(status + ' schedules are view only[.]')); assert.doesNotMatch(terminal.root.innerHTML, /data-sb-save-changes|data-sb-draft/); }
    const dirtyCancel = mountedEdit(); dirtyCancel.change('slot_limit', '30'); assert.equal(dirtyCancel.form.dataset.dirty, 'true'); dirtyCancel.click('[data-sb-create-back]');
    assert.equal(dirtyCancel.confirmDialog.open, true); assert.equal(dirtyCancel.navigations(), 0); dirtyCancel.click('[data-sb-close-create-confirm]'); assert.equal(dirtyCancel.navigations(), 0); assert.equal(dirtyCancel.inputs.slot_limit.value, '30');
    dirtyCancel.click('[data-sb-create-back]'); dirtyCancel.click('[data-sb-create-confirm-action]'); assert.equal(dirtyCancel.navigations(), 1);
    const cleanCancel = mountedEdit(); assert.equal(cleanCancel.click('[data-sb-create-back]').prevented, false, 'An unchanged Edit allows normal Back/Cancel link navigation'); assert.equal(cleanCancel.confirmDialog.open, false);

    const requests = [], newCourse = {id: 99, type: 'course', name: 'Fourth Newly Created Course'};
    const addingCourse = mountedEdit(editSchedule, unlimitedReferences, async (url, method, body) => { requests.push({url, method, body}); return url === '/api/admin/academic-options' ? {message: 'Course added.'} : {academic_options: [...unlimitedReferences.academic_options, newCourse], course_year_sections: unlimitedReferences.course_year_sections}; });
    for (const id of [12, 13, 14]) cardAddition(addingCourse, id, '3', 'A');
    addingCourse.click('[data-sb-add-group]'); addingCourse.click('[data-sb-add-course]', {sbAddCourse: '4'}); addingCourse.courseForm.elements.name.value = newCourse.name;
    await addingCourse.courseForm.listeners.submit({preventDefault() {}, target: addingCourse.courseForm}); assert.equal(addingCourse.courseDialog.open, false);
    assert.deepEqual(plain(requests[0]), {url: '/api/admin/academic-options', method: 'POST', body: {type: 'course', name: newCourse.name}}); assert.equal(addingCourse.instance.read().course_groups[4].course_id, '99', 'Add Course works after any number of other cards');
    addingCourse.select(4, 'sbGroupYear', '3'); addingCourse.select(4, 'sbGroupSection', 'A'); assert.deepEqual(plain(addingCourse.instance.read().course_groups[0]), {...restoredEditing.course_groups[0], department: 'Other / Legacy Courses'}, 'Adding a database course retains assigned group');
    for (const assigned of [true, false]) {
        const schedule = assigned ? editSchedule : {...editSchedule, registrations_count: 0, registrations_total_count: 0, registrations_recorded_attendance_count: 0, assigned_academic_groups: []};
        const fromExisting = mountedEdit(schedule, unlimitedReferences, async url => url === '/api/admin/academic-options' ? {message: 'Course added.'} : {academic_options: [...unlimitedReferences.academic_options, newCourse], course_year_sections: unlimitedReferences.course_year_sections});
        fromExisting.click('[data-sb-add-course]', {sbAddCourse: '0'}); fromExisting.courseForm.elements.name.value = newCourse.name;
        await fromExisting.courseForm.listeners.submit({preventDefault() {}, target: fromExisting.courseForm});
        assert.deepEqual(plain(fromExisting.instance.read().course_groups), [
            ...restoredEditing.course_groups.map(group => ({...group, department: 'Other / Legacy Courses'})),
            {department: 'Other / Legacy Courses', course_id: '99', major: '', year_level: '3', sections: []},
        ], 'Add Course from an existing card adds a new card and retains the saved group, including unassigned groups');
    }
    const sectionRequests = [], newSection = {id: 99, course_id: 11, year_level: '3', section: 'D'};
    const addingSection = mountedEdit(editSchedule, editReferences, async (url, method, body) => { sectionRequests.push({url, method, body}); return url === '/api/admin/course-year-sections' ? {message: 'Section added.'} : {academic_options: editReferences.academic_options, course_year_sections: [...editReferences.course_year_sections, newSection]}; });
    addingSection.click('[data-sb-add-section]', {sbAddSection: '0', sbCourseId: '11'}); assert.equal(addingSection.sectionDialog.open, true); addingSection.sectionForm.elements.section.value = 'D';
    await addingSection.sectionForm.listeners.submit({preventDefault() {}, target: addingSection.sectionForm}); assert.equal(addingSection.sectionDialog.open, false);
    assert.deepEqual(plain(sectionRequests[0]), {url: '/api/admin/course-year-sections', method: 'POST', body: {course_id: 11, year_level: '3', section: 'D'}}); assert.deepEqual(plain(addingSection.instance.read().course_groups[0].sections), ['A', 'D']);
    assert.deepEqual(plain(api.editPayload(addingSection.instance.read(), {...editReferences, course_year_sections: [...editReferences.course_year_sections, newSection]}, editSchedule).course_sections_by_year), {'11': {'3': ['A', 'D']}});
}
Promise.all([verifyCreateHandlers(), verifyEditSharedHandlers(), verifyCourseManagement()]).then(() => console.log('PASS: shared Create/Edit course-card process, database options, exact groups, unlimited additions, prefill, assigned/attendance locks, statuses, isolated drafts, confirmation, inline errors, and settings endpoints.')).catch(error => { console.error(error); process.exitCode = 1; });
