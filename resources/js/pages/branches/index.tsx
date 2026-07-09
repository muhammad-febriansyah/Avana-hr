import { Head, Link, router } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { create, destroy, edit, index } from '@/routes/branches';

type Branch = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    latitude: number | null;
    longitude: number | null;
    geofence_radius_m: number;
    timezone: string;
    cost_center: string | null;
};

type Props = {
    branches: Branch[];
    filters: { q: string };
};

export default function BranchesIndex({ branches, filters }: Props) {
    const { can } = usePermissions();
    const canManage = can('branches.manage');

    const search = (q: string) =>
        router.get(
            index().url,
            { q },
            {
                only: ['branches', 'filters'],
                preserveState: true,
                replace: true,
            },
        );

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Cabang" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Cabang' },
                ]}
            />
            <PageHeader
                title="Cabang & Lokasi Kerja"
                description="Kelola cabang beserta titik koordinat dan radius geofence"
                action={
                    canManage ? (
                        <Button asChild>
                            <Link href={create().url}>
                                <Plus className="size-4" />
                                Tambah Cabang
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            <div className="relative max-w-sm">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    placeholder="Cari nama atau kode..."
                    defaultValue={filters.q}
                    onChange={(e) => search(e.target.value)}
                    className="pl-9"
                />
            </div>

            <Card>
                <CardContent>
                    {branches.length === 0 ? (
                        <EmptyState
                            icon={MapPin}
                            title="Belum ada cabang"
                            description="Tambahkan lokasi kerja beserta koordinat geofence."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Kode</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Koordinat</TableHead>
                                        <TableHead className="text-right">
                                            Radius
                                        </TableHead>
                                        <TableHead>Zona Waktu</TableHead>
                                        {canManage && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {branches.map((branch) => (
                                        <TableRow key={branch.id}>
                                            <TableCell className="font-medium">
                                                {branch.code}
                                            </TableCell>
                                            <TableCell>
                                                <div>{branch.name}</div>
                                                {branch.address && (
                                                    <div className="max-w-xs truncate text-xs text-muted-foreground">
                                                        {branch.address}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {branch.latitude !== null &&
                                                branch.longitude !== null
                                                    ? `${branch.latitude}, ${branch.longitude}`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {branch.geofence_radius_m} m
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {branch.timezone}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                        aria-label={`Edit ${branch.name}`}
                                                    >
                                                        <Link
                                                            href={
                                                                edit(branch.id)
                                                                    .url
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    <ConfirmDialog
                                                        title={`Hapus cabang ${branch.name}?`}
                                                        onConfirm={() =>
                                                            router.delete(
                                                                destroy(
                                                                    branch.id,
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
                                                                aria-label={`Hapus ${branch.name}`}
                                                            >
                                                                <Trash2 className="size-4 text-red-600" />
                                                            </Button>
                                                        }
                                                    />
                                                </TableCell>
                                            )}
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
