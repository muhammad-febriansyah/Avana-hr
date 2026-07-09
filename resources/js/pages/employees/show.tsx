import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit, index } from '@/routes/employees';
import {
    destroy as destroyContract,
    download as downloadContract,
    store as storeContract,
    update as updateContract,
} from '@/routes/employees/contracts';

type Employee = {
    id: number;
    employee_code: string;
    full_name: string;
    status: string;
    nik_ktp: string | null;
    npwp: string | null;
    email: string | null;
    phone: string | null;
    birth_date: string | null;
    gender: string | null;
    marital_status: string | null;
    ptkp_status: string | null;
    position: string | null;
    grade: string | null;
    org_unit: string | null;
    branch: string | null;
    direct_manager: string | null;
    employment_status: string | null;
    join_date: string | null;
    bank_name: string | null;
    bank_account: string | null;
    bank_account_name: string | null;
    bpjs_kes_no: string | null;
    bpjs_tk_no: string | null;
};

type Contract = {
    id: number;
    contract_no: string;
    type: string;
    start_date: string | null;
    end_date: string | null;
    status: string;
    has_file: boolean;
};

type Audit = {
    id: number;
    event: string;
    user: string;
    created_at: string;
};

type CustomField = { label: string; value: string | null };

type Props = {
    employee: Employee;
    customFields: CustomField[];
    contracts: Contract[];
    audits: Audit[];
};

type Tab = 'profil' | 'kepegawaian' | 'kontrak' | 'payroll' | 'lainnya' | 'riwayat';

const TYPE_LABELS: Record<string, string> = {
    pkwt: 'PKWT',
    pkwtt: 'PKWTT',
    magang: 'Magang',
    kemitraan: 'Kemitraan',
};

const STATUS_LABELS: Record<string, string> = {
    active: 'Aktif',
    expired: 'Berakhir',
    terminated: 'Diputus',
};

const mask = (value: string | null) => {
    if (!value) {
        return '-';
    }

    return value.length <= 4
        ? value
        : `${'•'.repeat(Math.max(value.length - 4, 4))}${value.slice(-4)}`;
};

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid grid-cols-[10rem_1fr] gap-2 border-b border-border/60 py-2.5 text-sm last:border-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-foreground">{value || '-'}</span>
        </div>
    );
}

