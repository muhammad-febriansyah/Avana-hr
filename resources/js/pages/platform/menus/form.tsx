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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/platform/menus';

type RegistryMenu = {
    id: number;
    code: string;
    parent_id: number | null;
    label_default: string;
    icon: string | null;
    route_name: string | null;
    permission_code: string | null;
    feature_code: string | null;
    sort_order: number;
    is_core: boolean;
    is_active: boolean;
};

type Parent = { id: number; label_default: string };

type Props = {
    menu: RegistryMenu | null;
    parents: Parent[];
    permissionOptions: string[];
    featureOptions: string[];
    iconOptions: string[];
};

const NONE = '__none__';

export default function MenuForm({
    menu,
    parents,
    permissionOptions,
    featureOptions,
    iconOptions,
}: Props) {
    const form = useForm({
        code: menu?.code ?? '',
        label_default: menu?.label_default ?? '',
        icon: menu?.icon ?? NONE,
        route_name: menu?.route_name ?? '',
        parent_id: menu?.parent_id ? String(menu.parent_id) : NONE,
        permission_code: menu?.permission_code ?? NONE,
        feature_code: menu?.feature_code ?? NONE,
        sort_order: menu?.sort_order ?? 0,
        is_core: menu?.is_core ?? false,
        is_active: menu?.is_active ?? true,
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            icon: data.icon === NONE ? null : data.icon,
            route_name: data.route_name || null,
            parent_id: data.parent_id === NONE ? null : Number(data.parent_id),
            permission_code:
                data.permission_code === NONE ? null : data.permission_code,
            feature_code: data.feature_code === NONE ? null : data.feature_code,
        }));

        if (menu) {
            form.put(update(menu.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title={menu ? 'Edit Menu' : 'Tambah Menu'} />

            <PageBreadcrumb
                items={[
                    { title: 'Platform' },
                    { title: 'Registry Menu', href: index().url },
                    { title: menu ? 'Edit' : 'Tambah' },
                ]}
            />
            <PageHeader
                title={
                    menu ? `Edit Menu: ${menu.label_default}` : 'Tambah Menu'
                }
                description="Menu tampil di sidebar tenant sesuai paket & permission"
            />

            <Card className="max-w-3xl">
                <CardHeader>
                    <CardTitle>Detail Menu</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <Field label="Kode" error={form.errors.code}>
                        <Input
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                        />
                    </Field>
                    <Field label="Label" error={form.errors.label_default}>
                        <Input
                            value={form.data.label_default}
                            onChange={(e) =>
                                form.setData('label_default', e.target.value)
                            }
                        />
                    </Field>

                    <Field label="Icon">
                        <Selectable
                            value={form.data.icon}
                            onChange={(v) => form.setData('icon', v)}
                            options={iconOptions}
                            placeholder="Tanpa icon"
                        />
                    </Field>
                    <Field label="Induk (Parent)">
                        <Select
                            value={form.data.parent_id}
                            onValueChange={(v) => form.setData('parent_id', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>
                                    (Menu Utama)
                                </SelectItem>
                                {parents
                                    .filter((p) => p.id !== menu?.id)
                                    .map((p) => (
                                        <SelectItem
                                            key={p.id}
                                            value={String(p.id)}
                                        >
                                            {p.label_default}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <div className="sm:col-span-2">
                        <Field
                            label="Route Name"
                            error={form.errors.route_name}
                        >
                            <Input
                                value={form.data.route_name}
                                placeholder="mis. dashboard, roles.index"
                                onChange={(e) =>
                                    form.setData('route_name', e.target.value)
                                }
                            />
                        </Field>
                    </div>

                    <Field label="Permission">
                        <Selectable
                            value={form.data.permission_code}
                            onChange={(v) => form.setData('permission_code', v)}
                            options={permissionOptions}
                            placeholder="Tanpa permission"
                        />
                    </Field>
                    <Field label="Feature (gate paket)">
                        <Selectable
                            value={form.data.feature_code}
                            onChange={(v) => form.setData('feature_code', v)}
                            options={featureOptions}
                            placeholder="Selalu tersedia"
                        />
                    </Field>

                    <Field label="Urutan" error={form.errors.sort_order}>
                        <Input
                            type="number"
                            value={form.data.sort_order}
                            onChange={(e) =>
                                form.setData(
                                    'sort_order',
                                    Number(e.target.value),
                                )
                            }
                        />
                    </Field>

                    <div className="flex items-end gap-6">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_core}
                                onCheckedChange={(c) =>
                                    form.setData('is_core', c === true)
                                }
                            />
                            Inti
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_active}
                                onCheckedChange={(c) =>
                                    form.setData('is_active', c === true)
                                }
                            />
                            Aktif
                        </label>
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

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Selectable({
    value,
    onChange,
    options,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    options: string[];
    placeholder: string;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE}>{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option} value={option}>
                        {option}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
