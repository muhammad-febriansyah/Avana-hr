import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { generate, index } from '@/routes/schedules';

type Schedule = {
    employee_id: number;
    date: string;
    shift: string | null;
    is_day_off: boolean;
};

type Pattern = { id: number; name: string; cycle_days: number };
type EmployeeOption = { id: number; full_name: string };

type Props = {
    month: string;
    daysInMonth: number;
    schedules: Schedule[];
    patterns: Pattern[];
    employees: EmployeeOption[];
};

export default function SchedulesIndex({
    month,
    daysInMonth,
    schedules,
    patterns,
    employees,
}: Props) {
    const { can } = usePermissions();
    const canManage = can('shift.manage');

    const cell = new Map<string, Schedule>();
    const rowIds = new Set<number>();

    for (const s of schedules) {
        const day = Number(s.date.slice(8, 10));
        cell.set(`${s.employee_id}-${day}`, s);
        rowIds.add(s.employee_id);
    }

    const rows = employees.filter((e) => rowIds.has(e.id));
    const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Jadwal" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Jadwal' },
                ]}
            />
            <PageHeader
                title="Jadwal"
                description="Generate & tinjau jadwal kerja karyawan per bulan"
                action={
                    <Input
                        type="month"
                        className="w-44"
                        value={month}
                        onChange={(e) =>
                            router.get(
                                index().url,
                                { month: e.target.value },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                }
            />

            {canManage &&
                (patterns.length === 0 || employees.length === 0 ? (
                    <Card>
                        <CardContent className="py-4 text-sm text-muted-foreground">
                            Butuh minimal satu pola shift dan satu karyawan aktif
                            untuk generate jadwal.
                        </CardContent>
                    </Card>
                ) : (
                    <GenerateForm patterns={patterns} employees={employees} />
                ))}

            <Card>
                <CardContent>
                    {rows.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title="Belum ada jadwal bulan ini"
                            description="Generate jadwal dari pola shift di atas."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th className="sticky left-0 bg-background p-2 text-left font-medium">
                                            Karyawan
                                        </th>
                                        {days.map((d) => (
                                            <th
                                                key={d}
                                                className="w-8 p-1 text-center font-medium text-muted-foreground"
                                            >
                                                {d}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((employee) => (
                                        <tr
                                            key={employee.id}
                                            className="border-t border-border"
                                        >
                                            <td className="sticky left-0 bg-background p-2 font-medium whitespace-nowrap">
                                                {employee.full_name}
                                            </td>
                                            {days.map((d) => {
                                                const s = cell.get(
                                                    `${employee.id}-${d}`,
                                                );

                                                return (
                                                    <td
                                                        key={d}
                                                        className="p-1 text-center"
                                                        title={s?.shift ?? ''}
                                                    >
                                                        {!s ? (
                                                            <span className="text-muted-foreground">
                                                                ·
                                                            </span>
                                                        ) : s.is_day_off ? (
                                                            <span className="text-muted-foreground">
                                                                L
                                                            </span>
                                                        ) : (
                                                            <span className="inline-block size-2 rounded-full bg-primary" />
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <p className="mt-3 text-xs text-muted-foreground">
                                <span className="inline-block size-2 rounded-full bg-primary align-middle" />{' '}
                                kerja · L = libur/cuti · · = belum dijadwalkan
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function GenerateForm({
    patterns,
    employees,
}: {
    patterns: Pattern[];
    employees: EmployeeOption[];
}) {
    const form = useForm<{
        pattern_id: string;
        employee_ids: number[];
        start_date: string;
        end_date: string;
    }>({
        pattern_id: String(patterns[0]?.id ?? ''),
        employee_ids: [],
        start_date: '',
        end_date: '',
    });

    const toggle = (id: number) => {
        form.setData(
            'employee_ids',
            form.data.employee_ids.includes(id)
                ? form.data.employee_ids.filter((e) => e !== id)
                : [...form.data.employee_ids, id],
        );
    };

    const submit = () => {
        form.transform((d) => ({ ...d, pattern_id: Number(d.pattern_id) }));
        form.post(generate().url, { preserveScroll: true });
    };

    return (
        <Card>
            <CardContent className="grid gap-4">
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label>Pola Shift</Label>
                        <Select
                            value={form.data.pattern_id}
                            onValueChange={(v) => form.setData('pattern_id', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {patterns.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.name} ({p.cycle_days} hari)
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="sc-start">Mulai</Label>
                        <Input
                            id="sc-start"
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
                        <Label htmlFor="sc-end">Selesai</Label>
                        <Input
                            id="sc-end"
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
                    <Label>Karyawan ({form.data.employee_ids.length})</Label>
                    <div className="grid max-h-40 grid-cols-2 gap-1 overflow-y-auto rounded-md border border-border p-2 sm:grid-cols-3">
                        {employees.map((employee) => (
                            <label
                                key={employee.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    checked={form.data.employee_ids.includes(
                                        employee.id,
                                    )}
                                    onCheckedChange={() => toggle(employee.id)}
                                />
                                <span className="truncate">
                                    {employee.full_name}
                                </span>
                            </label>
                        ))}
                    </div>
                    {form.errors.employee_ids && (
                        <p className="text-sm text-red-600">
                            {form.errors.employee_ids}
                        </p>
                    )}
                </div>
                <div className="flex justify-end">
                    <Button
                        onClick={submit}
                        disabled={
                            form.processing ||
                            form.data.employee_ids.length === 0
                        }
                    >
                        Generate Jadwal
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
