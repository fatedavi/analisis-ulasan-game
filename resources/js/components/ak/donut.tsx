import { cn } from '@/lib/utils';

type DonutProps = {
    score: number;
    positive: number;
    negative: number;
    className?: string;
};

export default function Donut({
    score,
    positive,
    negative,
    className,
}: DonutProps) {
    const total = positive + negative;
    const posPct = total > 0 ? (positive / total) * 100 : 0;

    return (
        <div className={cn('grid place-items-center', className)}>
            <div className="relative size-64 sm:size-72">
                <div className="border-ak-gold/25 absolute -inset-3 animate-[spin_30s_linear_infinite] rounded-full border border-dashed" />
                <div className="border-ak-line absolute -inset-1.5 rounded-full border" />

                <div
                    className="absolute inset-0 rounded-full"
                    style={{
                        background: `conic-gradient(var(--color-ak-positive) ${posPct}%, var(--color-ak-negative) ${posPct}% 100%)`,
                    }}
                />

                <div className="border-ak-line bg-ak-panel absolute inset-[13%] rounded-full border" />

                <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <p className="ak-hud-label">Sentiment Score</p>
                    <p className="ak-mono text-ak-gold ak-gold-glow mt-1 text-[54px] leading-none font-semibold">
                        {score}
                    </p>
                    <p className="ak-hud-label mt-2">out of 100</p>
                </div>
            </div>
        </div>
    );
}
