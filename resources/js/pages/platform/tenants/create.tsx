import { Head, Link, useForm } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store } from '@/routes/platform/tenants';

type Plan = { id: number; name: string };

type Field = {
    name: string;
    slug: string;
    plan_id: string;
    employee_id_prefix: string;
    admin_name: string;
    admin_email: string;
    admin_password: string;
};

export default function CreateTenant({ plans }: { plans: Plan[] }) {
    const form = useForm<Field>({
        name: '',
        slug: '',
        plan_id: '',
        employee_id_prefix: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    const text = (key: keyof Field) => ({
        value: form.data[key],
        onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
            form.setData(key, e.target.value),
    });

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Tambah Tenant" />

            <PageBreadcrumb
                items={[
                    { title: 'Platform' },
                    { title: 'Tenant', href: index().url },
                    { title: 'Tambah' },
                ]}
            />
            <PageHeader
                title="Tambah Tenant"
                description="Tenant baru langsung punya 5 peran bawaan dan satu akun Company Admin yang terisolasi"
            />

            <div className="grid max-w-3xl gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Data Perusahaan</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="name">Nama Perusahaan</Label>
                            <Input id="name" {...text('name')} />
                            {form.errors.name && (
                                <p className="text-sm text-red-600">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input id="slug" {...text('slug')} />
                            {form.errors.slug && (
                                <p className="text-sm text-red-600">
                                    {form.errors.slug}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="prefix">Prefix ID Karyawan</Label>
                            <Input
                                id="prefix"
                                {...text('employee_id_prefix')}
                            />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
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
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Akun Company Admin</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="admin_name">Nama</Label>
                            <Input id="admin_name" {...text('admin_name')} />
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
                                {...text('admin_email')}
                            />
                            {form.errors.admin_email && (
                                <p className="text-sm text-red-600">
                                    {form.errors.admin_email}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="admin_password">Kata Sandi</Label>
                            <Input
                                id="admin_password"
                                type="password"
                                {...text('admin_password')}
                            />
                            {form.errors.admin_password && (
                                <p className="text-sm text-red-600">
                                    {form.errors.admin_password}
                                </p>
                            )}
                        </div>
                    </CardContent>
                    <CardFooter className="justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href={index().url}>Batal</Link>
                        </Button>
                        <Button
                            onClick={() => form.post(store().url)}
                            disabled={form.processing}
                        >
                            Buat Tenant
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>
    );
}
