import type { Stat } from '@/types/sentiment';

import { nf1, pct } from '@/lib/format';
import { cn } from '@/lib/utils';

export default function StatBlock({
    title,
    stat,
    className,
}: {
    title: string;
    stat: Stat;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'ak-clip-sm border-ak-line bg-ak-raised/70 border px-4 py-3.5',
                className,
            )}
        >
            <p className="ak-hud-label">{title}</p>
            <div className="mt-1.5 flex items-baseline gap-1.5">
                <span className="font-display text-foreground text-2xl font-bold">
                    {pct(stat.mean)}
                </span>
                <span className="ak-mono text-ak-faint text-[11px]">
                    ±{nf1.format(stat.std)}
                </span>
            </div>
        </div>
    );
}
