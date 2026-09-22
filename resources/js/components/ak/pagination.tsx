import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function pageWindow(page: number, lastPage: number): number[] {
    const set = new Set<number>();

    for (
        let i = Math.max(1, page - 2);
        i <= Math.min(lastPage, page + 2);
        i++
    ) {
        set.add(i);
    }

    set.add(1);
    set.add(lastPage);

    return [...set].sort((a, b) => a - b);
}

export default function Pagination({
    page,
    lastPage,
    onChange,
}: {
    page: number;
    lastPage: number;
    onChange: (page: number) => void;
}) {
    if (lastPage <= 1) {
        return null;
    }

    const items: Array<number | '…'> = [];
    let previous = 0;

    for (const pageNumber of pageWindow(page, lastPage)) {
        if (pageNumber - previous > 1) {
            items.push('…');
        }

        items.push(pageNumber);
        previous = pageNumber;
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-1.5">
            <Button
                variant="outline"
                size="icon"
                disabled={page <= 1}
                aria-label="Halaman sebelumnya"
                onClick={() => onChange(page - 1)}
            >
                <ChevronLeft className="size-4" />
            </Button>

            {items.map((item, index) =>
                item === '…' ? (
                    <span
                        key={`ellipsis-${index}`}
                        className="ak-mono text-ak-faint px-1 text-[11px]"
                    >
                        …
                    </span>
                ) : (
                    <Button
                        key={item}
                        variant="outline"
                        size="icon"
                        className={cn(
                            'ak-clip-sm',
                            item === page
                                ? 'border-ak-gold/50 bg-ak-gold/15 text-ak-gold'
                                : 'border-ak-line bg-ak-raised/60 text-ak-muted',
                        )}
                        aria-label={`Halaman ${item}`}
                        onClick={() => onChange(item)}
                    >
                        {item}
                    </Button>
                ),
            )}

            <Button
                variant="outline"
                size="icon"
                disabled={page >= lastPage}
                aria-label="Halaman berikutnya"
                onClick={() => onChange(page + 1)}
            >
                <ChevronRight className="size-4" />
            </Button>
        </div>
    );
}
