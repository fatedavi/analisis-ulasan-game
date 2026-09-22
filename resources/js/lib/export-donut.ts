import { nf } from '@/lib/format';

type ExportDonutArgs = {
    positive: number;
    negative: number;
    total: number;
    score: number;
    filename: string;
};

function download(dataUrl: string, filename: string): void {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = filename;
    link.click();
}

/**
 * Ekspor ring sentimen sebagai PNG tanpa DOM serialization (mencegah canvas
 * tertaint oleh font eksternal) — menggambar ulang proyeksi di atas canvas.
 */
export function exportDonutToPng({
    positive,
    negative,
    total,
    score,
    filename,
}: ExportDonutArgs): void {
    const size = 720;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return;
    }

    const cx = size / 2;
    const cy = size / 2;
    const radius = size * 0.32;
    const lineWidth = size * 0.075;

    ctx.fillStyle = '#0a0e14';
    ctx.fillRect(0, 0, size, size);

    const positiveShare = total > 0 ? positive / total : 0;
    const positiveAngle = positiveShare * Math.PI * 2;

    ctx.beginPath();
    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
    ctx.strokeStyle = '#171e2a';
    ctx.lineWidth = lineWidth;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(cx, cy, radius, -Math.PI / 2, -Math.PI / 2 + positiveAngle);
    ctx.strokeStyle = '#4d9fff';
    ctx.lineWidth = lineWidth;
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(
        cx,
        cy,
        radius,
        -Math.PI / 2 + positiveAngle,
        -Math.PI / 2 + Math.PI * 2,
    );
    ctx.strokeStyle = '#ff5470';
    ctx.lineWidth = lineWidth;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#8b93a7';
    ctx.font = '700 26px "Rajdhani", sans-serif';
    ctx.fillText('SENTIMENT SCORE', cx, cy - 92);

    ctx.fillStyle = '#e8c47a';
    ctx.font = '700 132px "Rajdhani", sans-serif';
    ctx.fillText(String(score), cx, cy + 42);

    ctx.fillStyle = '#565f72';
    ctx.font = '600 30px "Rajdhani", sans-serif';
    ctx.fillText('/100', cx, cy + 84);

    ctx.font = '600 22px "Rajdhani", sans-serif';
    ctx.fillText('OUT OF 100', cx, cy + 128);

    ctx.textAlign = 'left';
    ctx.font = '600 30px "Rajdhani", sans-serif';
    ctx.fillStyle = '#4d9fff';
    ctx.fillText(`POSITIF ${nf.format(positive)}`, size * 0.16, size * 0.8);
    ctx.fillStyle = '#ff5470';
    ctx.fillText(
        `NEGATIF ${nf.format(negative)}`,
        size * 0.16,
        size * 0.8 + 44,
    );

    download(canvas.toDataURL('image/png'), filename);
}
