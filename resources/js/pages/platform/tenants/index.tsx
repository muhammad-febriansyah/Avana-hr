import { Head, useForm } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { store } from '@/routes/platform/tenants';

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

type Plan = { id: number; name: string };

type Props = {
    tenants: Tenant[];
    plans: Plan[];
};

export default function PlatformTenantsIndex({ tenants, plans }: Props) {
    const [open, setOpen] = useState(false);

    const form = useForm<{
        name: string;
        slug: string;
        plan_id: string;
        employee_id_prefix: string;
        admin_name: string;
        admin_email: string;
        admin_password: string;
    }>({
        name: '',
        slug: '',
        plan_id: '',
        employee_id_prefix: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    const field = (key: keyof typeof form.data) => ({
        value: form.data[key],
        onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
            form.setData(key, e.target.value),
    });

    const submit = () => {
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

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
                    <Button onClick={() => setOpen(true)}>
                        <Plus className="size-4" />
                        Tambah Tenant
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

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Tambah Tenant</DialogTitle>
                        <DialogDescription>
                            Tenant baru langsung memiliki 5 peran bawaan dan
                            satu akun Company Admin yang terisolasi.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama Perusahaan</Label>
                            <Input id="name" {...field('name')} />
                            {form.errors.name && (
                                <p className="text-sm text-red-600">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug</Label>
                                <Input id="slug" {...field('slug')} />
                                {form.errors.slug && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.slug}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="prefix">
                                    Prefix ID Karyawan
                                </Label>
                                <Input
                                    id="prefix"
                                    {...field('employee_id_prefix')}
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label>Paket</Label>
                            <Select
                                value={form.data.plan_id}
                                onValueChange={(v) =>
                                    form.setData('plan_id', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih paket" />
                                </SelectTrigger>
                                <SelectContent>
                                    {plans.map((plan) => (
                                        <SelectItem
                                            key={plan.id}
                                            value={String(plan.id)}
                                        >
                                            {plan.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="mt-2 border-t border-border pt-4">
                            <p className="mb-3 text-sm font-medium">
                                Akun Company Admin
                            </p>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="admin_name">Nama</Label>
                                    <Input
                                        id="admin_name"
                                        {...field('admin_name')}
                                    />
                                    {form.errors.admin_name && (
                                        <p className="text-sm text-red-600">
                                            {form.errors.admin_name}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="admin_email">Email</Label>
                                    <Input
                                        id="admin_email"
                                        type="email"
                                        {...field('admin_email')}
                                    />
                                    {form.errors.admin_email && (
                                        <p className="text-sm text-red-600">
                                            {form.errors.admin_email}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="admin_password">
                                        Kata Sandi
                                    </Label>
                                    <Input
                                        id="admin_password"
                                        type="password"
                                        {...field('admin_password')}
                                    />
                                    {form.errors.admin_password && (
                                        <p className="text-sm text-red-600">
                                            {form.errors.admin_password}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            Buat Tenant
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
