import {
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type CSSProperties,
} from 'react';

import { cn } from '@/lib/utils';
import type { CloudWord, Words } from '@/types/sentiment';

type CloudFilter = 'semua' | 'positif' | 'negatif';

const FILTERS: { value: CloudFilter; label: string }[] = [
    { value: 'semua', label: 'Semua' },
    { value: 'positif', label: 'Positif' },
    { value: 'negatif', label: 'Negatif' },
];

const CLOUD_HEIGHT = 380;
const MAX_WORDS = 55;
const BOX_PAD_X = 8;
const OVERLAP_GAP = 12;

function hashString(input: string): number {
    let hash = 2166136261;

    for (let i = 0; i < input.length; i++) {
        hash ^= input.charCodeAt(i);
        hash = Math.imul(hash, 16777619);
    }

    return hash >>> 0;
}

function pick(seed: number, salt: number, min: number, max: number): number {
    const unit = (seed >>> (salt % 16)) / 0xffff;

    return min + (max - min) * (unit - Math.floor(unit));
}

type Box = { x: number; y: number; w: number; h: number; size: number };

function overlaps(a: Box, b: Box): boolean {
    return (
        a.x < b.x + b.w + OVERLAP_GAP &&
        a.x + a.w + OVERLAP_GAP > b.x &&
        a.y < b.y + b.h + OVERLAP_GAP &&
        a.y + a.h + OVERLAP_GAP > b.y
    );
}

function sizeFor(
    word: CloudWord,
    maxCount: number,
    width: number,
    len: number,
): number {
    const scale = Math.sqrt(word.count / maxCount);
    const size = Math.round(14 + scale * 34);
    const maxByWidth = Math.floor((width - 20) / (len * 0.55 + 1.0));

    return Math.min(size, maxByWidth, 48);
}

function dimensions(word: CloudWord, size: number): { w: number; h: number } {
    const w = size * (word.token.length * 0.55 + 1.0);

    return { w: Math.ceil(w), h: Math.ceil(size * 1.35) };
}

function fits(box: Box, width: number): boolean {
    const margin = BOX_PAD_X;

    if (
        box.x < margin ||
        box.y < 12 ||
        box.x + box.w > width - margin ||
        box.y + box.h > CLOUD_HEIGHT - 12
    ) {
        return false;
    }

    return true;
}

/**
 * Penempatan bebas-tumpang-tindih: spiral dari pusat, lalu grid-scan sebagai
 * fallback. Seluruh koordinat berbasis px agar presisi responsif.
 */
function layout(words: CloudWord[], width: number): Map<string, Box> {
    const placed: Box[] = [];
    const boxes = new Map<string, Box>();
    const maxCount = Math.max(...words.map((word) => word.count), 1);
    const cx = width / 2;
    const cy = CLOUD_HEIGHT / 2;
    const golden = 2.399963229728653;
    const maxRadius = Math.hypot(width, CLOUD_HEIGHT) + 40;

    const tryPlace = (
        x: number,
        y: number,
        w: number,
        h: number,
        size: number,
    ): boolean => {
        const box = { x, y, w, h, size };

        if (!fits(box, width)) {
            return false;
        }

        for (const other of placed) {
            if (overlaps(box, other)) {
                return false;
            }
        }

        placed.push(box);

        return true;
    };

    for (const word of words) {
        const size = sizeFor(word, maxCount, width, word.token.length);
        const { w, h } = dimensions(word, size);

        let placedBox: Box | null = null;

        for (let i = 0; i < 3000; i++) {
            const radius = 8 * Math.sqrt(i);

            if (radius > maxRadius) {
                break;
            }

            const x = cx + radius * Math.cos(i * golden) - w / 2;
            const y = cy + radius * 0.62 * Math.sin(i * golden) - h / 2;

            if (tryPlace(x, y, w, h, size)) {
                placedBox = { x, y, w, h, size };
                break;
            }
        }

        if (placedBox === null) {
            for (let gy = 14; gy + h <= CLOUD_HEIGHT - 10; gy += 4) {
                for (let gx = BOX_PAD_X; gx + w <= width - BOX_PAD_X; gx += 4) {
                    if (tryPlace(gx, gy, w, h, size)) {
                        placedBox = { x: gx, y: gy, w, h, size };
                        break;
                    }
                }

                if (placedBox !== null) {
                    break;
                }
            }
        }

        if (placedBox !== null) {
            boxes.set(word.token + word.label, placedBox);
        }
    }

    return boxes;
}

