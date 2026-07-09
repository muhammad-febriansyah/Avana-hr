import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/components/shared/data-table';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type AuditLog = {
    id: number;
    event: string;
    model: string;
    record_id: number;
    user: string;
    ip_address: string | null;
    created_at: string | null;
};

const EVENT_STYLES: Record<string, string> = {
    created: 'bg-green-100 text-green-800',
    updated: 'bg-blue-100 text-blue-800',
    deleted: 'bg-red-100 text-red-800',
};

const columns: ColumnDef<AuditLog, unknown>[] = [
    {
        accessorKey: 'created_at',
        header: 'Waktu',
        cell: ({ row }) =>
            row.original.created_at
                ? formatDateTime(row.original.created_at)
                : '-',
    },
    {
        accessorKey: 'event',
        header: 'Aksi',
        cell: ({ row }) => (
            <Badge
                className={cn(
                    'border-transparent',
                    EVENT_STYLES[row.original.event] ??
                        'bg-slate-100 text-slate-700',
                )}
            >
                {row.original.event}
            </Badge>
        ),
    },
    { accessorKey: 'model', header: 'Modul' },
    { accessorKey: 'record_id', header: 'ID Data' },
    { accessorKey: 'user', header: 'Oleh' },
    {
        accessorKey: 'ip_address',
        header: 'IP',
        cell: ({ row }) => row.original.ip_address ?? '-',
    },
];

export default function AuditIndex({ logs }: { logs: AuditLog[] }) {
    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Audit Trail" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Audit Trail' },
                ]}
            />
            <PageHeader
                title="Audit Trail"
                description="Riwayat perubahan data sensitif (200 entri terbaru)"
            />

            <Card>
                <CardContent>
                    <DataTable
                        columns={columns}
                        data={logs}
                        searchPlaceholder="Cari modul, aksi, atau user..."
                        emptyTitle="Belum ada aktivitas tercatat"
                    />
                </CardContent>
            </Card>
        </div>
    );
}
