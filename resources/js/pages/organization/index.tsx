import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronRight,
    GitBranch,
    Layers,
    Network,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { CurrencyInput } from '@/components/shared/currency-input';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
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
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import * as gradeRoutes from '@/routes/grades';
import * as orgUnitRoutes from '@/routes/org-units';
import * as positionRoutes from '@/routes/positions';

type OrgUnit = {
    id: number;
    parent_id: number | null;
    name: string;
    type: string;
    type_label: string;
    cost_center: string | null;
    effective_date: string | null;
    positions_count: number;
};

type Grade = {
    id: number;
    code: string;
    name: string;
    salary_min: number;
    salary_max: number;
    salary_min_formatted: string;
    salary_max_formatted: string;
};

type Position = {
    id: number;
    name: string;
    org_unit_id: number;
    org_unit_name: string | null;
    grade_id: number | null;
    grade_code: string | null;
    reports_to_position_id: number | null;
    reports_to_name: string | null;
};

type TypeOption = { value: string; label: string };

type Props = {
    orgUnits: OrgUnit[];
    grades: Grade[];
    positions: Position[];
    orgUnitTypes: TypeOption[];
};

type Tab = 'structure' | 'grades' | 'chart';

const NONE = '__none__';

export default function OrganizationIndex({
    orgUnits,
    grades,
    positions,
    orgUnitTypes,
}: Props) {
    const { can } = usePermissions();
    const canManage = can('organization.manage');
    const [tab, setTab] = useState<Tab>('structure');

    const tabs: { key: Tab; label: string; icon: typeof Network }[] = [
        { key: 'structure', label: 'Struktur & Posisi', icon: Network },
        { key: 'grades', label: 'Grade', icon: Layers },
        { key: 'chart', label: 'Bagan Organisasi', icon: GitBranch },
    ];

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Struktur Organisasi" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Struktur Organisasi' },
                ]}
            />
            <PageHeader
                title="Struktur Organisasi"
                description="Kelola unit organisasi, posisi, grade, dan garis pelaporan"
            />

            <div className="flex flex-wrap gap-1 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => setTab(t.key)}
                        className={
                            'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors ' +
                            (tab === t.key
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground')
                        }
                    >
                        <t.icon className="size-4" />
                        {t.label}
                    </button>
                ))}
            </div>

            {tab === 'structure' && (
                <div className="grid gap-5 lg:grid-cols-[1fr_1.3fr]">
                    <UnitsPanel
                        orgUnits={orgUnits}
                        orgUnitTypes={orgUnitTypes}
                        canManage={canManage}
                    />
                    <PositionsPanel
                        positions={positions}
                        orgUnits={orgUnits}
                        grades={grades}
                        canManage={canManage}
                    />
                </div>
            )}

            {tab === 'grades' && (
                <GradesPanel grades={grades} canManage={canManage} />
            )}

            {tab === 'chart' && <OrgChart positions={positions} />}
        </div>
    );
}

/* ------------------------------------------------------------------ Units */

function UnitsPanel({
    orgUnits,
    orgUnitTypes,
    canManage,
}: {
    orgUnits: OrgUnit[];
    orgUnitTypes: TypeOption[];
    canManage: boolean;
}) {
    const [dialog, setDialog] = useState<{
        editing: OrgUnit | null;
        parentId: number | null;
    } | null>(null);

    const roots = orgUnits.filter((u) => u.parent_id === null);
    const childrenOf = (id: number) =>
        orgUnits.filter((u) => u.parent_id === id);

    const remove = (unit: OrgUnit) =>
        router.delete(orgUnitRoutes.destroy(unit.id).url, {
            preserveScroll: true,
        });

    return (
        <Card>
            <CardContent className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold">Unit Organisasi</h2>
                    {canManage && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                setDialog({ editing: null, parentId: null })
                            }
                        >
                            <Plus className="size-4" />
                            Unit
                        </Button>
                    )}
                </div>

                {roots.length === 0 ? (
                    <EmptyState
                        icon={Network}
                        title="Belum ada unit"
                        description="Buat unit tingkat atas (perusahaan) dulu."
                    />
                ) : (
                    <div className="flex flex-col">
                        {roots.map((unit) => (
                            <UnitNode
                                key={unit.id}
                                unit={unit}
                                depth={0}
                                childrenOf={childrenOf}
                                canManage={canManage}
                                onAddChild={(parentId) =>
                                    setDialog({ editing: null, parentId })
                                }
                                onEdit={(u) =>
                                    setDialog({
                                        editing: u,
                                        parentId: u.parent_id,
                                    })
                                }
                                onRemove={remove}
                            />
                        ))}
                    </div>
                )}
            </CardContent>

            {dialog && (
                <UnitDialog
                    editing={dialog.editing}
                    parentId={dialog.parentId}
                    orgUnits={orgUnits}
                    orgUnitTypes={orgUnitTypes}
                    onClose={() => setDialog(null)}
                />
            )}
        </Card>
    );
}

