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
        const dark = document.documentElement.dataset.workspaceTheme === 'dark';
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
            foreColor: dark ? '#c4c4c4' : '#666666',
            grid: { borderColor: dark ? '#404040' : '#dededc', strokeDashArray: 4 },
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
            if (id === 'sales-chart-hours' && !data.hours.values.some(value => value > 0)) {
                element.textContent = 'Belum ada transaksi pada rentang tanggal ini.';
                return;
            }
            element.textContent = '';
            const chart = new ApexCharts(element, { ...base, ...options });
            charts.set(id, chart);
            chart.render();
        };
        add('sales-chart-daily', {
            chart: { ...base.chart, type: 'area' }, colors: [dark ? '#f5f5f5' : '#171717'],
            series: [{ name: 'Penjualan', data: data.daily.values }],
            xaxis: { categories: data.daily.labels, labels: { rotate: -45, hideOverlappingLabels: true } },
            yaxis: { labels: { formatter: value => `Rp${compact.format(value)}` } },
            stroke: { width: 3, curve: 'smooth' }, fill: { type: 'solid', opacity: 0.18 },
        });
        add('sales-chart-outlets', {
            chart: { ...base.chart, type: 'bar' }, colors: [dark ? '#f5f5f5' : '#171717'],
            series: [{ name: 'Penjualan', data: data.outlets.values }],
            xaxis: { categories: data.outlets.labels, labels: { trim: true } },
            yaxis: { labels: { formatter: value => `Rp${compact.format(value)}` } },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '48%' } },
        });
        add('sales-chart-methods', {
            chart: { ...base.chart, type: 'donut' },
            labels: data.methods.labels.map(method => ({ cash: 'Tunai', qris: 'QRIS', 'pay-later': 'Piutang', 'bank-transfer': 'Transfer' })[method] || method),
            series: data.methods.values, colors: dark ? ['#f5f5f5', '#c4c4c4', '#999999', '#707070', '#4a4a4a'] : ['#171717', '#444444', '#666666', '#888888', '#b0b0b0'],
            stroke: { colors: [dark ? '#202020' : '#ffffff'], width: 2 },
            legend: { position: 'bottom' },
            plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, formatter: () => rupiah.format(data.methods.values.reduce((sum, value) => sum + value, 0)) } } } } },
        });
        add('sales-chart-products', {
            chart: { ...base.chart, type: 'bar' }, colors: [dark ? '#c4c4c4' : '#555555'],
            series: [{ name: data.products.quantity ? 'Terjual' : 'Penjualan', data: data.products.quantity || data.products.values }],
            xaxis: { categories: data.products.labels, labels: { formatter: value => data.products.quantity ? compact.format(value) : `Rp${compact.format(value)}` } },
            tooltip: { y: { formatter: value => data.products.quantity ? compact.format(value) : rupiah.format(value) } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        });
        add('sales-chart-hours', {
            chart: { ...base.chart, type: 'bar', height: 340 },
            colors: [dark ? '#c4c4c4' : '#555555'],
            series: [{ name: 'Transaksi', data: data.hours.values }],
            xaxis: { categories: data.hours.labels, title: { text: 'Jam transaksi' } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => compact.format(value) }, title: { text: 'Jumlah transaksi' } },
            tooltip: { y: { formatter: value => `${compact.format(value)} transaksi` } },
            plotOptions: { bar: { borderRadius: 3, columnWidth: '58%' } },
        });
    }

    function schedule() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(draw);
    }

    document.addEventListener('DOMContentLoaded', () => {
        schedule();
        new MutationObserver(schedule).observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-sales-charts', 'data-workspace-theme'] });
    });
})();
