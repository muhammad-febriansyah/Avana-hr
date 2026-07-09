import { Head, router } from '@inertiajs/react';
import { ClipboardList, RefreshCw } from 'lucide-react';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { index, rebuild } from '@/routes/attendance';

type Summary = {
    id: number;
    employee: string | null;
    shift: string | null;
    clock_in: string | null;
    clock_out: string | null;
    status: string;
    late_minutes: number;
    work_minutes: number;
    is_locked: boolean;
};

type Props = {
    date: string;
    summaries: Summary[];
    counts: Record<string, number>;
};

const STATUS_LABELS: Record<string, string> = {
    present: 'Hadir',
    late: 'Terlambat',
    early_leave: 'Pulang Awal',
    absent: 'Alfa',
    leave: 'Cuti',
    holiday: 'Libur',
    day_off: 'Libur Jadwal',
    duty: 'Dinas',
    wfh: 'WFH',
};

export default function AttendanceIndex({ date, summaries, counts }: Props) {
    const { can } = usePermissions();
    const canManage = can('attendance.manage');

    const setDate = (value: string) =>
        router.get(
            index().url,
            { date: value },
            { preserveState: true, replace: true },
        );

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Kehadiran" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Kehadiran' },
                ]}
            />
            <PageHeader
                title="Monitoring Kehadiran"
                description="Ringkasan kehadiran harian dari absensi face recognition"
                action={
                    <div className="flex items-center gap-2">
                        <Input
                            type="date"
                            className="w-44"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                        {canManage && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        rebuild().url,
                                        { date },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw className="size-4" />
                                Susun Rekap
                            </Button>
                        )}
                    </div>
                }
            />

            {Object.keys(counts).length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {Object.entries(counts).map(([status, count]) => (
                        <div
                            key={status}
                            className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm"
                        >
                            <StatusBadge
                                status={status}
                                label={STATUS_LABELS[status] ?? status}
                            />
                            <span className="font-medium">{count}</span>
                        </div>
                    ))}
                </div>
            )}

            <Card>
                <CardContent>
                    {summaries.length === 0 ? (
                        <EmptyState
                            icon={ClipboardList}
                            title="Belum ada rekap"
                            description="Rekap disusun otomatis tiap malam, atau klik Susun Rekap. Data kehadiran berasal dari absensi face recognition di aplikasi mobile."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Karyawan</TableHead>
                                        <TableHead>Shift</TableHead>
                                        <TableHead>Masuk</TableHead>
                                        <TableHead>Pulang</TableHead>
                                        <TableHead>Telat</TableHead>
                                        <TableHead>Kerja</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {summaries.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell className="font-medium">
                                                {s.employee}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {s.shift ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {s.clock_in ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {s.clock_out ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {s.late_minutes > 0
                                                    ? `${s.late_minutes}m`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {Math.floor(s.work_minutes / 60)}
                                                j {s.work_minutes % 60}m
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={s.status}
                                                    label={
                                                        STATUS_LABELS[
                                                            s.status
                                                        ] ?? s.status
                                                    }
                                                />
                                                {s.is_locked && (
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        🔒
                                                    </span>
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
        </div>
    );
}
