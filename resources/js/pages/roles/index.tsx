import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { create, destroy, edit } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    is_default: boolean;
    permissions_count: number;
};

export default function RolesIndex({ roles }: { roles: Role[] }) {
    const { can } = usePermissions();
    const canManage = can('roles.manage');

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Peran & Akses" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Peran & Akses' },
                ]}
            />
            <PageHeader
                title="Peran & Akses"
                description="Kelola peran dan hak akses (permission) per modul"
                action={
                    canManage ? (
                        <Button asChild>
                            <Link href={create().url}>
                                <Plus className="size-4" />
                                Buat Peran
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            {roles.length === 0 ? (
                <Card>
                    <CardContent>
                        <EmptyState
                            icon={ShieldCheck}
                            title="Belum ada peran"
                            description="Buat peran pertama untuk mengatur hak akses tim."
                        />
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {roles.map((role) => (
                        <Card key={role.id}>
                            <CardHeader className="flex-row items-start justify-between gap-2 space-y-0">
                                <div className="min-w-0">
                                    <CardTitle className="flex items-center gap-2">
                                        {role.name}
                                        {role.is_default && (
                                            <Badge variant="secondary">
                                                Bawaan
                                            </Badge>
                                        )}
                                    </CardTitle>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {role.permissions_count} hak akses
                                    </p>
                                </div>
                                {canManage && (
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                            aria-label={`Edit ${role.name}`}
                                        >
                                            <Link href={edit(role.id).url}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        {!role.is_default && (
                                            <ConfirmDialog
                                                title={`Hapus peran ${role.name}?`}
                                                description="Pengguna yang memegang peran ini akan kehilangan aksesnya."
                                                onConfirm={() =>
                                                    router.delete(
                                                        destroy(role.id).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Hapus ${role.name}`}
                                                    >
                                                        <Trash2 className="size-4 text-red-600" />
                                                    </Button>
                                                }
                                            />
                                        )}
                                    </div>
                                )}
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}
