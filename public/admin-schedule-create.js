(function () {
    'use strict';

    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
    const unique = values => [...new Set(values)];
    const blankGroup = () => ({department: '', course_id: '', major: '', year_level: '', sections: []});
    const blank = () => ({draft_version: 3, course_groups: [blankGroup()], date: '', start_time: '08:00', end_time: '16:00', venue: '', slot_limit: '', status: 'Open for Registration'});
    const standardOptions = {
        year_level: ['1', '2', '3', '4'],
        section: Array.from({length: 26}, (_, index) => String.fromCharCode(65 + index)),
    };
    const optionNames = type => [...(standardOptions[type] || [])];
    const optionsFor = (state, type) => Object.hasOwn(standardOptions, type)
        ? unique([...optionNames(type), ...(state.academic_options || []).filter(option => option.type === type).map(option => String(option.name))]).map(name => ({type, name}))
        : (state.academic_options || []).filter(option => option.type === type);
    const timeMinutes = value => /^([01][0-9]|2[0-3]):[0-5][0-9]$/.test(value) ? Number(value.slice(0, 2)) * 60 + Number(value.slice(3)) : null;
    const validDate = value => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        const date = new Date(value + 'T12:00:00');
        return !Number.isNaN(date.getTime()) && date.getFullYear() === Number(value.slice(0, 4)) && date.getMonth() + 1 === Number(value.slice(5, 7)) && date.getDate() === Number(value.slice(8));
    };
    const formatDate = value => validDate(value) ? new Date(value + 'T12:00:00').toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'}) : 'Not set';
    const formatTime = value => {
        const minutes = timeMinutes(value);
        return minutes === null ? 'Not set' : `${Math.floor(minutes / 60) % 12 || 12}:${String(minutes % 60).padStart(2, '0')} ${minutes < 720 ? 'AM' : 'PM'}`;
    };

    const selection = (source, plural, legacy) => Array.isArray(source[plural])
        ? [...source[plural]]
        : source[plural] == null && source[legacy] != null && String(source[legacy]) !== ''
            ? [source[legacy]] : [];
    const validSelection = (values, names) => values.length > 0
        && unique(values).length === values.length
        && values.every(value => typeof value === 'string' && names.includes(value));
    const isSectionMap = value => value !== null && typeof value === 'object' && !Array.isArray(value);
    const sectionOptions = (state, courseId, yearLevel) => {
        const configured = (state.course_year_sections || []).filter(row => String(row.course_id) === String(courseId) && String(row.year_level) === String(yearLevel)).map(row => String(row.section));
        return configured.length ? unique(configured) : optionNames('section');
    };
    const selectedSections = (values, courseId) => Object.hasOwn(values, 'course_sections') && values.course_sections !== undefined
        ? isSectionMap(values.course_sections) && Array.isArray(values.course_sections[courseId]) ? [...values.course_sections[courseId]] : []
        : selection(values, 'sections', 'section');
    const scheduleSections = (schedule, courseId, yearLevel) => {
        const yearly = schedule.course_sections_by_year?.[courseId];
        const pivotValue = (schedule.courses || []).find(course => String(course.id) === String(courseId))?.pivot?.sections;
        const byYear = isSectionMap(yearly) ? yearly : isSectionMap(pivotValue) ? pivotValue : null;
        if (byYear) return yearLevel == null ? unique(Object.values(byYear).flat()) : [...(byYear[String(yearLevel)] || [])];
        const mapped = schedule.course_sections?.[courseId];
        const pivot = (schedule.courses || []).find(course => String(course.id) === String(courseId))?.pivot?.sections;
        return Array.isArray(mapped) && mapped.length ? mapped : Array.isArray(pivot) && pivot.length ? pivot : selection(schedule, 'sections', 'section');
    };
    const yearLabel = year => /^[1-4]$/.test(String(year)) ? `${year}${({1: 'st', 2: 'nd', 3: 'rd', 4: 'th'})[year]} Year` : String(year);

    // Course names remain database references. The visible year and section
    // ranges are shared by Create and Edit; configured sections limit selection.
    const createOptions = (state, type) => Object.hasOwn(standardOptions, type)
        ? (Array.isArray(state.academic_population) ? unique(state.academic_population.map(row=>String(row[type] || '')).filter(Boolean)).sort() : optionNames(type)).map(name => ({type, name}))
        : (state.academic_options || []).filter(option => option.type === type && (!Array.isArray(state.academic_population) || state.academic_population.some(row=>row.course===option.name))).map(option => ({...option, name: String(option.name)}));
    const legacyDepartment = 'Other / Legacy Courses';
    const catalogDepartments = state => Array.isArray(state.academic_catalog?.departments) ? state.academic_catalog.departments : [];
    const catalogCourse = (state, name) => catalogDepartments(state).flatMap(department => (department.courses || []).map(course => ({...course, department: department.name}))).find(course => course.name === name);
    const courseDepartment = (state, course) => course ? catalogCourse(state, course.name)?.department || legacyDepartment : '';
    const courseMajors = (state, course) => (catalogCourse(state, course?.name)?.majors || []).filter(major=>!Array.isArray(state.academic_population)||state.academic_population.some(row=>row.course===course?.name&&row.major===major));
    const majorMap = cards => Object.fromEntries(cards.filter(card => card.course_id && card.major).map(card => [card.course_id, card.major]));
    const populationFor = (state, courseId, major = '') => {
        const name = (state.academic_options || []).find(row=>String(row.id)===String(courseId))?.name;
        return (state.academic_population || []).filter(row=>row.course===name&&(!major||row.major===major));
    };
    const createSectionOptions = (state, courseId, year, major = '') => Array.isArray(state.academic_population)
        ? unique(populationFor(state,courseId,major).filter(row=>String(row.year_level)===String(year)).map(row=>String(row.section||'')).filter(Boolean)).sort()
        : sectionOptions(state,courseId,year);
    function createGroups(values) {
        if (Array.isArray(values.course_groups)) return values.course_groups.map(group => ({department: String(group?.department ?? ''), course_id: String(group?.course_id ?? ''), major: String(group?.major ?? values.course_majors?.[group?.course_id] ?? ''), year_level: String(group?.year_level ?? ''), sections: Array.isArray(group?.sections) ? [...group.sections] : []}));
        const ids = Array.isArray(values.course_ids) ? values.course_ids.map(String) : [];
        const years = selection(values, 'year_levels', 'year_level').map(String);
        const nested = isSectionMap(values.course_sections_by_year) ? values.course_sections_by_year : null;
        const groups = ids.flatMap(id => (nested && isSectionMap(nested[id]) ? Object.keys(nested[id]) : years.length ? years : ['']).map(year => ({department: '', course_id: id, major: String(values.course_majors?.[id] ?? ''), year_level: year, sections: nested ? Array.isArray(nested[id]?.[year]) ? [...nested[id][year]] : [] : selectedSections(values, id)})));
        return groups.length ? groups : [{...blankGroup(), year_level: years[0] || ''}];
    }
    function restoreDraft(value, state) {
        const source = value && typeof value === 'object' ? value : {};
        const result = blank(), ids = new Set(createOptions(state, 'course').map(option => String(option.id))), years = new Set(createOptions(state, 'year_level').map(option => option.name));
        result.course_groups = createGroups(source).map(group => {
            const courseId = ids.has(group.course_id) ? group.course_id : '', year = years.has(group.year_level) ? group.year_level : '';
            const course = createOptions(state, 'course').find(option => String(option.id) === courseId);
            const department = courseDepartment(state, course);
            const majors = courseMajors(state, course);
            return {department, course_id: courseId, major: majors.includes(group.major) ? group.major : '', year_level: year, sections: courseId && year ? unique(group.sections.filter(section => typeof section === 'string' && createSectionOptions(state, courseId, year).includes(section))) : []};
        });
        if (!result.course_groups.length) result.course_groups = [blankGroup()];
        for (const name of ['date', 'start_time', 'end_time', 'venue', 'slot_limit']) if (typeof source[name] === 'string') result[name] = source[name];
        if (source.status === 'Open' || source.status === 'Open for Registration') result.status = 'Open for Registration';
        else if (['Draft', 'Closed'].includes(source.status)) result.status = source.status;
        return result;
    }
    function evaluate(values, state) {
        const cards = createGroups(values), availableCourses = createOptions(state, 'course'), yearNames = createOptions(state, 'year_level').map(option => option.name);
        const courseIds = unique(cards.map(card => card.course_id).filter(Boolean)), courses = availableCourses.filter(course => courseIds.includes(String(course.id)));
        const courseValid = cards.length > 0 && cards.every(card => card.course_id && availableCourses.some(course => String(course.id) === card.course_id));
        const majorValid = courseValid && cards.every(card => { const majors = courseMajors(state, availableCourses.find(course => String(course.id) === card.course_id)); return majors.length === 0 || majors.includes(card.major); });
        const yearValid = cards.length > 0 && cards.every(card => yearNames.includes(card.year_level)) && unique(cards.map(card => JSON.stringify([card.course_id, card.year_level]))).length === cards.length;
        const sectionValid = courseValid && yearValid && cards.every(card => validSelection(card.sections, createSectionOptions(state, card.course_id, card.year_level, card.major)));
        const yearLevels = unique(cards.map(card => card.year_level).filter(Boolean)), sections = unique(cards.flatMap(card => card.sections));
        const courseSectionsByYear = {};
        for (const card of cards) if (card.course_id && card.year_level) {
            courseSectionsByYear[card.course_id] ||= {};
            courseSectionsByYear[card.course_id][card.year_level] = unique([...(courseSectionsByYear[card.course_id][card.year_level] || []), ...card.sections]);
        }
        const groups = cards.flatMap((card, groupIndex) => {
            const course = availableCourses.find(course => String(course.id) === card.course_id);
            return course && yearNames.includes(card.year_level) ? card.sections.filter(section => typeof section === 'string' && createSectionOptions(state, card.course_id, card.year_level, card.major).includes(section)).map(section => ({id: JSON.stringify([card.course_id, card.year_level, section]), groupIndex, courseId: card.course_id, major: card.major, year_level: card.year_level, section, label: course.name + (card.major ? ' (' + card.major + ')' : '') + ' - ' + card.year_level + section})) : [];
        });
        const dateValid = validDate(values.date), start = timeMinutes(values.start_time), end = timeMinutes(values.end_time), timeValid = start !== null && end !== null && end > start;
        const venue = String(values.venue ?? '').trim(), venueValid = venue.length > 0 && venue.length <= 190;
        const capacity = String(values.slot_limit ?? '').trim(), capacityValid = capacity === '' || (/^[0-9]+$/.test(capacity) && Number(capacity) >= 1 && Number(capacity) <= 100000);
        const settingsValid = capacityValid && ['Open for Registration', 'Draft', 'Closed'].includes(values.status);
        const overlaps = dateValid && timeValid ? (state.schedules || []).filter(schedule => {
            const otherStart = timeMinutes(String(schedule.start_time || '').slice(0, 5)), otherEnd = timeMinutes(String(schedule.end_time || '').slice(0, 5));
            return schedule.status !== 'Cancelled' && schedule.display_status !== 'Cancelled' && schedule.date === values.date && otherStart !== null && otherEnd !== null && start < otherEnd && end > otherStart;
        }) : [];
        const studentConflicts = overlaps.filter(schedule => groups.some(group => {
            const course = (schedule.courses || []).find(course => String(course.id) === group.courseId), years = selection(schedule, 'year_levels', 'year_level').map(String);
            if (!course || (years.length && !years.includes(group.year_level))) return false;
            const otherMajor = String(schedule.course_majors?.[group.courseId] || '');
            if (group.major && otherMajor && group.major !== otherMajor) return false;
            const available = scheduleSections(schedule, group.courseId, group.year_level), nested = isSectionMap(schedule.course_sections_by_year?.[group.courseId]) || isSectionMap(course.pivot?.sections);
            return (!nested && !available.length) || available.map(String).includes(group.section);
        }));
        const venueConflicts = venue ? overlaps.filter(schedule => String(schedule.venue || '').trim().toLocaleLowerCase() === venue.toLocaleLowerCase()) : [];
        const requiredValid = courseValid && majorValid && yearValid && sectionValid && dateValid && timeValid && venueValid && settingsValid;
        return {courses, course_ids: courseIds, course_majors: majorMap(cards), course_groups: cards, year_levels: yearLevels, sections, course_sections_by_year: courseSectionsByYear, groups, courseValid, majorValid, yearValid, sectionValid, dateValid, timeValid, venueValid, capacityValid, settingsValid, requiredValid, studentConflicts, venueConflicts, canSave: requiredValid && !studentConflicts.length && !venueConflicts.length};
    }
    function payload(values, state, action = 'another') {
        action = action === true ? 'publish' : action === false ? 'another' : action;
        if (!['draft', 'another', 'publish'].includes(action)) throw Error('Choose a valid save action.');
        const effective = action === 'draft' ? {...values, status: 'Draft'} : values, result = evaluate(effective, state);
        if (!result.courseValid) throw Error('Select one valid course in every course group.');
        if (!result.majorValid) throw Error('Select a valid major for every course that offers majors.');
        if (!result.yearValid) throw Error('Select one year level per course group. The same course and year level cannot be selected twice.');
        if (!result.sectionValid) throw Error('Select at least one configured section for every course group.');
        if (!result.requiredValid) throw Error('Complete the course, year level, sections, date, time and venue. End time must be after start time and capacity must be a positive whole number.');
        if (result.studentConflicts.length) {
            const conflict = result.studentConflicts[0];
            throw Error('A selected course, year level and section overlaps ' + (conflict.title || 'another Recollection Day') + ' at the selected date and time.');
        }
        if (result.venueConflicts.length) throw Error('The venue is already reserved by ' + (result.venueConflicts[0].title || 'another Recollection Day') + ' at the selected date and time.');
        if (action === 'publish' && effective.status === 'Draft') throw Error('Change registration status from Draft before publishing.');
        const status = effective.status === 'Open for Registration' ? 'Open' : effective.status;
        return {course_ids: result.course_ids.map(Number), course_majors: result.course_majors, year_levels: [...result.year_levels], sections: [...result.sections], course_sections_by_year: result.course_sections_by_year, course_groups: result.course_groups.map(group => ({course_id: Number(group.course_id), year_level: group.year_level, sections: [...group.sections]})), date: values.date, start_time: values.start_time, end_time: values.end_time, venue: String(values.venue).trim(), slot_limit: String(values.slot_limit ?? '').trim() === '' ? null : Number(values.slot_limit), status, active: status === 'Open', save_action: action};
    }

    const required = '<span class="cfo-required" aria-hidden="true">*</span>';
    const svg = kind => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${kind === 'calendar' ? '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>' : kind === 'check' ? '<path d="m5 12 4 4L19 6"/>' : '<path d="M12 8v5m0 3h.01"/><path d="m12 3 10 18H2z"/>'}</svg>`;
    const cardTitle = (step, title, subtitle) => `<div class="sb-card-title"><span class="sb-step">${step}</span><div><h2>${title}</h2><p>${subtitle}</p></div></div>`;

    // Create and Edit deliberately share the same course-card form. Mode-specific
    // rules stay in the existing evaluators and payload builders.
    function mount(context) {
        const {root, request, toast, editSchedule: schedule} = context, editing = !!schedule;
        const state = editing && Array.isArray(context.state.academic_population)
            ? {...context.state, academic_population:[...context.state.academic_population, ...savedGroups(schedule).map(group=>({course:(schedule.courses || []).find(course=>String(course.id)===group.courseId)?.name, major:group.major, year_level:group.year_level, section:group.section}))]}
            : context.state;
        state.academic_catalog ||= context.academicCatalog || window.CFO_BACKEND?.academic_catalog || {departments: []};
        if (editing && ['Completed', 'Cancelled'].includes(editStatus(schedule))) {
            root.innerHTML = `<div class="schedule-builder"><p role="status">${escape(editStatus(schedule))} schedules are view only.</p><a class="sb-btn" href="#admin-recollection-schedule">Back to Schedules</a></div>`;
            return {read: () => restoreEdit(schedule), destroy() {}};
        }
        let storage;
        if (!editing) try { storage = context.storage || window.localStorage; } catch (_) { storage = null; }
        const draftKey = 'cfo-admin-schedule-draft-v1:' + context.userId;
        let values = editing ? restoreEdit(schedule) : blank(), attempted = false, saving = false, addingCourse = false, addingSection = false, courseTarget = 0, sectionTarget = null, pendingCourseName = '', confirmAction = null;
        if (!editing) try { values = restoreDraft(JSON.parse(storage?.getItem(draftKey) || 'null'), state); } catch (_) { /* Draft recovery is optional. */ }
        values.course_groups = createGroups(values).map(group => {
            const course = (editing ? editCourseOptions(state, schedule) : createOptions(state, 'course')).find(option => String(option.id) === group.course_id);
            const majors = courseMajors(state, course);
            return {...group, department: group.department || courseDepartment(state, course), major: majors.includes(group.major) ? group.major : ''};
        });
        const original = editing ? editFingerprint(values) : JSON.stringify(blank()), oldKeys = new Set(editing ? savedGroups(schedule).map(groupKey) : []);
        const lockedGroups = (schedule?.assigned_academic_groups || []).map(group => ({courseId: String(group.course_id), year_level: String(group.year_level), section: String(group.section)})).filter(group => oldKeys.has(groupKey(group)));
        const isLocked = (group, section) => lockedGroups.some(locked => locked.courseId === group.course_id && locked.year_level === group.year_level && (section == null || locked.section === section));
        const assignedCount = editing ? Math.max(0, Number(schedule.registrations_total_count ?? schedule.registrations_count) || 0) : 0, attendanceRecorded = editing && Number(schedule.registrations_recorded_attendance_count) > 0;
        const courseOptions = () => editing ? editCourseOptions(state, schedule) : createOptions(state, 'course');
        const yearOptions = group => Array.isArray(state.academic_population)
            ? unique([...populationFor(state,group.course_id,group.major).map(row=>String(row.year_level||'')).filter(Boolean), ...(editing?savedGroups(schedule).filter(saved=>saved.courseId===group.course_id).map(saved=>saved.year_level):[])]).sort().map(name=>({name}))
            : editing ? editYearNames(schedule, group.course_id).map(name => ({name})) : createOptions(state, 'year_level');
        const availableSections = group => {
            const course = courseOptions().find(option => String(option.id) === group.course_id), majors = courseMajors(state, course);
            if (!course || (majors.length && !majors.includes(group.major)) || !group.year_level) return [];
            return unique([...createSectionOptions(state, group.course_id, group.year_level, group.major), ...(editing?savedGroups(schedule).filter(saved => saved.courseId === group.course_id && saved.year_level === group.year_level).map(saved => saved.section):[])]);
        };
        const dirty = () => (editing ? editFingerprint(values) : JSON.stringify(values)) !== original;
        const fieldNames = ['date', 'start_time', 'end_time', 'venue', 'slot_limit', 'status'];
        const lockedAttribute = attendanceRecorded ? ' disabled aria-describedby="sb-attendance-lock"' : '';
        const yearLocked = editing && assignedCount > 0 && validDate(values.date), savedDateYear = String(schedule?.date || '').slice(0, 4);
        const dateAttribute = !attendanceRecorded && yearLocked ? ` min="${savedDateYear}-01-01" max="${savedDateYear}-12-31" aria-describedby="sb-calendar-year-lock"` : '';
        const statusOptions = editing ? editStatuses(schedule).map(status => `<option value="${status}">${status === 'Open' ? 'Open for Registration' : status}</option>`).join('') : '<option>Open for Registration</option><option>Draft</option><option>Closed</option>';
        const backLink = `<a class="sb-btn" href="#admin-recollection-schedule" data-sb-create-back${editing ? ' data-sb-edit-back' : ''}>← Back to Schedules</a>`;
        const saveButtons = !editing ? '<button class="sb-btn" type="button" data-sb-draft>Save as Draft</button><button class="sb-btn sb-btn-blue" type="submit" name="save_mode" value="another">Save &amp; Create Another</button><button class="sb-btn sb-btn-green" type="submit" name="save_mode" value="publish">Publish Schedule</button>' : editStatus(schedule) === 'Draft' ? '<button class="sb-btn" type="button" data-sb-draft>Save as Draft</button><button class="sb-btn sb-btn-green" type="submit" value="publish">Publish Schedule</button>' : '<button class="sb-btn sb-btn-blue" type="submit" data-sb-save-changes value="changes">Save Changes</button>';
        root.innerHTML = `<div class="schedule-builder sb-grouped${editing ? '' : ' sb-create'}"${editing ? ` data-edit-schedule-id="${escape(schedule.id)}"` : ''}>
          <div class="sb-banner"><div><p class="sb-kicker">RECOLLECTION</p><p>${editing ? 'Edit the schedule, keeping assigned student groups included.' : 'Set the schedule, select the courses, year levels, and sections for the recollection.'}</p></div>${editing ? backLink : ''}</div>
          <form id="connected-schedule-form" novalidate><div class="sb-form-error" role="alert" tabindex="-1" hidden></div><div class="sb-content"><div class="sb-left">
          <section class="sb-card">${cardTitle(1, 'Select Students (Course / Year / Section)', 'Choose one course, one year level, and its sections in each course group.')}${editing ? '<p class="sb-hint">Existing groups are marked Already included. Assigned groups must stay included.</p>' : ''}<div data-sb-course-groups></div><button class="sb-add-group" type="button" data-sb-add-group><span aria-hidden="true">＋</span><span><strong>Add Another Course Group</strong><span class="sb-hint">Create another course container with its own year level and section selections.</span></span></button>${editing ? '<p class="sb-hint" data-sb-added-summary aria-live="polite"></p>' : ''}</section>
          <section class="sb-card">${cardTitle(2, 'Date, Time &amp; Venue', 'Set the date, time, and venue for the recollection.')}${attendanceRecorded ? '<p class="sb-hint" id="sb-attendance-lock">Date, time and venue are locked because attendance has been recorded.</p>' : yearLocked ? '<p class="sb-hint" id="sb-calendar-year-lock">The recollection year cannot change because students are already assigned.</p>' : ''}<div class="sb-date-grid"><label class="sb-field">Date ${required}<input type="date" name="date" required${lockedAttribute}${dateAttribute}></label><label class="sb-field">Start Time ${required}<input type="time" name="start_time" required${lockedAttribute}></label><label class="sb-field">End Time ${required}<input type="time" name="end_time" required${lockedAttribute}></label></div><label class="sb-field sb-venue">Venue ${required}<input name="venue" maxlength="190" placeholder="Enter venue…" required${lockedAttribute}></label><p class="sb-hint">${editing ? 'Enter the venue manually. Existing unchanged venue bookings are retained; changed date, time or venue is checked for conflicts.' : 'Enter the venue manually. All non-cancelled schedules reserve the venue at their scheduled date and time.'}</p><div class="sb-conflict is-pending" data-sb-conflict aria-live="polite"><span class="sb-conflict-icon">${svg('notice')}</span><div><strong data-sb-conflict-title></strong><p data-sb-conflict-description></p></div></div></section>
          <section class="sb-card">${cardTitle(3, 'Registration Settings', 'Configure registration status and optional capacity.')}<div class="sb-settings-grid"><label class="sb-field">Maximum Capacity (Optional)<input type="number" name="slot_limit" min="${Math.max(1, assignedCount)}" max="100000" step="1" placeholder="Leave blank for no limit" aria-describedby="sb-capacity-help"><span class="sb-hint" id="sb-capacity-help">${assignedCount ? `Cannot be lower than the ${assignedCount} students already assigned.` : 'Leave empty for no limit.'}</span></label><label class="sb-field">Registration Status ${required}<select name="status" required>${statusOptions}</select><span class="sb-hint">${editing ? 'Only the existing status transitions are available. Edit sessions do not use the unfinished create entry.' : 'Draft and Closed schedules cannot be selected for student registration. Unfinished entries are saved on this browser.'}</span></label></div></section></div>
          <aside class="sb-right" aria-label="Schedule preview"><section class="sb-card sb-summary-card"><div class="sb-summary-head"><span class="sb-summary-icon">${svg('calendar')}</span><h2>Schedule Summary</h2><span class="sb-summary-status">Open</span></div><div data-sb-summary></div></section><section class="sb-card"><div class="sb-summary-head"><span class="sb-summary-icon sb-validation-icon">${svg('check')}</span><h2>Validation Checklist</h2></div><ul class="sb-checks" data-sb-checks></ul></section></aside></div>
          <div class="sb-actions">${editing ? '<a class="sb-btn" href="#admin-recollection-schedule" data-sb-edit-back data-sb-create-back>Cancel</a>' : `<div class="sb-actions-left"><button class="sb-btn" type="button" data-sb-reset>Cancel / Reset</button>${backLink}</div>`}<div class="sb-actions-right">${saveButtons}</div></div></form>
          <dialog class="schedule-course-dialog" data-sb-course-dialog aria-labelledby="sb-course-dialog-title"><div class="sb-course-dialog-heading"><h2 id="sb-course-dialog-title">Add Course</h2><button type="button" class="sb-dialog-close" aria-label="Close Add Course" data-sb-close-course>×</button></div><p>Add a course using the existing academic reference list, then select its year level and sections.</p><form data-sb-course-form><label class="sb-field">Course Name ${required}<input name="name" maxlength="190" placeholder="e.g. BSIT" autocomplete="off" required></label><p class="sb-form-error" data-sb-course-error role="alert" hidden></p><div class="sb-actions-right"><button class="sb-btn" type="button" data-sb-close-course>Cancel</button><button class="sb-btn sb-btn-blue" type="submit">Add Course</button></div></form><section data-sb-course-manage aria-label="Manage existing courses"></section></dialog>
          ${editing ? `<dialog class="schedule-course-dialog" data-sb-section-dialog aria-labelledby="sb-section-dialog-title"><div class="sb-course-dialog-heading"><h2 id="sb-section-dialog-title">Add Section</h2><button type="button" class="sb-dialog-close" aria-label="Close Add Section" data-sb-close-section>×</button></div><p data-sb-section-context></p><form data-sb-section-form><label class="sb-field">Section Name ${required}<input name="section" maxlength="190" autocomplete="off" required></label><p class="sb-form-error" data-sb-section-error role="alert" hidden></p><div class="sb-actions-right"><button class="sb-btn" type="button" data-sb-close-section>Cancel</button><button class="sb-btn sb-btn-blue" type="submit">Add Section</button></div></form></dialog>` : ''}
          <dialog class="schedule-course-dialog" data-sb-create-confirm aria-labelledby="sb-create-confirm-title"><div class="sb-course-dialog-heading"><h2 id="sb-create-confirm-title">Confirm changes</h2><button type="button" class="sb-dialog-close" aria-label="Close confirmation" data-sb-close-create-confirm>×</button></div><p data-sb-create-confirm-text></p><div class="sb-actions-right"><button type="button" class="sb-btn" data-sb-close-create-confirm>Keep Editing</button><button type="button" class="sb-btn sb-btn-blue" data-sb-create-confirm-action>Confirm</button></div></dialog></div>`;
        const builder = root.querySelector('.schedule-builder'), form = root.querySelector('#connected-schedule-form'), cardsRoot = builder.querySelector('[data-sb-course-groups]');
        const courseDialog = builder.querySelector('.schedule-course-dialog'), confirmDialog = builder.querySelector('[data-sb-create-confirm]'), sectionDialog = editing ? builder.querySelector('[data-sb-section-dialog]') : null, errorBox = form.querySelector('.sb-form-error');
        const courseManager = window.CfoCourseManage.mount(courseDialog.querySelector('[data-sb-course-manage]'), {
            state, request, toast, isBlocked: () => addingCourse || saving,
            onBusy(busy) {
                for (const control of [...courseDialog.querySelector('form').querySelectorAll('input,button'), ...courseDialog.querySelectorAll('[data-sb-close-course]')]) control.disabled = busy;
            },
            onChanged() {
                read();
                values.course_groups = window.CfoCourseManage.reconcileGroups(values.course_groups, state);
                renderCards(); update(); rememberDraft();
            },
        });
        function error(failure) {
            errorBox.textContent = typeof failure === 'string' ? failure : failure?.errors ? unique(Object.values(failure.errors).flat()).join(' ') : failure?.message || 'The schedule could not be saved. Please try again.';
            errorBox.hidden = false; errorBox.focus(); errorBox.scrollIntoView?.({block: 'nearest'});
        }
        function read() {
            values.course_groups = [...cardsRoot.querySelectorAll('[data-sb-course-group]')].map(card => ({department: String(card.querySelector('[data-sb-group-department]').value), course_id: String(card.querySelector('[data-sb-group-course]').value), major: String(card.querySelector('[data-sb-group-major]').value), year_level: card.querySelector('[data-sb-group-year]:checked')?.value || '', sections: [...card.querySelectorAll('[data-sb-group-section]:checked')].map(input => input.value)}));
            for (const name of fieldNames) values[name] = String(form.elements[name].value || '');
            return values;
        }
        const groupBadge = (group, section) => editing ? `<span class="sb-group-badge">${oldKeys.has(groupKey({courseId: group.course_id, year_level: group.year_level, section})) ? 'Already included' : 'New'}</span>` : '';
        function renderCards() {
            const courses = courseOptions();
            cardsRoot.innerHTML = values.course_groups.map((group, index) => {
                const course = courses.find(course => String(course.id) === group.course_id), years = yearOptions(group), allowedSections = availableSections(group), sections = Array.isArray(state.academic_population) ? allowedSections : unique([...optionNames('section'), ...allowedSections]), locked = isLocked(group);
                const departments = catalogDepartments(state).map(department => department.name).filter(name=>courses.some(course=>courseDepartment(state,course)===name)), hasLegacy = courses.some(option => courseDepartment(state, option) === legacyDepartment);
                if (hasLegacy) departments.push(legacyDepartment);
                const departmentCourses = group.department ? courses.filter(option => courseDepartment(state, option) === group.department) : [];
                const majors = courseMajors(state, course), pathReady = !!course && (!majors.length || majors.includes(group.major));
                const combinations = pathReady && group.year_level ? group.sections.map(section => ({section, label: course.name + (group.major ? ' (' + group.major + ')' : '') + ' - ' + group.year_level + section})) : [];
                const usedPair = (courseId, year) => !!courseId && !!year && values.course_groups.some((other, otherIndex) => otherIndex !== index && other.course_id === courseId && other.year_level === year);
                const lockNote = locked ? '<p class="sb-hint">🔒 Students are already assigned to this group.</p>' : '';
                return `<article class="sb-course-group" data-sb-course-group="${index}"><div class="sb-course-group-head"><span class="sb-course-group-number">${index + 1}</span><div class="sb-course-group-title"><div class="sb-academic-path"><label class="sb-field" for="sb-group-department-${index}">Department ${required}<select id="sb-group-department-${index}" data-sb-group-department="${index}" aria-label="Department for group ${index + 1}" required${locked ? ' disabled title="Students are already assigned to this group."' : ''}><option value=""${!group.department ? ' selected' : ''}>Select department</option>${departments.map(name => `<option value="${escape(name)}"${name === group.department ? ' selected' : ''}>${escape(name)}</option>`).join('')}</select></label><label class="sb-field" for="sb-group-course-${index}">Course ${required}<select id="sb-group-course-${index}" data-sb-group-course="${index}" aria-label="Course for group ${index + 1}" required${!group.department || locked ? ` disabled${locked ? ' title="Students are already assigned to this group."' : ''}` : ''}><option value=""${!group.course_id ? ' selected' : ''}>${group.department ? 'Select course' : 'Select a department first'}</option>${departmentCourses.map(option => `<option value="${escape(option.id)}"${String(option.id) === group.course_id ? ' selected' : ''}${usedPair(String(option.id), group.year_level) ? ' disabled' : ''}>${escape(option.name)}</option>`).join('')}</select></label><label class="sb-field" for="sb-group-major-${index}">Major${majors.length ? ' ' + required : ''}<select id="sb-group-major-${index}" data-sb-group-major="${index}" aria-label="Major for group ${index + 1}"${majors.length ? ' required' : ''}${!course || !majors.length || locked ? ` disabled${locked ? ' title="Students are already assigned to this group."' : ''}` : ''}><option value=""${!group.major ? ' selected' : ''}>${!course ? 'Select a course first' : majors.length ? 'Select major' : 'No major required'}</option>${majors.map(major => `<option value="${escape(major)}"${major === group.major ? ' selected' : ''}>${escape(major)}</option>`).join('')}</select></label></div><div class="sb-course-choice"><p class="sb-hint">Choose a department first; its courses will unlock, followed by majors when available.</p><button class="sb-btn sb-btn-blue sb-add-course" type="button" data-sb-add-course="${index}">＋ Add Course</button></div>${lockNote}</div><button class="sb-btn sb-remove-group" type="button" data-sb-remove-group="${index}"${values.course_groups.length === 1 || locked ? ` disabled title="${locked ? 'Students are already assigned to this group.' : 'At least one course group must remain.'}"` : ''}>Remove Group</button></div><div class="sb-course-group-grid">
                  <div class="sb-field"><div class="sb-label-row"><span id="sb-group-year-label-${index}">Year Level ${required}</span></div><div class="sb-options" role="radiogroup" aria-labelledby="sb-group-year-label-${index}">${years.map(year => `<label class="sb-option"><input type="radio" name="sb-year-${index}" value="${escape(year.name)}" data-sb-group-year="${index}"${group.year_level === year.name ? ' checked' : ''}${!pathReady || usedPair(group.course_id, year.name) || locked ? ' disabled' : ''}><span>${escape(year.name)}${!optionNames('year_level').includes(year.name) ? ' <span class="sb-group-badge">Already included</span>' : ''}</span></label>`).join('')}</div><p class="sb-hint">${!pathReady ? 'Complete the department, course, and required major first.' : `Choose exactly one year level from 1–4 for this course.${years.some(year => !optionNames('year_level').includes(year.name)) ? ' Other saved year levels are preserved for this course.' : ''}`}</p></div>
                  <div class="sb-field"><div class="sb-label-row"><span id="sb-group-section-label-${index}">Sections ${required}</span></div><div class="sb-options sb-section-options" role="group" aria-labelledby="sb-group-section-label-${index}"><label class="sb-option"><input type="checkbox" data-sb-group-select-all="${index}" aria-describedby="sb-group-section-help-${index}"${allowedSections.length && allowedSections.every(section => group.sections.includes(section)) ? ' checked' : ''}${!allowedSections.some(section => !isLocked(group, section)) ? ' disabled' : ''}><span>Select All</span></label>${sections.map(section => { const checked = group.sections.includes(section), sectionLocked = checked && isLocked(group, section), allowed = allowedSections.includes(section), note = sectionLocked ? 'Students are already assigned to this group.' : !group.course_id || !group.year_level ? 'Select a course and year level first.' : !allowed ? 'Not configured for this course and year level.' : ''; return `<label class="sb-option"${note ? ` title="${note}"` : ''}><input type="checkbox" value="${escape(section)}" data-sb-group-section="${index}" aria-describedby="sb-group-section-help-${index}"${checked ? ' checked' : ''}${sectionLocked || !allowed ? ' disabled aria-disabled="true"' : ''}${note ? ` title="${note}"` : ''}><span>${escape(section)}${sectionLocked ? ' <span aria-hidden="true">🔒</span><span class="sr-only"> Students are already assigned to this group.</span>' : ''}</span>${checked ? groupBadge(group, section) : ''}</label>`; }).join('')}</div><p class="sb-hint" id="sb-group-section-help-${index}">${!group.course_id || !group.year_level ? 'Select a course and year level to enable their sections.' : 'Sections A–Z are shown. Only sections configured for this course and year level can be selected. When none are configured, all A–Z sections are available.'}${editing && allowedSections.some(section => !optionNames('section').includes(section)) ? ' Additional configured or saved section names are preserved.' : ''}</p>${editing && course && group.year_level ? `<button type="button" class="sb-btn sb-add-section" data-sb-add-section="${index}" data-sb-course-id="${escape(group.course_id)}">+ Add section</button>` : ''}</div>
                  <div class="sb-field sb-course-group-combinations"><div class="sb-label-row"><span>Selected Combination</span><span class="sb-group-count" data-sb-group-count="${index}">${combinations.length} sections</span></div><div class="sb-groups" data-sb-groups="${index}" aria-live="polite">${combinations.map(combination => `<div class="sb-group"><span>${escape(combination.label)} ${groupBadge(group, combination.section)}</span><button type="button" data-sb-remove-section="${index}" data-sb-section="${escape(combination.section)}" aria-label="Remove ${escape(combination.label)}"${isLocked(group, combination.section) ? ' disabled title="Students are already assigned to this group."' : ''}>×</button></div>`).join('') || '<p class="sb-hint">Select a year level and section to create combinations.</p>'}</div></div></div></article>`;
            }).join('');
            cardsRoot.querySelectorAll('[data-sb-group-course]').forEach(select => {
                if (select.options[0]) select.options[0].disabled = true;
            });
            if (Array.isArray(state.academic_population)) {
                cardsRoot.querySelectorAll('[data-sb-add-course],[data-sb-add-section]').forEach(button=>button.hidden=true);
                cardsRoot.querySelectorAll('[id^="sb-group-section-help-"]').forEach(hint=>hint.textContent='Only sections with existing student accounts are available. Saved groups are retained when editing.');
            }
            for (const checkbox of cardsRoot.querySelectorAll('[data-sb-group-select-all]')) {
                const group = values.course_groups[Number(checkbox.dataset.sbGroupSelectAll)], sections = availableSections(group);
                checkbox.indeterminate = sections.some(section => group.sections.includes(section)) && !sections.every(section => group.sections.includes(section));
            }
        }
        function applyValues() { for (const name of fieldNames) form.elements[name].value = values[name]; renderCards(); update(); }
        function update() {
            const result = editing ? evaluateEdit(values, state, schedule) : evaluate(values, state), cards = values.course_groups;
            const courseGroups = cards.map(group => {
                const course = result.courses.find(course => String(course.id) === group.course_id);
                if (!course) return '';
                const year = group.year_level ? yearLabel(group.year_level) : 'Year not selected';
                const sections = unique(group.sections).map(section => '<span class="sb-summary-section">' + escape(group.year_level + section) + '</span>').join('');
                return '<li class="sb-summary-course"><strong class="sb-summary-course-name">' + escape(course.name) + (group.major ? ' · ' + escape(group.major) : '') + ' · ' + escape(year) + '</strong><div class="sb-summary-sections" aria-label="' + escape(course.name + ' ' + year + ' sections') + '">' + (sections || '<span class="sb-summary-empty">Sections not selected</span>') + '</div></li>';
            }).join('');
            const courses = courseGroups ? '<ul class="sb-summary-courses">' + courseGroups + '</ul>' : '<span class="sb-summary-empty">Not selected</span>';
            const statusLabel = ['Open', 'Open for Registration'].includes(values.status) ? 'Open for Registration' : values.status;
            const rows = [['Course(s)', courses], ...(editing ? [['New groups', String(result.changes.added.length)]] : []), ['Date', escape(formatDate(values.date))], ['Time', escape(formatTime(values.start_time)) + ' – ' + escape(formatTime(values.end_time))], ['Venue', escape(values.venue.trim() || 'Not set')], ['Capacity', escape(values.slot_limit.trim() ? values.slot_limit.trim() + ' students' : 'No limit')], ['Status', '<span class="sb-status-pill' + (['Open', 'Open for Registration'].includes(values.status) ? '' : ' is-closed') + '">' + escape(statusLabel) + '</span>']];
            builder.querySelector('[data-sb-summary]').innerHTML = '<dl class="sb-summary-list">' + rows.map(([label, text]) => '<div class="sb-summary-row"><dt class="sb-summary-label">' + label + '</dt><dd class="sb-summary-value">' + text + '</dd></div>').join('') + '</dl>';
            builder.querySelector('.sb-summary-status').textContent = values.status === 'Open for Registration' ? 'Open' : values.status;
            if (editing) builder.querySelector('[data-sb-added-summary]').textContent = result.changes.added.length ? `You are adding: ${result.changes.added.map(group => group.label).join(', ')}. ${capacitySummary(values, schedule)}` : 'No new groups selected.';
            const ready = result.dateValid && result.timeValid && result.venueValid, venueBlocked = result.venueConflicts.length && (!editing || !result.venueBookingUnchanged);
            const checks = [[result.courseValid, 'Department and course selected in every group'], [result.majorValid, 'Major selected when the course requires one'], [result.yearValid, 'One year level per course group; no duplicate course/year groups'], [result.sectionValid, 'At least one section selected for every course group'], [result.dateValid && result.timeValid && (!editing || (result.attendanceValid && result.calendarYearValid)), 'Date and time are set'], [result.venueValid, 'Venue is set'], [result.timeValid, 'End time is after start time'], [result.capacityValid, 'Capacity is valid (optional)'], [result.settingsValid, 'Registration settings are valid'], [ready && result.sectionValid && !result.studentConflicts.length, 'No student schedule conflict'], [ready && !venueBlocked, 'No venue conflict'], ...(editing ? [[result.assignedValid, 'Assigned student groups stay included']] : []), [result.requiredValid, 'Schedule details are valid']];
            builder.querySelector('[data-sb-checks]').innerHTML = checks.map(([valid, label]) => '<li class="sb-check ' + (valid ? 'is-valid' : attempted ? 'is-invalid' : 'is-pending') + '"><span class="sb-check-icon" aria-hidden="true">' + (valid ? '✓' : '!') + '</span><span>' + label + '<span class="sr-only">: ' + (valid ? 'valid' : 'not yet satisfied') + '</span></span></li>').join('');
            const conflict = !ready ? 'pending' : result.studentConflicts.length || venueBlocked ? 'invalid' : result.venueConflicts.length ? 'warning' : 'valid';
            builder.querySelector('[data-sb-conflict]').className = 'sb-conflict is-' + conflict;
            builder.querySelector('[data-sb-conflict-title]').textContent = conflict === 'pending' ? 'Select a date, time and venue' : result.studentConflicts.length ? 'Student Schedule Conflict' : result.venueConflicts.length ? venueBlocked ? 'Venue Already Reserved' : 'Existing Venue Booking' : 'No Conflicts Detected';
            builder.querySelector('[data-sb-conflict-description]').textContent = conflict === 'pending' ? 'Schedule availability will appear here.' : result.studentConflicts.length ? 'A selected course, year level and section overlaps ' + (result.studentConflicts[0].title || 'another Recollection Day') + '.' : venueBlocked ? 'This venue is reserved by ' + (result.venueConflicts[0].title || 'another Recollection Day') + ' at this time. Choose another venue or time.' : result.venueConflicts.length ? 'The existing date, time and venue remain unchanged. This earlier shared booking is retained.' : 'No overlap found in the current schedules. Conflicts are checked again when saving.';
            form.dataset.dirty = dirty() ? 'true' : 'false';
        }
        function rememberDraft() { if (!editing) try { storage?.setItem(draftKey, JSON.stringify(values)); } catch (_) { /* Server saving works without local storage. */ } }
        function changed(event) {
            const target = event?.target, isCardControl = target?.matches?.('[data-sb-group-department],[data-sb-group-course],[data-sb-group-major],[data-sb-group-year],[data-sb-group-section],[data-sb-group-select-all]');
            const index = Number(target?.dataset?.sbGroupDepartment ?? target?.dataset?.sbGroupCourse ?? target?.dataset?.sbGroupMajor ?? target?.dataset?.sbGroupYear ?? target?.dataset?.sbGroupSection ?? target?.dataset?.sbGroupSelectAll);
            const before = Number.isInteger(index) ? {...values.course_groups[index]} : null;
            if (target?.matches?.('[data-sb-group-select-all]')) {
                const card = cardsRoot.querySelector('[data-sb-course-group="' + target.dataset.sbGroupSelectAll + '"]');
                for (const input of card.querySelectorAll('[data-sb-group-section]')) if (!input.disabled) input.checked = target.checked;
            }
            read();
            if (target?.matches?.('[data-sb-group-department]')) {
                const group = values.course_groups[index];
                group.course_id = ''; group.major = ''; group.year_level = ''; group.sections = [];
            }
            if (target?.matches?.('[data-sb-group-course]')) {
                const group = values.course_groups[index];
                if (before?.course_id !== group.course_id) { group.major = ''; group.year_level = ''; group.sections = []; }
                const selected = courseOptions().find(option => String(option.id) === group.course_id);
                group.department = courseDepartment(state, selected) || group.department;
            }
            if (target?.matches?.('[data-sb-group-major]')) {
                const group = values.course_groups[index];
                if (before?.major !== group.major) group.sections = [];
                for (const other of values.course_groups) if (other.course_id === group.course_id) other.major = group.major;
            }
            if (target?.matches?.('[data-sb-group-course],[data-sb-group-major],[data-sb-group-year]')) {
                const group = values.course_groups[index];
                if (!yearOptions(group).some(year => year.name === group.year_level)) group.year_level = '';
                group.sections = group.sections.filter(section => group.course_id && group.year_level && availableSections(group).includes(section));
            }
            if (isCardControl || !event) renderCards();
            update(); rememberDraft(); if (!attempted) errorBox.hidden = true;
            if (isCardControl) {
                const card = cardsRoot.querySelector('[data-sb-course-group="' + index + '"]'), attr = target.matches('[data-sb-group-department]') ? '[data-sb-group-department]' : target.matches('[data-sb-group-course]') ? '[data-sb-group-course]' : target.matches('[data-sb-group-major]') ? '[data-sb-group-major]' : target.matches('[data-sb-group-year]') ? '[data-sb-group-year]' : target.matches('[data-sb-group-section]') ? '[data-sb-group-section]' : '[data-sb-group-select-all]';
                [...card.querySelectorAll(attr)].find(input => input.value === target.value)?.focus();
            }
        }
        function confirm(title, text, label, action) { confirmAction = action; builder.querySelector('#sb-create-confirm-title').textContent = title; builder.querySelector('[data-sb-create-confirm-text]').textContent = text; builder.querySelector('[data-sb-create-confirm-action]').textContent = label; confirmDialog.showModal(); builder.querySelector('[data-sb-close-create-confirm]').focus(); }
        function leave() { form.dataset.dirty = 'false'; if (!editing) try { storage?.removeItem(draftKey); } catch (_) {} window.removeEventListener?.('beforeunload', beforeUnload); context.navigate?.(); }
        function resetForm() { values = blank(); attempted = false; errorBox.hidden = true; applyValues(); try { storage?.removeItem(draftKey); } catch (_) {} form.dataset.dirty = 'false'; cardsRoot.querySelector('[data-sb-group-department]')?.focus(); }
        async function commit(body, mode) {
            if (saving) return;
            saving = true; errorBox.hidden = true;
            const buttons = [...form.querySelectorAll('button')], disabled = buttons.map(button => button.disabled); buttons.forEach(button => { button.disabled = true; });
            const onSaved = saved => {
                if (!editing) try { storage?.removeItem(draftKey); } catch (_) {}
                if (!editing && mode === 'another') resetForm();
                else { form.dataset.dirty = 'false'; window.removeEventListener?.('beforeunload', beforeUnload); context.navigate?.(saved); }
            };
            try { await context.save(body, onSaved, error); } catch (failure) { error(failure); }
            finally { saving = false; buttons.forEach((button, index) => { button.disabled = disabled[index]; }); renderCards(); }
        }
        function saveSchedule(mode) {
            if (saving) return;
            read(); if (mode === 'draft') values.status = 'Draft'; else if (editing && mode === 'publish') values.status = 'Open';
            form.elements.status.value = values.status; attempted = true; update(); rememberDraft();
            if (!form.checkValidity()) { error('Check the course, date, times, venue and optional capacity.'); form.reportValidity(); return; }
            let body;
            try { body = editing ? editPayload(values, state, schedule) : payload(values, state, mode); } catch (failure) { error(failure); return; }
            const result = editing ? evaluateEdit(values, state, schedule) : null;
            if (result?.changes.added.length) confirm('Confirm added groups', `You are adding: ${result.changes.added.map(group => group.label).join(', ')}. ${capacitySummary(values, schedule)}`, mode === 'publish' ? 'Publish Schedule' : mode === 'draft' ? 'Save as Draft' : 'Save Changes', () => commit(body, mode));
            else commit(body, mode);
        }
        const beforeUnload = event => { if (form.dataset.dirty === 'true') { event.preventDefault(); event.returnValue = ''; } };
        window.addEventListener?.('beforeunload', beforeUnload);
        form.addEventListener('input', event => { if (!event.target.matches?.('[data-sb-group-department],[data-sb-group-course],[data-sb-group-major],[data-sb-group-year],[data-sb-group-section],[data-sb-group-select-all]')) changed(event); });
        form.addEventListener('change', changed);
        form.addEventListener('submit', event => { event.preventDefault(); saveSchedule(editing ? event.submitter?.value === 'publish' ? 'publish' : 'changes' : event.submitter?.value === 'another' ? 'another' : 'publish'); });
        builder.addEventListener('click', event => {
            const target = event.target, add = target.closest('[data-sb-add-group]'), remove = target.closest('[data-sb-remove-group]'), chip = target.closest('[data-sb-remove-section]'), back = target.closest('[data-sb-create-back]');
            if (add && !saving) { read(); values.course_groups.push(blankGroup()); renderCards(); update(); rememberDraft(); const card = cardsRoot.querySelector('[data-sb-course-group="' + (values.course_groups.length - 1) + '"]'); card.scrollIntoView?.({block: 'nearest'}); card.querySelector('[data-sb-group-department]').focus(); }
            if (remove && !remove.disabled && !saving) { read(); const index = Number(remove.dataset.sbRemoveGroup); confirm('Remove course group?', 'Remove this course group and its selected sections from the schedule?', 'Remove Group', () => { values.course_groups.splice(index, 1); renderCards(); update(); rememberDraft(); }); }
            if (chip && !chip.disabled && !saving) { read(); const group = values.course_groups[Number(chip.dataset.sbRemoveSection)]; group.sections = group.sections.filter(section => section !== chip.dataset.sbSection); renderCards(); update(); rememberDraft(); }
            if (target.closest('[data-sb-draft]')) saveSchedule('draft');
            if (target.closest('[data-sb-reset]') && !saving) confirm('Reset schedule?', 'Clear all course groups and schedule fields? This unfinished entry will be removed from this browser.', 'Reset Form', resetForm);
            if (back) { read(); if (dirty()) { event.preventDefault(); confirm(editing ? 'Discard unsaved changes?' : 'Discard unfinished schedule?', editing ? 'Your changes to this schedule have not been saved.' : 'Your unfinished schedule has not been saved. Leave this page and discard it?', 'Discard and Leave', leave); } }
            const addCourse = target.closest('[data-sb-add-course]');
            if (addCourse && !saving) { read(); courseTarget = Number(addCourse.dataset.sbAddCourse); courseDialog.querySelector('[data-sb-course-error]').hidden = true; courseManager.reset(); courseDialog.showModal(); courseDialog.querySelector('input').focus(); }
            if (target.closest('[data-sb-close-course]') && !addingCourse && !courseManager.busy()) courseDialog.close();
            const addSection = target.closest('[data-sb-add-section]');
            if (addSection && !saving && sectionDialog) { read(); sectionTarget = Number(addSection.dataset.sbAddSection); const group = values.course_groups[sectionTarget], course = courseOptions().find(row => String(row.id) === group.course_id); builder.querySelector('[data-sb-section-context]').textContent = `${course?.name || 'Course'} · ${yearLabel(group.year_level)}`; builder.querySelector('[data-sb-section-error]').hidden = true; sectionDialog.showModal(); sectionDialog.querySelector('input').focus(); }
            if (target.closest('[data-sb-close-section]') && !addingSection) sectionDialog?.close();
            if (target.closest('[data-sb-close-create-confirm]')) { confirmAction = null; confirmDialog.close(); }
            if (target.closest('[data-sb-create-confirm-action]')) { const action = confirmAction; confirmAction = null; confirmDialog.close(); action?.(); }
        });
        for (const dialog of [courseDialog, sectionDialog, confirmDialog].filter(Boolean)) {
            dialog.addEventListener('click', event => { if (event.target === dialog && !addingCourse && !addingSection && !courseManager.busy()) dialog.close(); });
            dialog.addEventListener('cancel', event => { if (addingCourse || addingSection || courseManager.busy()) event.preventDefault(); });
            dialog.addEventListener('keydown', event => {
                if (event.key !== 'Tab') return;
                const controls = [...dialog.querySelectorAll('button,input,select,textarea,a[href],[tabindex]')].filter(control => !control.disabled && !control.hidden && control.tabIndex >= 0 && (!control.getClientRects || control.getClientRects().length > 0));
                if (!controls.length) { event.preventDefault(); return; }
                const current = controls.indexOf(document.activeElement), next = event.shiftKey ? current <= 0 ? controls.length - 1 : current - 1 : current < 0 || current === controls.length - 1 ? 0 : current + 1;
                event.preventDefault(); controls[next].focus();
            });
        }
        courseDialog.querySelector('form').addEventListener('submit', async event => {
            event.preventDefault(); if (addingCourse || courseManager.busy()) return;
            const input = event.target.elements.name, name = input.value.trim();
            if (!name) { input.setCustomValidity('Enter a course name.'); input.reportValidity(); return; }
            input.setCustomValidity('');
            const duplicate = courseOptions().find(course => course.name.trim().toLocaleLowerCase() === name.toLocaleLowerCase());
            if (duplicate) { courseDialog.querySelector('[data-sb-course-error]').textContent = 'This course already exists. Select it from the course dropdown.'; courseDialog.querySelector('[data-sb-course-error]').hidden = false; return; }
            addingCourse = true; let courseAdded = pendingCourseName === name;
            const controls = [...courseDialog.querySelectorAll('button,input')]; controls.forEach(control => { control.disabled = true; });
            const courseError = courseDialog.querySelector('[data-sb-course-error]'); courseError.hidden = true;
            try {
                if (!courseAdded) { await request('/api/admin/academic-options', 'POST', {type: 'course', name}); courseAdded = true; pendingCourseName = name; }
                const refreshed = await request('/api/admin/state?resources=academic_options');
                if (!Array.isArray(refreshed.academic_options)) throw Error('Reference options unavailable.');
                state.academic_options = refreshed.academic_options;
                if (Array.isArray(refreshed.course_year_sections)) state.course_year_sections = refreshed.course_year_sections;
                const added = courseOptions().find(course => course.name === pendingCourseName);
                if (!added) throw Error('The new course could not be found.');
                if (editing && values.course_groups[courseTarget].course_id) {
                    const year = values.course_groups[courseTarget].year_level;
                    values.course_groups.push({department: legacyDepartment, course_id: String(added.id), major: '', year_level: optionNames('year_level').includes(year) ? year : '', sections: []});
                }
                else { const group = values.course_groups[courseTarget]; group.department = legacyDepartment; group.course_id = String(added.id); group.major = ''; group.sections = []; }
                pendingCourseName = ''; courseManager.render(); renderCards(); update(); rememberDraft(); courseDialog.close(); event.target.reset(); toast?.('Course added.');
            } catch (failure) { courseError.textContent = courseAdded ? 'Course was added. Click Add Course again to refresh the list.' : failure.errors ? unique(Object.values(failure.errors).flat()).join(' ') : failure.message; courseError.hidden = false; }
            finally { addingCourse = false; controls.forEach(control => { control.disabled = false; }); }
        });
        courseDialog.querySelector('input').addEventListener('input', event => event.target.setCustomValidity(''));
        sectionDialog?.querySelector('form').addEventListener('submit', async event => {
            event.preventDefault(); if (addingSection || sectionTarget == null) return;
            const section = event.target.elements.section.value.trim(), group = values.course_groups[sectionTarget], sectionError = builder.querySelector('[data-sb-section-error]');
            if (!section) return;
            addingSection = true; sectionError.hidden = true;
            const controls = [...sectionDialog.querySelectorAll('input,button')]; controls.forEach(control => { control.disabled = true; });
            try {
                await request('/api/admin/course-year-sections', 'POST', {course_id: Number(group.course_id), year_level: group.year_level, section});
                const refreshed = await request('/api/admin/state?resources=academic_options');
                if (!Array.isArray(refreshed.course_year_sections)) throw Error('Section added. Refresh the page to load the latest configured sections.');
                state.course_year_sections = refreshed.course_year_sections;
                if (Array.isArray(refreshed.academic_options)) state.academic_options = refreshed.academic_options;
                const configured = state.course_year_sections.find(row => String(row.course_id) === group.course_id && String(row.year_level) === group.year_level && String(row.section).toLocaleLowerCase() === section.toLocaleLowerCase());
                group.sections = unique([...group.sections, String(configured?.section || section)]);
                renderCards(); update(); sectionDialog.close(); event.target.reset(); toast?.('Section added.');
            } catch (failure) { sectionError.textContent = failure.errors ? unique(Object.values(failure.errors).flat()).join(' ') : failure.message; sectionError.hidden = false; }
            finally { addingSection = false; controls.forEach(control => { control.disabled = false; }); }
        });
        applyValues();
        return {read: () => { read(); return JSON.parse(JSON.stringify(values)); }, destroy: () => { courseManager.destroy(); window.removeEventListener?.('beforeunload', beforeUnload); }};
    }


    const groupKey = group => JSON.stringify([String(group.courseId ?? group.course_id), String(group.year_level), String(group.section)]);
    const editStatus = schedule => schedule.finalized_at ? 'Completed' : schedule.display_status || schedule.status || (schedule.active ? 'Open' : 'Closed');
    const editStatuses = schedule => ({Draft: ['Draft', 'Open'], Open: ['Open', 'Closed'], Closed: ['Closed', 'Open']})[editStatus(schedule)] || [];
    const editCourseOptions = (state, schedule) => {
        const rows = [...(Array.isArray(state.academic_population) ? createOptions(state, 'course') : optionsFor(state, 'course'))];
        for (const course of schedule.courses || []) if (!rows.some(row => String(row.id) === String(course.id))) rows.push(course);
        return rows;
    };
    function savedGroups(schedule) {
        const years = selection(schedule, 'year_levels', 'year_level').map(String);
        return (schedule.courses || []).flatMap(course => years.flatMap(year => scheduleSections(schedule, course.id, year).map(section => ({courseId: String(course.id), major: String(schedule.course_majors?.[course.id] || ''), year_level: year, section: String(section), label: `${course.name}${schedule.course_majors?.[course.id] ? ` (${schedule.course_majors[course.id]})` : ''} ${year}${section}`}))));
    }
    const editYearNames = (schedule, courseId) => unique([...optionNames('year_level'), ...savedGroups(schedule).filter(group => group.courseId === String(courseId)).map(group => group.year_level)]);
    function restoreEdit(schedule) {
        const years = selection(schedule, 'year_levels', 'year_level').map(String);
        const courseGroups = years.flatMap(year => (schedule.courses || []).filter(course => scheduleSections(schedule, course.id, year).length > 0).map(course => ({department: '', course_id: String(course.id), major: String(schedule.course_majors?.[course.id] || ''), year_level: year, sections: scheduleSections(schedule, course.id, year).map(String)})));
        if (!courseGroups.length) courseGroups.push(blankGroup());
        return {course_groups: courseGroups, date: String(schedule.date || '').slice(0, 10), start_time: String(schedule.start_time || '').slice(0, 5), end_time: String(schedule.end_time || '').slice(0, 5), venue: String(schedule.venue || ''), slot_limit: schedule.slot_limit == null ? '' : String(schedule.slot_limit), status: editStatus(schedule)};
    }
    // Older consumers can still provide the former year-block shape. The shared
    // form itself reads and writes the same course_groups shape in both modes.
    function editCards(values) {
        return Array.isArray(values.course_groups) ? createGroups(values) : (Array.isArray(values.year_blocks) ? values.year_blocks : []).flatMap(block => (Array.isArray(block.course_ids) ? block.course_ids : []).map(id => ({course_id: String(id), year_level: String(block.year_level || ''), sections: Array.isArray(block.course_sections?.[id]) ? [...block.course_sections[id]] : []})));
    }
    function editChanges(schedule, groups) {
        const previous = savedGroups(schedule), previousKeys = new Set(previous.map(groupKey)), nextKeys = new Set(groups.map(groupKey));
        const added = groups.filter(group => !previousKeys.has(groupKey(group)));
        const removed = previous.filter(group => !nextKeys.has(groupKey(group)));
        const years = selection(schedule, 'year_levels', 'year_level');
        const finiteScope = years.length > 0 && (schedule.courses || []).length > 0 && (schedule.courses || []).every(course => {
            const nested = schedule.course_sections_by_year?.[course.id] || (isSectionMap(course.pivot?.sections) ? course.pivot.sections : null);
            return nested ? Object.values(nested).every(sections => Array.isArray(sections) && sections.length > 0) : years.every(year => scheduleSections(schedule, course.id, year).length > 0);
        });
        return {added, removed, finiteScope, pureAddition: finiteScope && previous.length > 0 && added.length > 0 && removed.length === 0};
    }
    function evaluateEdit(values, state, schedule) {
        const cards = editCards(values), blocks = Array.isArray(values.year_blocks) && !Array.isArray(values.course_groups) ? values.year_blocks : null;
        const previous = new Set(savedGroups(schedule).map(groupKey));
        const availableCourses = editCourseOptions(state, schedule), courseIds = unique(cards.map(card => card.course_id).filter(Boolean));
        const courses = availableCourses.filter(course => courseIds.includes(String(course.id)));
        const courseValid = cards.length > 0 && cards.every(card => card.course_id && availableCourses.some(course => String(course.id) === card.course_id)) && (!blocks || blocks.every(block => Array.isArray(block.course_ids) && block.course_ids.length > 0 && unique(block.course_ids.map(String)).length === block.course_ids.length));
        const majorValid = courseValid && cards.every(card => { const majors = courseMajors(state, availableCourses.find(course => String(course.id) === card.course_id)); return majors.length === 0 || majors.includes(card.major); });
        const years = unique(cards.map(card => card.year_level));
        const yearValid = cards.length > 0 && cards.every(card => editYearNames(schedule, card.course_id).includes(card.year_level)) && unique(cards.map(card => JSON.stringify([card.course_id, card.year_level]))).length === cards.length && (!blocks || unique(blocks.map(block => block.year_level)).length === blocks.length);
        const sectionValid = courseValid && yearValid && (!blocks || blocks.every(block => isSectionMap(block.course_sections) && Object.keys(block.course_sections).every(id => block.course_ids.map(String).includes(id)))) && cards.every(card => {
            const id = card.course_id, sections = card.sections;
            return Array.isArray(sections) && sections.length > 0 && unique(sections).length === sections.length && sections.every(section => {
                if (typeof section !== 'string') return false;
                const group = {courseId: id, year_level: card.year_level, section, label: `${availableCourses.find(course => String(course.id) === id)?.name || id} ${card.year_level}${section}`};
                return previous.has(groupKey(group)) || sectionOptions(state, id, card.year_level).includes(section);
            });
        });
        const previewGroups = cards.flatMap(card => card.sections.map(section => ({courseId: card.course_id, major: card.major, year_level: card.year_level, section: String(section), label: `${availableCourses.find(course => String(course.id) === card.course_id)?.name || card.course_id}${card.major ? ` (${card.major})` : ''} ${card.year_level}${section}`})));
        const changes = editChanges(schedule, previewGroups);
        const locked = (schedule.assigned_academic_groups || []).map(group => ({courseId: String(group.course_id), year_level: String(group.year_level), section: String(group.section)}));
        const chosenKeys = new Set(previewGroups.map(groupKey));
        const unchangedGroups = changes.finiteScope && changes.added.length === 0 && changes.removed.length === 0;
        const assignedValid = unchangedGroups || changes.pureAddition || locked.every(group => chosenKeys.has(groupKey(group)));
        const attendanceRecorded = Number(schedule.registrations_recorded_attendance_count) > 0;
        const dateValid = validDate(values.date), start = timeMinutes(values.start_time), end = timeMinutes(values.end_time), timeValid = start !== null && end !== null && end > start;
        const venue = String(values.venue || '').trim(), venueValid = venue.length > 0 && venue.length <= 190;
        const attendanceValid = !attendanceRecorded || (values.date === String(schedule.date || '').slice(0, 10) && values.start_time === String(schedule.start_time || '').slice(0, 5) && values.end_time === String(schedule.end_time || '').slice(0, 5) && venue === String(schedule.venue || '').trim());
        const assignedCount = Math.max(0, Number(schedule.registrations_total_count ?? schedule.registrations_count) || 0), capacity = String(values.slot_limit ?? '').trim();
        const calendarYearValid = !assignedCount || String(values.date || '').slice(0, 4) === String(schedule.date || '').slice(0, 4);
        const capacityValid = capacity === '' || (/^[0-9]+$/.test(capacity) && Number(capacity) >= Math.max(1, assignedCount) && Number(capacity) <= 100000);
        const settingsValid = capacityValid && editStatuses(schedule).includes(values.status);
        const calendarUnchanged = values.date === String(schedule.date || '').slice(0, 10) && values.start_time === String(schedule.start_time || '').slice(0, 5) && values.end_time === String(schedule.end_time || '').slice(0, 5);
        const checkedGroups = changes.pureAddition && calendarUnchanged ? changes.added : previewGroups;
        const overlaps = dateValid && timeValid ? (state.schedules || []).filter(other => String(other.id) !== String(schedule.id) && editStatus(other) !== 'Cancelled' && other.date === values.date && timeMinutes(String(other.start_time || '').slice(0, 5)) !== null && timeMinutes(String(other.end_time || '').slice(0, 5)) !== null && start < timeMinutes(String(other.end_time).slice(0, 5)) && end > timeMinutes(String(other.start_time).slice(0, 5))) : [];
        const studentConflicts = overlaps.filter(other => checkedGroups.some(group => {
            const course = (other.courses || []).find(row => String(row.id) === group.courseId);
            if (!course) return false;
            const otherMajor = String(other.course_majors?.[group.courseId] || '');
            if (group.major && otherMajor && group.major !== otherMajor) return false;
            const otherYears = selection(other, 'year_levels', 'year_level').map(String);
            if (otherYears.length && !otherYears.includes(group.year_level)) return false;
            const sections = scheduleSections(other, group.courseId, group.year_level), nested = isSectionMap(other.course_sections_by_year?.[group.courseId]) || isSectionMap(course.pivot?.sections);
            return (!nested && sections.length === 0) || sections.map(String).includes(group.section);
        }));
        const venueConflicts = overlaps.filter(other => String(other.venue || '').trim().toLocaleLowerCase() === venue.toLocaleLowerCase());
        const venueBookingUnchanged = calendarUnchanged && venue === String(schedule.venue || '');
        const requiredValid = courseValid && majorValid && yearValid && sectionValid && dateValid && calendarYearValid && timeValid && venueValid && settingsValid && assignedValid && attendanceValid;
        return {courses, course_ids: courseIds, course_majors: majorMap(cards), course_groups: cards, year_levels: years, groups: previewGroups, changes, courseValid, majorValid, yearValid, sectionValid, dateValid, calendarYearValid, timeValid, venueValid, capacityValid, settingsValid, assignedValid, attendanceValid, attendanceRecorded, assignedCount, requiredValid, studentConflicts, venueConflicts, venueBookingUnchanged, canSave: requiredValid && !studentConflicts.length && (venueBookingUnchanged || !venueConflicts.length) && !['Completed', 'Cancelled'].includes(editStatus(schedule))};
    }
    function editPayload(values, state, schedule, publish = false) {
        const next = {...values, status: publish ? 'Open' : values.status}, result = evaluateEdit(next, state, schedule);
        if (['Completed', 'Cancelled'].includes(editStatus(schedule))) throw Error(editStatus(schedule) + ' schedules are view only.');
        if (!result.courseValid) throw Error('Select one valid course in every course group.');
        if (!result.majorValid) throw Error('Select a valid major for every course that offers majors.');
        if (!result.yearValid) throw Error('Select one year level per course group. The same course and year level cannot be selected twice.');
        if (!result.sectionValid) {
            const previous = new Set(savedGroups(schedule).map(groupKey)), invalid = result.groups.find(group => !previous.has(groupKey(group)) && !sectionOptions(state, group.courseId, group.year_level).includes(group.section));
            throw Error(invalid ? `${invalid.label} is not a configured course/year/section combination.` : 'Select at least one configured section for every course group.');
        }
        if (!result.assignedValid) throw Error('Changes cannot exclude students who are already assigned.');
        if (!result.attendanceValid) throw Error('Date, time and venue are locked because attendance has been recorded.');
        if (!result.calendarYearValid) throw Error('The recollection year cannot change because students are already assigned.');
        if (!result.capacityValid) throw Error(`Capacity cannot be lower than the ${result.assignedCount} students already assigned and must be a positive whole number if filled.`);
        if (!result.requiredValid) throw Error('Complete the course, year level, sections, date, time and venue. End time must be after start time.');
        if (result.studentConflicts.length) {
            const conflict = result.studentConflicts[0], groups = result.changes.pureAddition && values.date === String(schedule.date || '').slice(0, 10) && values.start_time === String(schedule.start_time || '').slice(0, 5) && values.end_time === String(schedule.end_time || '').slice(0, 5) ? result.changes.added : result.groups;
            const group = groups.find(group => (conflict.courses || []).some(course => String(course.id) === group.courseId) && (!selection(conflict, 'year_levels', 'year_level').length || selection(conflict, 'year_levels', 'year_level').map(String).includes(group.year_level)) && scheduleSections(conflict, group.courseId, group.year_level).map(String).includes(group.section));
            throw Error(`${group?.label || 'A selected course, year level and section'} overlaps ${conflict.title || 'another Recollection Day'} at the selected date and time.`);
        }
        if (!result.venueBookingUnchanged && result.venueConflicts.length) throw Error('The venue is already reserved by ' + (result.venueConflicts[0].title || 'another Recollection Day') + ' at the selected date and time.');
        const byYear = {};
        for (const card of result.course_groups) { byYear[card.course_id] ||= {}; byYear[card.course_id][card.year_level] = [...card.sections]; }
        return {course_ids: result.course_ids.map(Number), course_majors: result.course_majors, year_levels: [...result.year_levels], sections: unique(result.groups.map(group => group.section)), course_sections_by_year: byYear, date: values.date, start_time: values.start_time, end_time: values.end_time, venue: String(values.venue).trim(), slot_limit: String(values.slot_limit ?? '').trim() === '' ? null : Number(values.slot_limit), status: next.status, active: next.status === 'Open'};
    }
    const capacitySummary = (values, schedule) => String(values.slot_limit ?? '').trim() === '' ? 'No capacity limit.'
        : Number(values.slot_limit) === Number(schedule.slot_limit) ? `Capacity stays at ${values.slot_limit}.` : `Capacity will be ${values.slot_limit}.`;
    const editFingerprint = values => JSON.stringify({...values, course_groups: editCards(values).map(card => ({...card, sections: [...card.sections].sort()})).sort((first, second) => first.course_id.localeCompare(second.course_id) || first.year_level.localeCompare(second.year_level))});

    window.CfoScheduleCreate = {mount, evaluate, payload, restoreDraft, createOptions, createSectionOptions, createGroups, formatDate, formatTime, optionNames, sectionOptions, scheduleSections, restoreEdit, evaluateEdit, editPayload, savedGroups, editChanges, editStatuses};
})();
