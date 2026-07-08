import { Link } from '@inertiajs/react';
import { ScanFace, Smartphone, Wallet } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const features = [
    {
        icon: ScanFace,
        title: 'Absensi Face Recognition',
        description: 'Presensi akurat & anti-titip absen',
    },
    {
        icon: Wallet,
        title: 'Payroll Otomatis PPh 21 & BPJS',
        description: 'Hitung gaji sesuai regulasi terbaru',
    },
    {
        icon: Smartphone,
        title: 'Employee Self-Service',
        description: 'Ajukan cuti & slip gaji dari genggaman',
    },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-dvh bg-background">
            {/* Illustration panel */}
            <div
                className="relative hidden w-[55%] flex-col justify-between overflow-hidden p-14 text-white lg:flex"
                style={{
                    background:
                        'linear-gradient(140deg,#1B2B5E 0%,#26408C 55%,#4A6BD8 100%)',
                }}
            >
                <div
                    className="pointer-events-none absolute inset-0 opacity-50"
                    style={{
                        backgroundImage:
                            'radial-gradient(rgba(255,255,255,.14) 1.3px,transparent 1.3px)',
                        backgroundSize: '26px 26px',
                    }}
                />

                <Link
                    href={home()}
                    className="relative flex items-center gap-3"
                >
                    <AppLogoIcon className="h-10 w-14 text-white" />
                    <span className="text-2xl font-semibold tracking-tight">
                        AvanaHR
                    </span>
                </Link>

                <div className="relative max-w-lg">
                    <h1 className="mb-4 text-4xl leading-tight font-semibold tracking-tight">
                        Advancing People,
                        <br />
                        Empowering Growth
                    </h1>
                    <p className="mb-10 text-base leading-relaxed font-light text-white/80">
                        Platform HRIS &amp; HCM terpadu untuk mengelola seluruh
                        siklus karyawan perusahaan Anda dalam satu tempat.
                    </p>
                    <div className="flex flex-col gap-4">
                        {features.map((feature) => (
                            <div
                                key={feature.title}
                                className="flex items-center gap-4"
                            >
                                <div className="flex size-11 flex-none items-center justify-center rounded-xl bg-white/15">
                                    <feature.icon className="size-5" />
                                </div>
                                <div>
                                    <div className="text-sm font-medium">
                                        {feature.title}
                                    </div>
                                    <div className="text-[13px] font-light text-white/70">
                                        {feature.description}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="relative text-[13px] font-light text-white/60">
                    Dipercaya oleh 200+ perusahaan di Indonesia
                </div>
            </div>

            {/* Form panel */}
            <div className="flex flex-1 items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-sm">
                    <Link
                        href={home()}
                        className="mb-9 flex items-center gap-2.5"
                    >
                        <AppLogoIcon className="h-8 w-11 text-primary" />
                        <span className="text-xl font-semibold tracking-tight">
                            <span className="text-brand-navy">Avana</span>
                            <span className="text-primary">HR</span>
                        </span>
                    </Link>

                    <h2 className="mb-2 text-2xl font-semibold tracking-tight text-foreground">
                        {title}
                    </h2>
                    <p className="mb-8 text-sm text-muted-foreground">
                        {description}
                    </p>

                    {children}
                </div>
            </div>
        </div>
    );
}
