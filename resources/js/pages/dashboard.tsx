import { Head } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    CalendarCheck,
    CalendarClock,
    CalendarPlus,
    CheckCircle2,
    CircleCheck,
    Clock,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dashboard } from '@/routes';

const CHART_COLORS = [
    '#1B2B5E',
    '#4A6BD8',
    '#7C97E8',
    '#A9BEF0',
    '#64748B',
    '#CBD5E1',
];

const kpis: {
    label: string;
    value: string;
    trend: string;
    up: boolean | null;
    icon: LucideIcon;
    iconClass: string;
}[] = [
    {
        label: 'Total Karyawan',
        value: '1.248',
        trend: '3,2%',
        up: true,
        icon: Users,
        iconClass: 'bg-[#EAF0FC] text-primary',
    },
    {
        label: 'Total Biaya Payroll',
        value: 'Rp 2,45 M',
        trend: '8,6%',
        up: true,
        icon: Wallet,
        iconClass: 'bg-[#E7ECF7] text-brand-navy',
    },
    {
        label: 'Kehadiran Rata-rata',
        value: '91,4%',
        trend: '2,1%',
        up: false,
        icon: CircleCheck,
        iconClass: 'bg-[#FEF3C7] text-[#F59E0B]',
    },
    {
        label: 'Kontrak Akan Berakhir',
        value: '18',
        trend: 'Perlu tindakan',
        up: null,
        icon: CalendarClock,
        iconClass: 'bg-[#FEE2E2] text-destructive',
    },
];

const payroll = [
    { m: 'Feb', v: 2.1 },
    { m: 'Mar', v: 2.16 },
    { m: 'Apr', v: 2.23 },
    { m: 'Mei', v: 2.29 },
    { m: 'Jun', v: 2.38 },
    { m: 'Jul', v: 2.45 },
];

const departments = [
    { name: 'Operasional', pct: 36.5 },
    { name: 'Sales & Marketing', pct: 25 },
    { name: 'Finance', pct: 14.3 },
    { name: 'HR & GA', pct: 12.5 },
    { name: 'IT', pct: 7.8 },
    { name: 'Lainnya', pct: 3.9 },
];

const quickAccess: { name: string; icon: LucideIcon; className: string }[] = [
    {
        name: 'Tambah Karyawan',
        icon: UserPlus,
        className: 'bg-[#EAF0FC] text-primary',
    },
    {
        name: 'Proses Payroll',
        icon: Wallet,
        className: 'bg-[#DCFCE7] text-[#16A34A]',
    },
    {
        name: 'Approval',
        icon: CheckCircle2,
        className: 'bg-[#E7ECF7] text-brand-navy',
    },
    {
        name: 'Pengajuan Cuti',
        icon: CalendarPlus,
        className: 'bg-[#FEF3C7] text-[#F59E0B]',
    },
    {
        name: 'Jadwal Shift',
        icon: Clock,
        className: 'bg-[#EAF0FC] text-primary',
    },
    {
        name: 'Laporan',
        icon: BarChart3,
        className: 'bg-[#E7ECF7] text-brand-navy',
    },
];

const contracts = [
    {
        name: 'Rina Anggraini',
        role: 'Staff Operasional',
        date: '21 Jul 2026',
        remaining: '12 hari lagi',
        initials: 'RA',
    },
    {
        name: 'Budi Santoso',
        role: 'Sales Executive',
        date: '25 Jul 2026',
        remaining: '16 hari lagi',
        initials: 'BS',
    },
    {
        name: 'Dewi Sartika',
        role: 'Finance Analyst',
        date: '28 Jul 2026',
        remaining: '19 hari lagi',
        initials: 'DS',
    },
    {
        name: 'Andi Pratama',
        role: 'IT Support',
        date: '03 Agu 2026',
        remaining: '25 hari lagi',
        initials: 'AP',
    },
];

const approvals: {
    name: string;
    type: string;
    detail: string;
    icon: LucideIcon;
}[] = [
    {
        name: 'Rina Anggraini',
        type: 'Cuti Tahunan',
        detail: '2 hari',
        icon: CalendarCheck,
    },
    { name: 'Budi Santoso', type: 'Lembur', detail: '4 jam', icon: Clock },
    {
        name: 'Dewi Sartika',
        type: 'Koreksi Absen',
        detail: '08 Jul',
        icon: CheckCircle2,
    },
];

