(function (global) {
    'use strict';

    const STATUSES = ['Open', 'Draft', 'Closed', 'Completed', 'Cancelled'];
    const ATTENTION_WINDOW_DAYS = 7;
    const ALMOST_FULL_RATIO = 0.9;
    const MAX_ATTENTION_BADGES = 2;
    const TABLE_COLUMNS = [['Date & Time', 'date_time'], ['Course(s)', 'courses'],
        ['Venue', 'venue'], ['Status', 'status'], ['Registration', 'registration']];
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
    const unique = values => [...new Set(values)];
    const text = value => String(value ?? '').trim();
    const dateOnly = value => text(value).slice(0, 10);
    const pad = value => String(value).padStart(2, '0');
    const localDate = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const collator = new Intl.Collator('en', {numeric: true, sensitivity: 'base'});

    function statusOf(schedule) {
        if (schedule.finalized_at) return 'Completed';
        return STATUSES.includes(schedule.status) && schedule.status !== 'Completed'
            ? schedule.status : schedule.active ? 'Open' : 'Closed';
    }

    function selections(schedule, plural, legacy) {
        return Array.isArray(schedule[plural]) ? unique(schedule[plural].map(text).filter(Boolean))
            : text(schedule[legacy]) ? [text(schedule[legacy])] : [];
    }

    function courseNames(schedule) {
        return (schedule.courses || []).map(course => text(typeof course === 'string' ? course : course.name)).filter(Boolean);
    }

    function courseSectionsByYear(schedule) {
        const result = {};
        const add = (id, years) => {
            if (!years || typeof years !== 'object' || Array.isArray(years)) return;
            const mapped = {};
            Object.entries(years).forEach(([year, sections]) => {
                if (Array.isArray(sections)) mapped[text(year)] = unique(sections.map(text).filter(Boolean));
            });
            if (Object.keys(mapped).length) result[text(id)] = mapped;
        };
        Object.entries(schedule.course_sections_by_year || {}).forEach(([id, years]) => add(id, years));
        Object.entries(schedule.course_sections || {}).forEach(([id, years]) => {
            if (!Object.hasOwn(result, text(id))) add(id, years);
        });
        (schedule.courses || []).forEach(course => {
            if (course && typeof course === 'object' && !Object.hasOwn(result, text(course.id))) add(course.id, course.pivot?.sections);
        });
        return result;
    }

    function courseSections(schedule) {
        const result = {};
        const supplied = schedule.course_sections;
        if (supplied && typeof supplied === 'object' && !Array.isArray(supplied)) {
            Object.entries(supplied).forEach(([id, sections]) => {
                if (Array.isArray(sections)) result[text(id)] = unique(sections.map(text).filter(Boolean));
            });
        }
        (schedule.courses || []).forEach(course => {
            if (course && typeof course === 'object' && Array.isArray(course.pivot?.sections) && !Object.hasOwn(result, text(course.id))) {
                result[text(course.id)] = unique(course.pivot.sections.map(text).filter(Boolean));
            }
        });
        Object.entries(courseSectionsByYear(schedule)).forEach(([id, years]) => {
            result[id] = unique(Object.values(years).flat());
        });
        return result;
    }

    const hasCourseSections = schedule => Object.keys(courseSections(schedule)).length > 0;
    function sectionsForCourse(schedule, courseId, year) {
        const byYear = courseSectionsByYear(schedule);
        if (year != null && Object.hasOwn(byYear, text(courseId))) return byYear[text(courseId)][text(year)] || [];
        const mapped = courseSections(schedule);
        return Object.hasOwn(mapped, text(courseId)) ? mapped[text(courseId)] : selections(schedule, 'sections', 'section');
    }

    function academicGroups(schedule) {
        return (schedule.courses || []).flatMap(course => selections(schedule, 'year_levels', 'year_level')
            .flatMap(year => sectionsForCourse(schedule, course.id, year).map(section => ({
                courseId: text(course.id), courseName: text(course.name), year, section,
                label: `${text(course.name)} ${year}${section}`,
            }))));
    }

    const editPageUrl = id => `/admin/schedules/${encodeURIComponent(text(id))}/edit`;

    function yearSections(schedule) {
        const years = selections(schedule, 'year_levels', 'year_level');
        const sections = selections(schedule, 'sections', 'section');
        if (hasCourseSections(schedule)) return years.length
            ? unique(academicGroups(schedule).map(group => group.year + group.section))
            : unique((schedule.courses || []).flatMap(course => sectionsForCourse(schedule, course.id)));
        if (!years.length) return sections;
        if (!sections.length) return years;
        return years.flatMap(year => sections.map(section => year + section));
    }

    function yearSection(schedule) {
        if (!hasCourseSections(schedule)) return yearSections(schedule).join(', ') || '—';
        const years = selections(schedule, 'year_levels', 'year_level');
        return (schedule.courses || []).map(course => {
            const groups = years.length ? years.flatMap(year => sectionsForCourse(schedule, course.id, year).map(section => year + section)) : sectionsForCourse(schedule, course.id);
            return groups.length ? `${text(course.name)} ${groups.join(', ')}` : '';
        }).filter(Boolean).join(' · ') || '—';
    }
    function courseGroupLabels(schedule) {
        if (!hasCourseSections(schedule)) return [];
        return academicGroups(schedule).map(group => group.label);
    }
    const registered = schedule => Math.max(0, Number(statusOf(schedule) === 'Completed' ? schedule.registrations_total_count ?? schedule.registrations_count : schedule.registrations_count) || 0);
    const capacity = schedule => schedule.slot_limit == null || schedule.slot_limit === '' ? null : Number(schedule.slot_limit);
    const timeRange = schedule => `${text(schedule.start_time).slice(0, 5) || '—'} – ${text(schedule.end_time).slice(0, 5) || '—'}`;
    const minutes = value => /^([01]\d|2[0-3]):[0-5]\d$/.test(text(value).slice(0, 5)) ? Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5)) : null;
    function clock(value) {
        const valueMinutes = minutes(text(value));
        return valueMinutes == null ? '—' : `${Math.floor(valueMinutes / 60) % 12 || 12}:${pad(valueMinutes % 60)} ${valueMinutes < 720 ? 'AM' : 'PM'}`;
    }
    const calendarTime = schedule => `${clock(schedule.start_time)}–${clock(schedule.end_time)}`;

    function tableDate(schedule) {
        const day = dateOnly(schedule.date);
        if (!validDate(day)) return {date: '—', weekday: '', datetime: ''};
        // Parse a local calendar day so an ISO date never shifts into yesterday.
        const date = new Date(day + 'T12:00:00');
        return {date: date.toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}),
            weekday: date.toLocaleDateString('en-US', {weekday: 'long'}), datetime: day};
    }

    function tableTimeRange(schedule) {
        const format = value => {
            const valueMinutes = minutes(text(value));
            return valueMinutes == null ? '—' : `${pad(Math.floor(valueMinutes / 60) % 12 || 12)}:${pad(valueMinutes % 60)} ${valueMinutes < 720 ? 'AM' : 'PM'}`;
        };
        return `${format(schedule.start_time)} – ${format(schedule.end_time)}`;
    }

    function tableGroupLines(schedule) {
        if (!hasCourseSections(schedule)) return [yearSection(schedule)];
        const years = selections(schedule, 'year_levels', 'year_level');
        return (schedule.courses || []).map(course => {
            const groups = years.length ? years.flatMap(year => sectionsForCourse(schedule, course.id, year).map(section => year + section))
                : sectionsForCourse(schedule, course.id);
            return groups.length ? `${text(course.name)} ${unique(groups).join(', ')}` : '';
        }).filter(Boolean);
    }

    function tableCourseGroups(schedule) {
        const mapped = courseSectionsByYear(schedule), years = selections(schedule, 'year_levels', 'year_level');
        return (schedule.courses || []).flatMap(course => {
            const courseName = text(typeof course === 'string' ? course : course.name), courseId = text(course.id);
            if (!courseName) return [];
            const hasYearMap = Object.hasOwn(mapped, courseId), courseYears = hasYearMap ? Object.keys(mapped[courseId]) : years;
            if (!courseYears.length) return [{courseName, year: '', sections: sectionsForCourse(schedule, courseId)}];
            return courseYears.flatMap(year => {
                const sections = sectionsForCourse(schedule, courseId, year);
                return hasYearMap && !sections.length ? [] : [{courseName, year, sections}];
            });
        });
    }

    const courseYearLabel = year => /^[1-4]$/.test(year) ? year + ({1: 'st', 2: 'nd', 3: 'rd', 4: 'th'})[year] + ' Year' : year || 'Year not specified';

    function courseGroupsHtml(schedule) {
        const groups = tableCourseGroups(schedule);
        if (!groups.length) return '—';
        return '<ul class="ms-course-groups" aria-label="Courses, year levels and sections">' + groups.map(group => {
            const year = courseYearLabel(group.year);
            const sections = group.sections.map(section => '<span class="ms-section-chip">' + escape(group.year + section) + '</span>').join('');
            return '<li class="ms-course-group"><strong class="ms-course-group-name">' + escape(group.courseName) + ' · ' + escape(year) + '</strong><div class="ms-section-chips" aria-label="' + escape(group.courseName + ' ' + year + ' sections') + '">' + (sections || '<span class="ms-muted">Sections not specified</span>') + '</div></li>';
        }).join('') + '</ul>';
    }

    function tableStatus(schedule) {
        const status = statusOf(schedule);
        return {status, description: {Open: 'Registration ongoing', Draft: 'Not yet open', Closed: 'Registration ended',
            Completed: 'Registration closed', Cancelled: 'Schedule cancelled'}[status]};
    }

    function registrationProgress(schedule) {
        const count = registered(schedule), savedLimit = capacity(schedule);
        const hasLimit = savedLimit != null && Number.isFinite(savedLimit) && savedLimit > 0;
        const limit = hasLimit ? savedLimit : null;
        const percentage = hasLimit ? Math.round(count / limit * 100) : null;
        return {count, limit, percentage, width: hasLimit ? Math.min(100, Math.max(0, percentage)) : 0, hasLimit};
    }

    function calendarDayNumber(value) {
        const day = dateOnly(value);
        if (!validDate(day)) return null;
        const [year, month, date] = day.split('-').map(Number);
        return Date.UTC(year, month - 1, date) / 86400000;
    }

    function attentionIndicators(schedule, today = localDate(new Date())) {
        const status = statusOf(schedule);
        if (status === 'Completed' || status === 'Cancelled') return [];
        const indicators = [], count = registered(schedule), limit = capacity(schedule);
        const hasLimit = limit != null && Number.isFinite(limit) && limit > 0;
        const pending = Math.max(0, Number(schedule.current_pending_change_requests_count ?? schedule.current_change_requests_count) || 0)
            + Math.max(0, Number(schedule.requested_pending_change_requests_count ?? schedule.requested_change_requests_count) || 0);
        if (status === 'Open' && hasLimit && count >= limit) indicators.push({kind: 'full', label: 'Full', icon: '!'});
        if (pending > 0) indicators.push({kind: 'pending', label: `${pending} change request${pending === 1 ? '' : 's'} pending`, icon: '↔'});
        if (status === 'Open' && hasLimit && count < limit && count >= ALMOST_FULL_RATIO * limit) indicators.push({kind: 'almost-full', label: 'Almost full', icon: '!'});
        const day = calendarDayNumber(schedule.date), currentDay = calendarDayNumber(today);
        if (status === 'Open' && count === 0 && day != null && currentDay != null && day >= currentDay && day - currentDay <= ATTENTION_WINDOW_DAYS) {
            indicators.push({kind: 'no-registrations', label: 'No registrations yet', icon: '○'});
        }
        if (status === 'Draft') indicators.push({kind: 'draft', label: 'Not published', icon: '✎'});
        return indicators;
    }

    function attentionPresentation(schedule, today) {
        const indicators = attentionIndicators(schedule, today), hidden = indicators.slice(MAX_ATTENTION_BADGES);
        return {indicators, visible: indicators.slice(0, MAX_ATTENTION_BADGES), hidden,
            hiddenTitle: hidden.map(indicator => indicator.label).join('; ')};
    }

    function defaultOrderSchedules(rows, today = localDate(new Date())) {
        const currentDay = calendarDayNumber(today);
        const group = schedule => {
            if (['Completed', 'Cancelled'].includes(statusOf(schedule))) return 2;
            const day = calendarDayNumber(schedule.date);
            return day != null && (currentDay == null || day >= currentDay) ? 0 : 1;
        };
        return [...rows].sort((left, right) => group(left) - group(right)
            || collator.compare(dateOnly(left.date) || '9999-12-31', dateOnly(right.date) || '9999-12-31')
            || Number(left.id) - Number(right.id));
    }

    function editRestrictions(schedule = {}) {
        const assignedCount = Math.max(0, Number(schedule.registrations_total_count ?? schedule.registrations_count) || 0);
        const status = statusOf(schedule);
        return {assignedCount, minCapacity: Math.max(1, assignedCount),
            attendanceRecorded: Number(schedule.registrations_recorded_attendance_count) > 0,
            readOnlyReason: status === 'Completed' ? 'Completed schedules are view only.' : status === 'Cancelled' ? 'Cancelled schedules are view only.' : ''};
    }

    function filterSchedules(rows, filters = {}) {
        const query = text(filters.q).toLocaleLowerCase();
        return rows.filter(schedule => {
            const displayedDate = tableDate(schedule);
            const searchable = [schedule.title, dateOnly(schedule.date), displayedDate.date, displayedDate.weekday,
                timeRange(schedule), calendarTime(schedule), tableTimeRange(schedule), schedule.venue,
                ...courseNames(schedule), yearSection(schedule), ...courseGroupLabels(schedule), ...yearSections(schedule), ...tableCourseGroups(schedule).map(group => group.courseName + ' ' + courseYearLabel(group.year)), ...selections(schedule, 'year_levels', 'year_level'),
                ...selections(schedule, 'sections', 'section'), statusOf(schedule)].join(' ').toLocaleLowerCase();
            const course = text(filters.course), year = text(filters.year_level ?? filters.year), section = text(filters.section);
            const academic = filters.academic;
            const matchingCourses = (schedule.courses || []).filter(item => {
                const name = text(typeof item === 'string' ? item : item.name);
                if (course && course !== 'All' && name !== course && text(item.id) !== course) return false;
                if (!academic || !global.CfoAcademicFilters) return true;
                const profile = global.CfoAcademicFilters.profile({course:name, major:schedule.course_majors?.[item.id]});
                // A schedule without a major restriction includes every major in its course.
                const scope = !profile.major ? {...academic, major:[]} : academic;
                return global.CfoAcademicFilters.matches(scope, profile)
                    && (profile.major || !academic.major?.length || academic.major.some(value => { try { return JSON.parse(value)[0] === name; } catch { return false; } }));
            });
            const yearFilter = year && year !== 'All', sectionFilter = section && section !== 'All';
            const sectionMatches = hasCourseSections(schedule) && (yearFilter || sectionFilter)
                ? academicGroups(schedule).some(group => matchingCourses.some(item => text(item.id) === group.courseId)
                    && (!yearFilter || group.year === year) && (!sectionFilter || group.section === section))
                : !sectionFilter || (hasCourseSections(schedule)
                ? matchingCourses.some(item => sectionsForCourse(schedule, item.id).includes(section))
                : selections(schedule, 'sections', 'section').includes(section));
            return (!query || searchable.includes(query))
                && (!filters.status || filters.status === 'All' || statusOf(schedule) === filters.status)
                && (!course || course === 'All' || matchingCourses.length > 0)
                && (!academic?.department?.length || matchingCourses.length > 0)
                && (!year || year === 'All' || selections(schedule, 'year_levels', 'year_level').includes(year))
                && sectionMatches;
        });
    }

    function sortSchedules(rows, key = 'date', direction = 'asc') {
        const value = schedule => ({title: () => text(schedule.title), courses: () => courseNames(schedule).join(', '),
            year_section: () => yearSection(schedule), date: () => dateOnly(schedule.date), time: () => timeRange(schedule),
            date_time: () => `${dateOnly(schedule.date)} ${text(schedule.start_time).slice(0, 5)} ${text(schedule.end_time).slice(0, 5)}`,
            venue: () => text(schedule.venue), registration: () => registered(schedule), status: () => statusOf(schedule)}[key] || (() => dateOnly(schedule.date)))();
        const sign = direction === 'desc' ? -1 : 1;
        return [...rows].sort((left, right) => {
            const a = value(left), b = value(right);
            const comparison = typeof a === 'number' ? a - b : collator.compare(a, b);
            return comparison * sign || collator.compare(dateOnly(left.date), dateOnly(right.date)) || Number(left.id) - Number(right.id);
        });
    }

    function paginateSchedules(rows, requestedPage = 1, perPage = 10) {
        const size = [10, 25, 50].includes(Number(perPage)) ? Number(perPage) : 10;
        const totalPages = Math.max(1, Math.ceil(rows.length / size));
        const page = Math.min(totalPages, Math.max(1, Number(requestedPage) || 1));
        const start = (page - 1) * size;
        return {rows: rows.slice(start, start + size), page, totalPages, total: rows.length,
            from: rows.length ? start + 1 : 0, to: Math.min(start + size, rows.length)};
    }

    function buildCsv(rows) {
        const content = [['Recollection Day', 'Course(s)', 'Year/Section', 'Date', 'Time', 'Venue', 'Registration', 'Capacity', 'Status'],
            ...rows.map(schedule => [schedule.title, courseNames(schedule).join(' / '), yearSection(schedule), dateOnly(schedule.date),
                timeRange(schedule), schedule.venue, registered(schedule), capacity(schedule) == null ? 'No limit' : capacity(schedule), statusOf(schedule)])];
        // Neutralize spreadsheet formulas in user-entered fields while preserving
        // normal punctuation, quotes and multiline descriptions in valid CSV.
        const quote = value => '"' + String(value ?? '').replace(/^[\t\r\n ]*(?=[=+@-])/, "'").replace(/"/g, '""') + '"';
        return '\uFEFF' + content.map(row => row.map(quote).join(',')).join('\r\n') + '\r\n';
    }

    function calendarMonth(rows, year, month) {
        const first = new Date(year, month, 1, 12);
        const start = new Date(first);
        start.setDate(1 - (first.getDay() + 6) % 7);
        const count = Math.ceil(((first.getDay() + 6) % 7 + new Date(year, month + 1, 0).getDate()) / 7) * 7;
        const dates = new Map();
        sortSchedules(rows, 'time').forEach(schedule => {
            const date = dateOnly(schedule.date);
            if (!dates.has(date)) dates.set(date, []);
            dates.get(date).push(schedule);
        });
        return {title: first.toLocaleDateString('en-US', {month: 'long', year: 'numeric'}),
            weekdays: ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'],
            cells: Array.from({length: count}, (_, index) => {
                const day = new Date(start); day.setDate(start.getDate() + index);
                const date = localDate(day), events = dates.get(date) || [];
                return {date, day: day.getDate(), outside: day.getMonth() !== first.getMonth(), events,
                    visibleEvents: events.slice(0, 2), more: Math.max(0, events.length - 2)};
            })};
    }

    function optionRows(state, type) {
        return (state.academic_options || []).filter(option => option.type === type);
    }
    function optionNames(state, type) {
        const plural = type === 'year_level' ? 'year_levels' : 'sections';
        const defaults = type === 'year_level' ? ['1', '2', '3', '4'] : Array.from({length: 26}, (_, index) => String.fromCharCode(65 + index));
        return unique([...defaults, ...optionRows(state, type).map(option => text(option.name)),
            ...(state.schedules || []).flatMap(schedule => selections(schedule, plural, type))].filter(Boolean)).sort(collator.compare);
    }
    function validDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        const day = new Date(value + 'T12:00:00');
        return !Number.isNaN(day.getTime()) && localDate(day) === value;
    }

    const button = (label, action, className = 'ms-secondary', extra = '') => `<button type="button" class="${className}" data-ms-action="${action}" ${extra}>${label}</button>`;
    const badge = schedule => `<span class="ms-badge ms-${statusOf(schedule).toLowerCase()}"><span aria-hidden="true">●</span> ${statusOf(schedule)}</span>`;
    function registrationHtml(schedule) {
        const {count, limit, percentage, width, hasLimit} = registrationProgress(schedule);
        return !hasLimit ? `<div class="ms-registration"><div>${count} registered</div><div class="ms-muted">No limit</div></div>`
            : `<div class="ms-registration"><div class="ms-registration-count">${count} / ${limit}</div><div class="ms-registration-meter"><div class="ms-progress" role="progressbar" aria-label="Registration capacity" aria-valuemin="0" aria-valuemax="${limit}" aria-valuenow="${Math.min(count, limit)}" aria-valuetext="${count} of ${limit} registered, ${percentage}%"><i style="width:${width}%"></i></div><span class="ms-registration-percent">${percentage}%</span></div></div>`;
    }

    const controllers = typeof WeakMap === 'function' ? new WeakMap() : null;
    let instance = 0;
    class MySchedules {
        constructor(options) {
            this.root = options.root;
            this.options = options;
            this.state = options.state || {};
            this.ui = {q: '', status: '', course: '', year_level: '', section: '', sort: null, direction: 'asc', page: 1, perPage: 10, view: 'table'};
            const now = new Date(dateOnly(this.state.server_date) ? dateOnly(this.state.server_date) + 'T12:00:00' : Date.now());
            this.year = now.getFullYear(); this.month = now.getMonth();
            this.uid = 'ms-' + ++instance;
            this.abort = new AbortController();
            this.modal = null; this.menu = null; this.busy = false; this.destroyed = false;
            this.root.classList.add('my-schedules');
            this.renderShell();
            this.bind();
            this.draw();
            if (options.openId != null) this.openEdit(options.openId);
            else if (options.create) this.options.onCreate?.();
        }

        update(state, options = {}) {
            this.state = state || this.state;
            this.options = {...this.options, ...options, state: this.state};
            this.refreshFilterOptions();
            this.draw();
            return this;
        }

        isModalOpen() { return Boolean(this.modal); }
        destroy() {
            this.academicControl?.destroy();
            this.destroyed = true;
            this.abort.abort();
            this.closeMenu(); this.closeModal(true);
            this.root.classList.remove('my-schedules');
            controllers?.delete(this.root);
        }
        rows() { return this.state.schedules || []; }
        find(id) { return this.rows().find(schedule => text(schedule.id) === text(id)); }
        today() { return dateOnly(this.state.server_date) || localDate(new Date()); }
        matching() {
            const rows = filterSchedules(this.rows(), this.ui);
            return this.ui.sort ? sortSchedules(rows, this.ui.sort, this.ui.direction) : defaultOrderSchedules(rows, this.today());
        }
        fieldId(name) { return `${this.uid}-${name}`; }

        renderShell() {
            this.root.innerHTML = `<div class="ms-shell">
                <section class="ms-page-head"><div><div class="ms-kicker">RECOLLECTION</div><h1 id="ms-page-title" tabindex="-1">Your Recollection Schedules</h1><p>Browse schedules, manage registration, and open their details.</p></div>${button('＋ Create New Recollection Schedule', 'create', 'ms-primary')}</section>
                <section class="ms-summary-grid" aria-label="Schedule summary"></section>
                <section class="ms-filter-panel"><div class="ms-panel-title">Filter this table</div><div class="ms-filters">
                    ${this.filterField('Schedule status', 'status', STATUSES)}<div class="ms-field ms-search-field"><label for="${this.fieldId('search')}">Search schedules</label><input id="${this.fieldId('search')}" data-ms-filter="q" type="search" placeholder="Recollection title, date, venue, course or status" autocomplete="off"></div>${this.filterField('Course', 'course', [])}${this.filterField('Year level', 'year_level', [])}${this.filterField('Section', 'section', [])}
                    <div class="ms-field"><span class="ms-field-label">View</span><div class="ms-view-switch" role="group" aria-label="Schedule view">${button('Table', 'view-table', 'ms-active', 'aria-pressed="true"')}${button('Calendar', 'view-calendar', '', 'aria-pressed="false"')}</div></div><div class="ms-filter-actions">${button('Clear filters', 'clear-filters')}</div></div></section>
                <section class="ms-table-panel"><div class="ms-table-head"><div><h2>Recollection Schedules</h2><small data-ms-record-count></small></div></div><div class="ms-table-wrap" tabindex="0" role="region" aria-label="Recollection schedules table"><table><colgroup><col class="ms-col-date-time"><col class="ms-col-courses"><col class="ms-col-venue"><col class="ms-col-status"><col class="ms-col-registration"><col class="ms-col-actions"></colgroup><thead></thead><tbody></tbody></table></div></section>
                <section class="ms-calendar-panel" hidden><div class="ms-calendar-head"><h2></h2><div class="ms-calendar-controls">${button('←', 'month-prev', 'ms-page-btn', 'aria-label="Previous month"')}${button('Today', 'month-today', 'ms-page-btn')}${button('→', 'month-next', 'ms-page-btn', 'aria-label="Next month"')}</div></div><div class="ms-calendar-scroll"><div class="ms-calendar-grid" aria-label="Monthly schedule calendar"></div></div></section>
                <footer class="ms-table-footer" aria-label="Schedule pagination"></footer>
                <div class="ms-page-feedback" role="status" aria-live="polite"></div>
            </div>`;
            this.refreshFilterOptions();
            if (global.CfoAcademicFilters) {
                const host = document.createElement('div');
                this.root.querySelector('.ms-filter-panel').prepend(host);
                this.root.querySelector('[data-ms-filter="course"]').closest('.ms-field').hidden = true;
                this.academicControl = global.CfoAcademicFilters.mount(host, {
                    courses: this.rows().flatMap(courseNames),
                    profiles: () => this.state.academic_population || [],
                    onChange: value => { this.ui.academic = value; this.ui.page = 1; this.refreshFilterOptions(); this.draw(); }
                });
                this.ui.academic = this.academicControl.value();
            }
        }

        filterField(label, key, values) {
            return `<div class="ms-field"><label for="${this.fieldId(key)}">${label}</label><select id="${this.fieldId(key)}" data-ms-filter="${key}"><option value="">All</option>${values.map(value => `<option value="${escape(value)}">${escape(value)}</option>`).join('')}</select></div>`;
        }
        refreshFilterOptions() {
            const courses = unique([...optionRows(this.state, 'course').map(course => course.name), ...this.rows().flatMap(courseNames)]).sort(collator.compare);
            const values = {course: courses, year_level: optionNames(this.state, 'year_level'), section: optionNames(this.state, 'section')};
            if (Array.isArray(this.state.academic_population) && global.CfoAcademicFilters) {
                const population=this.state.academic_population.filter(row=>global.CfoAcademicFilters.matches(this.ui.academic || {},global.CfoAcademicFilters.profile(row)));
                values.year_level=unique(population.map(row=>text(row.year_level)).filter(Boolean)).sort();
                if(!values.year_level.includes(this.ui.year_level))this.ui.year_level='';
                values.section=unique(population.filter(row=>!this.ui.year_level||text(row.year_level)===this.ui.year_level).map(row=>text(row.section)).filter(Boolean)).sort();
                if(!values.section.includes(this.ui.section))this.ui.section='';
            }
            Object.entries(values).forEach(([name, names]) => {
                const select = this.root.querySelector(`[data-ms-filter="${name}"]`);
                if (!select) return;
                select.innerHTML = '<option value="">All</option>' + unique([...names, this.ui[name]].filter(Boolean)).map(value => `<option value="${escape(value)}">${escape(value)}</option>`).join('');
                select.value = this.ui[name];
            });
        }

        bind() {
            const signal = this.abort.signal;
            this.root.addEventListener('click', event => {
                const target = event.target.closest('[data-ms-action]');
                if (target && this.root.contains(target)) { event.preventDefault(); this.action(target.dataset.msAction, target); }
                else if (this.modal && event.target === this.modal) this.closeModal();
            }, {signal});
            this.root.addEventListener('input', event => {
                const filter = event.target.dataset.msFilter;
                if (filter === 'q') { this.ui.q = event.target.value; this.ui.page = 1; this.draw(); }
            }, {signal});
            this.root.addEventListener('change', event => {
                const filter = event.target.dataset.msFilter;
                if (filter && filter !== 'q') { this.ui[filter] = event.target.value; this.ui.page = 1; this.refreshFilterOptions(); this.draw(); }
                if (event.target.dataset.msPerPage != null) { this.ui.perPage = Number(event.target.value); this.ui.page = 1; this.draw(); }
            }, {signal});
            document.addEventListener('keydown', event => this.keydown(event), {signal});
            document.addEventListener('click', event => { if (this.menu && !this.menu.contains(event.target) && !event.target.closest('[data-ms-action="more"]')) this.closeMenu(); }, {signal});
            global.addEventListener('resize', () => this.closeMenu(), {signal});
            global.addEventListener('scroll', () => this.closeMenu(), {signal, capture: true});
        }

        draw() {
            if (this.destroyed) return;
            this.closeMenu();
            const all = this.rows();
            this.root.querySelector('.ms-summary-grid').innerHTML = [['Total Schedules', all.length], ['Open Registration', all.filter(row => statusOf(row) === 'Open').length], ['Drafts', all.filter(row => statusOf(row) === 'Draft').length], ['Completed', all.filter(row => statusOf(row) === 'Completed').length]]
                .map(([label, count]) => `<div class="ms-summary"><span>${label}</span><strong>${count}</strong></div>`).join('');
            const matching = this.matching(), page = paginateSchedules(matching, this.ui.page, this.ui.perPage);
            this.ui.page = page.page;
            this.root.querySelector('[data-ms-record-count]').textContent = `${matching.length} ${matching.length === 1 ? 'record' : 'records'}`;
            this.root.querySelector('thead').innerHTML = '<tr>' + TABLE_COLUMNS.map(([label, key]) => `<th scope="col" data-ms-column="${key}" aria-sort="${this.ui.sort === key ? this.ui.direction === 'asc' ? 'ascending' : 'descending' : 'none'}">${button(`${label} <span aria-hidden="true">${this.ui.sort === key ? this.ui.direction === 'asc' ? '↑' : '↓' : '↕'}</span>`, 'sort', '', `data-ms-sort="${key}" aria-label="Sort by ${label}, ${this.ui.sort === key && this.ui.direction === 'asc' ? 'descending' : 'ascending'}"`)}</th>`).join('') + '<th scope="col" data-ms-column="actions">Actions</th></tr>';
            this.root.querySelector('tbody').innerHTML = page.rows.length ? page.rows.map(schedule => {
                const restrictions = editRestrictions(schedule), attention = attentionPresentation(schedule, this.today());
                const day = tableDate(schedule), status = tableStatus(schedule);
                const rowClasses = [restrictions.readOnlyReason ? 'ms-terminal-row' : '', attention.indicators.length ? `ms-needs-attention ms-attention-${attention.indicators[0].kind}` : ''].filter(Boolean).join(' ');
                const attentionHtml = attention.visible.length ? `<div class="ms-attention-badges">${attention.visible.map(indicator => `<span class="ms-attention-badge ms-attention-${indicator.kind}"><span aria-hidden="true">${indicator.icon}</span> ${escape(indicator.label)}</span>`).join('')}</div>` : '';
                return `<tr class="${rowClasses}" data-ms-schedule-id="${schedule.id}"><td class="ms-date-time">${day.datetime ? `<time datetime="${day.datetime}">${escape(day.date)}</time>` : '<strong>—</strong>'}<div class="ms-time-line ms-muted">${escape(tableTimeRange(schedule))}</div>${day.weekday ? `<div class="ms-weekday ms-muted">${escape(day.weekday)}</div>` : ''}</td><td class="ms-courses">${courseGroupsHtml(schedule)}</td><td class="ms-venue">${escape(schedule.venue || '—')}</td><td class="ms-status" ${attention.hiddenTitle ? `title="${escape('Also needs attention: ' + attention.hiddenTitle)}"` : ''}>${badge(schedule)}<div class="ms-status-description ms-muted">${escape(status.description)}</div>${attentionHtml}</td><td class="ms-registration-cell">${registrationHtml(schedule)}</td><td class="ms-actions-cell"><div class="ms-actions">${button('View', 'view', 'ms-action', `data-ms-id="${schedule.id}"`)}${!restrictions.readOnlyReason ? button('Edit', 'edit', 'ms-action', `data-ms-id="${schedule.id}"`) : ''}${button('⋮', 'more', 'ms-action ms-more', `data-ms-id="${schedule.id}" aria-label="More actions for ${escape(schedule.title)}" aria-haspopup="menu" aria-expanded="false"`)}</div>${restrictions.readOnlyReason ? `<div class="ms-edit-reason">${escape(restrictions.readOnlyReason)}</div>` : ''}</td></tr>`;
            }).join('') : '<tr><td colspan="6" class="ms-empty">No schedules match the current search and filters.</td></tr>';
            this.root.querySelector('.ms-table-footer').innerHTML = `<span aria-live="polite">Showing ${page.from}–${page.to} of ${page.total}</span><div class="ms-pagination"><label for="${this.fieldId('per-page')}" class="ms-search-label">Rows per page</label><select id="${this.fieldId('per-page')}" data-ms-per-page aria-label="Rows per page">${[10, 25, 50].map(size => `<option ${size === this.ui.perPage ? 'selected' : ''}>${size}</option>`).join('')}</select>${button('←', 'page-prev', 'ms-page-btn', `aria-label="Previous page" ${page.page === 1 ? 'disabled' : ''}`)}<span class="ms-page-number">${page.page} / ${page.totalPages}</span>${button('→', 'page-next', 'ms-page-btn', `aria-label="Next page" ${page.page === page.totalPages ? 'disabled' : ''}`)}</div>`;
            this.root.querySelector('.ms-table-panel').hidden = this.ui.view !== 'table';
            this.root.querySelector('.ms-table-footer').hidden = this.ui.view !== 'table';
            this.root.querySelector('.ms-calendar-panel').hidden = this.ui.view !== 'calendar';
            this.root.querySelectorAll('.ms-view-switch button').forEach(item => {
                const active = item.dataset.msAction === 'view-' + this.ui.view;
                item.classList.toggle('ms-active', active); item.setAttribute('aria-pressed', String(active));
            });
            this.drawCalendar(matching);
        }

        drawCalendar(matching) {
            const month = calendarMonth(matching, this.year, this.month);
            this.root.querySelector('.ms-calendar-head h2').textContent = month.title;
            const today = dateOnly(this.state.server_date) || localDate(new Date());
            this.root.querySelector('.ms-calendar-grid').innerHTML = month.weekdays.map(day => `<div class="ms-day-name">${day}</div>`).join('') + month.cells.map(day => `<div class="ms-day ${day.outside ? 'ms-outside' : ''} ${day.date === today ? 'ms-today' : ''}"><div class="ms-day-num"><time datetime="${day.date}">${day.day}</time>${day.date === today ? ' · Today' : ''}</div>${day.visibleEvents.map(schedule => button(`<strong>${escape(schedule.title)}</strong><span>${escape(courseNames(schedule).join(' / '))} · ${escape(yearSection(schedule))}</span><br><span>${escape(calendarTime(schedule))}</span>`, 'view', `ms-event ${statusOf(schedule) === 'Open' ? 'ms-open-event' : statusOf(schedule) === 'Draft' ? 'ms-draft-event' : ''}`, `data-ms-id="${schedule.id}" aria-label="${escape(schedule.title + ', ' + day.date + ', ' + calendarTime(schedule))}"`)).join('')}${day.more ? button(`+${day.more} more`, 'day-more', 'ms-day-more', `data-ms-date="${day.date}" aria-label="View all ${day.events.length} schedules on ${day.date}"`) : ''}</div>`).join('');
        }

        action(action, target) {
            if (this.busy && this.modal) return;
            const id = target.dataset.msId;
            if (action === 'create') {
                if (this.options.onCreate) this.options.onCreate();
                else global.location.assign('/admin#admin-add-new-recollection-schedule');
            }
            else if (action === 'view') this.openView(id);
            else if (action === 'edit') this.openEdit(id);
            else if (action === 'close') this.closeModal();
            else if (action === 'clear-search') { this.ui.q = ''; this.root.querySelector('[data-ms-filter="q"]').value = ''; this.ui.page = 1; this.draw(); }
            else if (action === 'clear-filters') { ['status', 'course', 'year_level', 'section'].forEach(key => { this.ui[key] = ''; this.root.querySelector(`[data-ms-filter="${key}"]`).value = ''; }); this.academicControl?.reset(); this.ui.page = 1; this.draw(); }
            else if (action === 'sort') { const key = target.dataset.msSort; this.ui.direction = this.ui.sort === key && this.ui.direction === 'asc' ? 'desc' : 'asc'; this.ui.sort = key; this.ui.page = 1; this.draw(); this.root.querySelector(`[data-ms-sort="${key}"]`)?.focus({preventScroll: true}); }
            else if (action === 'page-prev' || action === 'page-next') { this.ui.page += action === 'page-prev' ? -1 : 1; this.draw(); this.root.querySelector(`[data-ms-action="${action}"]`)?.focus({preventScroll: true}); }
            else if (action === 'view-table' || action === 'view-calendar') { this.ui.view = action.slice(5); this.draw(); }
            else if (action === 'export') this.export();
            else if (action === 'month-prev' || action === 'month-next' || action === 'month-today') {
                const date = action === 'month-today' ? new Date((dateOnly(this.state.server_date) || localDate(new Date())) + 'T12:00:00') : new Date(this.year, this.month + (action === 'month-next' ? 1 : -1), 1);
                this.year = date.getFullYear(); this.month = date.getMonth(); this.drawCalendar(this.matching());
            } else if (action === 'day-more') this.openDay(target.dataset.msDate);
            else if (action === 'more') this.openMenu(id, target);
            else if (action === 'delete') this.openDelete(id);
            else if (action === 'cancel-schedule') this.openCancel(id);
            else if (action === 'delete-confirm') this.deleteSchedule(id);
            else if (action === 'cancel-confirm') this.changeStatus(id, 'Cancelled');
            else if (action === 'status') this.changeStatus(id, target.dataset.msStatus);
        }

        export() {
            const url = URL.createObjectURL(new Blob([buildCsv(this.matching())], {type: 'text/csv;charset=utf-8;'}));
            const link = document.createElement('a'); link.href = url; link.download = 'recollection-schedules.csv';
            document.body.append(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }

        openMenu(id, anchor) {
            if (this.menu?.dataset.msId === text(id)) { this.closeMenu(); return; }
            this.closeMenu();
            const schedule = this.find(id); if (!schedule) return;
            const status = statusOf(schedule), items = [];
            if (status === 'Open') items.push(['Close registration', 'status', 'Closed'], ['Cancel schedule', 'cancel-schedule']);
            else if (status === 'Draft') items.push(['Publish', 'status', 'Open'], ['Delete', 'delete']);
            else if (status === 'Closed') items.push(['Reopen registration', 'status', 'Open'], ['Cancel schedule', 'cancel-schedule']);
            else items.push(['View', 'view']);
            const menu = document.createElement('div'); menu.className = 'ms-menu'; menu.setAttribute('role', 'menu'); menu.dataset.msId = text(id);
            menu.innerHTML = items.map(([label, action, next]) => button(label, action, action === 'cancel-schedule' || action === 'delete' ? 'ms-menu-danger' : '', `role="menuitem" data-ms-id="${id}" ${next ? `data-ms-status="${next}"` : ''}`)).join('');
            this.root.append(menu); this.menu = menu; this.menuAnchor = anchor; anchor.setAttribute('aria-expanded', 'true');
            const rect = anchor.getBoundingClientRect(), width = menu.offsetWidth || 210, height = menu.offsetHeight;
            menu.style.left = `${Math.max(8, Math.min(rect.right - width, global.innerWidth - width - 8))}px`;
            menu.style.top = `${Math.max(8, rect.bottom + height + 8 <= global.innerHeight ? rect.bottom + 6 : rect.top - height - 6)}px`;
            menu.querySelector('button')?.focus({preventScroll: true});
        }
        closeMenu(restoreFocus = false) {
            if (!this.menu) return;
            this.menu.remove(); this.menu = null;
            this.menuAnchor?.setAttribute('aria-expanded', 'false');
            if (restoreFocus && this.menuAnchor?.isConnected) this.menuAnchor.focus({preventScroll: true});
            this.menuAnchor = null;
        }

        openModal(title, body, footer, small = false) {
            const previousFocus = this.modalReturnFocus || document.activeElement;
            this.closeMenu(); this.closeModal(true);
            this.modalReturnFocus = previousFocus;
            const backdrop = document.createElement('div'); backdrop.className = 'ms-modal-backdrop ms-show';
            backdrop.innerHTML = `<div class="ms-modal ${small ? 'ms-small' : ''}" role="dialog" aria-modal="true" aria-labelledby="${this.fieldId('modal-title')}" tabindex="-1"><div class="ms-modal-head"><h3 id="${this.fieldId('modal-title')}">${escape(title)}</h3>${button('×', 'close', 'ms-close', 'aria-label="Close dialog"')}</div><div class="ms-modal-body">${body}<div class="ms-modal-feedback" role="alert" tabindex="-1"></div></div><div class="ms-modal-foot">${footer}</div></div>`;
            this.root.append(backdrop); this.modal = backdrop;
            this.root.querySelector('.ms-shell').inert = true;
            const focus = backdrop.querySelector('[autofocus], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), .ms-close');
            focus?.focus({preventScroll: true});
        }
        closeModal(force = false) {
            if (!this.modal || this.busy && !force) return;
            this.modal.remove(); this.modal = null;
            const shell = this.root.querySelector('.ms-shell'); if (shell) shell.inert = false;
            if (!force) {
                const previous = this.modalReturnFocus;
                if (previous?.isConnected && !previous.closest('[inert]')) previous.focus({preventScroll: true});
                else this.root.querySelector('[data-ms-action="create"]')?.focus({preventScroll: true});
                this.modalReturnFocus = null;
                this.options.onModalClose?.();
            }
        }
        keydown(event) {
            if (this.menu) {
                const buttons = [...this.menu.querySelectorAll('button')];
                if (event.key === 'Escape') { event.preventDefault(); this.closeMenu(true); return; }
                if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                    event.preventDefault(); const current = buttons.indexOf(document.activeElement);
                    const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length;
                    buttons[next]?.focus(); return;
                }
            }
            if (!this.modal) return;
            if (event.key === 'Escape') { event.preventDefault(); this.closeModal(); return; }
            if (event.key !== 'Tab') return;
            const focusable = [...this.modal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex="0"]')].filter(element => element.getClientRects().length);
            if (!focusable.length) { event.preventDefault(); this.modal.querySelector('[role="dialog"]').focus(); return; }
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && (document.activeElement === first || !this.modal.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && (document.activeElement === last || !this.modal.contains(document.activeElement))) { event.preventDefault(); first.focus(); }
        }

        openView(id) {
            const schedule = this.find(id); if (!schedule) return;
            const detail = (label, value, full = false, html = false) => `<div class="ms-detail ${full ? 'ms-full' : ''}"><span>${label}</span><strong>${html ? value : escape(value || '—')}</strong></div>`;
            const body = `<div class="ms-detail-grid">${detail('Recollection Day', schedule.title)}${detail('Status', badge(schedule), false, true)}${detail('Date', dateOnly(schedule.date))}${detail('Time', timeRange(schedule))}${detail('Venue', schedule.venue, true)}<div class="ms-detail ms-full"><span>Courses</span><div class="ms-course-list">${courseNames(schedule).map(course => `<span class="ms-course-chip">${escape(course)}</span>`).join('') || '—'}</div></div>${detail('Year / Section', yearSection(schedule))}${detail('Registration', capacity(schedule) == null ? `${registered(schedule)} registered · No limit` : `${registered(schedule)} / ${capacity(schedule)}`)}</div><div class="ms-notice ms-detail-notice">Students register separately after this schedule is published. This schedule becomes the basis for registration and later attendance monitoring.</div>`;
            const restrictions = editRestrictions(schedule);
            this.openModal('Recollection Schedule Details', body, button('Close', 'close') + (!restrictions.readOnlyReason ? button('Edit Schedule', 'edit', 'ms-primary', `data-ms-id="${id}"`) : `<span class="ms-edit-reason">${escape(restrictions.readOnlyReason)}</span>`));
        }
        openDay(date) {
            const rows = this.matching().filter(schedule => dateOnly(schedule.date) === date);
            this.openModal(`Recollection Schedules · ${date}`, `<div class="ms-day-events">${rows.map(schedule => button(`<strong>${escape(schedule.title)}</strong><span>${escape(courseNames(schedule).join(' / '))} · ${escape(yearSection(schedule))}</span><br><span>${escape(calendarTime(schedule))}</span>`, 'view', `ms-event ${statusOf(schedule) === 'Open' ? 'ms-open-event' : statusOf(schedule) === 'Draft' ? 'ms-draft-event' : ''}`, `data-ms-id="${schedule.id}"`)).join('')}</div>`, button('Close', 'close'));
        }

        openEdit(id) {
            const schedule = this.find(id);
            if (!schedule) { this.feedback('The schedule was not found.'); return; }
            if (editRestrictions(schedule).readOnlyReason) { this.openView(id); return; }
            this.closeMenu(); this.closeModal(true);
            if (this.options.onEdit) this.options.onEdit(schedule);
            else global.location.assign(editPageUrl(id));
        }

        showErrors(errors, message = '') {
            const summary = this.modal?.querySelector('.ms-modal-feedback');
            if (!summary) return;
            const messages = unique(Object.values(errors || {}).flat().map(text).filter(Boolean));
            summary.textContent = messages.length ? messages.join('\n') : message;
            summary.focus({preventScroll: true}); summary.scrollIntoView({block: 'nearest'});
        }
        setBusy(value) {
            if (this.busy === value) return;
            this.busy = value;
            if (!this.modal) return;
            this.modal.setAttribute('aria-busy', String(value));
            this.modal.querySelectorAll('button, input, select, textarea').forEach(element => {
                if (value) { element.dataset.msWasDisabled = element.disabled ? '1' : '0'; element.disabled = true; }
                else { element.disabled = element.dataset.msWasDisabled === '1'; delete element.dataset.msWasDisabled; }
            });
        }
        async mutate(url, method, body, deletingId = null) {
            if (this.busy) return;
            this.closeMenu(); this.setBusy(true);
            try {
                const result = await this.options.request(url, method, body);
                if (this.destroyed) return;
                const row = result.schedule || (result.id != null ? result : null);
                if (deletingId != null) this.state.schedules = this.rows().filter(schedule => text(schedule.id) !== text(deletingId));
                else if (row) {
                    const index = this.rows().findIndex(schedule => text(schedule.id) === text(row.id));
                    this.state.schedules = [...this.rows()];
                    if (index < 0) this.state.schedules.push(row); else this.state.schedules[index] = row;
                }
                this.setBusy(false); this.closeModal(); this.refreshFilterOptions(); this.draw();
                this.options.toast?.(result.message || 'Changes saved.');
                if (this.options.onSaved) await this.options.onSaved(result);
                else if (this.options.refresh) await this.options.refresh(result);
            } catch (error) {
                if (this.destroyed) return;
                this.setBusy(false);
                if (this.modal) {
                    this.showErrors(error.fields || error.errors, error.message || 'Unable to save. Try again.');
                    if (method === 'DELETE') this.offerCancelAfterDelete(deletingId);
                } else this.feedback(error.message || 'Unable to save. Try again.');
            } finally { if (!this.destroyed) this.setBusy(false); }
        }
        feedback(message) {
            const element = this.root.querySelector('.ms-page-feedback');
            if (element) { element.textContent = message; element.scrollIntoView({block: 'nearest'}); }
            this.options.toast?.(message);
        }
        changeStatus(id, status) {
            const schedule = this.find(id); if (!schedule) return;
            return this.mutate('/api/admin/schedules/' + id + '/status', 'PATCH', {status});
        }

        linked(schedule) {
            return Boolean(schedule.has_linked_records || Number(schedule.registrations_total_count ?? schedule.registrations_count) > 0
                || (this.state.requests || []).some(request => text(request.requested_schedule_id ?? request.requested_schedule?.id) === text(schedule.id)));
        }
        openDelete(id) {
            const schedule = this.find(id); if (!schedule) return;
            const linked = this.linked(schedule);
            this.openModal('Delete Recollection Schedule?', `<div class="ms-notice">Are you sure you want to delete this schedule? If students are already registered, use Cancel Schedule instead so the record is preserved.</div><div class="ms-detail"><span>Schedule</span><strong>${escape(schedule.title)} · ${escape(dateOnly(schedule.date))}</strong></div>${linked ? '<div class="ms-notice ms-detail-notice">This schedule has registrations or a change request and cannot be deleted. Cancel Schedule keeps those records and notifies its registered students.</div>' : ''}`,
                button('Keep Schedule', 'close') + (linked ? button('Cancel Schedule', 'cancel-schedule', 'ms-action ms-danger', `data-ms-id="${id}"`) : button('Delete Schedule', 'delete-confirm', 'ms-action ms-danger', `data-ms-id="${id}"`)), true);
        }
        offerCancelAfterDelete(id) {
            if (!this.modal || !id || ['Completed', 'Cancelled'].includes(statusOf(this.find(id) || {}))) return;
            const footer = this.modal.querySelector('.ms-modal-foot');
            footer.innerHTML = button('Keep Schedule', 'close') + button('Cancel Schedule', 'cancel-schedule', 'ms-action ms-danger', `data-ms-id="${id}"`);
        }
        deleteSchedule(id) { return this.mutate('/api/admin/schedules/' + id, 'DELETE', {}, id); }
        openCancel(id) {
            const schedule = this.find(id); if (!schedule) return;
            this.openModal('Cancel Recollection Schedule?', `<div class="ms-notice">Cancel Schedule closes student registration and preserves the schedule, registrations, attendance, receipts, and certificates. Registered students will be notified. Students keep this schedule until an administrator reassigns them through the existing Student Attendance flow.</div><div class="ms-detail"><span>Schedule</span><strong>${escape(schedule.title)} · ${escape(dateOnly(schedule.date))}</strong></div>${registered(schedule) ? `<div class="ms-notice ms-detail-notice">${registered(schedule)} registered ${registered(schedule) === 1 ? 'student needs' : 'students need'} review. <a href="/admin#admin-student-attendance?batch=${encodeURIComponent(id)}">Open the existing attendance and reassign flow</a> after cancelling.</div>` : ''}`,
                button('Keep Schedule', 'close') + button('Cancel Schedule', 'cancel-confirm', 'ms-action ms-danger', `data-ms-id="${id}"`), true);
        }
    }

    function mount(options) {
        if (!options?.root) throw Error('A schedule page root is required.');
        const existing = controllers?.get(options.root);
        if (existing && !existing.destroyed && options.root.querySelector('.ms-shell')) return existing.update(options.state, options);
        existing?.destroy();
        const controller = new MySchedules(options); controllers?.set(options.root, controller); return controller;
    }

    const api = {mount, statusOf, selections, courseNames, courseSections, courseSectionsByYear, hasCourseSections, sectionsForCourse, academicGroups, editPageUrl, yearSections, yearSection, timeRange, calendarTime, filterSchedules,
        sortSchedules, defaultOrderSchedules, attentionIndicators, attentionPresentation, editRestrictions,
        TABLE_COLUMNS, ATTENTION_WINDOW_DAYS, ALMOST_FULL_RATIO, MAX_ATTENTION_BADGES,
        paginateSchedules, buildCsv, calendarMonth, tableDate, tableTimeRange, tableGroupLines, tableCourseGroups, courseGroupsHtml, tableStatus, registrationProgress, registrationHtml};
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (global) global.CfoMySchedules = api;
})(typeof window === 'object' ? window : globalThis);
