import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    Eye,
    Pencil,
    Plus,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { CurrencyInput } from '@/components/shared/currency-input';
import { DataTable } from '@/components/shared/data-table';
import { DatePicker } from '@/components/shared/date-picker';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { RequiredLabel } from '@/components/shared/required-label';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatRupiah } from '@/lib/format';

type Employee = {
    code: string;
    name: string;
    dept: string;
    salary: number;
    status: string;
};

const rows: Employee[] = [
    {
        code: 'DEMO-2026-001',
        name: 'Rina Anggraini',
        dept: 'Operasional',
        salary: 6500000,
        status: 'active',
    },
    {
        code: 'DEMO-2026-002',
        name: 'Budi Santoso',
        dept: 'Sales',
        salary: 7200000,
        status: 'active',
    },
    {
        code: 'DEMO-2026-003',
        name: 'Dewi Sartika',
        dept: 'Finance',
        salary: 8100000,
        status: 'inactive',
    },
    {
        code: 'DEMO-2026-004',
        name: 'Andi Pratama',
        dept: 'IT',
        salary: 9000000,
        status: 'pending',
    },
];

const columns: ColumnDef<Employee, unknown>[] = [
    { accessorKey: 'code', header: 'Kode' },
    { accessorKey: 'name', header: 'Nama' },
    { accessorKey: 'dept', header: 'Departemen' },
    {
        accessorKey: 'salary',
        header: 'Gaji Pokok',
        cell: ({ row }) => (
            <span className="tabular-nums">
                {formatRupiah(row.original.salary)}
            </span>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => <StatusBadge status={row.original.status} />,
    },
];

const actionButtons = [
    { variant: 'default' as const, label: 'Tambah', icon: Plus },
    { variant: 'edit' as const, label: 'Edit', icon: Pencil },
    { variant: 'delete' as const, label: 'Hapus', icon: Trash2 },
    { variant: 'detail' as const, label: 'Detail', icon: Eye },
    { variant: 'approve' as const, label: 'Setujui', icon: CheckCircle2 },
    { variant: 'export' as const, label: 'Export', icon: Download },
    { variant: 'outline' as const, label: 'Kembali', icon: ArrowLeft },
];

export default function DevComponents() {
    const [amount, setAmount] = useState(5000000);
    const [date, setDate] = useState<Date | undefined>(undefined);

    return (
        <div className="mx-auto max-w-5xl space-y-6 p-6">
            <Head title="Shared UI Kit" />

            <PageBreadcrumb
                items={[{ title: 'Dev' }, { title: 'Shared UI Kit' }]}
            />
            <PageHeader
                title="Shared UI Kit"
                description="Showcase komponen bersama AvanaHR (Design System 05)"
                action={
                    <Button variant="default">
                        <Plus className="size-4" /> Aksi Utama
                    </Button>
                }
            />

            <Card>
                <CardHeader>
                    <CardTitle>Tombol Aksi (B4)</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-3">
                    {actionButtons.map((button) => (
                        <Button key={button.label} variant={button.variant}>
                            <button.icon className="size-4" /> {button.label}
                        </Button>
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Status Badge (B7)</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                    {[
                        'pending',
                        'approved',
                        'rejected',
                        'draft',
                        'locked',
                        'terlambat',
                    ].map((status) => (
                        <StatusBadge key={status} status={status} />
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Form Controls (B5)</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <RequiredLabel htmlFor="name">
                            Nama Lengkap
                        </RequiredLabel>
                        <Input id="name" placeholder="Masukkan nama lengkap" />
                    </div>
                    <div className="grid gap-2">
                        <RequiredLabel>Gaji Pokok</RequiredLabel>
                        <CurrencyInput value={amount} onChange={setAmount} />
                        <p className="text-xs text-muted-foreground">
                            Nilai dikirim: {amount}
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <RequiredLabel>Tanggal Mulai</RequiredLabel>
                        <DatePicker value={date} onChange={setDate} />
                    </div>
                    <div className="flex items-end">
                        <ConfirmDialog
                            trigger={
                                <Button variant="delete">
                                    <Trash2 className="size-4" /> Hapus (contoh)
                                </Button>
                            }
                            description="Data karyawan Rina Anggraini akan dihapus permanen."
                            onConfirm={() => {}}
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>DataTable (B3)</CardTitle>
                </CardHeader>
                <CardContent>
                    <DataTable
                        columns={columns}
                        data={rows}
                        searchPlaceholder="Cari nama atau kode..."
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Empty State</CardTitle>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        icon={Users}
                        title="Belum ada karyawan"
                        description="Tambah karyawan pertama untuk mulai."
                        action={
                            <Button variant="default">
                                <Plus className="size-4" /> Tambah Karyawan
                            </Button>
                        }
                    />
                </CardContent>
            </Card>
        </div>
    );
}