function UnitNode({
    unit,
    depth,
    childrenOf,
    canManage,
    onAddChild,
    onEdit,
    onRemove,
}: {
    unit: OrgUnit;
    depth: number;
    childrenOf: (id: number) => OrgUnit[];
    canManage: boolean;
    onAddChild: (parentId: number) => void;
    onEdit: (unit: OrgUnit) => void;
    onRemove: (unit: OrgUnit) => void;
}) {
    const kids = childrenOf(unit.id);

    return (
        <>
            <div
                className="group flex items-center gap-2 rounded-md py-1.5 pr-1 hover:bg-muted/50"
                style={{ paddingLeft: depth * 20 }}
            >
                <ChevronRight className="size-3 shrink-0 text-muted-foreground" />
                <span className="font-medium">{unit.name}</span>
                <Badge variant="secondary" className="font-normal">
                    {unit.type_label}
                </Badge>
                {unit.positions_count > 0 && (
                    <span className="text-xs text-muted-foreground">
                        {unit.positions_count} posisi
                    </span>
                )}
                {canManage && (
                    <div className="ml-auto flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            onClick={() => onAddChild(unit.id)}
                            aria-label="Tambah sub-unit"
                        >
                            <Plus className="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            onClick={() => onEdit(unit)}
                            aria-label="Edit unit"
                        >
                            <Pencil className="size-3.5" />
                        </Button>
                        <ConfirmDialog
                            title={`Hapus ${unit.name}?`}
                            onConfirm={() => onRemove(unit)}
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    aria-label="Hapus unit"
                                >
                                    <Trash2 className="size-3.5 text-red-600" />
                                </Button>
                            }
                        />
                    </div>
                )}
            </div>
            {kids.map((child) => (
                <UnitNode
                    key={child.id}
                    unit={child}
                    depth={depth + 1}
                    childrenOf={childrenOf}
                    canManage={canManage}
                    onAddChild={onAddChild}
                    onEdit={onEdit}
                    onRemove={onRemove}
                />
            ))}
        </>
    );
}

