/*
 * FOURLINE — tema único de gráficos (Chart.js). Painel, Relatórios e demais telas
 * usam estes helpers para ficarem idênticos: mesmas cores da marca, fonte,
 * grade, legenda, dica (tooltip), barras arredondadas e modo claro/escuro.
 */
(function () {
    if (!window.Chart) return;

    var css = getComputedStyle(document.documentElement);
    function v(name, fallback) { return (css.getPropertyValue(name) || '').trim() || fallback; }

    var C = {
        abertos: '#A8CF45',     // verde-limão da marca
        resolvidos: '#068A4F',  // verde da marca
        principal: '#068A4F',
        area: 'rgba(6,138,79,.12)',
        perigo: '#dc2626',
        texto: v('--muted-c', '#64748b'),
        grade: v('--border-c', '#e6eaf0'),
    };

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = C.texto;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.position = 'bottom';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'rectRounded';
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
    Chart.defaults.plugins.legend.labels.padding = 16;
    Chart.defaults.plugins.tooltip.backgroundColor = '#033d22';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.boxPadding = 4;
    Chart.defaults.plugins.tooltip.usePointStyle = true;
    Chart.defaults.elements.bar.borderRadius = 6;
    Chart.defaults.elements.bar.borderSkipped = false;
    Chart.defaults.datasets.bar.maxBarThickness = 26;
    Chart.defaults.datasets.bar.categoryPercentage = 0.7;
    Chart.defaults.datasets.bar.barPercentage = 0.85;

    var eixoX = { grid: { display: false }, border: { display: false } };
    var eixoY = { beginAtZero: true, grid: { color: C.grade }, border: { display: false }, ticks: { precision: 0 } };

    function el(id) { return typeof id === 'string' ? document.getElementById(id) : id; }

    window.FLCharts = {
        cores: C,

        /** Abertos × resolvidos (barras lado a lado). */
        abertosResolvidos: function (id, labels, abertos, resolvidos) {
            var e = el(id); if (!e) return null;
            return new Chart(e, {
                type: 'bar',
                data: { labels: labels, datasets: [
                    { label: 'Abertos', data: abertos, backgroundColor: C.abertos, hoverBackgroundColor: C.abertos },
                    { label: 'Resolvidos', data: resolvidos, backgroundColor: C.resolvidos, hoverBackgroundColor: C.resolvidos },
                ] },
                options: { interaction: { mode: 'index', intersect: false }, scales: { x: eixoX, y: eixoY } },
            });
        },

        /** Linha com área (ex.: tempo médio de solução). Meses sem dado ficam como lacuna. */
        linha: function (id, labels, dados, rotulo, sufixo) {
            var e = el(id); if (!e) return null;
            return new Chart(e, {
                type: 'line',
                data: { labels: labels, datasets: [{
                    label: rotulo, data: dados, borderColor: C.principal, backgroundColor: C.area,
                    fill: true, tension: 0.35, pointRadius: 4, pointHoverRadius: 6,
                    pointBackgroundColor: C.principal, spanGaps: true,
                }] },
                options: {
                    plugins: { legend: { display: false }, tooltip: { callbacks: {
                        label: function (c) { return ' ' + (c.parsed.y == null ? '—' : c.parsed.y.toLocaleString('pt-BR') + (sufixo || '')); },
                    } } },
                    scales: { x: eixoX, y: Object.assign({}, eixoY, { ticks: { precision: 1 } }) },
                },
            });
        },

        /** Rosca de proporção (ex.: SLA no prazo × fora do prazo). */
        rosca: function (id, rotulos, valores, cores) {
            var e = el(id); if (!e) return null;
            return new Chart(e, {
                type: 'doughnut',
                data: { labels: rotulos, datasets: [{ data: valores, backgroundColor: cores, borderWidth: 0, hoverOffset: 4 }] },
                options: { cutout: '74%', plugins: { legend: { display: false } } },
            });
        },
    };
})();
