import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, CalendarPlus, Users } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { cancel, index, store } from '@/routes/leave';

type LeaveTypeOption = {
    id: number;
    name: string;
    requires_attachment: boolean;
    min_notice_days: number;
};
type Balance = {
    type: string | null;
    entitled: number;
    used: number;
    pending: number;
    expired: number;
    available: number;
};
type MyRequest = {
    id: number;
    type: string | null;
    start_date: string;
    end_date: string;
    total_days: number;
    status: string;
    can_cancel: boolean;
};
type TeamLeave = {
    employee: string | null;
    type: string | null;
    start_date: string;
    end_date: string;
    status: string;
};

type Props = {
    employeeId: number | null;
    leaveTypes: LeaveTypeOption[];
    balances: Balance[];
    requests: MyRequest[];
    month: string;
    teamLeaves: TeamLeave[];
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const overlaps = (a: TeamLeave, b: TeamLeave) =>
    a.start_date <= b.end_date && b.start_date <= a.end_date;

export default function LeaveIndex({
    employeeId,
    leaveTypes,
    balances,
    requests,
    month,
    teamLeaves,
}: Props) {
    const { can } = usePermissions();
    const canRequest = can('leave.request') && employeeId !== null;
    const [open, setOpen] = useState(false);

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Cuti" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Cuti' },
                ]}
            />
            <PageHeader
                title="Cuti"
                description="Saldo, pengajuan, dan kalender cuti tim"
                action={
                    canRequest && (
                        <Button onClick={() => setOpen(true)}>
                            <CalendarPlus className="size-4" />
                            Ajukan Cuti
                        </Button>
                    )
                }
            />

            {employeeId !== null && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {balances.length === 0 ? (
                        <Card className="sm:col-span-2 lg:col-span-4">
                            <CardContent className="py-4 text-sm text-muted-foreground">
                                Belum ada saldo cuti. Saldo dibuat saat accrual
                                tahunan atau pengajuan pertama.
                            </CardContent>
                        </Card>
                    ) : (
                        balances.map((b) => (
                            <Card key={b.type}>
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        {b.type}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-semibold">
                                        {b.available}
                                        <span className="text-sm font-normal text-muted-foreground">
                                            {' '}
                                            hari tersedia
                                        </span>
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {b.pending} pending · {b.used} terpakai ·{' '}
                                        {b.expired} hangus
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            )}

            {employeeId !== null && (
                <Card>
                    <CardHeader>
                        <CardTitle>Pengajuan Saya</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {requests.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">
                                Belum ada pengajuan cuti.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Hari</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell className="font-medium">
                                                {r.type}
                                            </TableCell>
                                            <TableCell>
                                                {r.start_date} → {r.end_date}
                                            </TableCell>
                                            <TableCell>{r.total_days}</TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={r.status}
                                                    label={
                                                        STATUS_LABELS[
                                                            r.status
                                                        ] ?? r.status
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {r.can_cancel && canRequest && (
                                                    <ConfirmDialog
                                                        title="Batalkan pengajuan cuti?"
                                                        description="Saldo akan dikembalikan."
                                                        confirmLabel="Ya, Batalkan"
                                                        onConfirm={() =>
                                                            router.post(
                                                                cancel(r.id)
                                                                    .url,
                                                                {},
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                Batalkan
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader className="flex-row items-center justify-between gap-3">
                    <CardTitle>Kalender Cuti Tim</CardTitle>
                    <Input
                        type="month"
                        className="w-40"
                        value={month}
                        onChange={(e) =>
                            router.get(
                                index().url,
                                { month: e.target.value },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                </CardHeader>
                <CardContent>
                    {teamLeaves.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Tidak ada cuti bulan ini"
                            description="Belum ada pengajuan cuti tim pada periode ini."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Karyawan</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {teamLeaves.map((leave, i) => {
                                    const overlap = teamLeaves.some(
                                        (o, j) => j !== i && overlaps(leave, o),
                                    );

                                    return (
                                        <TableRow key={i}>
                                            <TableCell className="font-medium">
                                                {leave.employee}
                                                {overlap && (
                                                    <span
                                                        className="ml-2 inline-flex items-center gap-1 text-xs text-amber-600"
                                                        title="Overlap dengan cuti lain"
                                                    >
                                                        <AlertTriangle className="size-3" />
                                                        overlap
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {leave.type}
                                            </TableCell>
                                            <TableCell>
                                                {leave.start_date} →{' '}
                                                {leave.end_date}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={leave.status}
                                                    label={
                                                        STATUS_LABELS[
                                                            leave.status
                                                        ] ?? leave.status
                                                    }
                                                />
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {canRequest && (
                <RequestDialog
                    open={open}
                    onOpenChange={setOpen}
                    leaveTypes={leaveTypes}
                />
            )}
        </div>
    );
}

function RequestDialog({
    open,
    onOpenChange,
    leaveTypes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    leaveTypes: LeaveTypeOption[];
}) {
    const form = useForm<{
        leave_type_id: string;
        start_date: string;
        end_date: string;
        reason: string;
        attachment: File | null;
    }>({
        leave_type_id: String(leaveTypes[0]?.id ?? ''),
        start_date: '',
        end_date: '',
        reason: '',
        attachment: null,
    });

    const selected = leaveTypes.find(
        (t) => String(t.id) === form.data.leave_type_id,
    );

    const submit = () => {
        form.post(store().url, {
            forceFormData: true,
            preserveScroll: true,
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
                    <DialogTitle>Ajukan Cuti</DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>Jenis Cuti</Label>
                        <Select
                            value={form.data.leave_type_id}
                            onValueChange={(v) =>
                                form.setData('leave_type_id', v)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {leaveTypes.map((t) => (
                                    <SelectItem key={t.id} value={String(t.id)}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.leave_type_id && (
                            <p className="text-sm text-red-600">
                                {form.errors.leave_type_id}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="lv-start">Mulai</Label>
                            <Input
                                id="lv-start"
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
                            <Label htmlFor="lv-end">Selesai</Label>
                            <Input
                                id="lv-end"
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
                        <Label htmlFor="lv-reason">Alasan</Label>
                        <Input
                            id="lv-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                    </div>
                    {selected?.requires_attachment && (
                        <div className="grid gap-2">
                            <Label htmlFor="lv-file">Lampiran (wajib)</Label>
                            <Input
                                id="lv-file"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) =>
                                    form.setData(
                                        'attachment',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            {form.errors.attachment && (
                                <p className="text-sm text-red-600">
                                    {form.errors.attachment}
                                </p>
                            )}
                        </div>
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
                        Ajukan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
