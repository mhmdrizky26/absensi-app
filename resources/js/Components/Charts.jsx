import { useLayoutEffect, useRef, useState } from 'react';

/**
 * Grafik SVG ringan untuk halaman tren (tanpa library). Batang tipis dengan
 * ujung membulat 4px, garis 2px, grid tipis, tooltip saat disentuh/hover,
 * dan label nilai hanya pada titik yang penting.
 */

const HEIGHT = 220;
const MARGIN = { top: 20, right: 12, bottom: 28, left: 36 };

function useWidth() {
    const ref = useRef(null);
    const [width, setWidth] = useState(0);

    useLayoutEffect(() => {
        const element = ref.current;
        const observer = new ResizeObserver(([entry]) => setWidth(Math.floor(entry.contentRect.width)));
        observer.observe(element);
        setWidth(Math.floor(element.getBoundingClientRect().width));

        return () => observer.disconnect();
    }, []);

    return [ref, width];
}

/** Batas atas sumbu yang bulat: 5, 10, 20, 25, 50, 100, … */
function niceMax(value) {
    if (value <= 0) {
        return 10;
    }

    const steps = [5, 10, 20, 25, 50, 100];

    return steps.find((step) => step >= value) ?? Math.ceil(value / 100) * 100;
}

function ticksFor(max) {
    return [0, 0.25, 0.5, 0.75, 1].map((fraction) => Math.round(max * fraction * 10) / 10);
}

/** Batang dengan ujung data membulat 4px dan pangkal persegi. */
function barPath(x, y, width, height, radius = 4) {
    if (height <= 0) {
        return '';
    }

    const r = Math.min(radius, width / 2, height);

    return `M${x},${y + height}V${y + r}Q${x},${y} ${x + r},${y}H${x + width - r}Q${x + width},${y} ${x + width},${y + r}V${y + height}Z`;
}

function Tooltip({ tip }) {
    if (!tip) {
        return null;
    }

    return (
        <div className="chart-tip" style={{ left: tip.x, top: tip.y }} role="status">
            <b>{tip.title}</b>
            {tip.rows.map((row) => (
                <span key={row.label} className="chart-tip-row">
                    {row.color && <i style={{ background: row.color }} aria-hidden="true" />}
                    {row.label}
                    <b>{row.value}</b>
                </span>
            ))}
        </div>
    );
}

function Grid({ width, max, unit, plotHeight }) {
    return ticksFor(max).map((tick) => {
        const y = MARGIN.top + plotHeight - (tick / max) * plotHeight;

        return (
            <g key={tick}>
                <line x1={MARGIN.left} x2={width - MARGIN.right} y1={y} y2={y} className="chart-grid" />
                <text x={MARGIN.left - 6} y={y} dy="0.32em" textAnchor="end" className="chart-axis">
                    {tick}
                    {unit}
                </text>
            </g>
        );
    });
}

/**
 * Kolom satu seri. `highlight` = kunci titik yang diberi label nilai.
 */
export function ColumnChart({ data, max = 100, unit = '%', color = 'var(--color-accent)', label, highlight = [], format = (value) => `${value}${unit}`, tipRows }) {
    const [ref, width] = useWidth();
    const [tip, setTip] = useState(null);
    const plotHeight = HEIGHT - MARGIN.top - MARGIN.bottom;
    const band = data.length ? (width - MARGIN.left - MARGIN.right) / data.length : 0;
    const barWidth = Math.min(24, band * 0.6);

    return (
        <div ref={ref} className="chart" onMouseLeave={() => setTip(null)}>
            {width > 0 && (
                <svg width={width} height={HEIGHT} role="img" aria-label={label}>
                    <Grid width={width} max={max} unit={unit} plotHeight={plotHeight} />
                    {data.map((point, index) => {
                        const x = MARGIN.left + band * index;
                        const value = point.value ?? 0;
                        const barHeight = (Math.min(value, max) / max) * plotHeight;
                        const y = MARGIN.top + plotHeight - barHeight;
                        const show = () => setTip({ x: x + band / 2, y: Math.max(y - 8, 0), title: point.label, rows: tipRows ? tipRows(point) : [{ label, value: point.value === null ? '—' : format(point.value) }] });

                        return (
                            <g key={point.key}>
                                <path d={barPath(x + (band - barWidth) / 2, y, barWidth, barHeight)} fill={color} opacity={tip && tip.title !== point.label ? 0.45 : 1} />
                                {point.value !== null && highlight.includes(point.key) && (
                                    <text x={x + band / 2} y={y - 6} textAnchor="middle" className="chart-value">
                                        {format(point.value)}
                                    </text>
                                )}
                                <text x={x + band / 2} y={HEIGHT - 8} textAnchor="middle" className="chart-axis">
                                    {point.label}
                                </text>
                                <rect x={x} y={MARGIN.top} width={band} height={plotHeight} fill="transparent" onMouseEnter={show} onClick={show} />
                            </g>
                        );
                    })}
                    <line x1={MARGIN.left} x2={width - MARGIN.right} y1={MARGIN.top + plotHeight} y2={MARGIN.top + plotHeight} className="chart-baseline" />
                </svg>
            )}
            <Tooltip tip={tip} />
        </div>
    );
}

