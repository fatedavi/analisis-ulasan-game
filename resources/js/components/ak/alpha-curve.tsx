import { useState } from 'react';

import { pct } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FoldRow, KfoldSeries } from '@/types/sentiment';

type Metric = 'accuracy' | 'macro_f1';

type Point = {
    alpha: number;
    train: number;
    test: number;
};

type SeriesKey = 'train' | 'test';

const CHART = {
    width: 640,
    height: 240,
    left: 46,
    right: 52,
    top: 18,
    bottom: 34,
};

const METRICS: { value: Metric; label: string }[] = [
    { value: 'accuracy', label: 'Akurasi' },
    { value: 'macro_f1', label: 'Macro F1' },
];

function average(rows: FoldRow[], pick: (row: FoldRow) => number): number {
    if (rows.length === 0) {
        return 0;
    }

    return rows.reduce((total, row) => total + pick(row), 0) / rows.length;
}

function toPoints(series: KfoldSeries[], metric: Metric): Point[] {
    return [...series]
        .sort((a, b) => a.alpha - b.alpha)
        .map((item) => ({
            alpha: item.alpha,
            train: average(item.per_fold, (row) =>
                metric === 'accuracy'
                    ? (row.train_accuracy ?? row.accuracy)
                    : (row.train_macro_f1 ?? row.macro_f1),
            ),
            test: average(item.per_fold, (row) =>
                metric === 'accuracy' ? row.accuracy : row.macro_f1,
            ),
        }));
}

function Legend({
    label,
    swatch,
    dashed = false,
    active,
    onToggle,
}: {
    label: string;
    swatch: string;
    dashed?: boolean;
    active: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onToggle}
            className={cn(
                'ak-hud-label inline-flex cursor-pointer items-center gap-1.5 transition-opacity',
                active
                    ? 'text-ak-muted'
                    : 'text-ak-faint line-through opacity-60',
            )}
        >
            <span
                className={cn(
                    'inline-block',
                    dashed ? 'w-5 border-t-2 border-dashed' : 'size-2.5',
                    active
                        ? swatch
                        : dashed
                          ? 'border-ak-faint'
                          : 'bg-ak-faint',
                )}
            />
            {label}
        </button>
    );
}

