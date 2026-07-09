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
import { Eye, EyeOff, GripVertical, RotateCcw, Users } from 'lucide-react';
import { useState } from 'react';
import { MenuIcon } from '@/components/menu-icon';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { reset as resetRoute, update } from '@/routes/settings/menus';

type MenuItem = {
    id: number;
    code: string;
    label_default: string;
    label_alias: string | null;
    icon: string | null;
    is_core: boolean;
    is_visible: boolean;
    sort_order: number;
    parent_id: number | null;
    role_ids: number[];
};

type Group = MenuItem & { children: MenuItem[] };
type Role = { id: number; name: string };

type Props = {
    menus: Group[];
    roles: Role[];
};

export default function MenuSettings({ menus, roles }: Props) {
    const [groups, setGroups] = useState<Group[]>(menus);
    const [roleDialog, setRoleDialog] = useState<MenuItem | null>(null);
    const form = useForm({});

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const parentIds = groups.map((g) => g.id);

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

                const from = g.children.findIndex((c) => c.id === active.id);
                const to = g.children.findIndex((c) => c.id === over.id);

                if (from < 0 || to < 0) {
                    return g;
                }

                const children = [...g.children];
                const [moved] = children.splice(from, 1);
                children.splice(to, 0, moved);

                return { ...g, children };
            }),
        );
    };

    // Move a leaf menu to another group or promote it to top level.
    const moveItem = (itemId: number, targetParentId: number | null) => {
        setGroups((prev) => {
            let found: MenuItem | undefined;

            let next = prev.filter((g) => {
                if (g.id === itemId) {
                    found = g;

                    return false;
                }

                return true;
            });

            if (!found) {
                next = next.map((g) => {
                    const idx = g.children.findIndex((c) => c.id === itemId);

                    if (idx >= 0) {
                        found = g.children[idx];

                        return {
                            ...g,
                            children: g.children.filter((c) => c.id !== itemId),
                        };
                    }

                    return g;
                });
            }

            if (!found) {
                return prev;
            }

            const moved: Group = { ...found, children: [] };

            if (targetParentId === null) {
                return [...next, { ...moved, parent_id: null }];
            }

            return next.map((g) =>
                g.id === targetParentId
                    ? {
                          ...g,
                          children: [
                              ...g.children,
                              { ...moved, parent_id: g.id },
                          ],
                      }
                    : g,
            );
        });
    };

    const groupOptions = groups.map((g) => ({
        id: g.id,
        label: g.label_alias || g.label_default,
    }));

    const patchItem = (id: number, patch: Partial<MenuItem>) => {
        setGroups((prev) =>
            prev.map((g) => ({
                ...g,
                ...(g.id === id ? patch : {}),
                children: g.children.map((c) =>
                    c.id === id ? { ...c, ...patch } : c,
                ),
            })),
        );
        setRoleDialog((current) =>
            current && current.id === id ? { ...current, ...patch } : current,
        );
    };

    const flatten = () => {
        const items: Array<{
            menu_id: number;
            is_visible: boolean;
            label_alias: string | null;
            sort_order: number;
            parent_id: number | null;
            role_ids: number[];
        }> = [];
        groups.forEach((group, gi) => {
            items.push({
                menu_id: group.id,
                is_visible: group.is_visible,
                label_alias: group.label_alias || null,
                sort_order: gi,
                parent_id: null,
                role_ids: group.role_ids,
            });
            group.children.forEach((child, ci) => {
                items.push({
                    menu_id: child.id,
                    is_visible: child.is_visible,
                    label_alias: child.label_alias || null,
                    sort_order: ci,
                    parent_id: group.id,
                    role_ids: child.role_ids,
                });
            });
        });

        return items;
    };

    const save = () => {
        form.transform(() => ({ items: flatten() }));
        form.put(update().url, { preserveScroll: true });
    };

    const reset = () => {
        router.delete(resetRoute().url, {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['menus'] }),
        });
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Pengaturan Menu" />

            <PageBreadcrumb
                items={[{ title: 'Pengaturan' }, { title: 'Menu' }]}
            />
            <PageHeader
                title="Pengaturan Menu"
                description="Atur tampilan, urutan, grup, dan nama menu sidebar. Seret untuk mengubah urutan."
                action={
                    <div className="flex gap-2">
                        <ConfirmDialog
                            title="Kembalikan ke default?"
                            description="Semua penyesuaian menu tenant ini akan dihapus."
                            confirmLabel="Ya, Reset"
                            onConfirm={reset}
                            trigger={
                                <Button variant="outline" size="sm">
                                    <RotateCcw className="size-4" />
                                    Reset
                                </Button>
                            }
                        />
                        <Button
                            size="sm"
                            onClick={save}
                            disabled={form.processing}
                        >
                            Simpan
                        </Button>
                    </div>
                }
            />

            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={reorderParents}
            >
                <SortableContext
                    items={parentIds}
                    strategy={verticalListSortingStrategy}
                >
                    <div className="flex flex-col gap-2">
                        {groups.map((group) => (
                            <GroupRow
                                key={group.id}
                                group={group}
                                roles={roles}
                                groupOptions={groupOptions}
                                onPatch={patchItem}
                                onMove={moveItem}
                                onRoleClick={setRoleDialog}
                                onReorderChildren={reorderChildren(group.id)}
                                sensors={sensors}
                            />
                        ))}
                    </div>
                </SortableContext>
            </DndContext>

            <RoleDialog
                item={roleDialog}
                roles={roles}
                onClose={() => setRoleDialog(null)}
                onToggle={(item, roleId, checked) =>
                    patchItem(item.id, {
                        role_ids: checked
                            ? [...item.role_ids, roleId]
                            : item.role_ids.filter((r) => r !== roleId),
                    })
                }
            />
        </div>
    );
}

