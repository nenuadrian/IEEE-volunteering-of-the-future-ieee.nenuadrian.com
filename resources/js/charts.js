/**
 * Declarative Chart.js wrapper. Server-rendered markup:
 *
 *   <div data-chart='{"type":"line","labels":[...],"series":[{"name":"…","data":[…]}]}'>
 *     <div class="relative h-64"><canvas></canvas></div>
 *     <details><summary>View as table</summary><div data-chart-table></div></details>
 *   </div>
 *
 * Conventions (see the dataviz guidance this follows):
 *  - fixed categorical order, validated for colour-vision deficiency;
 *    a series may pin its slot so colour follows the entity, not its rank
 *  - 2px lines, 10% area wash for a single series, bars ≤ 24px with 4px
 *    rounded data-ends, 2px surface gaps between stacked segments
 *  - hairline solid grid, legend only for ≥ 2 series, crosshair + one
 *    tooltip listing every series on line charts
 *  - every chart has a table twin (values never live only in a tooltip)
 */
import {
    Chart, LineController, BarController, LineElement, BarElement, PointElement,
    CategoryScale, LinearScale, Filler, Tooltip, Legend,
} from 'chart.js';

Chart.register(LineController, BarController, LineElement, BarElement, PointElement,
    CategoryScale, LinearScale, Filler, Tooltip, Legend);

// IEEE blue, IEEE orange, then the validated reference order.
export const SERIES = ['#00629b', '#e87722', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
const INK = '#222222';
const INK_2 = '#52514e';
const MUTED = '#898781';
const GRID = '#ececea';
const SURFACE = '#ffffff';

Chart.defaults.font.family = '"Open Sans", system-ui, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = MUTED;

const crosshair = {
    id: 'crosshair',
    afterDatasetsDraw(chart) {
        if (chart.config.type !== 'line') return;
        const active = chart.tooltip?.getActiveElements?.() || [];
        if (!active.length) return;
        const { ctx, chartArea } = chart;
        const x = active[0].element.x;
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.strokeStyle = '#c3c2b7';
        ctx.stroke();
        ctx.restore();
    },
};

function hexToRgba(hex, alpha) {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
}

function formatter(format) {
    const nf = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 });
    return (v) => {
        if (v === null || v === undefined) return '–';
        if (format === 'percent') return `${nf.format(v)}%`;
        if (format === 'hours') return `${nf.format(v)} h`;
        if (format === 'days') return `${nf.format(v)} d`;
        return nf.format(v);
    };
}

function buildDatasets(cfg) {
    const many = cfg.series.length > 1;
    return cfg.series.map((s, i) => {
        const color = s.color || SERIES[(s.slot ?? i) % SERIES.length];
        if (cfg.type === 'line') {
            return {
                label: s.name,
                data: s.data,
                borderColor: color,
                backgroundColor: hexToRgba(color, 0.1),
                fill: !many && cfg.area !== false,
                borderWidth: 2,
                borderJoinStyle: 'round',
                borderCapStyle: 'round',
                cubicInterpolationMode: 'monotone',
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBorderWidth: 2,
                pointHoverBorderColor: SURFACE,
                pointHoverBackgroundColor: color,
            };
        }
        return {
            label: s.name,
            data: s.data,
            backgroundColor: color,
            hoverBackgroundColor: hexToRgba(color, 0.8),
            maxBarThickness: 24,
            borderSkipped: 'start',
            borderRadius: cfg.stacked ? 0 : 4,
            borderColor: SURFACE,
            borderWidth: cfg.stacked ? (cfg.type === 'hbar' ? { right: 2 } : { top: 2 }) : 0,
        };
    });
}

function renderTable(root, cfg, fmt) {
    const holder = root.querySelector('[data-chart-table]');
    if (!holder) return;
    const table = document.createElement('table');
    table.className = 'mt-2 w-full text-left text-xs';
    const thead = table.createTHead().insertRow();
    [cfg.categoryLabel || '', ...cfg.series.map((s) => s.name)].forEach((h) => {
        const th = document.createElement('th');
        th.className = 'border-b border-light-gray px-2 py-1.5 font-semibold text-ink';
        th.textContent = h;
        thead.appendChild(th);
    });
    const tbody = table.createTBody();
    cfg.labels.forEach((label, i) => {
        const tr = tbody.insertRow();
        const th = document.createElement('th');
        th.className = 'border-b border-light-gray px-2 py-1 font-normal text-warmer-gray';
        th.textContent = label;
        tr.appendChild(th);
        cfg.series.forEach((s) => {
            const td = tr.insertCell();
            td.className = 'border-b border-light-gray px-2 py-1 tabular-nums text-ink';
            td.textContent = fmt(s.data[i]);
        });
    });
    holder.replaceChildren(table);
}

export function renderChart(root) {
    let cfg;
    try {
        cfg = JSON.parse(root.dataset.chart);
    } catch (e) {
        console.error('Invalid chart config', e);
        return;
    }
    const canvas = root.querySelector('canvas');
    if (!canvas || !cfg.series?.length) return;

    const fmt = formatter(cfg.format);
    const horizontal = cfg.type === 'hbar';
    const isLine = cfg.type === 'line';
    const many = cfg.series.length > 1;

    const valueAxis = {
        beginAtZero: true,
        stacked: !!cfg.stacked,
        grid: { color: GRID, drawTicks: false },
        border: { display: false },
        ticks: { color: MUTED, padding: 8, precision: 0, callback: (v) => fmt(v), maxTicksLimit: 6 },
        max: cfg.format === 'percent' && cfg.capPercent ? 100 : undefined,
    };
    const categoryAxis = {
        stacked: !!cfg.stacked,
        grid: { display: false },
        border: { color: '#c3c2b7' },
        ticks: { color: MUTED, autoSkip: true, maxRotation: 0, autoSkipPadding: 12 },
    };

    new Chart(canvas, {
        type: isLine ? 'line' : 'bar',
        data: { labels: cfg.labels, datasets: buildDatasets(cfg) },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            animation: { duration: 300 },
            interaction: isLine ? { mode: 'index', intersect: false } : { mode: 'nearest', intersect: true },
            scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
            plugins: {
                legend: {
                    display: many,
                    position: 'top',
                    align: 'start',
                    labels: { color: INK_2, usePointStyle: true, pointStyle: isLine ? 'line' : 'rect', boxWidth: 14, padding: 14 },
                },
                tooltip: {
                    backgroundColor: SURFACE,
                    titleColor: INK_2,
                    bodyColor: INK,
                    borderColor: 'rgba(11,11,11,0.12)',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    usePointStyle: true,
                    titleFont: { weight: '400' },
                    bodyFont: { weight: '600' },
                    callbacks: {
                        label: (ctx) => ` ${fmt(horizontal ? ctx.parsed.x : ctx.parsed.y)}  ${many ? ctx.dataset.label : ''}`,
                        labelPointStyle: () => ({ pointStyle: 'line', rotation: 0 }),
                    },
                },
            },
        },
        plugins: [crosshair],
    });

    renderTable(root, cfg, fmt);
}

export function initCharts(nodes) {
    nodes.forEach(renderChart);
}
