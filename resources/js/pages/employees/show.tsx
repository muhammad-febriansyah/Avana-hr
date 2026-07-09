import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit, index } from '@/routes/employees';

type Employee = {
    id: number;
    employee_code: string;
    full_name: string;
    status: string;
    nik_ktp: string | null;
    npwp: string | null;
    email: string | null;
    phone: string | null;
    birth_date: string | null;
    gender: string | null;
    marital_status: string | null;
    ptkp_status: string | null;
    position: string | null;
    grade: string | null;
    org_unit: string | null;
    branch: string | null;
    direct_manager: string | null;
    employment_status: string | null;
    join_date: string | null;
    bank_name: string | null;
    bank_account: string | null;
    bank_account_name: string | null;
    bpjs_kes_no: string | null;
    bpjs_tk_no: string | null;
};

type Audit = {
    id: number;
    event: string;
    user: string;
    created_at: string;
};

type Props = {
    employee: Employee;
    audits: Audit[];
};

type Tab = 'profil' | 'kepegawaian' | 'payroll' | 'lainnya' | 'riwayat';

const mask = (value: string | null) => {
    if (!value) {
        return '-';
    }

    return value.length <= 4
        ? value
        : `${'•'.repeat(Math.max(value.length - 4, 4))}${value.slice(-4)}`;
};

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid grid-cols-[10rem_1fr] gap-2 border-b border-border/60 py-2.5 text-sm last:border-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-foreground">{value || '-'}</span>
        </div>
    );
}

export default function EmployeeShow({ employee, audits }: Props) {
    const { can } = usePermissions();
    const [tab, setTab] = useState<Tab>('profil');

    const tabs: { key: Tab; label: string }[] = [
        { key: 'profil', label: 'Profil' },
        { key: 'kepegawaian', label: 'Kepegawaian' },
        { key: 'payroll', label: 'Payroll' },
        { key: 'lainnya', label: 'Kehadiran & Cuti' },
        { key: 'riwayat', label: 'Riwayat' },
    ];

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title={employee.full_name} />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Karyawan', href: index().url },
                    { title: employee.full_name },
                ]}
            />
            <PageHeader
                title={employee.full_name}
                description={`${employee.employee_code}`}
                action={
                    <div className="flex items-center gap-3">
                        <StatusBadge
                            status={
                                employee.status === 'active'
                                    ? 'aktif'
                                    : 'nonaktif'
                            }
                            label={
                                employee.status === 'active'
                                    ? 'Aktif'
                                    : 'Nonaktif'
                            }
                        />
                        {can('employees.update') && (
                            <Button asChild>
                                <Link href={edit(employee.id).url}>
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="flex flex-wrap gap-1 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => setTab(t.key)}
                        className={
                            'border-b-2 px-4 py-2 text-sm font-medium transition-colors ' +
                            (tab === t.key
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground')
                        }
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <Card>
                <CardContent>
                    {tab === 'profil' && (
                        <div className="max-w-2xl">
                            <Row
                                label="Nama Lengkap"
                                value={employee.full_name}
                            />
                            <Row
                                label="NIK KTP"
                                value={mask(employee.nik_ktp)}
                            />
                            <Row label="NPWP" value={mask(employee.npwp)} />
                            <Row label="Email" value={employee.email} />
                            <Row label="Telepon" value={employee.phone} />
                            <Row
                                label="Tanggal Lahir"
                                value={employee.birth_date}
                            />
                            <Row
                                label="Jenis Kelamin"
                                value={employee.gender}
                            />
                            <Row
                                label="Status Nikah"
                                value={employee.marital_status}
                            />
                            <Row label="PTKP" value={employee.ptkp_status} />
                        </div>
                    )}

                    {tab === 'kepegawaian' && (
                        <div className="max-w-2xl">
                            <Row label="Posisi" value={employee.position} />
                            <Row label="Grade" value={employee.grade} />
                            <Row label="Unit" value={employee.org_unit} />
                            <Row label="Cabang" value={employee.branch} />
                            <Row
                                label="Atasan"
                                value={employee.direct_manager}
                            />
                            <Row
                                label="Status Kerja"
                                value={employee.employment_status}
                            />
                            <Row
                                label="Tanggal Masuk"
                                value={employee.join_date}
                            />
                        </div>
                    )}

                    {tab === 'payroll' && (
                        <div className="max-w-2xl">
                            <Row label="Bank" value={employee.bank_name} />
                            <Row
                                label="No. Rekening"
                                value={mask(employee.bank_account)}
                            />
                            <Row
                                label="Pemilik Rekening"
                                value={employee.bank_account_name}
                            />
                            <Row
                                label="BPJS Kesehatan"
                                value={employee.bpjs_kes_no}
                            />
                            <Row label="BPJS TK" value={employee.bpjs_tk_no} />
                        </div>
                    )}

                    {tab === 'lainnya' && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            Data kehadiran & cuti tersedia setelah modul Time
                            Management aktif.
                        </p>
                    )}

                    {tab === 'riwayat' && (
                        <div className="flex flex-col divide-y divide-border">
                            {audits.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Belum ada riwayat perubahan.
                                </p>
                            ) : (
                                audits.map((audit) => (
                                    <div
                                        key={audit.id}
                                        className="flex items-center justify-between py-2.5 text-sm"
                                    >
                                        <span className="capitalize">
                                            {audit.event}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {audit.user} ·{' '}
                                            {formatDateTime(audit.created_at)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
