import { Head, Link } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create } from '@/routes/platform/tenants';

type Tenant = {
    id: number;
    name: string;
    slug: string;
    plan: string | null;
    employee_id_prefix: string | null;
    is_active: boolean;
    users_count: number;
    employees_count: number;
};

export default function PlatformTenantsIndex({
    tenants,
}: {
    tenants: Tenant[];
}) {
    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Tenant" />

            <PageBreadcrumb
                items={[{ title: 'Platform' }, { title: 'Tenant' }]}
            />
            <PageHeader
                title="Tenant"
                description="Kelola perusahaan (tenant) di seluruh platform"
                action={
                    <Button asChild>
                        <Link href={create().url}>
                            <Plus className="size-4" />
                            Tambah Tenant
                        </Link>
                    </Button>
                }
            />

            <Card>
                <CardContent>
                    {tenants.length === 0 ? (
                        <EmptyState
                            icon={Building2}
                            title="Belum ada tenant"
                            description="Buat tenant pertama untuk mulai onboarding perusahaan."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Slug</TableHead>
                                    <TableHead>Paket</TableHead>
                                    <TableHead className="text-right">
                                        Pengguna
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Karyawan
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tenants.map((tenant) => (
                                    <TableRow key={tenant.id}>
                                        <TableCell className="font-medium">
                                            {tenant.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {tenant.slug}
                                        </TableCell>
                                        <TableCell>
                                            {tenant.plan ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {tenant.users_count}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {tenant.employees_count}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    tenant.is_active
                                                        ? 'aktif'
                                                        : 'nonaktif'
                                                }
                                                label={
                                                    tenant.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
