(() => {
    'use strict';
    const mobile = window.matchMedia('(max-width: 1199.98px)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const sidebar = document.getElementById('sidebar');
    let lastFocus = null;
    let dialogWasOpen = false;

    function prepareTables() {
        document.querySelectorAll('.workspace-shell .table-responsive table').forEach(table => {
            const headings = table.querySelectorAll('thead > tr');
            if (headings.length !== 1 || table.querySelector('th[colspan], th[rowspan], td[rowspan]')) return;
            const labels = [...headings[0].children].map(cell => cell.textContent.trim());
            if (!labels.length || !table.tBodies.length) return;
            for (const body of table.tBodies) {
                for (const row of body.rows) {
                    if (row.cells.length !== labels.length) continue;
                    [...row.cells].forEach((cell, index) => {
                        if (!cell.hasAttribute('colspan')) cell.dataset.label = labels[index] || 'Aksi';
                    });
                }
            }
            table.classList.add('mobile-records');
        });
    }

    function syncOverlays() {
        const darkTheme = document.documentElement.dataset.workspaceTheme === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.setAttribute('aria-pressed', String(darkTheme));
            const label = button.querySelector('span');
            const text = darkTheme ? 'Ganti tema terang' : 'Ganti tema gelap';
            if (label && label.textContent !== text) label.textContent = text;
        });
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', darkTheme ? '#171717' : '#ffffff');
        const panel = document.querySelector('.pos-cart-panel.is-open');
        const cartOpen = !!panel && mobile.matches;
        document.body.classList.toggle('pos-cart-open', cartOpen);
        if (panel) {
            panel.setAttribute('role', cartOpen ? 'dialog' : 'region');
            if (cartOpen) panel.setAttribute('aria-modal', 'true');
            else panel.removeAttribute('aria-modal');
        }
        const sidebarOpen = mobile.matches && sidebar?.classList.contains('is-open');
        if (sidebar) {
            sidebar.setAttribute('role', sidebarOpen ? 'dialog' : 'complementary');
            if (sidebarOpen) sidebar.setAttribute('aria-modal', 'true');
            else sidebar.removeAttribute('aria-modal');
        }
        document.querySelectorAll('[data-sidebar-toggle]').forEach(button => button.setAttribute('aria-expanded', String(!!sidebarOpen)));
        if (sidebar && mobile.matches) sidebar.inert = !sidebarOpen;
        else if (sidebar) sidebar.inert = false;
        const overlay = cartOpen ? panel : sidebarOpen ? sidebar : null;
        const open = !!overlay;
        if (open && !dialogWasOpen) {
            lastFocus = document.activeElement;
            overlay.querySelector('button, a, input, select, [tabindex="0"]')?.focus({ preventScroll: true });
        } else if (!open && dialogWasOpen) {
            const restoreTarget = lastFocus?.isConnected && lastFocus.getClientRects().length
                ? lastFocus
                : document.getElementById('workspace-content');
            restoreTarget?.focus({ preventScroll: true });
        }
        dialogWasOpen = open;
    }

    function notify(event) {
        const detail = event.detail || {};
        if (typeof detail.message !== 'string') return;
        const region = document.querySelector('[data-toast-region]');
        if (!region) return;
        const toast = document.createElement('div');
        toast.className = 'workspace-toast';
        if (detail.type === 'warning') toast.classList.add('is-warning');
        if (detail.type === 'danger' || detail.type === 'error') toast.classList.add('is-danger');
        const message = document.createElement('span');
        message.textContent = detail.message;
        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Tutup pemberitahuan');
        close.textContent = '×';
        close.addEventListener('click', () => toast.remove());
        toast.append(message, close);
        region.appendChild(toast);
        // Keep important messages available; dismiss them explicitly.
        while (region.children.length > 3) region.firstElementChild.remove();
    }

    document.addEventListener('click', event => {
        const dismiss = event.target.closest('[data-bs-dismiss="alert"]');
        if (dismiss) { dismiss.closest('.alert')?.remove(); return; }
        const collapse = event.target.closest('[data-bs-toggle="collapse"]');
        if (collapse) {
            const target = collapse.getAttribute('data-bs-target');
            const panel = target?.startsWith('#') ? document.getElementById(target.slice(1)) : null;
            if (panel) {
                const expanded = panel.classList.toggle('show');
                collapse.setAttribute('aria-expanded', String(expanded));
                collapse.setAttribute('aria-controls', panel.id);
            }
            return;
        }

        if (event.target.closest('[data-theme-toggle]')) {
            const theme = document.documentElement.dataset.workspaceTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.workspaceTheme = theme;
            try { localStorage.setItem('workspace-theme', theme); } catch (_) { /* Private mode can deny storage. */ }
            syncOverlays();
            return;
        }
        if (event.target.closest('[data-sidebar-toggle]')) {
            sidebar?.classList.add('is-open');
            syncOverlays();
            return;
        }
        if (event.target.closest('[data-sidebar-close]') && mobile.matches) {
            sidebar?.classList.remove('is-open');
            syncOverlays();
            return;
        }
        const submenuToggle = event.target.closest('#sidebar .sidebar-item.has-sub > .sidebar-link');
        if (submenuToggle) {
            event.preventDefault();
            const submenu = submenuToggle.parentElement.querySelector(':scope > .submenu');
            const expanded = submenuToggle.getAttribute('aria-expanded') === 'true';
            submenuToggle.setAttribute('aria-expanded', String(!expanded));
            submenu?.classList.toggle('submenu-open', !expanded);
            return;
        }
        if (mobile.matches && event.target.closest('#sidebar a[href]')) {
            sidebar?.classList.remove('is-open');
            syncOverlays();
        }
    });

    document.addEventListener('keydown', event => {
        const cart = mobile.matches && document.querySelector('.pos-cart-panel.is-open');
        const overlay = cart || (mobile.matches && sidebar?.classList.contains('is-open') ? sidebar : null);
        if (event.key === 'Escape') {
            if (mobile.matches && sidebar?.classList.contains('is-open')) {
                sidebar.classList.remove('is-open');
                syncOverlays();
            }
        }
        if (event.key !== 'Tab' || !overlay) return;
        const focusable = [...overlay.querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"]')].filter(element => !element.disabled && element.getClientRects().length);
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (!first) return;
        if (event.shiftKey && (document.activeElement === first || !overlay.contains(document.activeElement))) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !overlay.contains(document.activeElement))) {
            event.preventDefault(); first.focus();
        }
    });

    window.addEventListener('toast', notify);
    window.addEventListener('offline', () => {
        const status = document.querySelector('[data-workspace-status]');
        if (status) { status.hidden = false; status.textContent = 'Koneksi terputus. Hubungkan internet kembali sebelum menyimpan atau membayar.'; }
    });
    window.addEventListener('online', () => {
        const status = document.querySelector('[data-workspace-status]');
        if (status) status.hidden = true;
    });
    mobile.addEventListener('change', () => {
        if (!mobile.matches) sidebar?.classList.remove('is-open');
        syncOverlays();
    });
    let pending = false;
    new MutationObserver(records => {
        const tableChanged = records.some(record => record.target.closest?.('.table-responsive') ||
            [...record.addedNodes].some(node => node.nodeType === 1 &&
                (node.matches('table, .table-responsive') || node.querySelector('table'))));
        if (!tableChanged || pending) return;
        pending = true;
        requestAnimationFrame(() => { pending = false; prepareTables(); });
    }).observe(document.getElementById('app'), { childList: true, subtree: true });
    if (sidebar) new MutationObserver(syncOverlays).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    document.querySelectorAll('.pos-workspace').forEach(root => {
        new MutationObserver(syncOverlays).observe(root, { attributes: true, subtree: true, attributeFilter: ['class'] });
    });
    document.addEventListener('livewire:initialized', () => {
        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => requestAnimationFrame(() => {
                const error = document.querySelector('.pos-cart-panel.is-open .alert-danger') || document.querySelector('.pos-workspace .alert-danger, .workspace-shell [data-error-summary]');
                if (error) error.scrollIntoView({ block: 'nearest', behavior: reducedMotion.matches ? 'instant' : 'smooth' });
            }));
        });
    });
    prepareTables();
    syncOverlays();
})();
