import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronRight,
    Eye,
    GripVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { MenuIcon } from '@/components/menu-icon';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    availability,
    destroy,
    index,
    reorder,
    store,
    update,
} from '@/routes/platform/menus';
import type { MenuNode } from '@/types';

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
    children?: RegistryMenu[];
};

type Tenant = { id: number; name: string; plan_id: number | null };
type RoleOption = { id: number; name: string };

type Props = {
    menus: RegistryMenu[];
    tenants: Tenant[];
    overrides: Record<number, Record<number, boolean>>;
    permissionOptions: string[];
    featureOptions: string[];
    iconOptions: string[];
    previewRoles: RoleOption[];
    preview: MenuNode[] | null;
};

const NONE = '__none__';

export default function PlatformMenus({
    menus,
    tenants,
    overrides,
    permissionOptions,
    featureOptions,
    iconOptions,
    previewRoles,
    preview,
}: Props) {
    const [editing, setEditing] = useState<RegistryMenu | null>(null);
    const [open, setOpen] = useState(false);
    const [tenantId, setTenantId] = useState<string>('');
    const [groups, setGroups] = useState<RegistryMenu[]>(menus);
    const [dirty, setDirty] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const flatMenus = menus.flatMap((m) => [m, ...(m.children ?? [])]);

    const openCreate = () => {
        setEditing(null);
        setOpen(true);
    };
    const openEdit = (menu: RegistryMenu) => {
        setEditing(menu);
        setOpen(true);
    };

    const reorderParents = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        setGroups((prev) => {
            const from = prev.findIndex((g) => g.id === active.id);
            const to = prev.findIndex((g) => g.id === over.id);

            if (from < 0 || to < 0) {
                return prev;
            }

            const next = [...prev];
            const [moved] = next.splice(from, 1);
            next.splice(to, 0, moved);

            return next;
        });
        setDirty(true);
    };

    const reorderChildren = (groupId: number) => (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        setGroups((prev) =>
            prev.map((g) => {
                if (g.id !== groupId) {
                    return g;
                }

                const kids = g.children ?? [];
                const from = kids.findIndex((c) => c.id === active.id);
                const to = kids.findIndex((c) => c.id === over.id);

                if (from < 0 || to < 0) {
                    return g;
                }

                const children = [...kids];
                const [moved] = children.splice(from, 1);
                children.splice(to, 0, moved);

                return { ...g, children };
            }),
        );
        setDirty(true);
    };

    const saveOrder = () => {
        const items: Array<{
            id: number;
            parent_id: number | null;
            sort_order: number;
        }> = [];
        groups.forEach((group, gi) => {
            items.push({ id: group.id, parent_id: null, sort_order: gi });
            (group.children ?? []).forEach((child, ci) => {
                items.push({
                    id: child.id,
                    parent_id: group.id,
                    sort_order: ci,
                });
            });
        });
        router.post(
            reorder().url,
            { items },
            { preserveScroll: true, onSuccess: () => setDirty(false) },
        );
    };

    const changePreviewTenant = (value: string) => {
        router.get(
            index().url,
            { preview_tenant_id: value },
            {
                only: ['previewRoles', 'preview'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const changePreviewRole = (tenant: string, role: string) => {
        router.get(
            index().url,
            { preview_tenant_id: tenant, preview_role_id: role },
            {
                only: ['preview'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Registry Menu" />

            <PageBreadcrumb
                items={[{ title: 'Platform' }, { title: 'Registry Menu' }]}
            />
            <PageHeader
                title="Registry Menu"
                description="Kelola daftar menu platform, urutan, dan ketersediaannya per tenant"
                action={
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        Tambah Menu
                    </Button>
                }
            />

            <Card>
                <CardHeader className="flex-row items-center justify-between space-y-0">
                    <CardTitle>Daftar Menu</CardTitle>
                    {dirty && (
                        <Button size="sm" onClick={saveOrder}>
                            Simpan Urutan
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="flex flex-col gap-1">
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={reorderParents}
                    >
                        <SortableContext
                            items={groups.map((g) => g.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            {groups.map((group) => (
                                <RegistryGroup
                                    key={group.id}
                                    group={group}
                                    sensors={sensors}
                                    onEdit={openEdit}
                                    onReorderChildren={reorderChildren(
                                        group.id,
                                    )}
                                />
                            ))}
                        </SortableContext>
                    </DndContext>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ketersediaan per Tenant</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="max-w-sm">
                        <Label className="mb-2 block">Tenant</Label>
                        <Select value={tenantId} onValueChange={setTenantId}>
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih tenant" />
                            </SelectTrigger>
                            <SelectContent>
                                {tenants.map((tenant) => (
                                    <SelectItem
                                        key={tenant.id}
                                        value={String(tenant.id)}
                                    >
                                        {tenant.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {tenantId && (
                        <div className="flex flex-col divide-y divide-border">
                            {flatMenus.map((menu) => (
                                <AvailabilityRow
                                    key={menu.id}
                                    menu={menu}
                                    tenantId={Number(tenantId)}
                                    current={
                                        overrides[Number(tenantId)]?.[menu.id]
                                    }
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            <PreviewCard
                tenants={tenants}
                previewRoles={previewRoles}
                preview={preview}
                onTenantChange={changePreviewTenant}
                onRoleChange={changePreviewRole}
            />

            <MenuFormDialog
                key={editing?.id ?? 'new'}
                open={open}
                onOpenChange={setOpen}
                editing={editing}
                parents={menus}
                permissionOptions={permissionOptions}
                featureOptions={featureOptions}
                iconOptions={iconOptions}
            />
        </div>
    );
}

function RegistryGroup({
    group,
    sensors,
    onEdit,
    onReorderChildren,
}: {
    group: RegistryMenu;
    sensors: ReturnType<typeof useSensors>;
    onEdit: (menu: RegistryMenu) => void;
    onReorderChildren: (event: DragEndEvent) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition } =
        useSortable({ id: group.id });
    const children = group.children ?? [];

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
        >
            <RegistryRow
                menu={group}
                onEdit={onEdit}
                dragHandle={
                    <DragHandle attributes={attributes} listeners={listeners} />
                }
            />
            {children.length > 0 && (
                <div className="ml-6 border-l border-border pl-2">
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={onReorderChildren}
                    >
                        <SortableContext
                            items={children.map((c) => c.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            {children.map((child) => (
                                <RegistryChild
                                    key={child.id}
                                    menu={child}
                                    onEdit={onEdit}
                                />
                            ))}
                        </SortableContext>
                    </DndContext>
                </div>
            )}
        </div>
    );
}

function RegistryChild({
    menu,
    onEdit,
}: {
    menu: RegistryMenu;
    onEdit: (menu: RegistryMenu) => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition } =
        useSortable({ id: menu.id });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
        >
            <RegistryRow
                menu={menu}
                nested
                onEdit={onEdit}
                dragHandle={
                    <DragHandle attributes={attributes} listeners={listeners} />
                }
            />
        </div>
    );
}

function DragHandle({
    attributes,
    listeners,
}: {
    attributes: ReturnType<typeof useSortable>['attributes'];
    listeners: ReturnType<typeof useSortable>['listeners'];
}) {
    return (
        <button
            type="button"
            className="cursor-grab touch-none text-muted-foreground"
            {...attributes}
            {...listeners}
            aria-label="Seret"
        >
            <GripVertical className="size-4" />
        </button>
    );
}

function RegistryRow({
    menu,
    nested = false,
    onEdit,
    dragHandle,
}: {
    menu: RegistryMenu;
    nested?: boolean;
    onEdit: (menu: RegistryMenu) => void;
    dragHandle: React.ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/50">
            {dragHandle}
            {nested && (
                <ChevronRight className="size-3 text-muted-foreground" />
            )}
            <MenuIcon
                name={menu.icon}
                className="size-4 shrink-0 text-muted-foreground"
            />
            <span className="font-medium">{menu.label_default}</span>
            <span className="text-xs text-muted-foreground">{menu.code}</span>

            <div className="ml-auto flex items-center gap-1">
                {menu.is_core && <Badge variant="secondary">Inti</Badge>}
                {!menu.is_active && <Badge variant="outline">Nonaktif</Badge>}
                {menu.feature_code && (
                    <Badge variant="outline">{menu.feature_code}</Badge>
                )}
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => onEdit(menu)}
                    aria-label={`Edit ${menu.label_default}`}
                >
                    <Pencil className="size-4" />
                </Button>
                <ConfirmDialog
                    title={`Hapus menu ${menu.label_default}?`}
                    description="Submenu akan menjadi menu tingkat atas."
                    onConfirm={() =>
                        router.delete(destroy(menu.id).url, {
                            preserveScroll: true,
                        })
                    }
                    trigger={
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Hapus ${menu.label_default}`}
                        >
                            <Trash2 className="size-4 text-red-600" />
                        </Button>
                    }
                />
            </div>
        </div>
    );
}

function AvailabilityRow({
    menu,
    tenantId,
    current,
}: {
    menu: RegistryMenu;
    tenantId: number;
    current: boolean | undefined;
}) {
    // Tri-state: default (follow plan), forced on, forced off.
    const value = current === undefined ? 'default' : current ? 'on' : 'off';

    const change = (next: string) => {
        router.post(
            availability().url,
            {
                tenant_id: tenantId,
                menu_id: menu.id,
                is_enabled: next === 'default' ? null : next === 'on',
            },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <div className="flex items-center gap-3 py-2">
            <span className="flex-1">{menu.label_default}</span>
            <Select value={value} onValueChange={change}>
                <SelectTrigger size="sm" className="w-36">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="default">Ikut Paket</SelectItem>
                    <SelectItem value="on">Selalu Aktif</SelectItem>
                    <SelectItem value="off">Selalu Nonaktif</SelectItem>
                </SelectContent>
            </Select>
        </div>
    );
}

function PreviewCard({
    tenants,
    previewRoles,
    preview,
    onTenantChange,
    onRoleChange,
}: {
    tenants: Tenant[];
    previewRoles: RoleOption[];
    preview: MenuNode[] | null;
    onTenantChange: (tenantId: string) => void;
    onRoleChange: (tenantId: string, roleId: string) => void;
}) {
    const [tenantId, setTenantId] = useState('');
    const [roleId, setRoleId] = useState('');

    return (
        <Card>
            <CardHeader>
                <CardTitle>Preview Sidebar (sebagai tenant & role)</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label className="mb-2 block">Tenant</Label>
                        <Select
                            value={tenantId}
                            onValueChange={(v) => {
                                setTenantId(v);
                                setRoleId('');
                                onTenantChange(v);
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih tenant" />
                            </SelectTrigger>
                            <SelectContent>
                                {tenants.map((tenant) => (
                                    <SelectItem
                                        key={tenant.id}
                                        value={String(tenant.id)}
                                    >
                                        {tenant.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label className="mb-2 block">Role</Label>
                        <Select
                            value={roleId}
                            disabled={!tenantId}
                            onValueChange={(v) => {
                                setRoleId(v);
                                onRoleChange(tenantId, v);
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua (tanpa filter role)" />
                            </SelectTrigger>
                            <SelectContent>
                                {previewRoles.map((role) => (
                                    <SelectItem
                                        key={role.id}
                                        value={String(role.id)}
                                    >
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {preview === null ? (
                    <EmptyState
                        icon={Eye}
                        title="Pilih tenant untuk melihat preview"
                        description="Sidebar dirender sesuai paket, setting, dan role terpilih."
                    />
                ) : (
                    <div className="rounded-md border border-border p-3">
                        <PreviewTree nodes={preview} />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function PreviewTree({ nodes }: { nodes: MenuNode[] }) {
    if (nodes.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Tidak ada menu untuk kombinasi ini.
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-1 text-sm">
            {nodes.map((node) => (
                <li key={node.code}>
                    <div className="flex items-center gap-2">
                        <MenuIcon
                            name={node.icon}
                            className="size-4 text-muted-foreground"
                        />
                        <span>{node.label}</span>
                        {node.url === null && (
                            <span className="text-xs text-muted-foreground">
                                (grup)
                            </span>
                        )}
                    </div>
                    {node.children.length > 0 && (
                        <ul className="mt-1 ml-6 flex flex-col gap-1 text-muted-foreground">
                            {node.children.map((child) => (
                                <li key={child.code}>{child.label}</li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ul>
    );
}

function MenuFormDialog({
    open,
    onOpenChange,
    editing,
    parents,
    permissionOptions,
    featureOptions,
    iconOptions,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: RegistryMenu | null;
    parents: RegistryMenu[];
    permissionOptions: string[];
    featureOptions: string[];
    iconOptions: string[];
}) {
    const form = useForm({
        code: editing?.code ?? '',
        label_default: editing?.label_default ?? '',
        icon: editing?.icon ?? '',
        route_name: editing?.route_name ?? '',
        parent_id: editing?.parent_id ? String(editing.parent_id) : NONE,
        permission_code: editing?.permission_code ?? NONE,
        feature_code: editing?.feature_code ?? NONE,
        sort_order: editing?.sort_order ?? 0,
        is_core: editing?.is_core ?? false,
        is_active: editing?.is_active ?? true,
    });

    const submit = () => {
        const payload = {
            ...form.data,
            parent_id:
                form.data.parent_id === NONE
                    ? null
                    : Number(form.data.parent_id),
            permission_code:
                form.data.permission_code === NONE
                    ? null
                    : form.data.permission_code,
            feature_code:
                form.data.feature_code === NONE ? null : form.data.feature_code,
            icon: form.data.icon || null,
            route_name: form.data.route_name || null,
        };
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (editing) {
            router.put(update(editing.id).url, payload, options);
        } else {
            router.post(store().url, payload, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Menu' : 'Tambah Menu'}
                    </DialogTitle>
                    <DialogDescription>
                        Menu tampil di sidebar tenant sesuai paket & permission.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2 sm:grid-cols-2">
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
                                    form.setData(
                                        'label_default',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <Field label="Icon">
                            <Selectable
                                value={form.data.icon || NONE}
                                onChange={(v) =>
                                    form.setData('icon', v === NONE ? '' : v)
                                }
                                options={iconOptions}
                                placeholder="Tanpa icon"
                            />
                        </Field>
                        <Field label="Induk (Parent)">
                            <Select
                                value={form.data.parent_id}
                                onValueChange={(v) =>
                                    form.setData('parent_id', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        (Menu Utama)
                                    </SelectItem>
                                    {parents
                                        .filter((p) => p.id !== editing?.id)
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
                    </div>

                    <Field label="Route Name" error={form.errors.route_name}>
                        <Input
                            value={form.data.route_name}
                            placeholder="mis. dashboard, roles.index"
                            onChange={(e) =>
                                form.setData('route_name', e.target.value)
                            }
                        />
                    </Field>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <Field label="Permission">
                            <Selectable
                                value={form.data.permission_code}
                                onChange={(v) =>
                                    form.setData('permission_code', v)
                                }
                                options={permissionOptions}
                                placeholder="Tanpa permission"
                            />
                        </Field>
                        <Field label="Feature (gate paket)">
                            <Selectable
                                value={form.data.feature_code}
                                onChange={(v) =>
                                    form.setData('feature_code', v)
                                }
                                options={featureOptions}
                                placeholder="Selalu tersedia"
                            />
                        </Field>
                    </div>

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

                    <div className="flex gap-6">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_core}
                                onCheckedChange={(c) =>
                                    form.setData('is_core', c === true)
                                }
                            />
                            Inti (tak bisa disembunyikan tenant)
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
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
