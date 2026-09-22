import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import EmptyState from '@/components/ak/empty-state';
import Pagination from '@/components/ak/pagination';
import Panel from '@/components/ak/panel';
import ReviewCard from '@/components/ak/review-card';
import { nf } from '@/lib/format';
import { cn } from '@/lib/utils';
import sentiment from '@/routes/sentiment';
import type { ReviewsResponse, Summary } from '@/types/sentiment';

const TABS = [
    { value: null, label: 'Semua' },
    { value: 'positif', label: 'Positif' },
    { value: 'negatif', label: 'Negatif' },
] as const;

function navigate(
    params: Partial<{ label: string; rating: string; q: string; page: number }>,
): void {
    router.get(sentiment.reviews().url, params, {
        only: ['reviews'],
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

function KpiStrip({ reviews }: { reviews: ReviewsResponse }) {
    const items = [
        { label: 'Total ulasan', value: nf.format(reviews.total) },
        {
            label: 'Positif',
            value: nf.format(reviews.positif),
            accent: 'text-ak-positive',
        },
        {
            label: 'Negatif',
            value: nf.format(reviews.negatif),
            accent: 'text-ak-negative',
        },
    ];

    return (
        <div className="grid grid-cols-3 gap-3">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="ak-clip-sm border-ak-line bg-ak-raised/70 border px-4 py-3.5"
                >
                    <p className="ak-hud-label">{item.label}</p>
                    <p
                        className={cn(
                            'ak-mono mt-1.5 text-2xl font-semibold',
                            item.accent ?? 'text-foreground',
                        )}
                    >
                        {item.value}
                    </p>
                </div>
            ))}
        </div>
    );
}

function FilterBar({ reviews }: { reviews: ReviewsResponse }) {
    const rating = reviews.rating;
    const activeLabel = reviews.label;
    const [qInput, setQInput] = useState(reviews.q ?? '');

    useEffect(() => {
        setQInput(reviews.q ?? '');
    }, [reviews.q]);

    useEffect(() => {
        const trimmed = qInput.trim();
        const serverValue = (reviews.q ?? '').trim();

        if (trimmed === serverValue) {
            return;
        }

        const handle = setTimeout(() => {
            navigate({
                label: activeLabel ?? 'all',
                rating: rating === null ? 'all' : String(rating),
                q: trimmed || undefined,
                page: 1,
            });
        }, 400);

        return () => clearTimeout(handle);
    }, [qInput]);

    const starCounts = (value: number) =>
        reviews.rating_distribution[value] ?? 0;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2">
                {TABS.map((tab) => {
                    const active = activeLabel === tab.value;
                    const count =
                        tab.value === 'positif'
                            ? reviews.positif
                            : tab.value === 'negatif'
                              ? reviews.negatif
                              : reviews.total;

                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() =>
                                navigate({
                                    label: tab.value ?? 'all',
                                    rating:
                                        rating === null
                                            ? 'all'
                                            : String(rating),
                                    q: qInput.trim() || undefined,
                                    page: 1,
                                })
                            }
                            className={cn(
                                'ak-clip-sm font-display px-3 py-1.5 text-[11px] font-bold tracking-[0.14em] uppercase transition-colors',
                                active
                                    ? 'border-ak-gold/50 bg-ak-gold/15 text-ak-gold border'
                                    : 'border-ak-line bg-ak-raised/60 text-ak-faint hover:border-ak-gold/40 hover:text-ak-muted border',
                            )}
                        >
                            {tab.label} · {nf.format(count)}
                        </button>
                    );
                })}
            </div>

            <div className="flex flex-wrap items-center gap-1.5">
                <span className="ak-hud-label mr-1">Bintang</span>
                <button
                    type="button"
                    onClick={() =>
                        navigate({
                            label: activeLabel ?? 'all',
                            rating: 'all',
                            q: qInput.trim() || undefined,
                            page: 1,
                        })
                    }
                    className={cn(
                        'ak-clip-sm border px-2.5 py-1 text-[11px] transition-colors',
                        rating === null
                            ? 'border-ak-gold/50 bg-ak-gold/15 text-ak-gold'
                            : 'border-ak-line bg-ak-raised/60 text-ak-faint hover:border-ak-gold/40 hover:text-ak-muted border',
                    )}
                >
                    Semua
                </button>
                {[5, 4, 3, 2, 1].map((value) => {
                    const active = rating === value;

                    return (
                        <button
                            key={value}
                            type="button"
                            onClick={() =>
                                navigate({
                                    label: activeLabel ?? 'all',
                                    rating: String(value),
                                    q: qInput.trim() || undefined,
                                    page: 1,
                                })
                            }
                            className={cn(
                                'ak-clip-sm border px-2.5 py-1 text-[11px] transition-colors',
                                active
                                    ? 'border-ak-gold/50 bg-ak-gold/15 text-ak-gold'
                                    : 'border-ak-line bg-ak-raised/60 text-ak-faint hover:border-ak-gold/40 hover:text-ak-muted border',
                            )}
                        >
                            {value}★ · {nf.format(starCounts(value))}
                        </button>
                    );
                })}
            </div>

            <div className="relative">
                <Search className="text-ak-faint pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <input
                    type="search"
                    value={qInput}
                    onChange={(event) => setQInput(event.target.value)}
                    placeholder="Cari isi komentar…"
                    className="ak-clip-sm border-ak-line text-foreground placeholder:text-ak-faint focus:border-ak-gold/50 w-full border bg-[#0a0e14] py-2 pr-9 pl-9 text-sm outline-none"
                />
                {qInput.length > 0 && (
                    <button
                        type="button"
                        aria-label="Bersihkan pencarian"
                        onClick={() => setQInput('')}
                        className="text-ak-faint hover:text-ak-muted absolute top-1/2 right-3 -translate-y-1/2"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>

            <p className="ak-mono border-ak-line text-ak-faint border-t pt-3 text-[11px]">
                MENAMPILKAN{' '}
                <span className="text-ak-gold">
                    {reviews.total === 0
                        ? 0
                        : (reviews.page - 1) * reviews.per_page + 1}
                    –{Math.min(reviews.page * reviews.per_page, reviews.total)}
                </span>{' '}
                DARI {nf.format(reviews.total)} ULASAN
            </p>
        </div>
    );
}

