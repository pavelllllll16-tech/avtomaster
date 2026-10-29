/**
 * Главная страница: графики продаж.
 */
$(function () {
    const charts = [];

    function themeColors() {
        const dark = document.documentElement.dataset.bsTheme === 'dark';
        return {
            text: dark ? '#a7b0c0' : '#6b7280',
            grid: dark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)',
        };
    }

    function render() {
        charts.forEach(c => c.destroy());
        charts.length = 0;
        const c = themeColors();
        Chart.defaults.color = c.text;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

        const days = APP.salesByDay;
        charts.push(new Chart(document.getElementById('salesChart'), {
            type: 'bar',
            data: {
                labels: days.map(d => App.date(d.day).slice(0, 5)),
                datasets: [
                    { label: 'Выручка', data: days.map(d => d.revenue), backgroundColor: '#f26b1d', borderRadius: 6, maxBarThickness: 22 },
                    { label: 'Прибыль', data: days.map(d => d.profit), type: 'line', borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5 },
                ],
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + App.money(ctx.parsed.y) } },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: c.grid }, ticks: { callback: v => App.num(v) } },
                },
            },
        }));

        const catCanvas = document.getElementById('categoryChart');
        if (catCanvas) {
            charts.push(new Chart(catCanvas, {
                type: 'doughnut',
                data: {
                    labels: APP.byCategory.map(r => r.name),
                    datasets: [{
                        data: APP.byCategory.map(r => Number(r.revenue)),
                        backgroundColor: ['#f26b1d', '#0d6efd', '#198754', '#ffb020', '#6f42c1', '#20c997', '#dc3545'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: { callbacks: { label: ctx => ctx.label + ': ' + App.money(ctx.parsed) } },
                    },
                },
            }));
        }
    }

    render();
    $(document).on('themechange', render);
});
