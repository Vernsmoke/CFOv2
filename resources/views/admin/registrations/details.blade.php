@php
    $details = $registration->details ?? [];
    $group = $registration->academicGroup();
    $value = fn ($key, $fallback = '—') => isset($details[$key]) && is_scalar($details[$key]) && trim((string) $details[$key]) !== '' ? (string) $details[$key] : $fallback;
    $consented = fn ($key) => in_array($details[$key] ?? null, [true, 1, '1', 'yes', 'on'], true);
    $canSchedule = $registration->receipt_status === 'Approved' && $registration->has_receipt;
    $assigned = $registration->schedule;
    $formatDate = fn ($date) => \Illuminate\Support\Carbon::parse($date)->format('F d, Y');
    $formatTime = fn ($time) => \Illuminate\Support\Carbon::createFromFormat('H:i', $time)->format('h:i A');
@endphp
<div class="registration-details">
    @if(($registration->attempt_no ?? 1) > 1)<p class="modal-notice">Attempt {{ $registration->attempt_no }}. The previous missed recollection is retained in Recollection History.</p>@endif
    <p class="modal-notice" role="status" hidden></p>
    <div class="registration-review-grid">
        <div class="registration-information" id="personal-information" tabindex="-1">
            <section class="information-section reg-sec-personal">
                <h3>I. PERSONAL INFORMATION</h3>
                <dl class="information-fields personal-fields">
                    <div class="field-wide"><dt>Name:</dt><dd>{{ $value('name', $registration->student_display_name) }}</dd></div>
                    <div><dt>Nickname:</dt><dd>{{ $value('nickname') }}</dd></div>
                    <div><dt>Age:</dt><dd>{{ $value('age') }}</dd></div>
                    <div><dt>Sex:</dt><dd>{{ $value('sex') }}</dd></div>
                    <div class="field-wide"><dt>Course/Program:</dt><dd>{{ $group['course'] ?: '—' }}</dd></div>
                    <div><dt>Yr. / Section:</dt><dd>{{ ($group['year_level'] ?? '').($group['section'] ?? '') ?: '—' }}</dd></div>
                    <div class="field-wide"><dt>Email:</dt><dd>{{ $value('email', $registration->user->original_email ?: $registration->user->email) }}</dd></div>
                    <div class="field-wide"><dt>Contact Number:</dt><dd>{{ $value('phone') }}</dd></div>
                    <div class="field-wide"><dt>ID Number:</dt><dd>{{ $registration->user->student_id ?: '—' }}</dd></div>
                    <div class="field-wide"><dt>Date Registered:</dt><dd>{{ $registration->created_at?->format('M d, Y h:i A') ?? '—' }}</dd></div>
                </dl>
            </section>
            <section class="information-section reg-sec-emergency">
                <h3>II. EMERGENCY INFORMATION</h3>
                <dl class="information-fields">
                    <div class="field-full"><dt>Emergency Contact Person:</dt><dd>{{ $value('emergencyName') }}</dd></div>
                    <div><dt>Relationship:</dt><dd>{{ $value('relationship') }}</dd></div>
                    <div><dt>Contact Number:</dt><dd>{{ $value('emergencyPhone') }}</dd></div>
                </dl>
            </section>
            <section class="information-section reg-sec-health">
                <h3>III. HEALTH AND SAFETY</h3>
                <dl class="information-fields">
                    <div class="field-full"><dt>Medical Conditions (If any):</dt><dd>{{ $value('medical') }}</dd></div>
                    <div><dt>Allergies:</dt><dd>{{ $value('allergies') }}</dd></div>
                    <div><dt>Medications Currently Taking:</dt><dd>{{ $value('medications') }}</dd></div>
                </dl>
            </section>
            <section class="information-section reg-sec-faith">
                <h3>IV. FAITH AND SACRAMENTAL BACKGROUND</h3>
                <dl class="information-fields">
                    <div><dt>Religion:</dt><dd>{{ $value('religion') }}</dd></div>
                    <div class="sacrament-field field-full"><dt>Sacraments Received (Yes or No)</dt><dd><dl class="sacrament-values">
                        @foreach(['baptism' => 'Baptism', 'communion' => 'Holy Communion', 'confirmation' => 'Confirmation'] as $key => $label)
                            <div><dt>{{ $label }}</dt><dd>{{ $value($key) }}</dd></div>
                        @endforeach
                    </dl></dd></div>
                    <div><dt>Parish (if any):</dt><dd>{{ $value('parish') }}</dd></div>
                    <div><dt>Church/Campus Ministry Involvement:</dt><dd>{{ $value('ministry') }}</dd></div>
                </dl>
            </section>
            <section class="information-section reg-sec-experience">
                <h3>V. RECOLLECTION EXPERIENCE</h3>
                <dl class="information-fields">
                    <div class="field-full"><dt>Is this your first Catholic recollection?</dt><dd>{{ $value('firstRecollection') }}</dd></div>
                    <div class="field-full"><dt>Previous Recollections Attended (if any)</dt><dd>{{ $value('previousRecollections') }}</dd></div>
                </dl>
            </section>
            <section class="information-section narrative-section reg-sec-expectations">
                <h3>VI. EXPECTATIONS AND INTENTIONS</h3>
                <dl><dt>What do you hope to receive or experience during this recollection?</dt><dd>{{ $value('expectations') }}</dd></dl>
            </section>
            <section class="information-section narrative-section reg-sec-reflection">
                <h3>VII. PERSONAL REFLECTION (Optional)</h3>
                <dl><dt>Prayers intentions, struggles, or concerns you wish to share:</dt><dd>{{ $value('reflection') }}</dd></dl>
            </section>
            <section class="information-section consent-section reg-sec-consent">
                <h3>VIII. CONSENT AND COMMITMENT</h3>
                <label><input type="checkbox" disabled @checked($consented('participation'))> <span>I understand that this recollection is a faith-based activity and I freely choose to participate with openness, respect, and confidentiality.</span></label>
                <label><input type="checkbox" disabled @checked($consented('photos'))> <span>I understand that during the recollection photos will be taken for the purposes of the University.</span></label>
            </section>
        </div>
        <div class="registration-management">
            <section class="management-card" id="receipt-review" tabindex="-1">
                <div class="management-heading"><h3>@include('partials.admin-icon', ['name' => 'file']) Receipt</h3><span class="upload-badge {{ $registration->has_receipt ? 'uploaded' : '' }}">{{ $registration->has_receipt ? 'Receipt Uploaded' : 'Not uploaded' }}</span></div>
                <div class="management-body">
                    @if($registration->has_receipt)
                        <div class="receipt-viewer">
                            <div class="receipt-viewport" tabindex="0" aria-label="Uploaded receipt preview; scroll to inspect when zoomed">
                                <button type="button" class="receipt-image-open" data-receipt-modal="{{ url('/api/receipts/'.$registration->id) }}" aria-label="View full-size receipt for {{ $registration->student_display_name }}"><img class="receipt-preview" src="{{ url('/api/receipts/'.$registration->id) }}" alt="Uploaded receipt for {{ $registration->student_display_name }}"></button>
                                <p class="receipt-unavailable" hidden>Receipt preview is unavailable. Use View Full Size to open the file.</p>
                            </div>
                            <div class="receipt-toolbar">
                                <button type="button" data-receipt-zoom="in" aria-label="Zoom in receipt">@include('partials.admin-icon', ['name' => 'zoom-in'])</button>
                                <button type="button" data-receipt-zoom="out" aria-label="Zoom out receipt">@include('partials.admin-icon', ['name' => 'zoom-out'])</button>
                                <button type="button" data-receipt-zoom="reset" aria-label="Reset receipt zoom">@include('partials.admin-icon', ['name' => 'reset'])</button>
                                <button type="button" class="receipt-full-size" data-receipt-modal="{{ url('/api/receipts/'.$registration->id) }}">View Full Size</button>
                            </div>
                        </div>
                        <div class="receipt-decision">
                            <button type="button" class="button receipt-review-toggle" data-receipt-review-toggle aria-expanded="false" aria-controls="receipt-review-form">Receipt review <span class="status-badge {{ strtolower($registration->receipt_label) }}">{{ $registration->receipt_label }}</span></button>
                            <form id="receipt-review-form" class="workflow-form" action="{{ url('/api/admin/registrations/'.$registration->id) }}" data-method="PATCH" data-receipt-review hidden>
                                <label>Receipt decision<select name="receipt_status" required>@foreach(\App\Models\Registration::RECEIPT_LABELS as $label => $statuses)@php($decision = $statuses[0])<option value="{{ $decision }}" @selected($registration->receipt_status === $decision) @disabled($registration->recollection_schedule_id && $decision !== 'Approved')>{{ $label }}</option>@endforeach</select></label>
                                <label data-receipt-rejection-reason @if($registration->receipt_status !== 'Rejected') hidden @endif>Reason for rejection<textarea name="receipt_reason" maxlength="2000" rows="2" @required($registration->receipt_status === 'Rejected') @disabled($registration->receipt_status !== 'Rejected')>{{ $registration->receipt_reason }}</textarea></label>
                                @if($registration->recollection_schedule_id)<p class="muted">Assigned registrations must retain a verified receipt.</p>@endif
                                <button type="submit" class="button">Save receipt review</button><p class="form-message" role="alert" hidden></p>
                            </form>
                        </div>
                    @else
                        <div class="receipt-empty">@include('partials.admin-icon', ['name' => 'file'])<p>No receipt has been uploaded.</p></div>
                    @endif
                </div>
            </section>
            <section class="management-card" id="schedule-assignment" tabindex="-1" aria-label="Assign Schedule">
                <div class="management-heading"><h3>@include('partials.admin-icon', ['name' => 'calendar']) Set Schedule</h3></div>
                <div class="management-body">
                    <form id="registration-schedule-form" class="workflow-form" action="{{ url('/api/admin/registrations/'.$registration->id.'/schedule') }}" data-method="POST">
                        <label>Choose a Recollection Schedule<select name="schedule_id" required @disabled(!$canSchedule)>
                            <option value="">Choose a schedule</option>
                            @if($assigned && !$schedules->contains('id', $assigned->id))
                                <option value="{{ $assigned->id }}" selected data-date="{{ $formatDate($assigned->date) }}" data-time="{{ $formatTime($assigned->start_time) }} - {{ $formatTime($assigned->end_time) }}" data-location="{{ $assigned->venue }}" data-assigned="true">{{ $formatDate($assigned->date) }} ({{ $formatTime($assigned->start_time) }} - {{ $formatTime($assigned->end_time) }}) - {{ $assigned->venue }} · Current assignment</option>
                            @endif
                            @foreach($schedules as $schedule)
                                @php($full = $schedule->id !== $registration->recollection_schedule_id && $schedule->registrations_count >= ($schedule->slot_limit ?? $schedule->capacity))
                                <option value="{{ $schedule->id }}" @selected($schedule->id === $registration->recollection_schedule_id) @disabled($full) data-date="{{ $formatDate($schedule->date) }}" data-time="{{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}" data-location="{{ $schedule->venue }}" data-assigned="{{ $schedule->id === $registration->recollection_schedule_id ? 'true' : 'false' }}">{{ $formatDate($schedule->date) }} ({{ $formatTime($schedule->start_time) }} - {{ $formatTime($schedule->end_time) }}) - {{ $schedule->venue }} · {{ $schedule->title }}{{ $full ? ' · Full' : '' }}</option>
                            @endforeach
                        </select></label>
                        @if(!$canSchedule)<p class="muted">Verify the uploaded receipt before assigning a schedule.</p>@elseif($schedules->isEmpty() && !$assigned)<p class="muted">No active upcoming schedules are available. Publish a schedule in Recollection schedules.</p>@endif
                        <dl class="schedule-preview" aria-live="polite">
                            <div>@include('partials.admin-icon', ['name' => 'calendar'])<dt>Date:</dt><dd data-schedule-date>{{ $assigned ? $formatDate($assigned->date) : '—' }}</dd></div>
                            <div>@include('partials.admin-icon', ['name' => 'pending'])<dt>Time:</dt><dd data-schedule-time>{{ $assigned ? $formatTime($assigned->start_time).' - '.$formatTime($assigned->end_time) : '—' }}</dd></div>
                            <div>@include('partials.admin-icon', ['name' => 'location'])<dt>Location:</dt><dd data-schedule-location>{{ $assigned?->venue ?? '—' }}</dd></div>
                            <div>@include('partials.admin-icon', ['name' => 'verified'])<dt>Status:</dt><dd><span class="schedule-state {{ $assigned ? 'is-scheduled' : '' }}" data-schedule-status>{{ $assigned ? 'Scheduled' : 'No schedule assigned.' }}</span></dd></div>
                        </dl>
                        @if($canSchedule)<p class="schedule-help">Admins may assign any course, year level, or section to an available schedule. Save confirms the reviewed registration and assigns the selected schedule.</p>@endif
                        @if($registration->attendance !== 'Not Yet Recorded')<label class="check-label"><input type="checkbox" name="confirm_attendance_reset" value="1" required> I understand that changing the schedule clears attendance and withdraws the current certificate until attendance is recorded again.</label>@endif
                        <p class="form-message" role="alert" hidden></p>
                    </form>
                </div>
            </section>
        </div>
    </div>
    <footer class="registration-dialog-footer"><button type="button" class="button cancel-button" data-close-dialog>Cancel</button><button type="submit" form="registration-schedule-form" class="button primary" @disabled(!$canSchedule || ($schedules->isEmpty() && !$assigned))>Save</button></footer>
</div>
