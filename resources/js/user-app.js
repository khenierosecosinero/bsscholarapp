document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initProfileDropdown();
    initProfileTabs();
    initFileUpload();
    initFlashDismiss();
    initAttendanceLive();
});

function initSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const shell = document.getElementById('app-shell');
    if (!toggle || !shell) return;

    const mq = window.matchMedia('(max-width: 1000px)');
    const sync = () => {
        if (mq.matches) {
            shell.classList.remove('sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
        } else {
            shell.classList.add('sidebar-open');
            toggle.setAttribute('aria-expanded', 'true');
        }
    };
    sync();
    mq.addEventListener('change', sync);

    toggle.addEventListener('click', () => {
        shell.classList.toggle('sidebar-open');
        toggle.setAttribute('aria-expanded', shell.classList.contains('sidebar-open') ? 'true' : 'false');
    });
}

function initProfileDropdown() {
    const wrap = document.getElementById('profile-dropdown-wrap');
    const btn = document.getElementById('profile-toggle');
    const menu = document.getElementById('profile-menu');
    if (!wrap || !btn || !menu) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.hidden = !menu.hidden;
    });

    document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) menu.hidden = true;
    });
}

function initProfileTabs() {
    const tabs = document.querySelectorAll('.profile-tabs .tab, .tab-trigger');
    const panels = document.querySelectorAll('.tab-panel');
    if (!tabs.length) return;

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const id = tab.dataset.tab;
            if (!id) return;

            document.querySelectorAll('.profile-tabs .tab').forEach((t) => t.classList.remove('active'));
            const match = document.querySelector(`.profile-tabs .tab[data-tab="${id}"]`);
            if (match) match.classList.add('active');

            panels.forEach((p) => {
                p.hidden = p.id !== id;
                p.classList.toggle('active', p.id === id);
            });
        });
    });
}

function initFileUpload() {
    const input = document.getElementById('file-input');
    const label = document.getElementById('file-name');
    const zone = document.querySelector('.upload-zone');
    if (!input) return;

    input.addEventListener('change', () => {
        if (label) label.textContent = input.files[0]?.name ?? '';
        const form = document.getElementById('upload-form');
        const typeSelect = document.getElementById('doc-type-select');
        if (form && typeSelect?.value && input.files.length) {
            form.submit();
        }
    });

    if (zone) {
        zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                if (label) label.textContent = input.files[0].name;
            }
        });
    }
}

function initAttendanceLive() {
    const root = document.getElementById('attendance-live-root');
    if (!root?.dataset.attendanceLive) return;

    const url = root.dataset.attendanceLive;
    const eventsUrl = root.dataset.eventsUrl || '/user/events';
    const page = root.dataset.attendancePage || '';
    const eventId = String(root.dataset.attendanceEvent || '');
    let lastSignature = '';
    let lastEventStatus = root.dataset.attendanceStatus || '';
    let lastNotificationId = Number(root.dataset.latestNotification || 0);
    let primed = false;

    const poll = async () => {
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const data = await response.json();

            if (typeof data.unread_notifications !== 'undefined') {
                updateUnreadNotificationBadge(data.unread_notifications);
            }

            if (!primed) {
                lastSignature = data.signature || '';
                lastNotificationId = Number(data.latest_notification_id || 0);
                if (eventId && data.events?.[eventId]?.status) {
                    lastEventStatus = data.events[eventId].status;
                }
                primed = true;
                renderAttendanceSessions(data.sessions || [], eventsUrl);
                return;
            }

            if (eventId && data.events?.[eventId]?.status && data.events[eventId].status !== lastEventStatus) {
                window.location.reload();
                return;
            }

            if (page === 'notifications' && Number(data.latest_notification_id || 0) > lastNotificationId) {
                window.location.reload();
                return;
            }

            if (data.signature && data.signature !== lastSignature) {
                lastSignature = data.signature;
                renderAttendanceSessions(data.sessions || [], eventsUrl);
                updateEventAttendanceBadge(data.events?.[eventId]);
            }

            lastNotificationId = Number(data.latest_notification_id || lastNotificationId);
        } catch (error) {
            // Keep polling even if one request fails.
        }
    };

    poll();
    setInterval(poll, 5000);
}

function renderAttendanceSessions(sessions, eventsUrl) {
    const card = document.querySelector('[data-attendance-sessions]');
    const list = document.querySelector('[data-attendance-session-list]');
    if (!list) return;

    const visible = (sessions || []).filter((session) => session.status_visible !== false);

    if (!visible.length) {
        list.innerHTML = '';
        if (card) card.hidden = true;
        return;
    }

    if (card) card.hidden = false;

    const base = eventsUrl || '/user/events';
    list.innerHTML = visible.map((session) => {
        const isOpen = !!session.schedule_open;
        const status = session.schedule_status || session.status || (isOpen ? 'OPEN' : 'CLOSED');
        const message = session.schedule_message || session.message || '';

        return `
        <article class="attendance-status-item ${isOpen ? 'is-open' : 'is-closed'}" data-event-id="${session.event_id}">
            <div class="attendance-status-top">
                <strong>${escapeHtml(session.title)}</strong>
                <span class="badge ${isOpen ? 'attendance-open' : 'attendance-closed'}">${escapeHtml(status)}</span>
            </div>
            <div class="muted">${escapeHtml(session.full_date || '')} · ${escapeHtml(session.time || '')}</div>
            <p>${escapeHtml(message)}</p>
            <a href="${base}?event=${encodeURIComponent(session.event_id)}" class="link small">View Event</a>
        </article>
    `;
    }).join('');
}

function updateUnreadNotificationBadge(count) {
    const nav = document.querySelector('[data-nav="notifications"]');
    if (!nav) return;

    count = Number(count) || 0;
    let badge = nav.querySelector('[data-unread-notifications]');

    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'nav-notif-badge';
            badge.setAttribute('data-unread-notifications', '');
            nav.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.setAttribute('aria-label', `${count} unread notifications`);
        return;
    }

    if (badge) {
        badge.remove();
    }
}

function updateEventAttendanceBadge(session) {
    if (!session) return;
    const badge = document.querySelector('[data-attendance-event-badge]');
    const message = document.querySelector('[data-attendance-event-message]');
    if (badge) {
        badge.textContent = session.status;
        badge.classList.toggle('attendance-open', !!session.is_open);
        badge.classList.toggle('attendance-closed', !session.is_open);
    }
    if (message) {
        message.classList.toggle('attendance-open-box', !!session.is_open);
        message.classList.toggle('attendance-closed-box', !session.is_open);
        message.innerHTML = `<strong>Attendance ${escapeHtml(session.status)}:</strong> ${escapeHtml(session.message)}`;
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function initFlashDismiss() {
    document.querySelectorAll('.flash-alert').forEach((el) => {
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity .4s';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });
}
