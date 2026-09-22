import { Trophy } from 'lucide-react';

import ConfusionMatrix from '@/components/ak/confusion-matrix';
import EmptyState from '@/components/ak/empty-state';
import Panel from '@/components/ak/panel';
import StatBlock from '@/components/ak/stat-block';
import { pct } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Evaluation } from '@/types/sentiment';

function BestConfigStrip({ evaluation }: { evaluation: Evaluation }) {
    return (
        <div className="ak-clip-sm border-ak-gold/40 bg-ak-gold/10 flex flex-wrap items-center gap-x-6 gap-y-2 border px-5 py-3.5">
            <span className="inline-flex items-center gap-2">
                <Trophy className="text-ak-gold size-4" />
                <span className="ak-hud-label text-ak-gold">
                    Konfigurasi Terbaik
                </span>
            </span>
            <span className="ak-mono text-foreground text-sm">
                α ={' '}
                <span className="text-ak-gold">
                    {evaluation.best.alpha.toFixed(2)}
                </span>
                , K = <span className="text-ak-gold">{evaluation.best.k}</span>
            </span>
            <span className="ak-mono text-ak-faint text-[11px]">
                dipilih: macro-F1 tertinggi, lalu akurasi, deviasi terkecil
            </span>
        </div>
    );
}

function ConfigGridTable({ evaluation }: { evaluation: Evaluation }) {
    const { grid, best } = evaluation;

    return (
        <div className="ak-scroll overflow-x-auto">
            <table className="w-full min-w-[560px] border-collapse text-left">
                <thead>
                    <tr className="border-ak-line border-b">
                        <th className="ak-hud-label p-2.5 pl-0">No</th>
                        <th className="ak-hud-label p-2.5">K-Fold</th>
                        <th className="ak-hud-label p-2.5">Alpha (α)</th>
                        <th className="ak-hud-label p-2.5 text-right">
                            Macro F1
                        </th>
                        <th className="ak-hud-label p-2.5 pr-0 text-right">
                            Akurasi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {grid.map((row, index) => {
                        const isBest =
                            row.k === best.k &&
                            Math.abs(row.alpha - best.alpha) < 1e-9;

                        return (
                            <tr
                                key={`${row.k}-${row.alpha}`}
                                className={cn(
                                    'border-ak-line/60 border-b transition-colors',
                                    isBest
                                        ? 'bg-ak-gold/10'
                                        : 'hover:bg-ak-raised/50',
                                )}
                            >
                                <td className="ak-mono text-ak-faint p-2.5 pl-0 text-[12px]">
                                    {String(index + 1).padStart(2, '0')}
                                </td>
                                <td className="ak-mono text-foreground p-2.5 text-sm">
                                    {row.k}
                                </td>
                                <td className="ak-mono text-foreground p-2.5 text-sm">
                                    {row.alpha.toFixed(2)}
                                </td>
                                <td
                                    className={cn(
                                        'ak-mono p-2.5 text-right text-sm',
                                        isBest
                                            ? 'text-ak-gold font-semibold'
                                            : 'text-foreground',
                                    )}
                                >
                                    {pct(row.macro_f1, 2)}
                                </td>
                                <td className="ak-mono text-foreground p-2.5 pr-0 text-right text-sm">
                                    {pct(row.accuracy, 2)}
                                    {isBest && (
                                        <span className="ak-clip-sm bg-ak-gold ml-2 px-1.5 py-0.5 text-[9px] font-bold tracking-[0.14em] text-[#1b1408]">
                                            BEST
                                        </span>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function MetricsGrid({ evaluation }: { evaluation: Evaluation }) {
    const metrics = evaluation.metrics_mean_over_folds;
    const baseline = evaluation.baseline_majority_accuracy;

    return (
        <div className="grid grid-cols-2 gap-3 xl:grid-cols-5">
            <StatBlock title="Akurasi" stat={metrics.accuracy} />
            <StatBlock
                title="Macro F1"
                stat={metrics.macro.f1}
                className="border-ak-gold/40"
            />
            <StatBlock
                title="F1 Positif"
                stat={metrics.positive.f1}
                className="border-ak-positive/35"
            />
            <StatBlock
                title="F1 Negatif"
                stat={metrics.negative.f1}
                className="border-ak-negative/35"
            />
            <div className="ak-clip-sm border-ak-line bg-ak-raised/70 border px-4 py-3.5">
                <p className="ak-hud-label">Baseline Mayoritas</p>
                <div className="mt-1.5 flex items-baseline gap-1.5">
                    <span className="font-display text-ak-muted text-2xl font-bold">
                        {pct(baseline)}
                    </span>
                </div>
                <p className="ak-mono text-ak-faint mt-1 text-[10px]">
                    acuan akurasi tanpa model
                </p>
            </div>
        </div>
    );
}

export default function Visualisasi({
    evaluation,
}: {
    evaluation: Evaluation | null;
}) {
    if (!evaluation) {
        return <EmptyState title="Artefak Evaluasi Belum Tersedia" />;
    }

    return (
        <div className="flex flex-col gap-6">
            <MetricsGrid evaluation={evaluation} />

            <BestConfigStrip evaluation={evaluation} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                <Panel title="Confusion Matrix" code="CM-01">
                    <ConfusionMatrix confusion={evaluation.confusion_matrix} />
                    <p className="ak-mono border-ak-line text-ak-faint mt-4 border-t pt-3 text-[11px]">
                        {String(
                            evaluation.confusion_matrix.orientation,
                        ).toUpperCase()}
                    </p>
                </Panel>

                <Panel title="Rata-rata K-Fold" code="EV-01">
                    <div className="flex flex-col gap-4">
                        {(
                            [
                                {
                                    label: 'Kelas Positif',
                                    metrics:
                                        evaluation.metrics_mean_over_folds
                                            .positive,
                                    color: 'text-ak-positive',
                                },
                                {
                                    label: 'Kelas Negatif',
                                    metrics:
                                        evaluation.metrics_mean_over_folds
                                            .negative,
                                    color: 'text-ak-negative',
                                },
                            ] as const
                        ).map((section) => (
                            <div key={section.label}>
                                <p
                                    className={cn(
                                        'ak-hud-label mb-2',
                                        section.color,
                                    )}
                                >
                                    {section.label}
                                </p>
                                <div className="grid grid-cols-3 gap-3">
                                    <StatBlock
                                        title="Precision"
                                        stat={section.metrics.precision}
                                    />
                                    <StatBlock
                                        title="Recall"
                                        stat={section.metrics.recall}
                                    />
                                    <StatBlock
                                        title="F1"
                                        stat={section.metrics.f1}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </Panel>
            </div>

            <Panel title="Grid Eksperimen" code="EV-02">
                <ConfigGridTable evaluation={evaluation} />
            </Panel>
        </div>
    );
}