export default function EmployeeShow({
    employee,
    customFields,
    contracts,
    audits,
}: Props) {
    const { can } = usePermissions();
    const [tab, setTab] = useState<Tab>('profil');
    const [editing, setEditing] = useState<Contract | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const canManage = can('employees.update');

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };
    const openEdit = (contract: Contract) => {
        setEditing(contract);
        setDialogOpen(true);
    };

    const tabs: { key: Tab; label: string }[] = [
        { key: 'profil', label: 'Profil' },
        { key: 'kepegawaian', label: 'Kepegawaian' },
        { key: 'kontrak', label: 'Kontrak' },
        { key: 'payroll', label: 'Payroll' },
        { key: 'lainnya', label: 'Kehadiran & Cuti' },
        { key: 'riwayat', label: 'Riwayat' },
    ];

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title={employee.full_name} />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Karyawan', href: index().url },
                    { title: employee.full_name },
                ]}
            />
            <PageHeader
                title={employee.full_name}
                description={`${employee.employee_code}`}
                action={
                    <div className="flex items-center gap-3">
                        <StatusBadge
                            status={
                                employee.status === 'active'
                                    ? 'aktif'
                                    : 'nonaktif'
                            }
                            label={
                                employee.status === 'active'
                                    ? 'Aktif'
                                    : 'Nonaktif'
                            }
                        />
                        {canManage && (
                            <Button asChild>
                                <Link href={edit(employee.id).url}>
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="flex flex-wrap gap-1 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => setTab(t.key)}
                        className={
                            'border-b-2 px-4 py-2 text-sm font-medium transition-colors ' +
                            (tab === t.key
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground')
                        }
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <Card>
                <CardContent>
                    {tab === 'profil' && (
                        <div className="max-w-2xl">
                            <Row
                                label="Nama Lengkap"
                                value={employee.full_name}
                            />
                            <Row
                                label="NIK KTP"
                                value={mask(employee.nik_ktp)}
                            />
                            <Row label="NPWP" value={mask(employee.npwp)} />
                            <Row label="Email" value={employee.email} />
                            <Row label="Telepon" value={employee.phone} />
                            <Row
                                label="Tanggal Lahir"
                                value={employee.birth_date}
                            />
                            <Row
                                label="Jenis Kelamin"
                                value={employee.gender}
                            />
                            <Row
                                label="Status Nikah"
                                value={employee.marital_status}
                            />
                            <Row label="PTKP" value={employee.ptkp_status} />
                            {customFields.map((f) => (
                                <Row
                                    key={f.label}
                                    label={f.label}
                                    value={f.value}
                                />
                            ))}
                        </div>
                    )}

                    {tab === 'kepegawaian' && (
                        <div className="max-w-2xl">
                            <Row label="Posisi" value={employee.position} />
                            <Row label="Grade" value={employee.grade} />
                            <Row label="Unit" value={employee.org_unit} />
                            <Row label="Cabang" value={employee.branch} />
                            <Row
                                label="Atasan"
                                value={employee.direct_manager}
                            />
                            <Row
                                label="Status Kerja"
                                value={employee.employment_status}
                            />
                            <Row
                                label="Tanggal Masuk"
                                value={employee.join_date}
                            />
                        </div>
                    )}

                    {tab === 'kontrak' && (
                        <div className="flex flex-col gap-4">
                            {canManage && (
                                <div className="flex justify-end">
                                    <Button onClick={openCreate}>
                                        <Plus className="size-4" />
                                        Tambah Kontrak
                                    </Button>
                                </div>
                            )}

                            {contracts.length === 0 ? (
                                <EmptyState
                                    icon={FileText}
                                    title="Belum ada kontrak"
                                    description="Tambahkan kontrak kerja karyawan untuk memantau masa berlakunya."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>No. Kontrak</TableHead>
                                            <TableHead>Jenis</TableHead>
                                            <TableHead>Mulai</TableHead>
                                            <TableHead>Berakhir</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Aksi
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {contracts.map((contract) => (
                                            <TableRow key={contract.id}>
                                                <TableCell className="font-medium">
                                                    {contract.contract_no}
                                                </TableCell>
                                                <TableCell>
                                                    {TYPE_LABELS[
                                                        contract.type
                                                    ] ?? contract.type}
                                                </TableCell>
                                                <TableCell>
                                                    {contract.start_date ?? '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {contract.end_date ??
                                                        'Tidak terbatas'}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        status={contract.status}
                                                        label={
                                                            STATUS_LABELS[
                                                                contract.status
                                                            ] ?? contract.status
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center justify-end gap-1">
                                                        {contract.has_file && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                                aria-label="Unduh dokumen"
                                                            >
                                                                <a
                                                                    href={
                                                                        downloadContract(
                                                                            {
                                                                                employee:
                                                                                    employee.id,
                                                                                contract:
                                                                                    contract.id,
                                                                            },
                                                                        ).url
                                                                    }
                                                                >
                                                                    <Download className="size-4" />
                                                                </a>
                                                            </Button>
                                                        )}
                                                        {canManage && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        openEdit(
                                                                            contract,
                                                                        )
                                                                    }
                                                                    aria-label={`Edit ${contract.contract_no}`}
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Button>
                                                                <ConfirmDialog
                                                                    title={`Hapus kontrak ${contract.contract_no}?`}
                                                                    description="Dokumen terkait juga akan dihapus."
                                                                    onConfirm={() =>
                                                                        router.delete(
                                                                            destroyContract(
                                                                                {
                                                                                    employee:
                                                                                        employee.id,
                                                                                    contract:
                                                                                        contract.id,
                                                                                },
                                                                            ).url,
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
                                                                            aria-label={`Hapus ${contract.contract_no}`}
                                                                        >
                                                                            <Trash2 className="size-4 text-red-600" />
                                                                        </Button>
                                                                    }
                                                                />
                                                            </>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    )}

                    {tab === 'payroll' && (
                        <div className="max-w-2xl">
                            <Row label="Bank" value={employee.bank_name} />
                            <Row
                                label="No. Rekening"
                                value={mask(employee.bank_account)}
                            />
                            <Row
                                label="Pemilik Rekening"
                                value={employee.bank_account_name}
                            />
                            <Row
                                label="BPJS Kesehatan"
                                value={employee.bpjs_kes_no}
                            />
                            <Row label="BPJS TK" value={employee.bpjs_tk_no} />
                        </div>
                    )}

                    {tab === 'lainnya' && (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            Data kehadiran & cuti tersedia setelah modul Time
                            Management aktif.
                        </p>
                    )}

                    {tab === 'riwayat' && (
                        <div className="flex flex-col divide-y divide-border">
                            {audits.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Belum ada riwayat perubahan.
                                </p>
                            ) : (
                                audits.map((audit) => (
                                    <div
                                        key={audit.id}
                                        className="flex items-center justify-between py-2.5 text-sm"
                                    >
                                        <span className="capitalize">
                                            {audit.event}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {audit.user} ·{' '}
                                            {formatDateTime(audit.created_at)}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            {canManage && (
                <ContractDialog
                    key={editing?.id ?? 'new'}
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    employeeId={employee.id}
                    editing={editing}
                />
            )}
        </div>
    );
}

function ContractDialog({
    open,
    onOpenChange,
    employeeId,
    editing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employeeId: number;
    editing: Contract | null;
}) {
    const form = useForm<{
        contract_no: string;
        type: string;
        start_date: string;
        end_date: string;
        status: string;
        file: File | null;
    }>({
        contract_no: editing?.contract_no ?? '',
        type: editing?.type ?? 'pkwt',
        start_date: editing?.start_date ?? '',
        end_date: editing?.end_date ?? '',
        status: editing?.status ?? 'active',
        file: null,
    });

    const submit = () => {
        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => onOpenChange(false),
        };

        if (editing) {
            form.transform((d) => ({ ...d, _method: 'put' }));
            form.post(
                updateContract({ employee: employeeId, contract: editing.id })
                    .url,
                options,
            );
        } else {
            form.post(storeContract(employeeId).url, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Edit Kontrak' : 'Tambah Kontrak'}
                    </DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="ct-no">Nomor Kontrak</Label>
                        <Input
                            id="ct-no"
                            value={form.data.contract_no}
                            onChange={(e) =>
                                form.setData('contract_no', e.target.value)
                            }
                        />
                        {form.errors.contract_no && (
                            <p className="text-sm text-red-600">
                                {form.errors.contract_no}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Jenis</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(v) => form.setData('type', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(TYPE_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            {form.errors.type && (
                                <p className="text-sm text-red-600">
                                    {form.errors.type}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(v) => form.setData('status', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(STATUS_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ct-start">Tanggal Mulai</Label>
                            <Input
                                id="ct-start"
                                type="date"
                                value={form.data.start_date}
                                onChange={(e) =>
                                    form.setData('start_date', e.target.value)
                                }
                            />
                            {form.errors.start_date && (
                                <p className="text-sm text-red-600">
                                    {form.errors.start_date}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ct-end">Tanggal Berakhir</Label>
                            <Input
                                id="ct-end"
                                type="date"
                                value={form.data.end_date}
                                onChange={(e) =>
                                    form.setData('end_date', e.target.value)
                                }
                            />
                            {form.errors.end_date && (
                                <p className="text-sm text-red-600">
                                    {form.errors.end_date}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ct-file">
                            Dokumen (PDF/gambar, opsional)
                        </Label>
                        <Input
                            id="ct-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                form.setData('file', e.target.files?.[0] ?? null)
                            }
                        />
                        {editing?.has_file && (
                            <p className="text-xs text-muted-foreground">
                                Unggah berkas baru untuk mengganti dokumen yang
                                ada.
                            </p>
                        )}
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
                    <Button onClick={submit} disabled={form.processing}>
                        Simpan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
