document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initProfileDropdown();
    initProfileTabs();
    initFileUpload();
    initFlashDismiss();
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

function initFlashDismiss() {
    document.querySelectorAll('.flash-alert').forEach((el) => {
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity .4s';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });
}