const attendance = [
    { name: 'Hadir', pct: 87.6, count: '1.094', color: '#16A34A' },
    { name: 'Terlambat', pct: 7.2, count: '90', color: '#F59E0B' },
    { name: 'Tidak Hadir', pct: 5.2, count: '64', color: '#DC2626' },
];

// Ring geometry precomputed at module scope (avoids mutable accumulators in render).
const RING_R = 64;
const RING_C = 2 * Math.PI * RING_R;
const GAUGE_GAP = 3;

const cumulativeOffset = (items: { pct: number }[], index: number): number =>
    items.slice(0, index).reduce((sum, x) => sum + (x.pct / 100) * RING_C, 0);

const donutSegments = departments.map((d, i) => ({
    ...d,
    color: CHART_COLORS[i],
    length: (d.pct / 100) * RING_C,
    offset: -cumulativeOffset(departments, i),
}));

const gaugeSegments = attendance.map((a, i) => ({
    ...a,
    draw: Math.max((a.pct / 100) * RING_C - GAUGE_GAP, 0),
    offset: -cumulativeOffset(attendance, i),
}));

function Card({
    children,
    className = '',
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`rounded-xl border border-border bg-card p-5 shadow-[0_1px_2px_rgba(27,43,94,0.04)] ${className}`}
        >
            {children}
        </div>
    );
}

