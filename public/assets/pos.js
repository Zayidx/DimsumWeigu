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
    if (!navigator.onLine) showOfflineStatus();
})();
