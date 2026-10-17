(() => {
    'use strict';
    const header = document.querySelector('[data-admin-header]');
    if (!header) return;
    const title = header.querySelector('#admin-header-title');
    const breadcrumb = header.querySelector('#admin-header-breadcrumb');
    function setPage(name, ancestors = []) {
        title.textContent = name;
        breadcrumb.replaceChildren();
        if (name === 'Dashboard' && !ancestors.length) {
            const subtitle = document.createElement('span');
            subtitle.textContent = 'CFO / Admin workspace';
            breadcrumb.append(subtitle);
            return;
        }
        for (const ancestor of ancestors) {
            const link = document.createElement('a');
            link.href = ancestor.href;
            link.textContent = ancestor.name;
            const separator = document.createElement('span');
            separator.textContent = '›';
            separator.setAttribute('aria-hidden', 'true');
            breadcrumb.append(link, separator);
        }
        const current = document.createElement('span');
        current.textContent = name;
        current.setAttribute('aria-current', 'page');
        breadcrumb.append(current);
    }
    function setLiveStatus(value) {
        const status = header.querySelector('#live-status');
        status.textContent = value;
        status.classList.toggle('live', value === 'Live');
    }
    window.AdminHeader = {setPage, setLiveStatus};

    // The SPA retains its existing notification action. The server-rendered
    // registration layout reads the same current-admin endpoint on demand.
    const bell = header.querySelector('[data-header-notifications]');
    if (!bell) return;
    let notificationDialog;
    bell.addEventListener('click', async () => {
        if (!notificationDialog) {
            notificationDialog = document.createElement('dialog');
            notificationDialog.id = 'admin-header-notifications';
            notificationDialog.setAttribute('aria-labelledby', 'admin-header-notifications-title');
            const heading = document.createElement('div');
            heading.className = 'admin-header-dialog-heading';
            const label = document.createElement('h2');
            label.id = 'admin-header-notifications-title';
            label.textContent = 'Notifications';
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'admin-header-dialog-close';
            close.setAttribute('aria-label', 'Close notifications');
            close.textContent = '×';
            close.addEventListener('click', () => notificationDialog.close());
            heading.append(label, close);
            const content = document.createElement('div');
            content.className = 'admin-header-notification-content';
            content.setAttribute('role', 'status');
            notificationDialog.append(heading, content);
            document.body.append(notificationDialog);
        }
        const content = notificationDialog.querySelector('.admin-header-notification-content');
        content.textContent = 'Loading notifications…';
        content.setAttribute('aria-busy', 'true');
        if (!notificationDialog.open) notificationDialog.showModal();
        try {
            const response = await fetch('/api/admin/state?resources=notifications', {
                headers: {'Accept': 'application/json'}, cache: 'no-store'
            });
            if (!response.ok || response.redirected) throw new Error('Unable to load notifications. Please try again.');
            const state = await response.json();
            content.replaceChildren();
            const notifications = state.notifications || [];
            if (!notifications.length) content.textContent = 'No notifications.';
            for (const notification of notifications) {
                const message = document.createElement('p');
                message.textContent = notification.message;
                if (notification.created_at) {
                    const date = document.createElement('small');
                    date.textContent = notification.created_at;
                    message.append(document.createElement('br'), date);
                }
                content.append(message);
            }
        } catch (error) {
            content.textContent = error.message;
        } finally {
            content.setAttribute('aria-busy', 'false');
        }
    });
})();