function UnitDialog({
    editing,
    parentId,
    orgUnits,
    orgUnitTypes,
    onClose,
}: {
    editing: OrgUnit | null;
    parentId: number | null;
    orgUnits: OrgUnit[];
    orgUnitTypes: TypeOption[];
    onClose: () => void;
}) {
    const form = useForm({
        name: editing?.name ?? '',
        type: editing?.type ?? orgUnitTypes[0]?.value ?? 'company',
        parent_id:
            (editing?.parent_id ?? parentId)
                ? String(editing?.parent_id ?? parentId)
                : NONE,
        cost_center: editing?.cost_center ?? '',
        effective_date: editing?.effective_date ?? '',
    });

    const submit = () => {
        const payload = {
            ...form.data,
            parent_id:
                form.data.parent_id === NONE
                    ? null
                    : Number(form.data.parent_id),
            cost_center: form.data.cost_center || null,
            effective_date: form.data.effective_date || null,
        };
        const options = { preserveScroll: true, onSuccess: onClose };

        if (editing) {
            router.put(orgUnitRoutes.update(editing.id).url, payload, options);
        } else {
            router.post(orgUnitRoutes.store().url, payload, options);
        }
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Unit' : 'Tambah Unit'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>Nama Unit</Label>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        {form.errors.name && (
                            <p className="text-sm text-red-600">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Tipe</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(v) => form.setData('type', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {orgUnitTypes.map((t) => (
                                        <SelectItem
                                            key={t.value}
                                            value={t.value}
                                        >
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Induk</Label>
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
                                        (Tanpa induk)
                                    </SelectItem>
                                    {orgUnits
                                        .filter((u) => u.id !== editing?.id)
                                        .map((u) => (
                                            <SelectItem
                                                key={u.id}
                                                value={String(u.id)}
                                            >
                                                {u.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {form.errors.parent_id && (
                                <p className="text-sm text-red-600">
                                    {form.errors.parent_id}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Cost Center</Label>
                            <Input
                                value={form.data.cost_center}
                                onChange={(e) =>
                                    form.setData('cost_center', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Tanggal Efektif</Label>
                            <Input
                                type="date"
                                value={form.data.effective_date}
                                onChange={(e) =>
                                    form.setData(
                                        'effective_date',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
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

/* -------------------------------------------------------------- Positions */

function PositionsPanel({
    positions,
    orgUnits,
    grades,
    canManage,
}: {
    positions: Position[];
    orgUnits: OrgUnit[];
    grades: Grade[];
    canManage: boolean;
}) {
    const [dialog, setDialog] = useState<{ editing: Position | null } | null>(
        null,
    );

    const remove = (position: Position) =>
        router.delete(positionRoutes.destroy(position.id).url, {
            preserveScroll: true,
        });

    return (
        <Card>
            <CardContent className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold">Posisi</h2>
                    {canManage && (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={orgUnits.length === 0}
                            onClick={() => setDialog({ editing: null })}
                        >
                            <Plus className="size-4" />
                            Posisi
                        </Button>
                    )}
                </div>

                {positions.length === 0 ? (
                    <EmptyState
                        icon={Layers}
                        title="Belum ada posisi"
                        description="Tambahkan posisi dan garis pelaporannya."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Posisi</TableHead>
                                    <TableHead>Unit</TableHead>
                                    <TableHead>Grade</TableHead>
                                    <TableHead>Atasan</TableHead>
                                    {canManage && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {positions.map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell className="font-medium">
                                            {p.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {p.org_unit_name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {p.grade_code ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {p.reports_to_name ?? '-'}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    onClick={() =>
                                                        setDialog({
                                                            editing: p,
                                                        })
                                                    }
                                                    aria-label={`Edit ${p.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDialog
                                                    title={`Hapus ${p.name}?`}
                                                    onConfirm={() => remove(p)}
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Hapus ${p.name}`}
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

            {dialog && (
                <PositionDialog
                    editing={dialog.editing}
                    orgUnits={orgUnits}
                    grades={grades}
                    positions={positions}
                    onClose={() => setDialog(null)}
                />
            )}
        </Card>
    );
}

function PositionDialog({
    editing,
    orgUnits,
    grades,
    positions,
    onClose,
}: {
    editing: Position | null;
    orgUnits: OrgUnit[];
    grades: Grade[];
    positions: Position[];
    onClose: () => void;
}) {
    const form = useForm({
        name: editing?.name ?? '',
        org_unit_id: editing?.org_unit_id
            ? String(editing.org_unit_id)
            : orgUnits[0]
              ? String(orgUnits[0].id)
              : '',
        grade_id: editing?.grade_id ? String(editing.grade_id) : NONE,
        reports_to_position_id: editing?.reports_to_position_id
            ? String(editing.reports_to_position_id)
            : NONE,
    });

    const submit = () => {
        const payload = {
            name: form.data.name,
            org_unit_id: Number(form.data.org_unit_id),
            grade_id:
                form.data.grade_id === NONE ? null : Number(form.data.grade_id),
            reports_to_position_id:
                form.data.reports_to_position_id === NONE
                    ? null
                    : Number(form.data.reports_to_position_id),
        };
        const options = { preserveScroll: true, onSuccess: onClose };

        if (editing) {
            router.put(positionRoutes.update(editing.id).url, payload, options);
        } else {
            router.post(positionRoutes.store().url, payload, options);
        }
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Posisi' : 'Tambah Posisi'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>Nama Posisi</Label>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        {form.errors.name && (
                            <p className="text-sm text-red-600">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label>Unit Organisasi</Label>
                        <Select
                            value={form.data.org_unit_id}
                            onValueChange={(v) =>
                                form.setData('org_unit_id', v)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih unit" />
                            </SelectTrigger>
                            <SelectContent>
                                {orgUnits.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.org_unit_id && (
                            <p className="text-sm text-red-600">
                                {form.errors.org_unit_id}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Grade</Label>
                            <Select
                                value={form.data.grade_id}
                                onValueChange={(v) =>
                                    form.setData('grade_id', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>-</SelectItem>
                                    {grades.map((g) => (
                                        <SelectItem
                                            key={g.id}
                                            value={String(g.id)}
                                        >
                                            {g.code} — {g.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Melapor ke</Label>
                            <Select
                                value={form.data.reports_to_position_id}
                                onValueChange={(v) =>
                                    form.setData('reports_to_position_id', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        (Puncak)
                                    </SelectItem>
                                    {positions
                                        .filter((p) => p.id !== editing?.id)
                                        .map((p) => (
                                            <SelectItem
                                                key={p.id}
                                                value={String(p.id)}
                                            >
                                                {p.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {form.errors.reports_to_position_id && (
                                <p className="text-sm text-red-600">
                                    {form.errors.reports_to_position_id}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
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

/* ----------------------------------------------------------------- Grades */

function GradesPanel({
    grades,
    canManage,
}: {
    grades: Grade[];
    canManage: boolean;
}) {
    const [dialog, setDialog] = useState<{ editing: Grade | null } | null>(
        null,
    );

    const remove = (grade: Grade) =>
        router.delete(gradeRoutes.destroy(grade.id).url, {
            preserveScroll: true,
        });

    return (
        <Card>
            <CardContent className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold">Grade (Band Gaji)</h2>
                    {canManage && (
                        <Button
                            size="sm"
                            onClick={() => setDialog({ editing: null })}
                        >
                            <Plus className="size-4" />
                            Tambah Grade
                        </Button>
                    )}
                </div>

                {grades.length === 0 ? (
                    <EmptyState
                        icon={Layers}
                        title="Belum ada grade"
                        description="Definisikan band gaji minimum dan maksimum."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead className="text-right">
                                        Gaji Min
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Gaji Max
                                    </TableHead>
                                    {canManage && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {grades.map((g) => (
                                    <TableRow key={g.id}>
                                        <TableCell className="font-medium">
                                            {g.code}
                                        </TableCell>
                                        <TableCell>{g.name}</TableCell>
                                        <TableCell className="text-right">
                                            {g.salary_min_formatted}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {g.salary_max_formatted}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    onClick={() =>
                                                        setDialog({
                                                            editing: g,
                                                        })
                                                    }
                                                    aria-label={`Edit ${g.code}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDialog
                                                    title={`Hapus grade ${g.code}?`}
                                                    onConfirm={() => remove(g)}
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Hapus ${g.code}`}
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

            {dialog && (
                <GradeDialog
                    editing={dialog.editing}
                    onClose={() => setDialog(null)}
                />
            )}
        </Card>
    );
}

function GradeDialog({
    editing,
    onClose,
}: {
    editing: Grade | null;
    onClose: () => void;
}) {
    const form = useForm({
        code: editing?.code ?? '',
        name: editing?.name ?? '',
        salary_min: editing?.salary_min ?? 0,
        salary_max: editing?.salary_max ?? 0,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };

        if (editing) {
            form.put(gradeRoutes.update(editing.id).url, options);
        } else {
            form.post(gradeRoutes.store().url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Grade' : 'Tambah Grade'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Kode</Label>
                            <Input
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData('code', e.target.value)
                                }
                            />
                            {form.errors.code && (
                                <p className="text-sm text-red-600">
                                    {form.errors.code}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label>Nama</Label>
                            <Input
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            {form.errors.name && (
                                <p className="text-sm text-red-600">
                                    {form.errors.name}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Gaji Minimum</Label>
                            <CurrencyInput
                                value={form.data.salary_min}
                                onChange={(v) => form.setData('salary_min', v)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Gaji Maksimum</Label>
                            <CurrencyInput
                                value={form.data.salary_max}
                                onChange={(v) => form.setData('salary_max', v)}
                            />
                            {form.errors.salary_max && (
                                <p className="text-sm text-red-600">
                                    {form.errors.salary_max}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
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

/* -------------------------------------------------------------- Org chart */

function OrgChart({ positions }: { positions: Position[] }) {
    const roots = useMemo(
        () => positions.filter((p) => p.reports_to_position_id === null),
        [positions],
    );
    const childrenOf = (id: number) =>
        positions.filter((p) => p.reports_to_position_id === id);

    if (positions.length === 0) {
        return (
            <Card>
                <CardContent>
                    <EmptyState
                        icon={GitBranch}
                        title="Bagan kosong"
                        description="Tambahkan posisi dengan garis pelaporan untuk melihat bagan."
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardContent className="overflow-x-auto">
                <div className="flex flex-col gap-2">
                    {roots.map((p) => (
                        <ChartNode
                            key={p.id}
                            position={p}
                            depth={0}
                            childrenOf={childrenOf}
                        />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function ChartNode({
    position,
    depth,
    childrenOf,
}: {
    position: Position;
    depth: number;
    childrenOf: (id: number) => Position[];
}) {
    const kids = childrenOf(position.id);

    return (
        <div style={{ paddingLeft: depth * 24 }}>
            <div className="inline-flex flex-col rounded-md border border-border bg-card px-3 py-2">
                <span className="text-sm font-medium">{position.name}</span>
                <span className="text-xs text-muted-foreground">
                    {position.org_unit_name ?? '-'}
                    {position.grade_code ? ` · ${position.grade_code}` : ''}
                </span>
            </div>
            {kids.length > 0 && (
                <div className="mt-2 flex flex-col gap-2 border-l border-dashed border-border pl-3">
                    {kids.map((child) => (
                        <ChartNode
                            key={child.id}
                            position={child}
                            depth={0}
                            childrenOf={childrenOf}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
