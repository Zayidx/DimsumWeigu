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
        if (!root) return;
        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const payload = `${root.dataset.salesCharts}|${dark ? 'dark' : 'light'}`;
        if (payload === lastPayload) return;
        lastPayload = payload;
        charts.forEach(chart => chart.destroy());
        charts.clear();

        if (typeof ApexCharts === 'undefined') {
            root.querySelectorAll('.sales-report-chart').forEach(target => { target.textContent = 'Grafik tidak tersedia. Gunakan tabel rincian penjualan di bawah.'; });
            return;
        }

        let data;
        try {
            const bytes = Uint8Array.from(atob(root.dataset.salesCharts), character => character.charCodeAt(0));
            data = JSON.parse(new TextDecoder().decode(bytes));
        } catch (_) {
            return;
        }

        const base = {
            chart: { height: 300, background: 'transparent', toolbar: { show: false }, fontFamily: 'Nunito, sans-serif', animations: { enabled: false } },
            theme: { mode: dark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            foreColor: dark ? '#c2b6a9' : '#746b63',
            grid: { borderColor: dark ? '#4b4138' : '#e4ded7', strokeDashArray: 4 },
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
            chart: { ...base.chart, type: 'area' }, colors: [dark ? '#c7987e' : '#805945'],
            series: [{ name: 'Penjualan', data: data.daily.values }],
            xaxis: { categories: data.daily.labels, labels: { rotate: -45, hideOverlappingLabels: true } },
            yaxis: { labels: { formatter: value => `Rp${compact.format(value)}` } },
            stroke: { width: 3, curve: 'smooth' }, fill: { type: 'solid', opacity: 0.18 },
        });
        add('sales-chart-outlets', {
            chart: { ...base.chart, type: 'bar' }, colors: [dark ? '#c7987e' : '#805945'],
            series: [{ name: 'Penjualan', data: data.outlets.values }],
            xaxis: { categories: data.outlets.labels, labels: { trim: true } },
            yaxis: { labels: { formatter: value => `Rp${compact.format(value)}` } },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } },
        });
        add('sales-chart-methods', {
            chart: { ...base.chart, type: 'donut' },
            labels: data.methods.labels.map(method => ({ cash: 'Tunai', qris: 'QRIS', 'pay-later': 'Piutang', 'bank-transfer': 'Transfer' })[method] || method),
            series: data.methods.values, colors: dark ? ['#c7987e', '#b6a18f', '#82917a', '#c19a61', '#a77e78'] : ['#805945', '#746b63', '#71806d', '#a8844f', '#936d68'],
            stroke: { colors: [dark ? '#292521' : '#ffffff'], width: 2 },
            legend: { position: 'bottom' },
            plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, formatter: () => rupiah.format(data.methods.values.reduce((sum, value) => sum + value, 0)) } } } } },
        });
        add('sales-chart-products', {
            chart: { ...base.chart, type: 'bar' }, colors: [dark ? '#b6a18f' : '#9b806e'],
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
