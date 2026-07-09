import { Head, router, useForm } from '@inertiajs/react';
import { Coins, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { destroy, store, update } from '@/routes/salary-components';

type Component = {
    id: number;
    code: string;
    name: string;
    type: string;
    calc_basis: string;
    fixed_amount: number | null;
    is_taxable: boolean;
    bpjs_basis: boolean;
    prorate_enabled: boolean;
    overtime_related: boolean;
    is_active: boolean;
    sort_order: number;
};

type Props = { components: Component[] };

const CALC_LABELS: Record<string, string> = {
    fixed: 'Tetap',
    table: 'Tabel Nilai',
    formula: 'Formula',
};

export default function SalaryComponentsIndex({ components }: Props) {
    const { can } = usePermissions();
    const canManage = can('payroll.manage-master');
    const [editing, setEditing] = useState<Component | null>(null);
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Komponen Gaji" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Komponen Gaji' },
                ]}
            />
            <PageHeader
                title="Komponen Gaji"
                description="Komponen earning & deduction dalam struktur payroll"
                action={
                    canManage && (
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setOpen(true);
                            }}
                        >
                            <Plus className="size-4" />
                            Tambah Komponen
                        </Button>
                    )
                }
            />

            <Card>
                <CardContent>
                    {components.length === 0 ? (
                        <EmptyState
                            icon={Coins}
                            title="Belum ada komponen"
                            description="Tambahkan komponen gaji pertama."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Tipe</TableHead>
                                    <TableHead>Basis</TableHead>
                                    <TableHead>Pajak</TableHead>
                                    {canManage && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {components.map((c) => (
                                    <TableRow
                                        key={c.id}
                                        className={c.is_active ? '' : 'opacity-50'}
                                    >
                                        <TableCell className="font-mono text-xs">
                                            {c.code}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {c.name}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    c.type === 'earning'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {c.type === 'earning'
                                                    ? 'Earning'
                                                    : 'Deduction'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {CALC_LABELS[c.calc_basis] ??
                                                c.calc_basis}
                                        </TableCell>
                                        <TableCell>
                                            {c.is_taxable ? 'Ya' : '-'}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        setEditing(c);
                                                        setOpen(true);
                                                    }}
                                                    aria-label={`Edit ${c.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <ConfirmDialog
                                                    title={`Hapus ${c.name}?`}
                                                    onConfirm={() =>
                                                        router.delete(
                                                            destroy(c.id).url,
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
                                                            aria-label={`Hapus ${c.name}`}
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
                <ComponentDialog
                    key={editing?.id ?? 'new'}
                    open={open}
                    onOpenChange={setOpen}
                    editing={editing}
                />
            )}
        </div>
    );
}

function ComponentDialog({
    open,
    onOpenChange,
    editing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: Component | null;
}) {
    const form = useForm({
        code: editing?.code ?? '',
        name: editing?.name ?? '',
        type: editing?.type ?? 'earning',
        calc_basis: editing?.calc_basis ?? 'fixed',
        fixed_amount: editing?.fixed_amount ?? 0,
        is_taxable: editing?.is_taxable ?? false,
        bpjs_basis: editing?.bpjs_basis ?? false,
        prorate_enabled: editing?.prorate_enabled ?? false,
        overtime_related: editing?.overtime_related ?? false,
        show_on_payslip: true,
        sort_order: editing?.sort_order ?? 0,
        is_active: editing?.is_active ?? true,
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

    const flag = (
        label: string,
        key:
            | 'is_taxable'
            | 'bpjs_basis'
            | 'prorate_enabled'
            | 'overtime_related'
            | 'is_active',
    ) => (
        <label className="flex items-center gap-2 text-sm">
            <Checkbox
                checked={form.data[key]}
                onCheckedChange={(c) => form.setData(key, c === true)}
            />
            {label}
        </label>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Komponen' : 'Tambah Komponen'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="sc-code">Kode</Label>
                            <Input
                                id="sc-code"
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
                            <Label htmlFor="sc-name">Nama</Label>
                            <Input
                                id="sc-name"
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
                            <Label>Tipe</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(v) => form.setData('type', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="earning">
                                        Earning
                                    </SelectItem>
                                    <SelectItem value="deduction">
                                        Deduction
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Basis Perhitungan</Label>
                            <Select
                                value={form.data.calc_basis}
                                onValueChange={(v) =>
                                    form.setData('calc_basis', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fixed">Tetap</SelectItem>
                                    <SelectItem value="table">
                                        Tabel Nilai
                                    </SelectItem>
                                    <SelectItem value="formula">
                                        Formula
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {form.data.calc_basis === 'fixed' && (
                            <div className="grid gap-2">
                                <Label htmlFor="sc-fixed">Nominal Tetap</Label>
                                <Input
                                    id="sc-fixed"
                                    type="number"
                                    value={form.data.fixed_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'fixed_amount',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                                {form.errors.fixed_amount && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.fixed_amount}
                                    </p>
                                )}
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="sc-sort">Urutan</Label>
                            <Input
                                id="sc-sort"
                                type="number"
                                value={form.data.sort_order}
                                onChange={(e) =>
                                    form.setData(
                                        'sort_order',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-x-6 gap-y-2">
                        {flag('Kena Pajak', 'is_taxable')}
                        {flag('Basis BPJS', 'bpjs_basis')}
                        {flag('Prorata', 'prorate_enabled')}
                        {flag('Terkait Lembur', 'overtime_related')}
                        {flag('Aktif', 'is_active')}
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
