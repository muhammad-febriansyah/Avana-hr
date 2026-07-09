import { Head, Link, useForm } from '@inertiajs/react';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/branches';

type Branch = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    latitude: number | null;
    longitude: number | null;
    geofence_radius_m: number;
    timezone: string;
    cost_center: string | null;
};

type Timezone = { value: string; label: string };

type Props = {
    branch: Branch | null;
    timezones: Timezone[];
};

export default function BranchForm({ branch, timezones }: Props) {
    const form = useForm({
        code: branch?.code ?? '',
        name: branch?.name ?? '',
        address: branch?.address ?? '',
        latitude: branch?.latitude != null ? String(branch.latitude) : '',
        longitude: branch?.longitude != null ? String(branch.longitude) : '',
        geofence_radius_m: branch?.geofence_radius_m ?? 100,
        timezone: branch?.timezone ?? 'Asia/Jakarta',
        cost_center: branch?.cost_center ?? '',
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            address: data.address || null,
            latitude: data.latitude === '' ? null : Number(data.latitude),
            longitude: data.longitude === '' ? null : Number(data.longitude),
            cost_center: data.cost_center || null,
        }));

        if (branch) {
            form.put(update(branch.id).url);
        } else {
            form.post(store().url);
        }
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title={branch ? 'Edit Cabang' : 'Tambah Cabang'} />

            <PageBreadcrumb
                items={[
                    { title: 'Cabang', href: index().url },
                    { title: branch ? 'Edit' : 'Tambah' },
                ]}
            />
            <PageHeader
                title={branch ? `Edit Cabang: ${branch.name}` : 'Tambah Cabang'}
                description="Data lokasi kerja dan geofence untuk validasi kehadiran"
            />

            <Card className="max-w-3xl">
                <CardHeader>
                    <CardTitle>Data Cabang</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="code">Kode Cabang</Label>
                        <Input
                            id="code"
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
                        <Label htmlFor="name">Nama Cabang</Label>
                        <Input
                            id="name"
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

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="address">Alamat</Label>
                        <textarea
                            id="address"
                            rows={2}
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="latitude">Latitude</Label>
                        <Input
                            id="latitude"
                            inputMode="decimal"
                            placeholder="mis. -6.2088"
                            value={form.data.latitude}
                            onChange={(e) =>
                                form.setData('latitude', e.target.value)
                            }
                        />
                        {form.errors.latitude && (
                            <p className="text-sm text-red-600">
                                {form.errors.latitude}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="longitude">Longitude</Label>
                        <Input
                            id="longitude"
                            inputMode="decimal"
                            placeholder="mis. 106.8456"
                            value={form.data.longitude}
                            onChange={(e) =>
                                form.setData('longitude', e.target.value)
                            }
                        />
                        {form.errors.longitude && (
                            <p className="text-sm text-red-600">
                                {form.errors.longitude}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="radius">Radius Geofence (meter)</Label>
                        <Input
                            id="radius"
                            type="number"
                            value={form.data.geofence_radius_m}
                            onChange={(e) =>
                                form.setData(
                                    'geofence_radius_m',
                                    Number(e.target.value),
                                )
                            }
                        />
                        {form.errors.geofence_radius_m && (
                            <p className="text-sm text-red-600">
                                {form.errors.geofence_radius_m}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label>Zona Waktu</Label>
                        <Select
                            value={form.data.timezone}
                            onValueChange={(v) => form.setData('timezone', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {timezones.map((tz) => (
                                    <SelectItem key={tz.value} value={tz.value}>
                                        {tz.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2 sm:col-span-2 sm:max-w-xs">
                        <Label htmlFor="cost_center">Cost Center</Label>
                        <Input
                            id="cost_center"
                            value={form.data.cost_center}
                            onChange={(e) =>
                                form.setData('cost_center', e.target.value)
                            }
                        />
                    </div>

                    <p className="text-xs text-muted-foreground sm:col-span-2">
                        Isi koordinat manual dari Google Maps (klik lokasi →
                        salin lat, long). Map picker menyusul.
                    </p>
                </CardContent>
                <CardFooter className="justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={index().url}>Batal</Link>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        Simpan
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}
