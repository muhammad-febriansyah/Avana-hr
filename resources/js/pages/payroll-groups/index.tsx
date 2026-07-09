import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { destroy, store, update } from '@/routes/payroll-groups';

type Group = {
    id: number;
    code: string;
    name: string;
    frequency: string;
    cutoff_day: number;
    is_active: boolean;
    component_count: number;
    component_ids: number[];
};
type ComponentOption = { id: number; code: string; name: string };

type Props = { groups: Group[]; components: ComponentOption[] };

const FREQ_LABELS: Record<string, string> = {
    monthly: 'Bulanan',
    weekly: 'Mingguan',
    biweekly: 'Dua Mingguan',
};

export default function PayrollGroupsIndex({ groups, components }: Props) {
    const { can } = usePermissions();
    const canManage = can('payroll.manage-master');
    const [editing, setEditing] = useState<Group | null>(null);
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Payroll Group" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Payroll Group' },
                ]}
            />
            <PageHeader
                title="Payroll Group"
                description="Periode, cut-off, dan set komponen untuk proses gaji"
                action={
                    canManage && (
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setOpen(true);
                            }}
                        >
                            <Plus className="size-4" />
                            Tambah Group
                        </Button>
                    )
                }
            />

            <Card>
                <CardContent>
                    {groups.length === 0 ? (
                        <EmptyState
                            icon={Wallet}
                            title="Belum ada payroll group"
                            description="Buat konfigurasi periode gaji pertama."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Frekuensi</TableHead>
                                    <TableHead>Cut-off</TableHead>
                                    <TableHead>Komponen</TableHead>
                                    {canManage && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {groups.map((g) => (
                                    <TableRow key={g.id}>
                                        <TableCell className="font-mono text-xs">
                                            {g.code}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {g.name}
                                        </TableCell>
                                        <TableCell>
                                            {FREQ_LABELS[g.frequency] ??
                                                g.frequency}
                                        </TableCell>
                                        <TableCell>
                                            Tgl {g.cutoff_day}
                                        </TableCell>
                                        <TableCell>
                                            {g.component_count}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        setEditing(g);
                                                        setOpen(true);
                                                    }}
                                                    aria-label={`Edit ${g.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDialog
                                                    title={`Hapus ${g.name}?`}
                                                    onConfirm={() =>
                                                        router.delete(
                                                            destroy(g.id).url,
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Hapus ${g.name}`}
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
                    )}
                </CardContent>
            </Card>

            {canManage && (
                <GroupDialog
                    key={editing?.id ?? 'new'}
                    open={open}
                    onOpenChange={setOpen}
                    editing={editing}
                    components={components}
                />
            )}
        </div>
    );
}

function GroupDialog({
    open,
    onOpenChange,
    editing,
    components,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: Group | null;
    components: ComponentOption[];
}) {
    const form = useForm<{
        code: string;
        name: string;
        description: string;
        frequency: string;
        period_start_day: number;
        cutoff_day: number;
        attendance_source: string;
        overtime_source: string;
        prorate_method: string;
        is_active: boolean;
        component_ids: number[];
    }>({
        code: editing?.code ?? '',
        name: editing?.name ?? '',
        description: '',
        frequency: editing?.frequency ?? 'monthly',
        period_start_day: 1,
        cutoff_day: editing?.cutoff_day ?? 25,
        attendance_source: 'current',
        overtime_source: 'current',
        prorate_method: 'calendar',
        is_active: editing?.is_active ?? true,
        component_ids: editing?.component_ids ?? [],
    });

    const toggle = (id: number) =>
        form.setData(
            'component_ids',
            form.data.component_ids.includes(id)
                ? form.data.component_ids.filter((c) => c !== id)
                : [...form.data.component_ids, id],
        );

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (editing) {
            form.put(update(editing.id).url, options);
        } else {
            form.post(store().url, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Payroll Group' : 'Tambah Payroll Group'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="pg-code">Kode</Label>
                            <Input
                                id="pg-code"
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
                            <Label htmlFor="pg-name">Nama</Label>
                            <Input
                                id="pg-name"
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
                            <Label>Frekuensi</Label>
                            <Select
                                value={form.data.frequency}
                                onValueChange={(v) =>
                                    form.setData('frequency', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="monthly">
                                        Bulanan
                                    </SelectItem>
                                    <SelectItem value="weekly">
                                        Mingguan
                                    </SelectItem>
                                    <SelectItem value="biweekly">
                                        Dua Mingguan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pg-cutoff">Tanggal Cut-off</Label>
                            <Input
                                id="pg-cutoff"
                                type="number"
                                min={1}
                                max={28}
                                value={form.data.cutoff_day}
                                onChange={(e) =>
                                    form.setData(
                                        'cutoff_day',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            {form.errors.cutoff_day && (
                                <p className="text-sm text-red-600">
                                    {form.errors.cutoff_day}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Komponen ({form.data.component_ids.length})</Label>
                        <div className="grid max-h-48 grid-cols-1 gap-1 overflow-y-auto rounded-md border border-border p-2 sm:grid-cols-2">
                            {components.map((c) => (
                                <label
                                    key={c.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.component_ids.includes(
                                            c.id,
                                        )}
                                        onCheckedChange={() => toggle(c.id)}
                                    />
                                    <span className="truncate">
                                        {c.name}{' '}
                                        <span className="text-muted-foreground">
                                            ({c.code})
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
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
