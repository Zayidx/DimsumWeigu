document.addEventListener('alpine:init', () => {
    Alpine.data('homepage', () => ({
        navigationFrame: null,
        onScroll: null,
        init() {
            const links = [...this.$root.querySelectorAll('[data-homepage-nav-link]')];
            const sections = links.map(link => document.querySelector(link.getAttribute('href')));
            const update = () => {
                this.navigationFrame = null;
                let active = 0;
                sections.forEach((section, index) => {
                    if (section && section.getBoundingClientRect().top <= 120) active = index;
                });
                links.forEach((link, index) => {
                    if (index === active) link.setAttribute('aria-current', 'location');
                    else link.removeAttribute('aria-current');
                });
            };
            this.onScroll = () => {
                if (this.navigationFrame === null) this.navigationFrame = requestAnimationFrame(update);
            };
            window.addEventListener('scroll', this.onScroll, { passive: true });
            window.addEventListener('resize', this.onScroll, { passive: true });
            this.$nextTick(update);
        },
        destroy() {
            window.removeEventListener('scroll', this.onScroll);
            window.removeEventListener('resize', this.onScroll);
            if (this.navigationFrame !== null) cancelAnimationFrame(this.navigationFrame);
        },
    }));

    Alpine.data('homepageMenu', () => ({
        activeCategory: 'dimsum-kukus',
        moveTab(event) {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const tabs = [...event.currentTarget.querySelectorAll('[role="tab"]')];
            const current = tabs.indexOf(event.target);
            if (current < 0) return;
            event.preventDefault();
            let next = (current + (event.key === 'ArrowLeft' ? -1 : 1) + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            tabs[next].click();
            tabs[next].focus({ preventScroll: true });
            tabs[next].scrollIntoView({ block: 'nearest', inline: 'nearest' });
        },
    }));

    Alpine.data('productReport', (whatsappNumber) => ({
        report: { product: '', branch: '', purchasedAt: '', details: '', agreed: false },
        openReport(event) {
            if (!event.target.reportValidity()) return;

            const message = [
                'Halo CS Weigu, saya ingin menyampaikan aduan/laporan produk.',
                `Produk: ${this.report.product}`,
                `Cabang: ${this.report.branch || 'Tidak disebutkan'}`,
                `Waktu pembelian: ${this.report.purchasedAt || 'Tidak disebutkan'}`,
                `Kronologi aduan: ${this.report.details}`,
            ].join('\n');

            window.open(`https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
        },
    }));
});
