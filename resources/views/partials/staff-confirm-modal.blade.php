<div class="staff-confirm-modal" id="staff-confirm-modal" aria-hidden="true">
    <div class="staff-confirm-backdrop" data-confirm-close></div>
    <div class="staff-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="staff-confirm-title">
        <div class="staff-confirm-icon" data-confirm-icon>!</div>
        <h3 id="staff-confirm-title" class="staff-confirm-title">Confirm action</h3>
        <p class="staff-confirm-name" id="staff-confirm-name" hidden></p>
        <p class="staff-confirm-message" id="staff-confirm-message"></p>
        <div class="staff-confirm-note" id="staff-confirm-note" hidden></div>
        <p class="staff-confirm-error" id="staff-confirm-error" hidden></p>
        <div class="staff-confirm-actions">
            <button type="button" class="staff-btn staff-confirm-no" data-confirm-no>No</button>
            <button type="button" class="staff-btn staff-btn-danger staff-confirm-yes" data-confirm-yes>Yes</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('staff-confirm-modal');
    if (!modal) return;

    var titleEl = modal.querySelector('.staff-confirm-title');
    var messageEl = modal.querySelector('.staff-confirm-message');
    var nameEl = document.getElementById('staff-confirm-name');
    var noteEl = document.getElementById('staff-confirm-note');
    var errorEl = document.getElementById('staff-confirm-error');
    var dialog = modal.querySelector('.staff-confirm-dialog');
    var iconEl = modal.querySelector('[data-confirm-icon]');
    var yesBtn = modal.querySelector('[data-confirm-yes]');
    var noBtn = modal.querySelector('[data-confirm-no]');
    var closeTriggers = modal.querySelectorAll('[data-confirm-close]');
    var pendingForm = null;
    var busy = false;
    var defaultTitle = titleEl ? titleEl.textContent : 'Confirm action';
    var defaultYes = yesBtn ? yesBtn.textContent : 'Yes';
    var defaultNo = noBtn ? noBtn.textContent : 'No';

    function setError(message) {
        if (!errorEl) return;
        if (message) {
            errorEl.hidden = false;
            errorEl.textContent = message;
        } else {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }
    }

    function setBusy(isBusy, label) {
        busy = isBusy;
        modal.classList.toggle('is-busy', isBusy);
        yesBtn.disabled = isBusy;
        noBtn.disabled = isBusy;
        yesBtn.classList.toggle('is-loading', isBusy);
        if (isBusy) {
            yesBtn.innerHTML = '<span class="staff-confirm-spinner" aria-hidden="true"></span>' + (label || 'Closing…');
        } else {
            yesBtn.textContent = pendingForm
                ? (pendingForm.getAttribute('data-confirm-yes') || defaultYes)
                : defaultYes;
        }
    }

    function openModal(message, form) {
        pendingForm = form;
        busy = false;
        setError('');
        if (titleEl) {
            titleEl.textContent = form.getAttribute('data-confirm-title') || defaultTitle;
        }
        messageEl.textContent = message;
        yesBtn.textContent = form.getAttribute('data-confirm-yes') || defaultYes;
        noBtn.textContent = form.getAttribute('data-confirm-no') || defaultNo;
        yesBtn.disabled = false;
        noBtn.disabled = false;
        yesBtn.classList.remove('is-loading');
        if (dialog) {
            dialog.classList.toggle('is-danger', form.getAttribute('data-confirm-variant') === 'danger');
        }
        if (iconEl) {
            iconEl.textContent = form.getAttribute('data-confirm-icon') || '!';
        }
        if (nameEl) {
            var name = (form.getAttribute('data-confirm-name') || '').trim();
            nameEl.hidden = !name;
            nameEl.textContent = name;
        }
        if (noteEl) {
            var note = (form.getAttribute('data-confirm-note') || '').trim();
            noteEl.hidden = !note;
            noteEl.textContent = note;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        yesBtn.focus();
    }

    function closeModal() {
        if (busy) return;
        modal.classList.remove('is-open', 'is-busy');
        modal.setAttribute('aria-hidden', 'true');
        pendingForm = null;
        setError('');
        yesBtn.disabled = false;
        noBtn.disabled = false;
        yesBtn.classList.remove('is-loading');
        yesBtn.textContent = defaultYes;
        noBtn.textContent = defaultNo;
        if (titleEl) titleEl.textContent = defaultTitle;
        if (dialog) dialog.classList.remove('is-danger');
        if (iconEl) iconEl.textContent = '!';
        if (nameEl) {
            nameEl.hidden = true;
            nameEl.textContent = '';
        }
        if (noteEl) {
            noteEl.hidden = true;
            noteEl.textContent = '';
        }
    }

    function csrfToken(form) {
        var input = form.querySelector('input[name="_token"]');
        if (input && input.value) return input.value;
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showFlash(type, message) {
        var main = document.querySelector('.staff-main');
        if (!main || !message) return;
        main.querySelectorAll('.flash-alert[data-attendance-flash]').forEach(function (el) {
            el.remove();
        });
        var alert = document.createElement('div');
        alert.className = 'alert ' + (type === 'error' ? 'error' : 'success') + ' flash-alert';
        alert.setAttribute('data-attendance-flash', 'true');
        alert.textContent = message;
        var confirm = document.getElementById('staff-confirm-modal');
        if (confirm && confirm.parentNode === main) {
            main.insertBefore(alert, confirm.nextSibling);
        } else {
            main.insertBefore(alert, main.firstChild);
        }
    }

    function applyClosedSession(session) {
        if (!session || session.is_open) return;

        document.querySelectorAll('[data-attendance-status-badge]').forEach(function (badge) {
            badge.textContent = session.status_label || 'CLOSED';
            badge.classList.remove('green', 'gray', 'orange', 'red');
            badge.classList.add(session.status_badge_class || 'gray');
        });

        var panel = document.querySelector('[data-attendance-session]');
        if (panel) {
            panel.classList.remove('is-open');
            panel.classList.add('is-closed');
        }

        var copy = document.querySelector('[data-attendance-session-copy]');
        if (copy && session.copy) {
            copy.textContent = session.copy;
        }

        var history = document.querySelector('[data-attendance-session-history]');
        if (history) {
            history.style.display = session.history ? '' : 'none';
            history.textContent = session.history || '';
        }

        var closeBtn = document.querySelector('[data-attendance-close-btn]');
        if (closeBtn) {
            closeBtn.disabled = true;
        }

        var openBtn = document.querySelector('[data-attendance-open-btn]');
        if (openBtn) {
            openBtn.disabled = false;
        }
    }

    function submitCloseAjax(form) {
        if (busy) return;

        var closeBtn = form.querySelector('[data-attendance-close-btn]');
        if (closeBtn) closeBtn.disabled = true;

        setBusy(true, 'Closing…');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(form)
            },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data || {} };
            }).catch(function () {
                return { ok: false, status: response.status, data: { message: 'Unable to close attendance. Please try again.' } };
            });
        }).then(function (result) {
            if (result.ok && result.data.ok !== false) {
                applyClosedSession(result.data.session || { is_open: false, status_label: 'CLOSED', status_badge_class: 'gray' });
                busy = false;
                closeModal();
                showFlash('success', result.data.message || 'Attendance is now CLOSED.');
                return;
            }

            setBusy(false);
            if (closeBtn) closeBtn.disabled = false;
            setError(result.data.message || 'Unable to close attendance. Please try again.');
        }).catch(function () {
            setBusy(false);
            if (closeBtn) closeBtn.disabled = false;
            setError('Unable to close attendance. Please try again.');
        });
    }

    function updateApprovalStats(stats) {
        if (!stats) return;
        Object.keys(stats).forEach(function (key) {
            var el = document.querySelector('[data-approval-stat="' + key + '"]');
            if (el) el.textContent = stats[key];
        });
    }

    function updateApprovalBadge(count) {
        var badge = document.querySelector('[data-nav-badge="approval-requests"]');
        if (!badge) return;
        count = Number(count) || 0;
        if (count > 0) {
            badge.hidden = false;
            badge.textContent = String(count);
        } else {
            badge.hidden = true;
            badge.textContent = '0';
        }
    }

    function setApprovalRowBusy(form, isBusy) {
        var row = form.closest('tr');
        var actions = form.closest('[data-approval-actions]');
        var scope = row || actions || form;

        if (row) {
            row.classList.toggle('is-processing', isBusy);
        }
        if (actions) {
            actions.classList.toggle('is-processing', isBusy);
        }

        scope.querySelectorAll('button').forEach(function (button) {
            button.disabled = isBusy;
        });

        var status = scope.querySelector('[data-approval-status]');
        if (status) {
            if (isBusy) {
                if (!status.dataset.originalText) status.dataset.originalText = status.textContent;
                status.textContent = 'Processing…';
            } else if (status.dataset.originalText) {
                status.textContent = status.dataset.originalText;
            }
        }
    }

    function applyApprovalResult(form, data) {
        var row = form.closest('tr');
        if (row) {
            row.remove();
        }

        var table = document.querySelector('.staff-approval-table tbody');
        if (table && !table.querySelector('tr[data-scholar-id]')) {
            table.innerHTML = '<tr class="staff-table-empty"><td colspan="7">No approval requests found.</td></tr>';
        }

        updateApprovalStats(data.stats);
        updateApprovalBadge(data.pending_count);

        var profileActions = form.closest('[data-approval-actions]');
        if (profileActions) {
            profileActions.remove();
        }
    }

    function submitApprovalAjax(form, fromModal) {
        if (form.getAttribute('data-in-flight') === 'true') return;
        if (fromModal && busy) return;

        form.setAttribute('data-in-flight', 'true');
        if (fromModal) {
            setBusy(true, 'Processing…');
        } else {
            setApprovalRowBusy(form, true);
        }

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(form)
            },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data || {} };
            }).catch(function () {
                return { ok: false, data: { message: 'Unable to process this request. Please try again.' } };
            });
        }).then(function (result) {
            if (result.ok && result.data.ok !== false) {
                if (fromModal) {
                    busy = false;
                    closeModal();
                }
                applyApprovalResult(form, result.data);
                showFlash('success', result.data.message || 'The approval request was updated.');
                return;
            }

            form.setAttribute('data-in-flight', 'false');
            if (fromModal) {
                setBusy(false);
                setError(result.data.message || 'Unable to process this request. Please try again.');
            } else {
                setApprovalRowBusy(form, false);
                showFlash('error', result.data.message || 'Unable to process this request. Please try again.');
            }
        }).catch(function () {
            form.setAttribute('data-in-flight', 'false');
            if (fromModal) {
                setBusy(false);
                setError('Unable to process this request. Please try again.');
            } else {
                setApprovalRowBusy(form, false);
                showFlash('error', 'Unable to process this request. Please try again.');
            }
        });
    }

    document.querySelectorAll('form[data-ajax-approval="approve"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitApprovalAjax(form, false);
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                form.dataset.confirmed = '';
                return;
            }

            event.preventDefault();
            openModal(form.getAttribute('data-confirm') || 'Are you sure?', form);
        });
    });

    yesBtn.addEventListener('click', function () {
        if (!pendingForm || busy) return;

        if (pendingForm.getAttribute('data-ajax-close') === 'true') {
            submitCloseAjax(pendingForm);
            return;
        }

        if (pendingForm.getAttribute('data-ajax-approval')) {
            submitApprovalAjax(pendingForm, true);
            return;
        }

        pendingForm.dataset.confirmed = 'true';
        pendingForm.submit();
        closeModal();
    });

    noBtn.addEventListener('click', function () {
        if (busy) return;
        closeModal();
    });

    closeTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            if (busy) return;
            closeModal();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open') && !busy) {
            closeModal();
        }
    });
});
</script>