type GroupOption = { id: number; label: string };

function GroupRow({
    group,
    roles,
    groupOptions,
    onPatch,
    onMove,
    onRoleClick,
    onReorderChildren,
    sensors,
}: {
    group: Group;
    roles: Role[];
    groupOptions: GroupOption[];
    onPatch: (id: number, patch: Partial<MenuItem>) => void;
    onMove: (itemId: number, targetParentId: number | null) => void;
    onRoleClick: (item: MenuItem) => void;
    onReorderChildren: (event: DragEndEvent) => void;
    sensors: ReturnType<typeof useSensors>;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: group.id });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'rounded-lg border border-border bg-card',
                isDragging && 'opacity-60 shadow-lg',
            )}
        >
            <MenuRowContent
                item={group}
                roleCount={group.role_ids.length}
                roleTotal={roles.length}
                currentParentId={null}
                // A group with children stays top-level (keeps nesting ≤ 2).
                groupOptions={group.children.length > 0 ? [] : groupOptions}
                onPatch={onPatch}
                onMove={onMove}
                onRoleClick={onRoleClick}
                dragHandle={
                    <button
                        type="button"
                        className="cursor-grab touch-none text-muted-foreground"
                        {...attributes}
                        {...listeners}
                        aria-label="Seret"
                    >
                        <GripVertical className="size-4" />
                    </button>
                }
            />

            {group.children.length > 0 && (
                <div className="border-t border-border pl-8">
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={onReorderChildren}
                    >
                        <SortableContext
                            items={group.children.map((c) => c.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            {group.children.map((child) => (
                                <ChildRow
                                    key={child.id}
                                    item={child}
                                    roles={roles}
                                    groupOptions={groupOptions}
                                    parentId={group.id}
                                    onPatch={onPatch}
                                    onMove={onMove}
                                    onRoleClick={onRoleClick}
                                />
                            ))}
                        </SortableContext>
                    </DndContext>
                </div>
            )}
        </div>
    );
}