/**
 * Kolom bertumpuk: setiap seri satu segmen, celah 2px di antara segmen.
 */
export function StackedColumnChart({ data, series, unit = '%', label }) {
    const [ref, width] = useWidth();
    const [tip, setTip] = useState(null);
    const plotHeight = HEIGHT - MARGIN.top - MARGIN.bottom;
    const max = niceMax(Math.max(0, ...data.map((point) => series.reduce((sum, item) => sum + (point.values[item.key] ?? 0), 0))));
    const band = data.length ? (width - MARGIN.left - MARGIN.right) / data.length : 0;
    const barWidth = Math.min(24, band * 0.6);

    return (
        <div ref={ref} className="chart" onMouseLeave={() => setTip(null)}>
            {width > 0 && (
                <svg width={width} height={HEIGHT} role="img" aria-label={label}>
                    <Grid width={width} max={max} unit={unit} plotHeight={plotHeight} />
                    {data.map((point, index) => {
                        const x = MARGIN.left + band * index;
                        let top = MARGIN.top + plotHeight;
                        const segments = series.map((item) => {
                            const value = point.values[item.key] ?? 0;
                            const height = (value / max) * plotHeight;
                            top -= height;

                            return { ...item, value, y: top, height };
                        });
                        const visible = segments.filter((segment) => segment.height > 0);
                        const show = () =>
                            setTip({
                                x: x + band / 2,
                                y: Math.max(top - 8, 0),
                                title: point.label,
                                rows: [...segments].reverse().map((segment) => ({ label: segment.label, value: `${segment.value}${unit}`, color: segment.color })),
                            });

                        return (
                            <g key={point.key} opacity={tip && tip.title !== point.label ? 0.45 : 1}>
                                {visible.map((segment, segmentIndex) => {
                                    const isTop = segmentIndex === visible.length - 1;
                                    const gap = isTop ? 0 : 2;
                                    const height = Math.max(segment.height - gap, 0.5);
                                    const left = x + (band - barWidth) / 2;

                                    return isTop ? (
                                        <path key={segment.key} d={barPath(left, segment.y, barWidth, height)} fill={segment.color} />
                                    ) : (
                                        <rect key={segment.key} x={left} y={segment.y + gap} width={barWidth} height={height} fill={segment.color} />
                                    );
                                })}
                                <text x={x + band / 2} y={HEIGHT - 8} textAnchor="middle" className="chart-axis">
                                    {point.label}
                                </text>
                                <rect x={x} y={MARGIN.top} width={band} height={plotHeight} fill="transparent" onMouseEnter={show} onClick={show} />
                            </g>
                        );
                    })}
                    <line x1={MARGIN.left} x2={width - MARGIN.right} y1={MARGIN.top + plotHeight} y2={MARGIN.top + plotHeight} className="chart-baseline" />
                </svg>
            )}
            <Tooltip tip={tip} />
        </div>
    );
}

/**
 * Garis satu seri dengan crosshair. Titik tanpa data memutus garis.
 */