export default function UlasanPengguna({
    summary,
    reviews,
}: {
    summary: Summary | null;
    reviews: ReviewsResponse | null;
}) {
    if (!reviews) {
        return <EmptyState title="Daftar Ulasan Belum Tersedia" />;
    }

    return (
        <div className="flex flex-col gap-6">
            <KpiStrip reviews={reviews} />

            <Panel
                title="Ulasan Pengguna"
                code="ULS-01"
                right={
                    <span className="ak-hud-label text-ak-gold">
                        RUN {reviews.run_id ?? '———'}
                    </span>
                }
            >
                <div className="flex flex-col gap-6">
                    <FilterBar reviews={reviews} />

                    {reviews.data.length === 0 ? (
                        <div className="ak-clip-sm border-ak-line bg-ak-raised/40 border p-6 text-center">
                            <p className="text-ak-muted text-sm">
                                Tak ada ulasan yang cocok dengan filter yang
                                dipilih.
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="flex flex-col gap-3">
                                {reviews.data.map((review) => (
                                    <ReviewCard
                                        key={review.review_id}
                                        review={review}
                                    />
                                ))}
                            </div>

                            <Pagination
                                page={reviews.page}
                                lastPage={reviews.last_page}
                                onChange={(page) =>
                                    navigate({
                                        label: reviews.label ?? 'all',
                                        rating:
                                            reviews.rating === null
                                                ? 'all'
                                                : String(reviews.rating),
                                        q: reviews.q ?? undefined,
                                        page,
                                    })
                                }
                            />
                        </>
                    )}

                    <p className="ak-mono border-ak-line text-ak-faint border-t pt-3 text-[11px]">
                        SUMBER REVIEWS.JSON · LABEL & PROBA DARI OOF ·{' '}
                        {summary?.generated_at
                            ? summary.generated_at
                            : 'TANPA TANGGAL'}
                    </p>
                </div>
            </Panel>
        </div>
    );
}
