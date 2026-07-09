import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index, store, update } from '@/routes/roles';

type Role = {
    id: number;
    name: string;
    is_default: boolean;
    permissions: string[];
};

type Props = {
    role: Role | null;
    permissionGroups: Record<string, string[]>;
};

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

export default function RoleForm({ role, permissionGroups }: Props) {
    const form = useForm<{ name: string; permissions: string[] }>({
        name: role?.name ?? '',
        permissions: role?.permissions ?? [],
    });

    const allPermissions = useMemo(
        () => Object.values(permissionGroups).flat(),
        [permissionGroups],
    );

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
        if (role) {
            form.put(update(role.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title={role ? 'Edit Peran' : 'Buat Peran'} />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Peran & Akses', href: index().url },
                    { title: role ? 'Edit' : 'Buat' },
                ]}
            />
            <PageHeader
                title={role ? `Edit Peran: ${role.name}` : 'Buat Peran'}
                description="Atur nama peran dan hak akses per modul"
            />

            <Card>
                <CardHeader>
                    <CardTitle>Detail Peran</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    <div className="grid max-w-md gap-2">
                        <Label htmlFor="role-name">Nama Peran</Label>
                        <Input
                            id="role-name"
                            value={form.data.name}
                            disabled={role?.is_default}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        {role?.is_default && (
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

                    <div className="grid gap-3">
                        <div className="flex items-center justify-between">
                            <Label>Hak Akses</Label>
                            <span className="text-sm text-muted-foreground">
                                {form.data.permissions.length} /{' '}
                                {allPermissions.length} dipilih
                            </span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {Object.entries(permissionGroups).map(
                                ([module, perms]) => {
                                    const allChecked = perms.every((p) =>
                                        form.data.permissions.includes(p),
                                    );

                                    return (
                                        <div
                                            key={module}
                                            className="rounded-lg border border-border p-3"
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
                                            <div className="mt-2 flex flex-col gap-2 pl-6">
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
                </CardContent>
                <CardFooter className="justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={index().url}>Batal</Link>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        Simpan
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}