function ChildRow({
    item,
    roles,
    groupOptions,
    parentId,
    onPatch,
    onMove,
    onRoleClick,
}: {
    item: MenuItem;
    roles: Role[];
    groupOptions: GroupOption[];
    parentId: number;
    onPatch: (id: number, patch: Partial<MenuItem>) => void;
    onMove: (itemId: number, targetParentId: number | null) => void;
    onRoleClick: (item: MenuItem) => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: item.id });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'border-b border-border last:border-b-0',
                isDragging && 'opacity-60',
            )}
        >
            <MenuRowContent
                item={item}
                roleCount={item.role_ids.length}
                roleTotal={roles.length}
                currentParentId={parentId}
                groupOptions={groupOptions}
                onPatch={onPatch}
                onMove={onMove}
                onRoleClick={onRoleClick}
                dragHandle={
                    <button
                        type="button"
                        className="cursor-grab touch-none text-muted-foreground"
                        {...attributes}
                        {...listeners}
                        aria-label="Seret"
                    >
                        <GripVertical className="size-4" />
                    </button>
                }
            />
        </div>
    );
}

const ROOT_VALUE = 'root';

function MenuRowContent({
    item,
    roleCount,
    roleTotal,
    currentParentId,
    groupOptions,
    onPatch,
    onMove,
    onRoleClick,
    dragHandle,
}: {
    item: MenuItem;
    roleCount: number;
    roleTotal: number;
    currentParentId: number | null;
    groupOptions: GroupOption[];
    onPatch: (id: number, patch: Partial<MenuItem>) => void;
    onMove: (itemId: number, targetParentId: number | null) => void;
    onRoleClick: (item: MenuItem) => void;
    dragHandle: React.ReactNode;
}) {
    const otherGroups = groupOptions.filter((g) => g.id !== item.id);

    return (
        <div className="flex items-center gap-3 p-3">
            {dragHandle}
            <MenuIcon
                name={item.icon}
                className="size-4 shrink-0 text-muted-foreground"
            />
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <Input
                    value={item.label_alias ?? ''}
                    placeholder={item.label_default}
                    onChange={(e) =>
                        onPatch(item.id, {
                            label_alias: e.target.value || null,
                        })
                    }
                    className="h-8 max-w-56"
                />
                {item.is_core && <Badge variant="secondary">Inti</Badge>}
            </div>

            {otherGroups.length > 0 && (
                <Select
                    value={
                        currentParentId === null
                            ? ROOT_VALUE
                            : String(currentParentId)
                    }
                    onValueChange={(v) =>
                        onMove(item.id, v === ROOT_VALUE ? null : Number(v))
                    }
                >
                    <SelectTrigger className="h-8 w-40" size="sm">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ROOT_VALUE}>Menu Utama</SelectItem>
                        {otherGroups.map((g) => (
                            <SelectItem key={g.id} value={String(g.id)}>
                                {g.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

            <Button
                variant="ghost"
                size="sm"
                onClick={() => onRoleClick(item)}
                className="gap-1"
            >
                <Users className="size-4" />
                {roleCount === 0 ? 'Semua role' : `${roleCount}/${roleTotal}`}
            </Button>

            <Button
                variant="ghost"
                size="icon"
                disabled={item.is_core}
                onClick={() =>
                    onPatch(item.id, { is_visible: !item.is_visible })
                }
                aria-label={item.is_visible ? 'Sembunyikan' : 'Tampilkan'}
            >
                {item.is_visible ? (
                    <Eye className="size-4" />
                ) : (
                    <EyeOff className="size-4 text-muted-foreground" />
                )}
            </Button>
        </div>
    );
}

function RoleDialog({
    item,
    roles,
    onClose,
    onToggle,
}: {
    item: MenuItem | null;
    roles: Role[];
    onClose: () => void;
    onToggle: (item: MenuItem, roleId: number, checked: boolean) => void;
}) {
    return (
        <Dialog
            open={item !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Visibilitas per Role</DialogTitle>
                    <DialogDescription>
                        Tanpa pilihan = tampil untuk semua role (yang punya
                        permission). Permission tetap otoritas akhir.
                    </DialogDescription>
                </DialogHeader>

                {item && (
                    <div className="flex flex-col gap-2">
                        {roles.map((role) => (
                            <label
                                key={role.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={item.role_ids.includes(role.id)}
                                    onCheckedChange={(c) =>
                                        onToggle(item, role.id, c === true)
                                    }
                                />
                                {role.name}
                            </label>
                        ))}
                    </div>
                )}

                <DialogFooter>
                    <Button onClick={onClose}>Selesai</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
