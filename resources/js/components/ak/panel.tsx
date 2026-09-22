import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export default function Panel({
    title,
    code,
    right,
    className,
    children,
}: {
    title?: string;
    code?: string;
    right?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section className={cn('ak-panel ak-clip ak-corner', className)}>
            {(title || right) && (
                <header className="border-ak-line flex items-center justify-between gap-3 border-b px-5 py-3">
                    <div className="flex items-baseline gap-2.5">
                        {code && (
                            <span className="ak-mono text-ak-gold-deep text-[10px]">
                                {code}
                            </span>
                        )}
                        {title && (
                            <h2 className="font-display text-ak-muted text-sm font-semibold tracking-[0.2em] uppercase">
                                {title}
                            </h2>
                        )}
                    </div>
                    {right && (
                        <div className="flex items-center gap-2">{right}</div>
                    )}
                </header>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}
