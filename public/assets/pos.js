(() => {
    'use strict';

    const region = document.querySelector('[data-toast-region]');
    const status = document.querySelector('[data-pos-status]');

    function showToast(event) {
        const detail = event.detail || {};
        if (!region || typeof detail.message !== 'string') return;

        const toast = document.createElement('div');
        toast.className = 'pos-toast';
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
        region.append(toast);
        while (region.children.length > 3) region.firstElementChild.remove();
    }

    function showOfflineStatus() {
        if (!status) return;
        status.hidden = false;
        status.textContent = 'Koneksi terputus. Hubungkan kembali sebelum menyimpan atau membayar.';
    }

    function hideOfflineStatus() {
        if (status) status.hidden = true;
    }

    window.addEventListener('toast', showToast);
    window.addEventListener('offline', showOfflineStatus);
    window.addEventListener('online', hideOfflineStatus);
    document.addEventListener('click', event => {
        document.querySelectorAll('.pos-account-menu[open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') document.querySelectorAll('.pos-account-menu[open]').forEach(menu => menu.removeAttribute('open'));
    });
    const mobile = window.matchMedia('(max-width: 1199.98px)');
    const panel = document.getElementById('pos-cart-panel');
    let previousFocus = null;
    let wasOpen = false;
    const syncCart = () => {
        const open = mobile.matches && panel?.classList.contains('is-open');
        if (!panel) return;
        panel.setAttribute('role', open ? 'dialog' : 'region');
        if (open) panel.setAttribute('aria-modal', 'true');
        else panel.removeAttribute('aria-modal');
        document.body.classList.toggle('pos-cart-open', !!open);
        if (open && !wasOpen) {
            previousFocus = document.activeElement;
            panel.querySelector('button')?.focus({ preventScroll: true });
        } else if (!open && wasOpen && previousFocus?.isConnected) {
            previousFocus.focus({ preventScroll: true });
        }
        wasOpen = !!open;
    };
    if (panel) new MutationObserver(syncCart).observe(panel, { attributes: true, attributeFilter: ['class'] });
    mobile.addEventListener('change', syncCart);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Tab' || !wasOpen || !panel) return;
        const controls = [...panel.querySelectorAll('a[href], button, input, select, textarea, [tabindex="0"]')]
            .filter(element => !element.disabled && element.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (!first) { event.preventDefault(); panel.focus(); return; }
        if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
            event.preventDefault(); first.focus();
        }
    });
    syncCart();
    if (!navigator.onLine) showOfflineStatus();
})();
