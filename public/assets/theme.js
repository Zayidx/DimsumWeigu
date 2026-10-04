(() => {
    let darkMode = false;

    try {
        darkMode = localStorage.getItem('darkMode') === 'true';
    } catch {
        // The default theme also works when browser storage is unavailable.
    }

    document.documentElement.classList.toggle('dark', darkMode);
    document.addEventListener('DOMContentLoaded', () => {
        document.body.classList.toggle('dark', darkMode);
        document.body.classList.toggle('light', !darkMode);
    });
})();
