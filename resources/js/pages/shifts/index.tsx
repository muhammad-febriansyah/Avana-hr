import { Head, router, useForm } from '@inertiajs/react';
import { Clock, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { destroy, store, update } from '@/routes/shifts';

type Shift = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    is_overnight: boolean;
    late_tolerance_min: number;
    break_minutes: number;
};

type Props = { shifts: Shift[] };

export default function ShiftsIndex({ shifts }: Props) {
    const { can } = usePermissions();
    const canManage = can('shift.manage');
    const [editing, setEditing] = useState<Shift | null>(null);
    const [open, setOpen] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setOpen(true);
    };
    const openEdit = (shift: Shift) => {
        setEditing(shift);
        setOpen(true);
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Shift" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Shift' },
                ]}
            />
            <PageHeader
                title="Shift"
                description="Definisi jam kerja untuk penjadwalan & kehadiran"
                action={
                    canManage && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            Tambah Shift
                        </Button>
                    )
                }
            />

            <Card>
                <CardContent>
                    {shifts.length === 0 ? (
                        <EmptyState
                            icon={Clock}
                            title="Belum ada shift"
                            description="Tambahkan shift kerja pertama."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Jam</TableHead>
                                    <TableHead>Toleransi</TableHead>
                                    <TableHead>Istirahat</TableHead>
                                    {canManage && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {shifts.map((shift) => (
                                    <TableRow key={shift.id}>
                                        <TableCell className="font-medium">
                                            {shift.name}
                                            {shift.is_overnight && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    Lintas hari
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {shift.start_time} – {shift.end_time}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {shift.late_tolerance_min} mnt
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {shift.break_minutes} mnt
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEdit(shift)
                                                    }
                                                    aria-label={`Edit ${shift.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDialog
                                                    title={`Hapus shift ${shift.name}?`}
                                                    onConfirm={() =>
                                                        router.delete(
                                                            destroy(shift.id)
                                                                .url,
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
                                                            aria-label={`Hapus ${shift.name}`}
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
                <ShiftDialog
                    key={editing?.id ?? 'new'}
                    open={open}
                    onOpenChange={setOpen}
                    editing={editing}
                />
            )}
        </div>
    );
}

function ShiftDialog({
    open,
    onOpenChange,
    editing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: Shift | null;
}) {
    const form = useForm({
        name: editing?.name ?? '',
        start_time: editing?.start_time ?? '08:00',
        end_time: editing?.end_time ?? '17:00',
        is_overnight: editing?.is_overnight ?? false,
        late_tolerance_min: editing?.late_tolerance_min ?? 15,
        break_minutes: editing?.break_minutes ?? 60,
    });

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
                        {editing ? 'Edit Shift' : 'Tambah Shift'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="sh-name">Nama</Label>
                        <Input
                            id="sh-name"
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
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="sh-start">Jam Mulai</Label>
                            <Input
                                id="sh-start"
                                type="time"
                                value={form.data.start_time}
                                onChange={(e) =>
                                    form.setData('start_time', e.target.value)
                                }
                            />
                            {form.errors.start_time && (
                                <p className="text-sm text-red-600">
                                    {form.errors.start_time}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sh-end">Jam Selesai</Label>
                            <Input
                                id="sh-end"
                                type="time"
                                value={form.data.end_time}
                                onChange={(e) =>
                                    form.setData('end_time', e.target.value)
                                }
                            />
                            {form.errors.end_time && (
                                <p className="text-sm text-red-600">
                                    {form.errors.end_time}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sh-tol">
                                Toleransi Telat (menit)
                            </Label>
                            <Input
                                id="sh-tol"
                                type="number"
                                value={form.data.late_tolerance_min}
                                onChange={(e) =>
                                    form.setData(
                                        'late_tolerance_min',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="sh-break">Istirahat (menit)</Label>
                            <Input
                                id="sh-break"
                                type="number"
                                value={form.data.break_minutes}
                                onChange={(e) =>
                                    form.setData(
                                        'break_minutes',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_overnight}
                            onCheckedChange={(c) =>
                                form.setData('is_overnight', c === true)
                            }
                        />
                        Shift lintas hari (berakhir keesokan hari)
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