export function LineChart({ data, max = 100, unit = '%', color = 'var(--color-accent)', label, highlight = [] }) {
    const [ref, width] = useWidth();
    const [active, setActive] = useState(null);
    const plotHeight = HEIGHT - MARGIN.top - MARGIN.bottom;
    const plotWidth = width - MARGIN.left - MARGIN.right;
    const step = data.length > 1 ? plotWidth / (data.length - 1) : 0;
    const xAt = (index) => MARGIN.left + step * index;
    const yAt = (value) => MARGIN.top + plotHeight - (Math.min(value, max) / max) * plotHeight;

    const segments = [];
    let current = [];

    data.forEach((point, index) => {
        if (point.value === null) {
            if (current.length) {
                segments.push(current);
            }
            current = [];
        } else {
            current.push(`${xAt(index)},${yAt(point.value)}`);
        }
    });

    if (current.length) {
        segments.push(current);
    }

    const labelEvery = Math.max(1, Math.ceil(data.length / Math.max(1, Math.floor(plotWidth / 56))));
    const activePoint = active === null ? null : data[active];

    function track(event) {
        const box = event.currentTarget.getBoundingClientRect();
        const index = Math.round((event.clientX - box.left - MARGIN.left) / (step || 1));
        setActive(Math.min(Math.max(index, 0), data.length - 1));
    }

    return (
        <div ref={ref} className="chart" onMouseLeave={() => setActive(null)}>
            {width > 0 && (
                <svg width={width} height={HEIGHT} role="img" aria-label={label} onMouseMove={track} onClick={track}>
                    <Grid width={width} max={max} unit={unit} plotHeight={plotHeight} />
                    {segments.map((points) =>
                        points.length === 1 ? (
                            <circle key={points[0]} cx={points[0].split(',')[0]} cy={points[0].split(',')[1]} r={3} fill={color} />
                        ) : (
                            <polyline key={points[0]} points={points.join(' ')} fill="none" stroke={color} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
                        ),
                    )}
                    {data.map((point, index) => (
                        <g key={point.key}>
                            {index % labelEvery === 0 && (
                                <text x={xAt(index)} y={HEIGHT - 8} textAnchor="middle" className="chart-axis">
                                    {point.label}
                                </text>
                            )}
                            {point.value !== null && highlight.includes(point.key) && (
                                <>
                                    <circle cx={xAt(index)} cy={yAt(point.value)} r={4} fill={color} className="chart-dot" />
                                    <text x={xAt(index) + (index === data.length - 1 && data.length > 1 ? -8 : index === 0 ? 8 : 0)} y={yAt(point.value) - 10} textAnchor={index === data.length - 1 && data.length > 1 ? 'end' : index === 0 ? 'start' : 'middle'} className="chart-value">
                                        {point.value}
                                        {unit}
                                    </text>
                                </>
                            )}
                        </g>
                    ))}
                    {activePoint && (
                        <g pointerEvents="none">
                            <line x1={xAt(active)} x2={xAt(active)} y1={MARGIN.top} y2={MARGIN.top + plotHeight} className="chart-crosshair" />
                            {activePoint.value !== null && <circle cx={xAt(active)} cy={yAt(activePoint.value)} r={5} fill={color} className="chart-dot" />}
                        </g>
                    )}
                    <line x1={MARGIN.left} x2={width - MARGIN.right} y1={MARGIN.top + plotHeight} y2={MARGIN.top + plotHeight} className="chart-baseline" />
                </svg>
            )}
            <Tooltip
                tip={
                    activePoint && {
                        x: xAt(active),
                        y: activePoint.value === null ? MARGIN.top : Math.max(yAt(activePoint.value) - 12, 0),
                        title: activePoint.label,
                        rows: [{ label, value: activePoint.value === null ? 'Tidak ada catatan' : `${activePoint.value}${unit}` }],
                    }
                }
            />
        </div>
    );
}

/**
 * Legenda untuk grafik dengan dua seri atau lebih.
 */
export function ChartLegend({ series }) {
    return (
        <div className="chart-legend">
            {series.map((item) => (
                <span key={item.key}>
                    <i style={{ background: item.color }} aria-hidden="true" />
                    {item.label}
                </span>
            ))}
        </div>
    );
}