function PayrollLineChart() {
    const W = 560;
    const H = 240;
    const padL = 46;
    const padR = 16;
    const padT = 18;
    const padB = 34;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;
    const yMin = 2.0;
    const yMax = 2.5;
    const yFor = (v: number) => padT + (1 - (v - yMin) / (yMax - yMin)) * plotH;
    const pts = payroll.map((p, i) => ({
        cx: padL + (i / (payroll.length - 1)) * plotW,
        cy: yFor(p.v),
        m: p.m,
    }));
    const line = pts
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.cx} ${p.cy}`)
        .join(' ');
    const area = `${line} L${pts[pts.length - 1].cx} ${H - padB} L${pts[0].cx} ${H - padB} Z`;
    const ticks = [2.0, 2.1, 2.2, 2.3, 2.4, 2.5];

    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="mt-2 block h-auto w-full">
            <defs>
                <linearGradient id="plArea" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#4A6BD8" stopOpacity="0.22" />
                    <stop offset="1" stopColor="#4A6BD8" stopOpacity="0" />
                </linearGradient>
            </defs>
            {ticks.map((t) => (
                <g key={t}>
                    <line
                        x1={padL}
                        x2={W - padR}
                        y1={yFor(t)}
                        y2={yFor(t)}
                        stroke="#EEF2F9"
                        strokeWidth="1"
                    />
                    <text
                        x={padL - 8}
                        y={yFor(t) + 4}
                        textAnchor="end"
                        fontSize="11"
                        fill="#94A3B8"
                    >
                        Rp {t.toFixed(1).replace('.', ',')}
                    </text>
                </g>
            ))}
            <path d={area} fill="url(#plArea)" />
            <path
                d={line}
                fill="none"
                stroke="#4A6BD8"
                strokeWidth="2.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            {pts.map((p) => (
                <g key={p.m}>
                    <circle
                        cx={p.cx}
                        cy={p.cy}
                        r="4"
                        fill="#fff"
                        stroke="#4A6BD8"
                        strokeWidth="2.4"
                    />
                    <text
                        x={p.cx}
                        y={H - 8}
                        textAnchor="middle"
                        fontSize="11.5"
                        fill="#64748B"
                    >
                        {p.m}
                    </text>
                </g>
            ))}
        </svg>
    );
}

function Donut() {
    return (
        <div className="flex items-center gap-5">
            <svg
                width="150"
                height="150"
                viewBox="0 0 180 180"
                className="flex-none"
            >
                <circle
                    cx="90"
                    cy="90"
                    r={RING_R}
                    fill="none"
                    stroke="#F1F4FA"
                    strokeWidth="24"
                />
                <g transform="rotate(-90 90 90)">
                    {donutSegments.map((s) => (
                        <circle
                            key={s.name}
                            cx="90"
                            cy="90"
                            r={RING_R}
                            fill="none"
                            stroke={s.color}
                            strokeWidth="24"
                            strokeDasharray={`${s.length} ${RING_C - s.length}`}
                            strokeDashoffset={s.offset}
                        />
                    ))}
                </g>
                <text
                    x="90"
                    y="84"
                    textAnchor="middle"
                    fontSize="26"
                    fontWeight="600"
                    fill="#1B2B5E"
                >
                    1.248
                </text>
                <text
                    x="90"
                    y="104"
                    textAnchor="middle"
                    fontSize="11.5"
                    fill="#94A3B8"
                >
                    karyawan
                </text>
            </svg>
            <div className="flex flex-1 flex-col gap-2.5">
                {donutSegments.map((s) => (
                    <div
                        key={s.name}
                        className="flex items-center gap-2 text-[12.5px]"
                    >
                        <span
                            className="size-2.5 flex-none rounded-[3px]"
                            style={{ background: s.color }}
                        />
                        <span className="flex-1 text-muted-foreground">
                            {s.name}
                        </span>
                        <span className="font-semibold text-foreground">
                            {s.pct.toFixed(1).replace('.', ',')}%
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Gauge() {
    return (
        <>
            <div className="relative my-1 flex justify-center">
                <svg width="160" height="160" viewBox="0 0 180 180">
                    <circle
                        cx="90"
                        cy="90"
                        r={RING_R}
                        fill="none"
                        stroke="#F1F4FA"
                        strokeWidth="22"
                    />
                    <g transform="rotate(-90 90 90)">
                        {gaugeSegments.map((s) => (
                            <circle
                                key={s.name}
                                cx="90"
                                cy="90"
                                r={RING_R}
                                fill="none"
                                stroke={s.color}
                                strokeWidth="22"
                                strokeLinecap="round"
                                strokeDasharray={`${s.draw} ${RING_C - s.draw}`}
                                strokeDashoffset={s.offset}
                            />
                        ))}
                    </g>
                    <text
                        x="90"
                        y="84"
                        textAnchor="middle"
                        fontSize="30"
                        fontWeight="600"
                        fill="#16A34A"
                    >
                        87,6%
                    </text>
                    <text
                        x="90"
                        y="106"
                        textAnchor="middle"
                        fontSize="11.5"
                        fill="#94A3B8"
                    >
                        tingkat kehadiran
                    </text>
                </svg>
            </div>
            <div className="flex flex-col gap-2.5">
                {gaugeSegments.map((s) => (
                    <div
                        key={s.name}
                        className="flex items-center gap-2 text-[12.5px]"
                    >
                        <span
                            className="size-2.5 flex-none rounded-[3px]"
                            style={{ background: s.color }}
                        />
                        <span className="flex-1 text-muted-foreground">
                            {s.name}
                        </span>
                        <span className="font-semibold text-foreground">
                            {s.count}
                        </span>
                        <span className="w-11 text-right text-[11.5px] text-muted-foreground">
                            {s.pct.toFixed(1).replace('.', ',')}%
                        </span>
                    </div>
                ))}
            </div>
        </>
    );
}

export default function Dashboard() {
    const today = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-5 p-6">
                {/* Greeting */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                            Selamat datang, Admin 👋
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {today} · 1.094 karyawan sudah presensi, 3
                            persetujuan menunggu Anda.
                        </p>
                    </div>
                </div>

                {/* KPI row */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {kpis.map((k) => (
                        <Card key={k.label}>
                            <div className="mb-4 flex items-center justify-between">
                                <span
                                    className={`flex size-11 items-center justify-center rounded-xl ${k.iconClass}`}
                                >
                                    <k.icon className="size-5" />
                                </span>
                                <span
                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold ${
                                        k.up
                                            ? 'bg-[#DCFCE7] text-[#16A34A]'
                                            : 'bg-[#FEF3C7] text-[#B45309]'
                                    }`}
                                >
                                    {k.up === true && (
                                        <ArrowUpRight className="size-3" />
                                    )}
                                    {k.up === false && (
                                        <ArrowDownRight className="size-3" />
                                    )}
                                    {k.trend}
                                </span>
                            </div>
                            <div className="text-2xl font-semibold tracking-tight text-foreground">
                                {k.value}
                            </div>
                            <div className="mt-1 text-[13px] text-muted-foreground">
                                {k.label}
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Charts row */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.55fr_1fr]">
                    <Card>
                        <div className="flex items-start justify-between">
                            <div>
                                <h3 className="text-[15.5px] font-semibold text-foreground">
                                    Tren Biaya Payroll
                                </h3>
                                <p className="mt-1 text-[12.5px] text-muted-foreground">
                                    6 bulan terakhir · dalam Miliar Rupiah
                                </p>
                            </div>
                            <div className="flex items-center gap-1.5 text-[12.5px] text-muted-foreground">
                                <span className="size-2.5 rounded-[3px] bg-primary" />
                                Biaya Payroll
                            </div>
                        </div>
                        <PayrollLineChart />
                    </Card>

                    <Card>
                        <h3 className="text-[15.5px] font-semibold text-foreground">
                            Karyawan per Departemen
                        </h3>
                        <p className="mt-1 mb-3.5 text-[12.5px] text-muted-foreground">
                            Total 1.248 karyawan
                        </p>
                        <Donut />
                    </Card>
                </div>

                {/* Quick access */}
                <Card>
                    <h3 className="mb-4 text-[15.5px] font-semibold text-foreground">
                        Akses Cepat
                    </h3>
                    <div className="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-6">
                        {quickAccess.map((q) => (
                            <button
                                key={q.name}
                                type="button"
                                className="flex flex-col items-center gap-2.5 rounded-[10px] border border-border bg-card p-4 transition-colors hover:border-primary hover:bg-[#EAF0FC]"
                            >
                                <span
                                    className={`flex size-[42px] items-center justify-center rounded-xl ${q.className}`}
                                >
                                    <q.icon className="size-5" />
                                </span>
                                <span className="text-center text-[12.5px] leading-tight font-medium text-foreground">
                                    {q.name}
                                </span>
                            </button>
                        ))}
                    </div>
                </Card>

                {/* Bottom widgets */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1.1fr_1.1fr_0.9fr]">
                    {/* Contracts ending */}
                    <Card>
                        <div className="mb-3.5 flex items-center justify-between">
                            <h3 className="text-[15px] font-semibold text-foreground">
                                Kontrak Akan Berakhir
                            </h3>
                            <a
                                href="#"
                                className="text-[12.5px] font-medium text-primary"
                            >
                                Lihat semua
                            </a>
                        </div>
                        <div className="flex flex-col">
                            {contracts.map((c) => (
                                <div
                                    key={c.name}
                                    className="flex items-center gap-3 border-b border-[#F1F4FA] py-2.5 last:border-0"
                                >
                                    <div className="flex size-9 flex-none items-center justify-center rounded-[9px] bg-primary text-[12.5px] font-semibold text-white">
                                        {c.initials}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-[13.5px] font-medium text-foreground">
                                            {c.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {c.role} · {c.date}
                                        </div>
                                    </div>
                                    <span className="flex-none rounded-full bg-[#FEF3C7] px-2.5 py-1 text-[11.5px] font-semibold whitespace-nowrap text-[#B45309]">
                                        {c.remaining}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* Approvals */}
                    <Card>
                        <div className="mb-3.5 flex items-center justify-between">
                            <h3 className="text-[15px] font-semibold text-foreground">
                                Persetujuan Menunggu
                            </h3>
                            <span className="rounded-full bg-[#EAF0FC] px-2.5 py-1 text-[11.5px] font-semibold text-primary">
                                3 baru
                            </span>
                        </div>
                        <div className="flex flex-col gap-3">
                            {approvals.map((a) => (
                                <div
                                    key={a.name}
                                    className="flex items-center gap-3"
                                >
                                    <div className="flex size-[34px] flex-none items-center justify-center rounded-[9px] bg-[#EAF0FC] text-primary">
                                        <a.icon className="size-4" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-[13px] font-medium text-foreground">
                                            {a.name}
                                        </div>
                                        <div className="text-[11.5px] text-muted-foreground">
                                            {a.type} · {a.detail}
                                        </div>
                                    </div>
                                    <div className="flex flex-none gap-1.5">
                                        <button
                                            type="button"
                                            title="Setujui"
                                            className="flex size-[30px] items-center justify-center rounded-lg bg-[#16A34A] text-white"
                                        >
                                            <CheckCircle2 className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            title="Tolak"
                                            className="flex size-[30px] items-center justify-center rounded-lg border border-[#FCA5A5] bg-card text-destructive"
                                        >
                                            <ArrowDownRight className="size-4 rotate-45" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* Attendance gauge */}
                    <Card>
                        <h3 className="text-[15px] font-semibold text-foreground">
                            Absensi Hari Ini
                        </h3>
                        <Gauge />
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
