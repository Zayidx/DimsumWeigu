document.addEventListener('alpine:init', () => {
    Alpine.data('homepage', () => ({
        observer: null,
        init() {
            const elements = this.$root.querySelectorAll('.scroll-reveal');
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
                elements.forEach((element) => element.classList.add('is-visible'));
                return;
            }

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        this.observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -48px 0px' });

            elements.forEach((element) => this.observer.observe(element));
        },
        destroy() {
            this.observer?.disconnect();
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
