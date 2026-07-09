import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { destroy, store, update } from '@/routes/leave-types';

type LeaveType = {
    id: number;
    name: string;
    code: string;
    annual_quota: number;
    deduct_balance: boolean;
    allow_carry_over: boolean;
    carry_over_max: number;
    requires_attachment: boolean;
    min_notice_days: number;
    max_consecutive_days: number | null;
};

type Props = { leaveTypes: LeaveType[] };

export default function LeaveTypesIndex({ leaveTypes }: Props) {
    const { can } = usePermissions();
    const canManage = can('leave.manage-types');
    const [editing, setEditing] = useState<LeaveType | null>(null);
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Jenis Cuti" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Jenis Cuti' },
                ]}
            />
            <PageHeader
                title="Jenis Cuti"
                description="Kategori cuti beserta kuota & kebijakan saldo"
                action={
                    canManage && (
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setOpen(true);
                            }}
                        >
                            <Plus className="size-4" />
                            Tambah Jenis
                        </Button>
                    )
                }
            />

            <Card>
                <CardContent>
                    {leaveTypes.length === 0 ? (
                        <EmptyState
                            icon={CalendarCheck}
                            title="Belum ada jenis cuti"
                            description="Tambahkan jenis cuti pertama."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Kuota/thn</TableHead>
                                    <TableHead>Potong Saldo</TableHead>
                                    {canManage && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {leaveTypes.map((type) => (
                                    <TableRow key={type.id}>
                                        <TableCell className="font-medium">
                                            {type.name}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {type.code}
                                        </TableCell>
                                        <TableCell>
                                            {type.annual_quota} hari
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    type.deduct_balance
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {type.deduct_balance
                                                    ? 'Ya'
                                                    : 'Tidak (unpaid)'}
                                            </Badge>
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        setEditing(type);
                                                        setOpen(true);
                                                    }}
                                                    aria-label={`Edit ${type.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDialog
                                                    title={`Hapus ${type.name}?`}
                                                    onConfirm={() =>
                                                        router.delete(
                                                            destroy(type.id).url,
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
                                                            aria-label={`Hapus ${type.name}`}
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
                <LeaveTypeDialog
                    key={editing?.id ?? 'new'}
                    open={open}
                    onOpenChange={setOpen}
                    editing={editing}
                />
            )}
        </div>
    );
}

function LeaveTypeDialog({
    open,
    onOpenChange,
    editing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: LeaveType | null;
}) {
    const form = useForm({
        name: editing?.name ?? '',
        code: editing?.code ?? '',
        annual_quota: editing?.annual_quota ?? 12,
        deduct_balance: editing?.deduct_balance ?? true,
        allow_carry_over: editing?.allow_carry_over ?? false,
        carry_over_max: editing?.carry_over_max ?? 0,
        requires_attachment: editing?.requires_attachment ?? false,
        min_notice_days: editing?.min_notice_days ?? 0,
        max_consecutive_days: editing?.max_consecutive_days
            ? String(editing.max_consecutive_days)
            : '',
    });

    const submit = () => {
        form.transform((d) => ({
            ...d,
            max_consecutive_days: d.max_consecutive_days
                ? Number(d.max_consecutive_days)
                : null,
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

    const num = (
        label: string,
        key:
            | 'annual_quota'
            | 'carry_over_max'
            | 'min_notice_days'
            | 'max_consecutive_days',
        placeholder = '',
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`lt-${key}`}>{label}</Label>
            <Input
                id={`lt-${key}`}
                type="number"
                placeholder={placeholder}
                value={form.data[key]}
                onChange={(e) =>
                    form.setData(
                        key,
                        key === 'max_consecutive_days'
                            ? e.target.value
                            : (Number(e.target.value) as never),
                    )
                }
            />
            {form.errors[key] && (
                <p className="text-sm text-red-600">{form.errors[key]}</p>
            )}
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Jenis Cuti' : 'Tambah Jenis Cuti'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="lt-name">Nama</Label>
                            <Input
                                id="lt-name"
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
                            <Label htmlFor="lt-code">Kode</Label>
                            <Input
                                id="lt-code"
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
                        {num('Kuota Tahunan', 'annual_quota')}
                        {num('Min. Pengajuan (hari)', 'min_notice_days')}
                        {num('Maks. Berturut', 'max_consecutive_days', 'kosong = tanpa batas')}
                        {num('Maks. Carry-over', 'carry_over_max')}
                    </div>
                    <div className="flex flex-col gap-2">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.deduct_balance}
                                onCheckedChange={(c) =>
                                    form.setData('deduct_balance', c === true)
                                }
                            />
                            Potong saldo (nonaktif = cuti unpaid)
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.allow_carry_over}
                                onCheckedChange={(c) =>
                                    form.setData('allow_carry_over', c === true)
                                }
                            />
                            Boleh carry-over ke tahun berikutnya
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.requires_attachment}
                                onCheckedChange={(c) =>
                                    form.setData(
                                        'requires_attachment',
                                        c === true,
                                    )
                                }
                            />
                            Wajib lampiran
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
