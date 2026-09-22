import { Database } from 'lucide-react';

import Panel from '@/components/ak/panel';

export default function EmptyState({
    title = 'Artefak Belum Tersedia',
}: {
    title?: string;
}) {
    return (
        <Panel title={title} code="ERR-00" className="max-w-2xl">
            <div className="flex flex-col items-start gap-4">
                <div className="ak-clip-sm border-ak-line bg-ak-raised/70 grid size-14 place-items-center border">
                    <Database className="text-ak-faint size-6" />
                </div>
                <p className="text-ak-muted max-w-md text-sm leading-relaxed">
                    Papan analisis membaca artefak hasil pipeline dari{' '}
                    <code className="ak-mono text-ak-gold">
                        storage/app/artifacts
                    </code>
                    . Berkas itu belum ditemukan, jadi tidak ada angka yang
                    ditampilkan — sistem tidak memakai perkiraan pengganti.
                </p>
                <div className="border-ak-line ak-clip-sm w-full border bg-[#0a0e14] p-4">
                    <p className="ak-hud-label mb-2">
                        Cara mengisi artefak demo
                    </p>
                    <div className="flex flex-col gap-1.5">
                        <code className="ak-mono text-foreground text-[12px]">
                            php artisan artifacts:demo
                        </code>
                        <span className="text-ak-faint text-[11px]">
                            atau jalankan pipeline Python lalu muat ulang
                            halaman.
                        </span>
                    </div>
                </div>
                <p className="ak-mono text-ak-faint text-[11px]">
                    TIP: angka muncul setelah `artifacts:demo` lalu klik 'Muat
                    Ulang'.
                </p>
            </div>
        </Panel>
    );
}