export default function WordCloud({ words }: { words: Words | null }) {
    const [filter, setFilter] = useState<CloudFilter>('semua');
    const [width, setWidth] = useState(640);
    const containerRef = useRef<HTMLDivElement>(null);
    const filterId = useId();

    useEffect(() => {
        const element = containerRef.current;

        if (!element) {
            return;
        }

        const update = () =>
            setWidth(element.getBoundingClientRect().width || 640);
        update();

        const observer = new ResizeObserver(update);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const filtered = useMemo(() => {
        if (!words) return [];
        if (filter === 'semua') return words.words;
        return words.words.filter((word) => word.label === filter);
    }, [words, filter]);

    const shown = filtered.slice(0, MAX_WORDS);

    const boxes = useMemo(() => layout(shown, width), [shown, width]);

    if (!words) {
        return (
            <p className="ak-mono text-ak-faint text-[12px]">
                Data kata belum tersedia. Jalankan{' '}
                <code>php artisan artifacts:import</code> untuk membangkitkan
                words.json.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <div role="tablist" aria-label="Filter kata" className="flex gap-2">
                {FILTERS.map((tab) => (
                    <button
                        key={tab.value}
                        role="tab"
                        aria-selected={filter === tab.value}
                        aria-controls={`${filterId}-cloud`}
                        id={`${filterId}-tab-${tab.value}`}
                        type="button"
                        onClick={() => setFilter(tab.value)}
                        className={cn(
                            'ak-clip-sm font-display px-3.5 py-1.5 text-[11px] font-bold tracking-[0.18em] uppercase transition-colors',
                            filter === tab.value
                                ? 'border-ak-gold/50 bg-ak-gold/15 text-ak-gold border'
                                : 'border-ak-line text-ak-faint hover:text-ak-muted border',
                        )}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            <div
                id={`${filterId}-cloud`}
                role="tabpanel"
                aria-labelledby={`${filterId}-tab-${filter}`}
                ref={containerRef}
                className="ak-corner group border-ak-line bg-ak-bg/60 relative h-[380px] overflow-hidden border"
            >
                {shown.map((word) => {
                    const box = boxes.get(word.token + word.label);

                    if (!box) return null;

                    const seed = hashString(
                        `${words.run_id}:${word.token}:${word.label}`,
                    );
                    const duration = (5 + pick(seed, 4, 0, 1) * 9).toFixed(2);
                    const delay = (pick(seed, 5, 0, 1) * 5).toFixed(2);
                    const dx = `${(3 + pick(seed, 6, 0, 1) * 9).toFixed(1)}px`;
                    const dy = `${(-3 - pick(seed, 7, 0, 1) * 9).toFixed(1)}px`;
                    const opacity = 0.5 + pick(seed, 8, 0, 1) * 0.5;

                    const style = {
                        '--x': `${box.x}px`,
                        '--y': `${box.y}px`,
                        '--rot': '0deg',
                        '--dur': `${duration}s`,
                        '--delay': `${delay}s`,
                        '--dx': dx,
                        '--dy': dy,
                        '--opacity': String(opacity),
                        fontSize: box.size,
                    } as unknown as CSSProperties;

                    return (
                        <span
                            key={`${word.token}-${word.label}`}
                            style={style}
                            title={`${word.token} · ${word.count}×`}
                            className={cn(
                                'ak-cloud-word cursor-default font-semibold whitespace-nowrap',
                                word.label === 'positif'
                                    ? 'text-ak-positive'
                                    : 'text-ak-negative',
                            )}
                        >
                            {word.token}
                        </span>
                    );
                })}

                <div className="ak-hud-label text-ak-faint pointer-events-none absolute right-3 bottom-3">
                    {filtered.length} TERM · {words.run_id.toUpperCase()}
                </div>
            </div>

            <p className="ak-mono border-ak-line text-ak-faint border-t pt-3 text-[11px]">
                FREKUENSI KATA DARI {words.total_docs.toLocaleString('id-ID')}{' '}
                ULASAN · {words.total_tokens.toLocaleString('id-ID')} TOKEN ·
                STOPWORD DIBUANG
            </p>
        </div>
    );
}
