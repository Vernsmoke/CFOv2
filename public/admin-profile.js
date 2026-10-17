/* Administrator profile summary and edit dialog, matching the student profile flow. */
(function (root) {
  'use strict';
  const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const initials = user => ((user.first_name || user.name || '').trim().charAt(0) + (user.last_name || '').trim().charAt(0)).toUpperCase() || 'AD';

  function pageHtml(user) {
    const line = (label, value) => `<dl class="admin-profile-line"><dt>${label}</dt><dd>${escape(value || '—')}</dd></dl>`;
    return `<section class="admin-profile"><div class="panel-head"><div><h2>Administrator Profile</h2><p>View your account and administrator information.</p></div><button type="button" class="btn" data-action="admin-profile-edit">Edit Basic Information</button></div>
      <div class="admin-profile-grid"><section class="panel admin-profile-summary"><div class="panel-body"><div class="admin-profile-avatar" aria-hidden="true">${escape(initials(user))}</div><h2>${escape(user.name || 'Administrator')}</h2><p class="muted">Employee ID: ${escape(user.student_id || '—')}</p><p class="muted">Account status: ${escape(user.status || '—')}</p></div></section>
      <section class="panel admin-profile-information"><div class="panel-head"><div><h2>Personal Information</h2><p>Your saved account details.</p></div></div><div class="panel-body">${line('Full Name',user.name)}${line('Registered Email',user.original_email || user.email)}${line('Login Email',user.login_email || user.system_username || user.email)}${line('Account Role',user.role || 'Admin')}</div></section></div>
      </section>`;
  }

  function formHtml(user) {
    const field = (name,label,value,type='text',attributes='',full=false) => `<label class="field${full?' full':''}" for="admin-profile-${name}">${label}<input id="admin-profile-${name}" name="${name}" type="${type}" value="${escape(value)}" ${attributes}></label>`;
    return `<form id="connected-profile-form"><div class="form-grid">${field('first_name','First Name',user.first_name,'text','required autocomplete="given-name"')}${field('middle_name','Middle Name',user.middle_name,'text','autocomplete="additional-name"')}${field('last_name','Last Name',user.last_name,'text','required autocomplete="family-name"')}${field('email','Registered Email',user.email,'email','required autocomplete="email"')}${field('current_password','Current Password *','','password','required autocomplete="current-password"',true)}${field('password','New Password','','password','autocomplete="new-password" placeholder="Leave blank to keep your password"')}${field('password_confirmation','Confirm New Password','','password','autocomplete="new-password"')}</div><p class="form-help">Enter your current password to save. New passwords need at least 8 characters, upper and lower case letters, a number and a symbol.</p></form>`;
  }

  function actions({user,showDialog,saveConnected,button,$,toast}) {
    return {'admin-profile-edit':() => {
      const saved = user();
      showDialog('Edit Basic Information',formHtml(saved),button('Cancel','close-dialog')+'<button class="btn primary" type="submit" form="connected-profile-form">Save Changes</button>',true);
      const form = $('#connected-profile-form');
      form.onsubmit = event => {
        event.preventDefault();
        const values = Object.fromEntries(new root.FormData(form));
        const changed = ['first_name','middle_name','last_name','email'].some(key => String(values[key] || '') !== String(saved[key] || '')) || Boolean(values.password);
        if (!changed) { toast('You have not changed any information.'); return; }
        return saveConnected('/api/profile','PUT',values);
      };
    }};
  }

  root.CfoAdminProfile = {initials,pageHtml,formHtml,actions};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoAdminProfile;
})(typeof window === 'undefined' ? globalThis : window);