export default function AlphaCurve({
    series,
    activeAlpha,
    bestAlpha,
}: {
    series: KfoldSeries[];
    activeAlpha: number;
    bestAlpha: number | null;
}) {
    const [metric, setMetric] = useState<Metric>('accuracy');
    const [visible, setVisible] = useState<Record<SeriesKey, boolean>>({
        train: true,
        test: true,
    });
    const points = toPoints(series, metric);
    const metricLabel =
        METRICS.find((item) => item.value === metric)?.label ?? 'Akurasi';
    const visibleKeys = (['train', 'test'] as SeriesKey[]).filter(
        (key) => visible[key],
    );
    const toggleSeries = (key: SeriesKey): void => {
        setVisible((current) => ({ ...current, [key]: !current[key] }));
    };

    if (points.length === 0) {
        return (
            <div className="border-ak-line bg-ak-raised/30 mb-4 border p-3">
                <p className="ak-mono text-ak-faint text-[11px]">
                    Belum ada data alpha untuk K ini.
                </p>
            </div>
        );
    }

    const plotWidth = CHART.width - CHART.left - CHART.right;
    const plotHeight = CHART.height - CHART.top - CHART.bottom;
    const values = points.flatMap((point) => {
        const selected: number[] = [];

        if (visible.train) {
            selected.push(point.train);
        }
        if (visible.test) {
            selected.push(point.test);
        }

        return selected;
    });
    const rawMin = values.length > 0 ? Math.min(...values) : 0;
    const rawMax = values.length > 0 ? Math.max(...values) : 1;
    const padding = Math.max(0.02, (rawMax - rawMin) * 0.25);
    const minValue = Math.max(0, rawMin - padding);
    const maxValue = Math.min(1, rawMax + padding);
    const valueRange = maxValue - minValue || 1;
    const minAlpha = points[0].alpha;
    const maxAlpha = points[points.length - 1].alpha;

    const xForAlpha = (alpha: number): number =>
        CHART.left +
        ((alpha - minAlpha) / (maxAlpha - minAlpha || 1)) * plotWidth;
    const yForValue = (value: number): number =>
        CHART.top + (1 - (value - minValue) / valueRange) * plotHeight;
    const linePoints = (key: 'train' | 'test'): string =>
        points
            .map(
                (point) => `${xForAlpha(point.alpha)},${yForValue(point[key])}`,
            )
            .join(' ');
    const yTicks = Array.from(
        { length: 5 },
        (_, index) => minValue + (valueRange * index) / 4,
    );
    const hasBestAlpha =
        bestAlpha !== null &&
        points.some((point) => Math.abs(point.alpha - bestAlpha) < 1e-9);
    const lastPoint = points[points.length - 1];

    return (
        <div className="border-ak-line bg-ak-raised/30 mb-4 border p-3">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-4">
                    <Legend
                        label="Latih"
                        swatch="bg-ak-positive"
                        active={visible.train}
                        onToggle={() => toggleSeries('train')}
                    />
                    <Legend
                        label="Uji"
                        swatch="border-ak-gold"
                        dashed
                        active={visible.test}
                        onToggle={() => toggleSeries('test')}
                    />
                    {hasBestAlpha && (
                        <span className="ak-mono text-ak-gold-deep text-[10px]">
                            ┆ alpha terbaik
                        </span>
                    )}
                </div>
                <div className="border-ak-line ak-clip-sm flex border p-0.5">
                    {METRICS.map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => setMetric(item.value)}
                            className={cn(
                                'ak-clip-sm ak-hud-label px-2.5 py-1 text-[10px] transition-colors',
                                metric === item.value
                                    ? 'bg-ak-gold/15 text-ak-gold'
                                    : 'text-ak-faint hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>

            {values.length > 0 ? (
                <svg
                    viewBox={`0 0 ${CHART.width} ${CHART.height}`}
                    className="w-full"
                    role="img"
                    aria-label={`Grafik ${metricLabel} ${visibleKeys.map((key) => (key === 'train' ? 'latih' : 'uji')).join(' dan ')} terhadap alpha`}
                >
                    {yTicks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={CHART.left}
                                x2={CHART.width - CHART.right}
                                y1={yForValue(tick)}
                                y2={yForValue(tick)}
                                stroke="var(--color-ak-line)"
                                strokeWidth={1}
                            />
                            <text
                                x={CHART.left - 8}
                                y={yForValue(tick) + 3}
                                textAnchor="end"
                                className="ak-mono"
                                fontSize={9}
                                fill="var(--color-ak-faint)"
                            >
                                {pct(tick, 0)}
                            </text>
                        </g>
                    ))}

                    {hasBestAlpha && bestAlpha !== null && (
                        <line
                            x1={xForAlpha(bestAlpha)}
                            x2={xForAlpha(bestAlpha)}
                            y1={CHART.top}
                            y2={CHART.top + plotHeight}
                            stroke="var(--color-ak-gold-deep)"
                            strokeDasharray="3 4"
                            strokeWidth={1}
                        />
                    )}
                    <line
                        x1={xForAlpha(activeAlpha)}
                        x2={xForAlpha(activeAlpha)}
                        y1={CHART.top}
                        y2={CHART.top + plotHeight}
                        stroke="var(--color-ak-muted)"
                        strokeDasharray="2 3"
                        strokeWidth={1}
                    />

                    {visible.train && (
                        <polyline
                            points={linePoints('train')}
                            fill="none"
                            stroke="var(--color-ak-positive)"
                            strokeWidth={3}
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    )}
                    {visible.test && (
                        <polyline
                            points={linePoints('test')}
                            fill="none"
                            stroke="var(--color-ak-gold)"
                            strokeWidth={3}
                            strokeDasharray="7 4"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    )}
                    {visible.train && (
                        <text
                            x={xForAlpha(lastPoint.alpha) + 8}
                            y={yForValue(lastPoint.train) - 6}
                            className="ak-hud-label"
                            fontSize={10}
                            fill="var(--color-ak-positive)"
                        >
                            Latih
                        </text>
                    )}
                    {visible.test && (
                        <text
                            x={xForAlpha(lastPoint.alpha) + 8}
                            y={yForValue(lastPoint.test) + 12}
                            className="ak-hud-label"
                            fontSize={10}
                            fill="var(--color-ak-gold)"
                        >
                            Uji
                        </text>
                    )}

                    {points.map((point) => {
                        const isActive =
                            Math.abs(point.alpha - activeAlpha) < 1e-9;

                        return (
                            <g key={point.alpha}>
                                {visible.train && (
                                    <circle
                                        cx={xForAlpha(point.alpha)}
                                        cy={yForValue(point.train)}
                                        r={isActive ? 5 : 3.5}
                                        fill="var(--color-ak-positive)"
                                        stroke="var(--color-ak-bg)"
                                        strokeWidth={1.5}
                                    >
                                        <title>{`α ${point.alpha.toFixed(2)} · Latih: ${pct(point.train, 2)}`}</title>
                                    </circle>
                                )}
                                {visible.test && (
                                    <circle
                                        cx={xForAlpha(point.alpha)}
                                        cy={yForValue(point.test)}
                                        r={isActive ? 5 : 3.5}
                                        fill="var(--color-ak-gold)"
                                        stroke="var(--color-ak-bg)"
                                        strokeWidth={1.5}
                                    >
                                        <title>{`α ${point.alpha.toFixed(2)} · Uji: ${pct(point.test, 2)}`}</title>
                                    </circle>
                                )}
                                <text
                                    x={xForAlpha(point.alpha)}
                                    y={CHART.height - 10}
                                    textAnchor="middle"
                                    className="ak-mono"
                                    fontSize={9}
                                    fill="var(--color-ak-faint)"
                                >
                                    {point.alpha.toFixed(2)}
                                </text>
                            </g>
                        );
                    })}
                </svg>
            ) : (
                <div className="border-ak-line bg-ak-base/60 flex min-h-40 items-center justify-center border px-4 text-center">
                    <p className="ak-mono text-ak-faint text-[11px]">
                        Pilih minimal satu seri untuk menampilkan grafik.
                    </p>
                </div>
            )}

            {values.length > 0 && (
                <p className="ak-mono text-ak-faint mt-2 text-right text-[10px]">
                    {points.length} nilai alpha · K = {series[0].k} · rata-rata{' '}
                    {series[0].per_fold.length} fold · {metricLabel}
                </p>
            )}
        </div>
    );
}
