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
import { Head, Link, router } from '@inertiajs/react';
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
    create,
    destroy,
    edit,
    index,
    reorder,
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
    previewRoles: RoleOption[];
    preview: MenuNode[] | null;
};

export default function PlatformMenus({
    menus,
    tenants,
    overrides,
    previewRoles,
    preview,
}: Props) {
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

    const changePreview = (tenant: string, role?: string) => {
        router.get(
            index().url,
            role
                ? { preview_tenant_id: tenant, preview_role_id: role }
                : { preview_tenant_id: tenant },
            {
                only: role ? ['preview'] : ['previewRoles', 'preview'],
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
                    <Button asChild>
                        <Link href={create().url}>
                            <Plus className="size-4" />
                            Tambah Menu
                        </Link>
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
                onChange={changePreview}
            />
        </div>
    );
}

function RegistryGroup({
    group,
    sensors,
    onReorderChildren,
}: {
    group: RegistryMenu;
    sensors: ReturnType<typeof useSensors>;
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
                                <RegistryChild key={child.id} menu={child} />
                            ))}
                        </SortableContext>
                    </DndContext>
                </div>
            )}
        </div>
    );
}

function RegistryChild({ menu }: { menu: RegistryMenu }) {
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
    dragHandle,
}: {
    menu: RegistryMenu;
    nested?: boolean;
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
                    asChild
                    aria-label={`Edit ${menu.label_default}`}
                >
                    <Link href={edit(menu.id).url}>
                        <Pencil className="size-4" />
                    </Link>
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
    onChange,
}: {
    tenants: Tenant[];
    previewRoles: RoleOption[];
    preview: MenuNode[] | null;
    onChange: (tenantId: string, roleId?: string) => void;
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
                                onChange(v);
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
                                onChange(tenantId, v);
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
