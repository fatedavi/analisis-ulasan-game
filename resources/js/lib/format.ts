export const nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

export const nf1 = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

export function pct(value: number, digits = 1): string {
    const asPercent = value * 100;

    return `${asPercent.toLocaleString('id-ID', { maximumFractionDigits: digits })}%`;
}

export function pctShare(part: number, total: number, digits = 1): string {
    if (total === 0) {
        return '0%';
    }

    return pct(part / total, digits);
}

export function fmtDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}
