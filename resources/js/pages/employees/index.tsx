import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { create, destroy, edit, index, show } from '@/routes/employees';

type Employee = {
    id: number;
    employee_code: string;
    full_name: string;
    email: string | null;
    position: string | null;
    org_unit: string | null;
    status: string;
};

type OrgUnit = { id: number; name: string };

type Filters = {
    q: string;
    status: string;
    org_unit_id: number | null;
};

type Props = {
    employees: Employee[];
    orgUnits: OrgUnit[];
    filters: Filters;
};

const ALL = '__all__';

export default function EmployeesIndex({
    employees,
    orgUnits,
    filters,
}: Props) {
    const { can } = usePermissions();

    const apply = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        router.get(
            index().url,
            {
                q: next.q || undefined,
                status: next.status || undefined,
                org_unit_id: next.org_unit_id ?? undefined,
            },
            {
                only: ['employees', 'filters'],
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Karyawan" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Karyawan' },
                ]}
            />
            <PageHeader
                title="Karyawan"
                description="Kelola data seluruh karyawan perusahaan"
                action={
                    can('employees.create') ? (
                        <Button asChild>
                            <Link href={create().url}>
                                <Plus className="size-4" />
                                Tambah Karyawan
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            <div className="flex flex-wrap gap-3">
                <div className="relative min-w-56 flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        placeholder="Cari nama, kode, email..."
                        defaultValue={filters.q}
                        onChange={(e) => apply({ q: e.target.value })}
                        className="pl-9"
                    />
                </div>
                <Select
                    value={filters.status || ALL}
                    onValueChange={(v) => apply({ status: v === ALL ? '' : v })}
                >
                    <SelectTrigger className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Semua Status</SelectItem>
                        <SelectItem value="active">Aktif</SelectItem>
                        <SelectItem value="inactive">Nonaktif</SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    value={
                        filters.org_unit_id ? String(filters.org_unit_id) : ALL
                    }
                    onValueChange={(v) =>
                        apply({ org_unit_id: v === ALL ? null : Number(v) })
                    }
                >
                    <SelectTrigger className="w-52">
                        <SelectValue placeholder="Semua Unit" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Semua Unit</SelectItem>
                        {orgUnits.map((u) => (
                            <SelectItem key={u.id} value={String(u.id)}>
                                {u.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <Card>
                <CardContent>
                    {employees.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Belum ada karyawan"
                            description="Tambahkan karyawan pertama perusahaan."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Kode</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Posisi</TableHead>
                                        <TableHead>Unit</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {employees.map((employee) => (
                                        <TableRow key={employee.id}>
                                            <TableCell className="font-mono text-xs">
                                                {employee.employee_code}
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={show(employee.id).url}
                                                    className="font-medium text-foreground hover:underline"
                                                >
                                                    {employee.full_name}
                                                </Link>
                                                {employee.email && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {employee.email}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {employee.position ?? '-'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {employee.org_unit ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        employee.status ===
                                                        'active'
                                                            ? 'aktif'
                                                            : 'nonaktif'
                                                    }
                                                    label={
                                                        employee.status ===
                                                        'active'
                                                            ? 'Aktif'
                                                            : 'Nonaktif'
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {can('employees.update') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                        aria-label={`Edit ${employee.full_name}`}
                                                    >
                                                        <Link
                                                            href={
                                                                edit(
                                                                    employee.id,
                                                                ).url
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('employees.delete') && (
                                                    <ConfirmDialog
                                                        title={`Hapus ${employee.full_name}?`}
                                                        description="Data karyawan akan diarsipkan (soft delete)."
                                                        onConfirm={() =>
                                                            router.delete(
                                                                destroy(
                                                                    employee.id,
                                                                ).url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Hapus ${employee.full_name}`}
                                                            >
                                                                <Trash2 className="size-4 text-red-600" />
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
