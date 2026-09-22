export default function Brand({ compact = false }: { compact?: boolean }) {
    return (
        <div className="flex items-center gap-3">
            <div className="relative grid size-10 shrink-0 place-items-center">
                <div className="ak-clip-sm from-ak-gold-deep via-ak-gold to-ak-gold-deep absolute inset-0 bg-gradient-to-br" />
                <span className="ak-clip-sm bg-ak-panel absolute inset-[3px]" />
                <span className="ak-diamond" />
                <span className="text-ak-gold absolute right-[7px] bottom-[7px] text-[8px] leading-none font-bold">
                    A
                </span>
            </div>
            {!compact && (
                <div className="leading-tight">
                    <p className="font-display text-ak-gold ak-gold-glow text-base font-bold tracking-[0.18em] uppercase">
                        OPS Sentimen
                    </p>
                    <p className="text-ak-faint text-[10px] font-medium tracking-[0.22em] uppercase">
                        Arknights · Endfield
                    </p>
                </div>
            )}
        </div>
    );
}
