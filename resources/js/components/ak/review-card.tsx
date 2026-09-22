import { pct } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ReviewRow } from '@/types/sentiment';

import StarRating from '@/components/ak/star-rating';

function fmtDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    const parsed = new Date(date.replace(' ', 'T'));

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
}

export default function ReviewCard({ review }: { review: ReviewRow }) {
    const positive = review.pred_label === 'positif';
    const confidence = positive ? review.proba_pos : 1 - review.proba_pos;

    return (
        <article className="ak-clip-sm border-ak-line bg-ak-raised/50 border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-3">
                    <StarRating value={review.rating} />
                    <span className="text-ak-muted text-sm">
                        {review.username}
                    </span>
                </div>
                <span className="ak-mono text-ak-faint text-[11px]">
                    {fmtDate(review.date)}
                </span>
            </div>

            <p className="text-foreground mt-3 text-sm leading-relaxed whitespace-pre-line">
                {review.review}
            </p>

            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span
                        className={cn(
                            'ak-clip-sm font-display px-2.5 py-1 text-[11px] font-bold tracking-[0.2em] uppercase',
                            positive
                                ? 'border-ak-positive/50 bg-ak-positive/15 text-ak-positive border'
                                : 'border-ak-negative/50 bg-ak-negative/15 text-ak-negative border',
                        )}
                    >
                        {review.pred_label}
                    </span>
                    <span className="ak-mono text-ak-faint text-[11px]">
                        keyakinan {pct(confidence, 0)}
                    </span>
                </div>
                <span className="ak-mono text-ak-faint text-[10px]">
                    {review.review_id} · FOLD {review.fold}
                </span>
            </div>
        </article>
    );
}
