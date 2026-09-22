import { AlertTriangle } from 'lucide-react';

import { cn } from '@/lib/utils';

export default function DemoBadge({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'border-ak-gold/40 bg-ak-gold/10 inline-flex items-center gap-1.5 border px-2.5 py-1',
                'ak-clip-sm font-display text-ak-gold text-[10px] font-bold tracking-[0.24em] uppercase',
                className,
            )}
        >
            <AlertTriangle className="size-3" />
            Data Demo
        </span>
    );
}
