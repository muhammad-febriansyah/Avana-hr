import { Head, router, useForm } from '@inertiajs/react';
import { CalendarOff, Plus, Trash2 } from 'lucide-react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { destroy, store } from '@/routes/holidays';

type Holiday = {
    id: number;
    date: string;
    name: string;
    is_national: boolean;
};

type Props = { holidays: Holiday[] };

export default function HolidaysIndex({ holidays }: Props) {
    const { can } = usePermissions();
    const canManage = can('shift.manage');
    const form = useForm({ date: '', name: '' });

    const submit = () => {
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Hari Libur" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Hari Libur' },
                ]}
            />
            <PageHeader
                title="Hari Libur"
                description="Libur nasional & cuti bersama yang memengaruhi penjadwalan"
            />

            {canManage && (
                <Card>
                    <CardContent className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="hd-date">Tanggal</Label>
                            <Input
                                id="hd-date"
                                type="date"
                                className="w-44"
                                value={form.data.date}
                                onChange={(e) =>
                                    form.setData('date', e.target.value)
                                }
                            />
                            {form.errors.date && (
                                <p className="text-sm text-red-600">
                                    {form.errors.date}
                                </p>
                            )}
                        </div>
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="hd-name">Nama</Label>
                            <Input
                                id="hd-name"
                                placeholder="mis. Idul Fitri"
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
                        <Button onClick={submit} disabled={form.processing}>
                            <Plus className="size-4" />
                            Tambah
                        </Button>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardContent>
                    {holidays.length === 0 ? (
                        <EmptyState
                            icon={CalendarOff}
                            title="Belum ada hari libur"
                            description="Tambahkan hari libur perusahaan atau libur nasional."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Nama</TableHead>
                                    {canManage && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {holidays.map((holiday) => (
                                    <TableRow key={holiday.id}>
                                        <TableCell className="font-mono text-xs">
                                            {holiday.date}
                                        </TableCell>
                                        <TableCell>
                                            {holiday.name}
                                            {holiday.is_national && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    Nasional
                                                </Badge>
                                            )}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-right">
                                                {!holiday.is_national && (
                                                    <ConfirmDialog
                                                        title={`Hapus ${holiday.name}?`}
                                                        onConfirm={() =>
                                                            router.delete(
                                                                destroy(
                                                                    holiday.id,
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
                                                                aria-label={`Hapus ${holiday.name}`}
                                                            >
                                                                <Trash2 className="size-4 text-red-600" />
                                                            </Button>
                                                        }
                                                    />
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
