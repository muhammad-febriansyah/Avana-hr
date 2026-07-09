import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Puzzle, Trash2 } from 'lucide-react';
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
import { dashboard } from '@/routes';
import { index as employeesIndex } from '@/routes/employees';
import { destroy, store, update } from '@/routes/employees/custom-fields';

type Definition = {
    id: number;
    label: string;
    key: string;
    field_type: string;
    options: string[];
    is_required: boolean;
    sort_order: number;
};

type Props = {
    definitions: Definition[];
    fieldTypes: string[];
};

const TYPE_LABELS: Record<string, string> = {
    text: 'Teks',
    number: 'Angka',
    date: 'Tanggal',
    select: 'Pilihan',
};

export default function CustomFields({ definitions, fieldTypes }: Props) {
    const [editing, setEditing] = useState<Definition | null>(null);
    const [open, setOpen] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setOpen(true);
    };
    const openEdit = (def: Definition) => {
        setEditing(def);
        setOpen(true);
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Custom Field Karyawan" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Karyawan', href: employeesIndex().url },
                    { title: 'Custom Field' },
                ]}
            />
            <PageHeader
                title="Custom Field Karyawan"
                description="Field tambahan yang muncul otomatis di form & detail karyawan"
                action={
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={employeesIndex().url}>
                                <ArrowLeft className="size-4" />
                                Karyawan
                            </Link>
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus className="size-4" />
                            Tambah Field
                        </Button>
                    </div>
                }
            />

            <Card>
                <CardContent className="flex flex-col gap-1">
                    {definitions.length === 0 ? (
                        <EmptyState
                            icon={Puzzle}
                            title="Belum ada custom field"
                            description="Tambahkan field khusus perusahaan Anda."
                        />
                    ) : (
                        definitions.map((def) => (
                            <div
                                key={def.id}
                                className="flex items-center gap-3 rounded-md px-2 py-2.5 hover:bg-muted/50"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 font-medium">
                                        {def.label}
                                        {def.is_required && (
                                            <Badge variant="secondary">
                                                Wajib
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {def.key} ·{' '}
                                        {TYPE_LABELS[def.field_type] ??
                                            def.field_type}
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => openEdit(def)}
                                    aria-label={`Edit ${def.label}`}
                                >
                                    <Pencil className="size-4" />
                                </Button>
                                <ConfirmDialog
                                    title={`Hapus field ${def.label}?`}
                                    description="Nilai yang tersimpan pada karyawan akan ikut terhapus."
                                    onConfirm={() =>
                                        router.delete(destroy(def.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Hapus ${def.label}`}
                                        >
                                            <Trash2 className="size-4 text-red-600" />
                                        </Button>
                                    }
                                />
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>

            <FieldDialog
                key={editing?.id ?? 'new'}
                open={open}
                onOpenChange={setOpen}
                editing={editing}
                fieldTypes={fieldTypes}
            />
        </div>
    );
}

function FieldDialog({
    open,
    onOpenChange,
    editing,
    fieldTypes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: Definition | null;
    fieldTypes: string[];
}) {
    const form = useForm({
        label: editing?.label ?? '',
        key: editing?.key ?? '',
        field_type: editing?.field_type ?? 'text',
        options: (editing?.options ?? []).join(', '),
        is_required: editing?.is_required ?? false,
        sort_order: editing?.sort_order ?? 0,
    });

    const submit = () => {
        form.transform((d) => ({
            ...d,
            options:
                d.field_type === 'select'
                    ? d.options
                          .split(',')
                          .map((o) => o.trim())
                          .filter(Boolean)
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

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Custom Field' : 'Tambah Custom Field'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="cf-label">Label</Label>
                        <Input
                            id="cf-label"
                            value={form.data.label}
                            onChange={(e) =>
                                form.setData('label', e.target.value)
                            }
                        />
                        {form.errors.label && (
                            <p className="text-sm text-red-600">
                                {form.errors.label}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cf-key">Key</Label>
                        <Input
                            id="cf-key"
                            placeholder="mis. nomor_induk"
                            value={form.data.key}
                            onChange={(e) =>
                                form.setData('key', e.target.value)
                            }
                        />
                        {form.errors.key && (
                            <p className="text-sm text-red-600">
                                {form.errors.key}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label>Tipe</Label>
                        <Select
                            value={form.data.field_type}
                            onValueChange={(v) => form.setData('field_type', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {fieldTypes.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {TYPE_LABELS[t] ?? t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {form.data.field_type === 'select' && (
                        <div className="grid gap-2">
                            <Label htmlFor="cf-options">
                                Opsi (pisah dengan koma)
                            </Label>
                            <Input
                                id="cf-options"
                                placeholder="Merah, Kuning, Hijau"
                                value={form.data.options}
                                onChange={(e) =>
                                    form.setData('options', e.target.value)
                                }
                            />
                            {form.errors.options && (
                                <p className="text-sm text-red-600">
                                    {form.errors.options}
                                </p>
                            )}
                        </div>
                    )}
                    <div className="flex items-center gap-6">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_required}
                                onCheckedChange={(c) =>
                                    form.setData('is_required', c === true)
                                }
                            />
                            Wajib diisi
                        </label>
                        <div className="grid gap-1">
                            <Label htmlFor="cf-sort" className="text-xs">
                                Urutan
                            </Label>
                            <Input
                                id="cf-sort"
                                type="number"
                                className="h-8 w-24"
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
