import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { destroy, store, update } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    is_default: boolean;
    permissions: string[];
};

type Props = {
    roles: Role[];
    permissionGroups: Record<string, string[]>;
};

// Human labels for the module group headers.
const MODULE_LABELS: Record<string, string> = {
    organization: 'Organisasi',
    employees: 'Karyawan',
    branches: 'Cabang',
    attendance: 'Kehadiran',
    leave: 'Cuti',
    overtime: 'Lembur',
    shift: 'Shift & Jadwal',
    payroll: 'Payroll',
    approval: 'Persetujuan',
    audit: 'Audit',
    roles: 'Peran & Akses',
    menu: 'Menu',
    crm: 'CRM',
    calendar: 'Kalender',
    announcement: 'Pengumuman',
    dashboard: 'Dashboard',
    reports: 'Laporan',
    ess: 'Self-Service',
    mss: 'Manager Self-Service',
};

export default function RolesIndex({ roles, permissionGroups }: Props) {
    const { can } = usePermissions();
    const canManage = can('roles.manage');

    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Role | null>(null);

    const form = useForm<{ name: string; permissions: string[] }>({
        name: '',
        permissions: [],
    });

    const allPermissions = useMemo(
        () => Object.values(permissionGroups).flat(),
        [permissionGroups],
    );

    const openCreate = () => {
        setEditing(null);
        form.setData({ name: '', permissions: [] });
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (role: Role) => {
        setEditing(role);
        form.setData({ name: role.name, permissions: [...role.permissions] });
        form.clearErrors();
        setOpen(true);
    };

    const togglePermission = (permission: string, checked: boolean) => {
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, permission]
                : form.data.permissions.filter((p) => p !== permission),
        );
    };

    const toggleGroup = (group: string[], checked: boolean) => {
        const set = new Set(form.data.permissions);
        group.forEach((p) => (checked ? set.add(p) : set.delete(p)));
        form.setData('permissions', [...set]);
    };

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(update(editing.id).url, options);
        } else {
            form.post(store().url, options);
        }
    };

    const remove = (role: Role) => {
        router.delete(destroy(role.id).url, { preserveScroll: true });
    };

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
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            Buat Peran
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
                                        {role.permissions.length} hak akses
                                    </p>
                                </div>
                                {canManage && (
                                    <div className="flex shrink-0 gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => openEdit(role)}
                                            aria-label={`Edit ${role.name}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        {!role.is_default && (
                                            <ConfirmDialog
                                                title={`Hapus peran ${role.name}?`}
                                                description="Pengguna yang memegang peran ini akan kehilangan aksesnya."
                                                onConfirm={() => remove(role)}
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

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit Peran' : 'Buat Peran'}
                        </DialogTitle>
                        <DialogDescription>
                            Pilih hak akses yang diberikan untuk peran ini.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="role-name">Nama Peran</Label>
                            <Input
                                id="role-name"
                                value={form.data.name}
                                disabled={editing?.is_default}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            {editing?.is_default && (
                                <p className="text-xs text-muted-foreground">
                                    Nama peran bawaan tidak dapat diubah.
                                </p>
                            )}
                            {form.errors.name && (
                                <p className="text-sm text-red-600">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col gap-4">
                            {Object.entries(permissionGroups).map(
                                ([module, perms]) => {
                                    const allChecked = perms.every((p) =>
                                        form.data.permissions.includes(p),
                                    );

                                    return (
                                        <div
                                            key={module}
                                            className="rounded-md border border-border p-3"
                                        >
                                            <label className="flex items-center gap-2 font-medium">
                                                <Checkbox
                                                    checked={allChecked}
                                                    onCheckedChange={(c) =>
                                                        toggleGroup(
                                                            perms,
                                                            c === true,
                                                        )
                                                    }
                                                />
                                                {MODULE_LABELS[module] ??
                                                    module}
                                            </label>
                                            <div className="mt-2 grid gap-2 pl-6 sm:grid-cols-2">
                                                {perms.map((permission) => (
                                                    <label
                                                        key={permission}
                                                        className="flex items-center gap-2 text-sm text-muted-foreground"
                                                    >
                                                        <Checkbox
                                                            checked={form.data.permissions.includes(
                                                                permission,
                                                            )}
                                                            onCheckedChange={(
                                                                c,
                                                            ) =>
                                                                togglePermission(
                                                                    permission,
                                                                    c === true,
                                                                )
                                                            }
                                                        />
                                                        {permission}
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                },
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <span className="mr-auto text-sm text-muted-foreground">
                            {form.data.permissions.length} /{' '}
                            {allPermissions.length} dipilih
                        </span>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
