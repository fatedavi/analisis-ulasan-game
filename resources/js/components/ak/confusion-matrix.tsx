import type { Evaluation } from '@/types/sentiment';

import { nf } from '@/lib/format';
import { cn } from '@/lib/utils';

type ConfusionMatrixProps = {
    confusion: Evaluation['confusion_matrix'];
};

const corner = 'grid place-items-center px-2 py-1';

function Cell({
    value,
    label,
    tone,
}: {
    value: number;
    label: string;
    tone: 'tp' | 'tn' | 'fp' | 'fn';
}) {
    const tones: Record<typeof tone, string> = {
        tp: 'border-ak-positive/40 bg-ak-positive/15',
        tn: 'border-ak-line bg-ak-panel',
        fp: 'border-ak-negative/40 bg-ak-negative/15',
        fn: 'border-ak-gold-deep/40 bg-ak-gold/10',
    };

    return (
        <div
            className={cn(
                'ak-clip-sm grid place-items-center gap-0.5 border py-7',
                tones[tone],
            )}
        >
            <span className="ak-mono text-foreground text-3xl font-semibold">
                {nf.format(value)}
            </span>
            <span className="ak-hud-label !tracking-[0.18em]">{label}</span>
        </div>
    );
}

export default function ConfusionMatrix({ confusion }: ConfusionMatrixProps) {
    const { tn, fp, fn, tp } = confusion;

    return (
        <div className="overflow-hidden">
            <div className="grid grid-cols-[auto_1fr_1fr] items-stretch gap-2">
                <div className={corner} />
                <p className="ak-hud-label grid place-items-center pb-1 text-center">
                    Terkirim · positif
                </p>
                <p className="ak-hud-label grid place-items-center pb-1 text-center">
                    Terkirim · negatif
                </p>

                <div className="flex flex-col items-center justify-center gap-1 pr-2">
                    <p className="ak-hud-label -rotate-90 whitespace-nowrap">
                        Aktual positif
                    </p>
                </div>
                <Cell value={tp} label="TP · benar" tone="tp" />
                <Cell value={fn} label="FN · salah" tone="fn" />

                <div className="flex flex-col items-center justify-center gap-1 pr-2">
                    <p className="ak-hud-label -rotate-90 whitespace-nowrap">
                        Aktual negatif
                    </p>
                </div>
                <Cell value={fp} label="FP · salah" tone="fp" />
                <Cell value={tn} label="TN · benar" tone="tn" />
            </div>
            <p className="ak-mono text-ak-faint mt-3 text-right text-[10px]">
                Σ = {nf.format(tn + fp + fn + tp)} ulasan
            </p>
        </div>
    );
}
