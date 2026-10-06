(() => {
    'use strict';

    const charts = new Map();
    let lastPayload = '';
    let scheduled = false;
    const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });

    function draw() {
        scheduled = false;
        const root = document.querySelector('[data-sales-report]');
        if (!root || root.dataset.salesCharts === lastPayload) return;
        lastPayload = root.dataset.salesCharts;
        charts.forEach(chart => chart.destroy());
        charts.clear();

        if (typeof ApexCharts === 'undefined') {
            root.querySelectorAll('.sales-report-chart').forEach(target => { target.textContent = 'Grafik tidak tersedia. Gunakan tabel rincian penjualan di bawah.'; });
            return;
        }

        let data;
        try {
            const bytes = Uint8Array.from(atob(lastPayload), character => character.charCodeAt(0));
            data = JSON.parse(new TextDecoder().decode(bytes));
        } catch (_) {
            return;
        }

        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const base = {
            chart: { height: 300, background: 'transparent', toolbar: { show: false }, fontFamily: 'Nunito, sans-serif', animations: { enabled: false } },
            theme: { mode: dark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            grid: { borderColor: dark ? '#42445a' : '#e5eaf1', strokeDashArray: 4 },
            tooltip: { y: { formatter: value => rupiah.format(value) } },
            noData: { text: 'Belum ada data' },
        };
        const add = (id, options) => {
            const element = document.getElementById(id);
            if (!element) return;
            if (id === 'sales-chart-methods' && !data.methods.values.some(value => value > 0)) {
                element.textContent = 'Belum ada pembayaran pada filter ini.';
                return;
            }
            if (id === 'sales-chart-products' && data.products.values.length === 0) {
                element.textContent = 'Belum ada rincian produk pada filter ini.';
                return;
            }
            element.textContent = '';
            const chart = new ApexCharts(element, { ...base, ...options });
            charts.set(id, chart);
            chart.render();
        };
        add('sales-chart-daily', {
            chart: { ...base.chart, type: 'area' }, colors: ['#435ebe'],
            series: [{ name: 'Penjualan', data: data.daily.values }],
            xaxis: { categories: data.daily.labels, labels: { rotate: -45, hideOverlappingLabels: true } },
            yaxis: { labels: { formatter: value => `Rp${compact.format(value)}` } },
            stroke: { width: 3, curve: 'smooth' }, fill: { type: 'solid', opacity: 0.18 },
        });
        add('sales-chart-outlets', {
            chart: { ...base.chart, type: 'bar' }, colors: ['#435ebe'],
            series: [{ name: 'Penjualan', data: data.outlets.values }],
            xaxis: { categories: data.outlets.labels, labels: { trim: true } },
            yaxis: { labels: { formatter: value => `Rp${compact.format(value)}` } },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } },
        });
        add('sales-chart-methods', {
            chart: { ...base.chart, type: 'donut' },
            labels: data.methods.labels.map(method => ({ cash: 'Tunai', qris: 'QRIS', 'pay-later': 'Piutang', 'bank-transfer': 'Transfer' })[method] || method),
            series: data.methods.values, colors: ['#435ebe', '#57caeb', '#5ddab4', '#ffb54a', '#9694ff'],
            legend: { position: 'bottom' },
            plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, formatter: () => rupiah.format(data.methods.values.reduce((sum, value) => sum + value, 0)) } } } } },
        });
        add('sales-chart-products', {
            chart: { ...base.chart, type: 'bar' }, colors: ['#57caeb'],
            series: [{ name: 'Penjualan', data: data.products.values }],
            xaxis: { categories: data.products.labels, labels: { formatter: value => `Rp${compact.format(value)}` } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        });
    }

    function schedule() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(draw);
    }

    document.addEventListener('DOMContentLoaded', () => {
        schedule();
        new MutationObserver(schedule).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-sales-charts', 'data-bs-theme'] });
    });
})();
