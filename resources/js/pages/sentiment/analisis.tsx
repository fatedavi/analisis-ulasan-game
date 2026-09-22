import { Download, Send, Sparkles } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import DemoBadge from '@/components/ak/demo-badge';
import Donut from '@/components/ak/donut';
import EmptyState from '@/components/ak/empty-state';
import Panel from '@/components/ak/panel';
import WordCloud from '@/components/ak/word-cloud';
import { Button } from '@/components/ui/button';
import { exportDonutToPng } from '@/lib/export-donut';
import { nf, pctShare } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Summary, Words } from '@/types/sentiment';

type PredictResponse = {
    label: 'positif' | 'negatif';
    proba: { negatif: number; positif: number };
    text_final: string;
    demo: boolean;
    note: string;
};

const examples = [
    'Grafik visual mantap dan runs seru, recommended!',
    'Sangat banyak bug, sering lag dan gacha mahal',
    'Game biasa saja, lambat tapi lumayan',
];

function Legend({ summary }: { summary: Summary }) {
    const items = [
        {
            label: 'Prediksi positif',
            color: 'bg-ak-positive',
            value: summary.positive,
            share: pctShare(summary.positive, summary.total),
        },
        {
            label: 'Prediksi negatif',
            color: 'bg-ak-negative',
            value: summary.negative,
            share: pctShare(summary.negative, summary.total),
        },
    ];

    return (
        <ul className="flex flex-col gap-3">
            {items.map((item) => (
                <li key={item.label} className="flex items-center gap-3">
                    <span className={cn('size-2.5 -rotate-45', item.color)} />
                    <span className="text-ak-muted text-sm">{item.label}</span>
                    <span className="ak-mono text-foreground ml-auto text-sm">
                        {nf.format(item.value)}
                    </span>
                    <span className="ak-mono text-ak-gold w-14 text-right text-sm">
                        {item.share}
                    </span>
                </li>
            ))}
        </ul>
    );
}

function StatsRow({ summary }: { summary: Summary }) {
    const stats = [
        { label: 'Total ulasan', value: nf.format(summary.total) },
        {
            label: 'Positif',
            value: nf.format(summary.positive),
            accent: 'text-ak-positive',
        },
        {
            label: 'Negatif',
            value: nf.format(summary.negative),
            accent: 'text-ak-negative',
        },
    ];

    return (
        <div className="grid grid-cols-3 gap-3">
            {stats.map((stat) => (
                <div
                    key={stat.label}
                    className="ak-clip-sm border-ak-line bg-ak-raised/70 border px-4 py-3.5"
                >
                    <p className="ak-hud-label">{stat.label}</p>
                    <p
                        className={cn(
                            'ak-mono mt-1.5 text-2xl font-semibold',
                            stat.accent ?? 'text-foreground',
                        )}
                    >
                        {stat.value}
                    </p>
                </div>
            ))}
        </div>
    );
}

