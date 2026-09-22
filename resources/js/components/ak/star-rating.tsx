import { Star } from 'lucide-react';

import { cn } from '@/lib/utils';

export default function StarRating({
    value,
    className,
    size = 'size-4',
}: {
    value: number;
    className?: string;
    size?: 'size-3' | 'size-3.5' | 'size-4' | 'size-5';
}) {
    const stars = Math.max(0, Math.min(5, Math.round(value)));

    return (
        <div
            className={cn('flex items-center gap-0.5', className)}
            aria-label={`${stars} dari 5 bintang`}
        >
            {Array.from({ length: 5 }, (_, index) => (
                <Star
                    key={index}
                    className={cn(
                        size,
                        index < stars ? 'text-ak-gold' : 'text-ak-line',
                    )}
                    fill="currentColor"
                    strokeWidth={0}
                />
            ))}
        </div>
    );
}
