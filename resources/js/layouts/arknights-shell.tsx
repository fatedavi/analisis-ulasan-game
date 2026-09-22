import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    LayoutDashboard,
    Menu,
    MessageSquareText,
    RefreshCw,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

import Brand from '@/components/ak/brand';
import DemoBadge from '@/components/ak/demo-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { fmtDateTime, nf } from '@/lib/format';
import { home } from '@/routes';
import sentiment from '@/routes/sentiment';
import { cn } from '@/lib/utils';
import type { Summary, Evaluation } from '@/types/sentiment';

const nav = [
    {
        title: 'Analisis Sentimen',
        code: 'ANL-01',
        href: home().url,
        icon: LayoutDashboard,
        isActive: (url: string) => url === '/' || url.startsWith('/?'),
    },
    {
        title: 'Ulasan Pengguna',
        code: 'ULS-01',
        href: sentiment.reviews().url,
        icon: MessageSquareText,
        isActive: (url: string) => url.startsWith('/ulasan-pengguna'),
    },
    {
        title: 'Visualisasi',
        code: 'VIS-01',
        href: sentiment.visualization().url,
        icon: BarChart3,
        isActive: (url: string) => url.startsWith('/visualisasi'),
    },
];

function NavList({
    activeUrl,
    onNavigate,
}: {
    activeUrl: string;
    onNavigate?: () => void;
}) {
    return (
        <nav className="flex flex-col gap-1.5">
            <p className="ak-hud-label px-3 pb-1">Menu Operasi</p>
            {nav.map((item) => {
                const active = item.isActive(activeUrl);
                const Icon = item.icon;

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        onClick={onNavigate}
                        className={cn(
                            'group flex items-center gap-3 border-l-2 px-3 py-2.5 transition-colors',
                            active
                                ? 'border-ak-gold bg-ak-gold/10'
                                : 'hover:border-ak-line hover:bg-ak-raised/60 border-transparent',
                        )}
                    >
                        <Icon
                            className={cn(
                                'size-4 transition-colors',
                                active
                                    ? 'text-ak-gold'
                                    : 'text-ak-faint group-hover:text-ak-muted',
                            )}
                        />
                        <span
                            className={cn(
                                'font-display text-sm tracking-[0.16em] uppercase',
                                active
                                    ? 'text-ak-gold font-bold'
                                    : 'text-ak-muted font-medium',
                            )}
                        >
                            {item.title}
                        </span>
                        {active && (
                            <span className="ak-diamond ml-auto scale-75" />
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}

function StatusFooter({
    summary,
    evaluation,
}: {
    summary?: Summary | null;
    evaluation?: Evaluation | null;
}) {
    const demo = Boolean(summary?.demo || evaluation?.demo);
    const runId = summary?.run_id ?? evaluation?.run_id ?? null;
    const total = summary?.total ?? null;
    const dataUp = Boolean(summary || evaluation);

    return (
        <div className="border-ak-line border-t pt-4">
            {dataUp ? (
                <>
                    <div className="flex items-center justify-between">
                        <span className="ak-hud-label">Link Data</span>
                        <span className="inline-flex items-center gap-1.5">
                            <span className="size-1.5 animate-pulse rounded-full bg-emerald-400" />
                            <span className="ak-mono text-[10px] text-emerald-300">
                                Tersedia
                            </span>
                        </span>
                    </div>
                    <p className="ak-mono text-ak-muted mt-2 text-[11px]">
                        RUN{' '}
                        <span className="text-ak-gold">{runId ?? '———'}</span>
                    </p>
                    {total !== null && (
                        <p className="ak-mono text-ak-muted text-[11px]">
                            ARUS {nf.format(total)} review
                        </p>
                    )}
                </>
            ) : (
                <p className="ak-mono text-ak-faint text-[10px] leading-relaxed">
                    Artefak belum ditemukan. Jalankan `php artisan
                    artifacts:demo` lalu muat ulang.
                </p>
            )}
            {demo && <DemoBadge className="mt-3" />}
        </div>
    );
}

function SidebarContent({
    summary,
    evaluation,
    onNavigate,
}: {
    summary?: Summary | null;
    evaluation?: Evaluation | null;
    onNavigate?: () => void;
}) {
    const { url } = usePage();

    return (
        <div className="flex h-full flex-col gap-6 p-5">
            <Brand />
            <NavList activeUrl={url} onNavigate={onNavigate} />
            <div className="flex-1" />
            <StatusFooter summary={summary} evaluation={evaluation} />
        </div>
    );
}

function PageHeader({
    title,
    sub,
    summary,
    evaluation,
}: {
    title: string;
    sub?: string;
    summary?: Summary | null;
    evaluation?: Evaluation | null;
}) {
    const generatedAt = summary?.generated_at ?? null;
    const demo = Boolean(summary?.demo || evaluation?.demo);

    return (
        <header className="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div className="flex items-center gap-2">
                    <span className="ak-diamond" />
                    <p className="ak-hud-label text-ak-gold">
                        Dossier · Play Store
                    </p>
                </div>
                <h1 className="font-display text-foreground mt-1 text-2xl font-bold tracking-[0.08em] uppercase">
                    {title}
                </h1>
                {sub && <p className="text-ak-muted mt-0.5 text-sm">{sub}</p>}
            </div>
            <div className="flex items-center gap-2">
                {demo && <DemoBadge />}
                {generatedAt && (
                    <span className="ak-mono text-ak-faint hidden text-[11px] md:inline">
                        {fmtDateTime(generatedAt)}
                    </span>
                )}
                <Button
                    size="sm"
                    variant="outline"
                    className="ak-clip-sm border-ak-line bg-ak-raised/60 text-ak-muted hover:border-ak-gold/50 hover:text-ak-gold"
                    onClick={() => router.reload()}
                >
                    <RefreshCw className="size-3.5" />
                    Muat Ulang
                </Button>
            </div>
        </header>
    );
}

function MobileNav({
    summary,
    evaluation,
}: {
    summary?: Summary | null;
    evaluation?: Evaluation | null;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    size="icon"
                    variant="outline"
                    className="ak-clip-sm border-ak-line bg-ak-raised/60 text-ak-gold"
                    aria-label="Buka menu"
                >
                    <Menu className="size-4" />
                </Button>
            </SheetTrigger>
            <SheetContent
                side="left"
                className="border-ak-line w-[280px] bg-[#0b0f17] p-0"
            >
                <SheetHeader className="hidden">
                    <SheetTitle />
                </SheetHeader>
                <SidebarContent
                    summary={summary}
                    evaluation={evaluation}
                    onNavigate={() => setOpen(false)}
                />
            </SheetContent>
        </Sheet>
    );
}

export default function ArknightsShell({
    children,
    summary,
    evaluation,
    title,
}: {
    children: ReactNode;
    summary?: Summary | null;
    evaluation?: Evaluation | null;
    title?: string;
}) {
    const { url } = usePage();
    const current = nav.find((item) => item.isActive(url));
    const pageTitle = title ?? current?.title ?? 'Analisis Sentimen';

    return (
        <div className="ak-backdrop text-foreground min-h-dvh font-sans">
            <div className="lg:grid lg:grid-cols-[252px_1fr]">
                <aside className="border-ak-line sticky top-0 hidden h-dvh border-r bg-[#0b0f17]/95 backdrop-blur lg:block">
                    <SidebarContent summary={summary} evaluation={evaluation} />
                </aside>

                <div className="min-w-0">
                    <div className="border-ak-line flex items-center justify-between gap-3 border-b bg-[#0b0f17]/95 px-4 py-3 backdrop-blur lg:hidden">
                        <Brand compact />
                        <MobileNav summary={summary} evaluation={evaluation} />
                    </div>

                    <main className="ak-scroll mx-auto w-full max-w-[1180px] px-4 py-8 sm:px-8">
                        <PageHeader
                            title={pageTitle}
                            summary={summary}
                            evaluation={evaluation}
                        />
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
