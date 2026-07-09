import { Head, router, useForm } from '@inertiajs/react';
import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
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
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { destroy, store, update } from '@/routes/shift-patterns';

type Pattern = {
    id: number;
    name: string;
    cycle_days: number;
    days: (number | null)[];
};

type ShiftOption = { id: number; name: string };

type Props = { patterns: Pattern[]; shifts: ShiftOption[] };

const OFF = '__off__';

export default function ShiftPatternsIndex({ patterns, shifts }: Props) {
    const { can } = usePermissions();
    const canManage = can('shift.manage');
    const [editing, setEditing] = useState<Pattern | null>(null);
    const [open, setOpen] = useState(false);

    const shiftName = (id: number | null) =>
        id === null ? 'Libur' : (shifts.find((s) => s.id === id)?.name ?? `#${id}`);

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Pola Shift" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Pola Shift' },
                ]}
            />
            <PageHeader
                title="Pola Shift"
                description="Pola rotasi shift (mis. 2-2-3) untuk generate jadwal"
                action={
                    canManage && (
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setOpen(true);
                            }}
                            disabled={shifts.length === 0}
                        >
                            <Plus className="size-4" />
                            Tambah Pola
                        </Button>
                    )
                }
            />

            <Card>
                <CardContent className="flex flex-col gap-1">
                    {patterns.length === 0 ? (
                        <EmptyState
                            icon={CalendarClock}
                            title="Belum ada pola shift"
                            description="Buat pola rotasi untuk penjadwalan otomatis."
                        />
                    ) : (
                        patterns.map((pattern) => (
                            <div
                                key={pattern.id}
                                className="flex items-center gap-3 rounded-md px-2 py-2.5 hover:bg-muted/50"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="font-medium">
                                        {pattern.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {pattern.cycle_days} hari ·{' '}
                                        {pattern.days
                                            .map((d) => shiftName(d))
                                            .join(' → ')}
                                    </div>
                                </div>
                                {canManage && (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => {
                                                setEditing(pattern);
                                                setOpen(true);
                                            }}
                                            aria-label={`Edit ${pattern.name}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <ConfirmDialog
                                            title={`Hapus pola ${pattern.name}?`}
                                            onConfirm={() =>
                                                router.delete(
                                                    destroy(pattern.id).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Hapus ${pattern.name}`}
                                                >
                                                    <Trash2 className="size-4 text-red-600" />
                                                </Button>
                                            }
                                        />
                                    </>
                                )}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>

            {canManage && (
                <PatternDialog
                    key={editing?.id ?? 'new'}
                    open={open}
                    onOpenChange={setOpen}
                    editing={editing}
                    shifts={shifts}
                />
            )}
        </div>
    );
}

function PatternDialog({
    open,
    onOpenChange,
    editing,
    shifts,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: Pattern | null;
    shifts: ShiftOption[];
}) {
    const form = useForm<{ name: string; cycle_days: number; days: string[] }>({
        name: editing?.name ?? '',
        cycle_days: editing?.cycle_days ?? 4,
        days: (editing?.days ?? [null, null, null, null]).map((d) =>
            d === null ? OFF : String(d),
        ),
    });

    const setCycle = (value: number) => {
        const cycle = Math.min(Math.max(value, 1), 31);
        const days = Array.from(
            { length: cycle },
            (_, i) => form.data.days[i] ?? OFF,
        );
        form.setData({ ...form.data, cycle_days: cycle, days });
    };

    const submit = () => {
        form.transform((d) => ({
            name: d.name,
            cycle_days: d.cycle_days,
            days: d.days.map((v) => (v === OFF ? null : Number(v))),
        }));
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
                        {editing ? 'Edit Pola Shift' : 'Tambah Pola Shift'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="pt-name">Nama</Label>
                            <Input
                                id="pt-name"
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
                            <Label htmlFor="pt-cycle">Panjang Siklus</Label>
                            <Input
                                id="pt-cycle"
                                type="number"
                                min={1}
                                max={31}
                                value={form.data.cycle_days}
                                onChange={(e) =>
                                    setCycle(Number(e.target.value))
                                }
                            />
                        </div>
                    </div>
                    <div className="grid max-h-64 gap-2 overflow-y-auto">
                        {form.data.days.map((day, index) => (
                            <div
                                key={index}
                                className="flex items-center gap-3"
                            >
                                <span className="w-16 text-sm text-muted-foreground">
                                    Hari {index + 1}
                                </span>
                                <Select
                                    value={day}
                                    onValueChange={(v) => {
                                        const days = [...form.data.days];
                                        days[index] = v;
                                        form.setData('days', days);
                                    }}
                                >
                                    <SelectTrigger className="flex-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={OFF}>Libur</SelectItem>
                                        {shifts.map((s) => (
                                            <SelectItem
                                                key={s.id}
                                                value={String(s.id)}
                                            >
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        ))}
                    </div>
                    {form.errors.days && (
                        <p className="text-sm text-red-600">
                            {form.errors.days}
                        </p>
                    )}
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
