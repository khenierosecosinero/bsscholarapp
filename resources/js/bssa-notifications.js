/**
 * Shared BSSA notification helpers: inline alerts, toasts, dismiss behavior.
 */
(function () {
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function dismissAlert(el) {
        if (!el || el.dataset.bssaLeaving === 'true') return;
        el.dataset.bssaLeaving = 'true';
        el.classList.add('is-leaving');
        window.setTimeout(function () {
            el.remove();
        }, 240);
    }

    function bindAlert(el) {
        if (!el || el.dataset.bssaBound === 'true') return;
        el.dataset.bssaBound = 'true';

        var closeBtn = el.querySelector('[data-bssa-alert-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                dismissAlert(el);
            });
        }

        var autoMs = parseInt(el.getAttribute('data-bssa-auto-dismiss') || '', 10);
        if (autoMs > 0) {
            window.setTimeout(function () {
                dismissAlert(el);
            }, autoMs);
        }
    }

    function initAlerts(root) {
        (root || document).querySelectorAll('.bssa-alert, .flash-alert').forEach(bindAlert);
    }

    function buildAlertElement(options) {
        var type = options.type || 'info';
        var title = options.title || '';
        var message = options.message || '';
        var messages = options.messages || [];
        var dismissible = options.dismissible !== false;
        var autoDismiss = options.autoDismiss;
        var el = document.createElement('div');
        el.className = 'bssa-alert bssa-alert--' + type + ' flash-alert';
        el.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
        if (autoDismiss) {
            el.setAttribute('data-bssa-auto-dismiss', String(autoDismiss === true ? 5000 : autoDismiss));
        }

        var listHtml = '';
        if (messages.length) {
            listHtml = '<ul class="bssa-alert__list">' + messages.map(function (item) {
                return '<li>' + escapeHtml(item) + '</li>';
            }).join('') + '</ul>';
        }

        var iconSvgs = {
            success: '<svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.15"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/><path d="M7.5 12.5l3 3 6-6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            error: '<svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/><path d="M12 7.5v5.5M12 16.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            warning: '<svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><path d="M12 3.8L21 20H3L12 3.8Z" fill="currentColor" opacity="0.12" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M12 10v4.5M12 17.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            info: '<svg class="bssa-alert__icon-svg" viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/><path d="M12 10.5V17M12 7.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        };
        var iconHtml = iconSvgs[type] || iconSvgs.info;

        el.innerHTML =
            '<div class="bssa-alert__icon" aria-hidden="true">' + iconHtml + '</div>' +
            '<div class="bssa-alert__body">' +
                (title ? '<p class="bssa-alert__title">' + escapeHtml(title) + '</p>' : '') +
                (message ? '<p class="bssa-alert__message">' + escapeHtml(message) + '</p>' : '') +
                listHtml +
            '</div>' +
            (dismissible
                ? '<button type="button" class="bssa-alert__close" data-bssa-alert-close aria-label="Dismiss notification">&times;</button>'
                : '');

        bindAlert(el);
        return el;
    }

    function toastHost() {
        var host = document.getElementById('bssa-toast-host');
        if (host) return host;
        host = document.createElement('div');
        host.id = 'bssa-toast-host';
        host.className = 'bssa-toast-host';
        host.setAttribute('aria-live', 'polite');
        host.setAttribute('aria-relevant', 'additions text');
        host.setAttribute('aria-atomic', 'false');
        document.body.appendChild(host);
        return host;
    }

    function showToast(type, message, options) {
        options = options || {};
        if (!message) return null;
        var titles = { success: 'Success!', error: 'Error', warning: 'Warning', info: 'Information' };
        var autoDismiss = options.autoDismiss;
        if (autoDismiss === undefined) {
            autoDismiss = type === 'error' || type === 'warning' ? false : 5000;
        }
        var el = buildAlertElement({
            type: type,
            title: options.title || titles[type] || titles.info,
            message: message,
            dismissible: options.dismissible !== false,
            autoDismiss: autoDismiss,
        });
        toastHost().appendChild(el);
        return el;
    }

    function showInlineFlash(type, message, options) {
        options = options || {};
        if (!message) return null;

        var titles = { success: 'Success!', error: 'Error', warning: 'Warning', info: 'Information' };
        var autoDismiss = options.autoDismiss;
        if (autoDismiss === undefined) {
            autoDismiss = type === 'success' || type === 'info' ? 5000 : false;
        }

        var mount =
            document.querySelector('.staff-main .bssa-flash-stack') ||
            document.querySelector('.main .inner .bssa-flash-stack') ||
            document.querySelector('.auth-card .bssa-flash-stack') ||
            null;

        if (!mount) {
            var parent =
                document.querySelector('.staff-main') ||
                document.querySelector('.main .inner') ||
                document.querySelector('.auth-card') ||
                null;
            if (parent) {
                mount = document.createElement('div');
                mount.className = 'bssa-flash-stack';
                var confirm = document.getElementById('staff-confirm-modal');
                if (confirm && confirm.parentNode === parent) {
                    parent.insertBefore(mount, confirm.nextSibling);
                } else {
                    parent.insertBefore(mount, parent.firstChild);
                }
            }
        }

        if (!mount) {
            return showToast(type, message, options);
        }

        if (options.replaceAttendance) {
            mount.querySelectorAll('.flash-alert[data-attendance-flash]').forEach(function (node) {
                node.remove();
            });
        }

        var el = buildAlertElement({
            type: type,
            title: options.title || titles[type] || titles.info,
            message: message,
            dismissible: true,
            autoDismiss: autoDismiss,
        });
        if (options.replaceAttendance) {
            el.setAttribute('data-attendance-flash', 'true');
        }
        mount.prepend(el);
        return el;
    }

    window.BssaNotify = {
        initAlerts: initAlerts,
        dismissAlert: dismissAlert,
        showToast: showToast,
        showInlineFlash: showInlineFlash,
        buildAlertElement: buildAlertElement,
    };

    document.addEventListener('DOMContentLoaded', function () {
        initAlerts(document);
    });
})();
