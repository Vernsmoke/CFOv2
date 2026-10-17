/* Keep Settings navigation available while its individual pages are rendered. */
(function (root) {
  'use strict';
  const destinations = [
    ['office','Office information','#admin-settings?tab=office'],
    ['phone','Phone access &amp; QR','#admin-lan-access'],
    ['admins','Admin accounts','#admin-settings?tab=admins']
  ];
  function selection(route, params = new URLSearchParams()) {
    if (route === 'admin-lan-access') return 'phone';
    if (route !== 'admin-settings') return null;
    const tab = params.get('tab');
    return ['office','admins'].includes(tab) ? tab : 'profile';
  }
  function navigationHtml() {
    return destinations.map(([key,label,href])=>`<a class="btn" data-settings-page="${key}" href="${href}">${label}</a>`).join('');
  }
  function mount(doc = root.document) {
    const main = doc.querySelector('#main-content');
    if (!main) return null;
    if (main.cfoSettingsNavigation) return main.cfoSettingsNavigation;
    const nav = doc.createElement('nav');
    nav.id = 'admin-settings-navigation'; nav.className = 'form-actions admin-settings-navigation'; nav.hidden = true;
    nav.setAttribute('aria-label','Settings pages'); nav.innerHTML = navigationHtml();
    main.appendChild(nav);
    const links = [...nav.querySelectorAll('[data-settings-page]')];
    const sync = (route, params) => {
      const active = selection(route,params); nav.hidden = active === null;
      links.forEach(link => {
        const current = link.dataset.settingsPage === active;
        link.classList.toggle('primary',current);
        if (current) link.setAttribute('aria-current','page'); else link.removeAttribute('aria-current');
      });
      if (active !== null) {
        const pageActions = doc.querySelector('#page-actions');
        if (pageActions) pageActions.innerHTML = active === 'profile' ? '' : '<a class="btn" href="#admin-settings">Administrator Profile</a>';
      }
    };
    main.cfoSettingsNavigation = {sync,nav};
    return main.cfoSettingsNavigation;
  }
  root.CfoAdminSettingsNavigation = {selection,navigationHtml,mount};
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CfoAdminSettingsNavigation;
})(typeof window === 'undefined' ? globalThis : window);