function PredictDemo() {
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<PredictResponse | null>(null);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBusy(true);
        setError(null);
        setResult(null);

        try {
            const response = await fetch('/api/predict', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ text }),
            });

            const body: PredictResponse | { message?: string; error?: string } =
                await response.json();

            if (!response.ok) {
                const maybeMessage =
                    'message' in body ? body.message : undefined;
                setError(
                    maybeMessage ?? 'Ulasan belum dapat diklasifikasikan.',
                );
                return;
            }

            setResult(body as PredictResponse);
        } catch {
            setError('Jaringan terganggu, coba lagi.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <Panel title="Uji Ulasan" code="PRD-01" right={<DemoBadge />}>
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-1.5">
                    {examples.map((example) => (
                        <button
                            key={example}
                            type="button"
                            onClick={() => setText(example)}
                            className="ak-clip-sm border-ak-line bg-ak-raised/60 text-ak-faint hover:border-ak-gold/40 hover:text-ak-muted border px-2.5 py-1 text-[11px] transition-colors"
                        >
                            {example}
                        </button>
                    ))}
                </div>

                <textarea
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    placeholder="Tulis ulasan dalam bahasa Indonesia / Inggris…"
                    rows={4}
                    maxLength={1000}
                    className="ak-clip-sm border-ak-line text-foreground placeholder:text-ak-faint focus:border-ak-gold/50 w-full border bg-[#0a0e14] px-4 py-3 text-sm outline-none"
                />

                <div className="flex items-center justify-between gap-3">
                    <p className="ak-mono text-ak-faint text-[11px]">
                        {text.length}/1000 karakter
                    </p>
                    <Button
                        type="submit"
                        disabled={busy || text.trim().length === 0}
                        className="ak-clip-sm from-ak-gold-deep to-ak-gold font-display gap-2 bg-gradient-to-br text-sm font-bold tracking-[0.14em] text-[#1b1408] uppercase hover:brightness-110 disabled:opacity-50"
                    >
                        {busy ? (
                            <Sparkles className="size-4 animate-pulse" />
                        ) : (
                            <Send className="size-4" />
                        )}
                        {busy ? 'Memproses…' : 'Klasifikasi'}
                    </Button>
                </div>
            </form>

            {error && (
                <p className="ak-mono border-ak-negative/40 bg-ak-negative/10 text-ak-negative mt-4 border px-3 py-2 text-[12px]">
                    ⚠ {error}
                </p>
            )}

            {result && (
                <div className="ak-clip-sm border-ak-line bg-ak-raised/60 mt-4 border p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <span
                            className={cn(
                                'ak-clip-sm font-display px-3 py-1 text-sm font-bold tracking-[0.2em] uppercase',
                                result.label === 'positif'
                                    ? 'border-ak-positive/50 bg-ak-positive/15 text-ak-positive border'
                                    : 'border-ak-negative/50 bg-ak-negative/15 text-ak-negative border',
                            )}
                        >
                            {result.label === 'positif' ? 'Positif' : 'Negatif'}
                        </span>
                        <span className="ak-mono text-ak-faint text-[12px]">
                            {result.text_final}
                        </span>
                    </div>

                    <div className="mt-4 space-y-2.5">
                        <div>
                            <div className="flex items-baseline justify-between">
                                <span className="ak-hud-label text-ak-positive">
                                    Positif
                                </span>
                                <span className="ak-mono text-ak-positive text-[12px]">
                                    {result.proba.positif.toFixed(3)}
                                </span>
                            </div>
                            <div className="bg-ak-line mt-1 h-1.5">
                                <div
                                    className="bg-ak-positive h-full"
                                    style={{
                                        width: `${result.proba.positif * 100}%`,
                                    }}
                                />
                            </div>
                        </div>
                        <div>
                            <div className="flex items-baseline justify-between">
                                <span className="ak-hud-label text-ak-negative">
                                    Negatif
                                </span>
                                <span className="ak-mono text-ak-negative text-[12px]">
                                    {result.proba.negatif.toFixed(3)}
                                </span>
                            </div>
                            <div className="bg-ak-line mt-1 h-1.5">
                                <div
                                    className="bg-ak-negative h-full"
                                    style={{
                                        width: `${result.proba.negatif * 100}%`,
                                    }}
                                />
                            </div>
                        </div>
                    </div>

                    {result.note && (
                        <p className="ak-mono text-ak-faint mt-4 text-[11px] leading-relaxed">
                            {result.note}
                        </p>
                    )}
                </div>
            )}
        </Panel>
    );
}

export default function AnalisisSentimen({
    summary,
    words,
}: {
    summary: Summary | null;
    words: Words | null;
}) {
    if (!summary) {
        return <EmptyState />;
    }

    const filename = `sentiment-score-${summary.run_id ?? 'arsip'}.png`;

    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                <Panel
                    title="Sentiment Score"
                    code="SC-01"
                    right={
                        <Button
                            size="sm"
                            variant="outline"
                            className="ak-clip-sm border-ak-line bg-ak-raised/60 text-ak-muted hover:border-ak-gold/50 hover:text-ak-gold"
                            onClick={() =>
                                exportDonutToPng({
                                    positive: summary.positive,
                                    negative: summary.negative,
                                    total: summary.total,
                                    score: summary.sentiment_score,
                                    filename,
                                })
                            }
                        >
                            <Download className="size-3.5" />
                            Ekspor PNG
                        </Button>
                    }
                >
                    <Donut
                        score={summary.sentiment_score}
                        positive={summary.positive}
                        negative={summary.negative}
                    />
                </Panel>

                <div className="flex flex-col gap-6">
                    <Panel title="Arus Ulasan" code="SC-02">
                        <StatsRow summary={summary} />
                    </Panel>

                    <Panel title="Distribusi Prediksi" code="SC-03">
                        <Legend summary={summary} />
                        <p className="ak-mono border-ak-line text-ak-faint mt-5 border-t pt-3 text-[11px]">
                            SUMBER {summary.source.toUpperCase()} · INVARIANT
                            POS+NEG=ARUS ·{' '}
                            {summary.generated_at
                                ? summary.generated_at
                                : 'TANPA TANGGAL'}
                        </p>
                    </Panel>
                </div>
            </div>

            <Panel title="Cloud Kata" code="WD-01">
                <WordCloud words={words} />
            </Panel>

            <PredictDemo />
        </div>
    );
}
