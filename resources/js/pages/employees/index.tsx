import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Download,
    FileDown,
    Pencil,
    Plus,
    Puzzle,
    Search,
    Trash2,
    Upload,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import { create, destroy, edit, index, show } from '@/routes/employees';
import { index as customFieldsIndex } from '@/routes/employees/custom-fields';
import {
    exceptions as importExceptions,
    store as importStore,
    template as importTemplate,
} from '@/routes/employees/import';

type Employee = {
    id: number;
    employee_code: string;
    full_name: string;
    email: string | null;
    position: string | null;
    org_unit: string | null;
    status: string;
};

type OrgUnit = { id: number; name: string };

type Filters = {
    q: string;
    status: string;
    org_unit_id: number | null;
};

type Props = {
    employees: Employee[];
    orgUnits: OrgUnit[];
    filters: Filters;
};

type ImportException = { row: number; name: string; reason: string };

type ImportResult = {
    imported: number;
    failed: number;
    exceptions: ImportException[];
    token: string | null;
};

const ALL = '__all__';

export default function EmployeesIndex({
    employees,
    orgUnits,
    filters,
}: Props) {
    const { can } = usePermissions();
    const { flash } = usePage<{
        flash?: { importResult?: ImportResult | null };
    }>().props;
    const flashResult = flash?.importResult ?? null;
    const [importOpen, setImportOpen] = useState(false);
    const [result, setResult] = useState<ImportResult | null>(flashResult);
    const [seenFlash, setSeenFlash] = useState(flashResult);

    // Re-open the result dialog whenever a fresh import result arrives via flash.
    if (flashResult !== seenFlash) {
        setSeenFlash(flashResult);
        setResult(flashResult);
    }

    const apply = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        router.get(
            index().url,
            {
                q: next.q || undefined,
                status: next.status || undefined,
                org_unit_id: next.org_unit_id ?? undefined,
            },
            {
                only: ['employees', 'filters'],
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Karyawan" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Karyawan' },
                ]}
            />
            <PageHeader
                title="Karyawan"
                description="Kelola data seluruh karyawan perusahaan"
                action={
                    <div className="flex gap-2">
                        {can('employees.update') && (
                            <Button variant="outline" asChild>
                                <Link href={customFieldsIndex().url}>
                                    <Puzzle className="size-4" />
                                    Custom Field
                                </Link>
                            </Button>
                        )}
                        {can('employees.create') && (
                            <Button
                                variant="outline"
                                onClick={() => setImportOpen(true)}
                            >
                                <Upload className="size-4" />
                                Import
                            </Button>
                        )}
                        {can('employees.create') && (
                            <Button asChild>
                                <Link href={create().url}>
                                    <Plus className="size-4" />
                                    Tambah Karyawan
                                </Link>
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="flex flex-wrap gap-3">
                <div className="relative min-w-56 flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        placeholder="Cari nama, kode, email..."
                        defaultValue={filters.q}
                        onChange={(e) => apply({ q: e.target.value })}
                        className="pl-9"
                    />
                </div>
                <Select
                    value={filters.status || ALL}
                    onValueChange={(v) => apply({ status: v === ALL ? '' : v })}
                >
                    <SelectTrigger className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Semua Status</SelectItem>
                        <SelectItem value="active">Aktif</SelectItem>
                        <SelectItem value="inactive">Nonaktif</SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    value={
                        filters.org_unit_id ? String(filters.org_unit_id) : ALL
                    }
                    onValueChange={(v) =>
                        apply({ org_unit_id: v === ALL ? null : Number(v) })
                    }
                >
                    <SelectTrigger className="w-52">
                        <SelectValue placeholder="Semua Unit" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Semua Unit</SelectItem>
                        {orgUnits.map((u) => (
                            <SelectItem key={u.id} value={String(u.id)}>
                                {u.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <Card>
                <CardContent>
                    {employees.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Belum ada karyawan"
                            description="Tambahkan karyawan pertama perusahaan."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Kode</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Posisi</TableHead>
                                        <TableHead>Unit</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {employees.map((employee) => (
                                        <TableRow key={employee.id}>
                                            <TableCell className="font-mono text-xs">
                                                {employee.employee_code}
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={show(employee.id).url}
                                                    className="font-medium text-foreground hover:underline"
                                                >
                                                    {employee.full_name}
                                                </Link>
                                                {employee.email && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {employee.email}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {employee.position ?? '-'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {employee.org_unit ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        employee.status ===
                                                        'active'
                                                            ? 'aktif'
                                                            : 'nonaktif'
                                                    }
                                                    label={
                                                        employee.status ===
                                                        'active'
                                                            ? 'Aktif'
                                                            : 'Nonaktif'
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {can('employees.update') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                        aria-label={`Edit ${employee.full_name}`}
                                                    >
                                                        <Link
                                                            href={
                                                                edit(
                                                                    employee.id,
                                                                ).url
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('employees.delete') && (
                                                    <ConfirmDialog
                                                        title={`Hapus ${employee.full_name}?`}
                                                        description="Data karyawan akan diarsipkan (soft delete)."
                                                        onConfirm={() =>
                                                            router.delete(
                                                                destroy(
                                                                    employee.id,
                                                                ).url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Hapus ${employee.full_name}`}
                                                            >
                                                                <Trash2 className="size-4 text-red-600" />
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <ImportDialog open={importOpen} onOpenChange={setImportOpen} />

            <ResultDialog
                result={result}
                onOpenChange={(open) => !open && setResult(null)}
            />
        </div>
    );
}

function ImportDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = () => {
        form.post(importStore().url, {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Import Karyawan</DialogTitle>
                    <DialogDescription>
                        Unggah berkas Excel/CSV sesuai template. Baris yang gagal
                        akan masuk daftar exception yang bisa diunduh.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <Button variant="outline" asChild className="justify-start">
                        <a href={importTemplate().url}>
                            <FileDown className="size-4" />
                            Unduh Template
                        </a>
                    </Button>
                    <div className="grid gap-2">
                        <Label htmlFor="import-file">Berkas (.xlsx/.csv)</Label>
                        <Input
                            id="import-file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) =>
                                form.setData('file', e.target.files?.[0] ?? null)
                            }
                        />
                        {form.errors.file && (
                            <p className="text-sm text-red-600">
                                {form.errors.file}
                            </p>
                        )}
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={form.processing || !form.data.file}
                    >
                        Import
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ResultDialog({
    result,
    onOpenChange,
}: {
    result: ImportResult | null;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={result !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hasil Import</DialogTitle>
                    <DialogDescription>
                        {result?.imported ?? 0} karyawan berhasil diimpor
                        {result && result.failed > 0
                            ? `, ${result.failed} baris gagal.`
                            : '.'}
                    </DialogDescription>
                </DialogHeader>

                {result && result.failed > 0 && (
                    <div className="flex flex-col gap-3">
                        {result.token && (
                            <Button
                                variant="outline"
                                asChild
                                className="justify-start"
                            >
                                <a href={importExceptions(result.token).url}>
                                    <Download className="size-4" />
                                    Unduh Daftar Exception (.xlsx)
                                </a>
                            </Button>
                        )}
                        <div className="max-h-64 overflow-y-auto rounded-md border border-border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-16">
                                            Baris
                                        </TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Alasan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {result.exceptions.map((ex) => (
                                        <TableRow key={ex.row}>
                                            <TableCell>{ex.row}</TableCell>
                                            <TableCell>
                                                {ex.name || '-'}
                                            </TableCell>
                                            <TableCell className="text-red-600">
                                                {ex.reason}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                )}

                <DialogFooter>
                    <Button onClick={() => onOpenChange(false)}>Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
