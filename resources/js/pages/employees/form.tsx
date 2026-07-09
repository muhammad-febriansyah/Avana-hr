import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
import { index, store, update } from '@/routes/employees';

type Option = { value: string; label: string };
type Entity = { id: number; name?: string; full_name?: string; code?: string };
type Position = { id: number; name: string };
type Grade = { id: number; code: string; name: string };
type Manager = { id: number; full_name: string; employee_code: string };

type Employee = Record<string, unknown> & {
    id: number;
    employee_code: string;
    full_name: string;
    status: string;
};

type Definition = {
    id: number;
    label: string;
    key: string;
    field_type: string;
    options: string[];
    is_required: boolean;
};

type Props = {
    employee: Employee | null;
    positions: Position[];
    grades: Grade[];
    orgUnits: Entity[];
    branches: Entity[];
    managers: Manager[];
    customFields: Definition[];
    customValues: Record<string, string | null>;
    options: {
        gender: Option[];
        marital_status: Option[];
        ptkp_status: Option[];
        employment_status: Option[];
    };
};

const NONE = '__none__';

const str = (v: unknown) => (v == null ? '' : String(v));

export default function EmployeeForm({
    employee,
    positions,
    grades,
    orgUnits,
    branches,
    managers,
    customFields,
    customValues,
    options,
}: Props) {
    const initialCustom: Record<string, string> = {};
    customFields.forEach((f) => {
        initialCustom[f.id] = str(customValues[f.id]);
    });
    const [customData, setCustomData] =
        useState<Record<string, string>>(initialCustom);

    const form = useForm({
        full_name: str(employee?.full_name),
        nik_ktp: str(employee?.nik_ktp),
        npwp: str(employee?.npwp),
        email: str(employee?.email),
        phone: str(employee?.phone),
        birth_date: str(employee?.birth_date),
        gender: str(employee?.gender) || NONE,
        marital_status: str(employee?.marital_status) || NONE,
        ptkp_status: str(employee?.ptkp_status) || NONE,
        position_id: str(employee?.position_id) || NONE,
        grade_id: str(employee?.grade_id) || NONE,
        org_unit_id: str(employee?.org_unit_id) || NONE,
        direct_manager_employee_id:
            str(employee?.direct_manager_employee_id) || NONE,
        branch_id: str(employee?.branch_id) || NONE,
        employment_status: str(employee?.employment_status) || NONE,
        join_date: str(employee?.join_date),
        status: str(employee?.status) || 'active',
        bank_name: str(employee?.bank_name),
        bank_account: str(employee?.bank_account),
        bank_account_name: str(employee?.bank_account_name),
        bpjs_kes_no: str(employee?.bpjs_kes_no),
        bpjs_tk_no: str(employee?.bpjs_tk_no),
    });

    type Key = keyof typeof form.data;
    const nullable = (v: string) => (v === NONE || v === '' ? null : v);
    const asId = (v: string) => (v === NONE || v === '' ? null : Number(v));

    const submit = () => {
        form.transform((d) => ({
            ...d,
            gender: nullable(d.gender),
            marital_status: nullable(d.marital_status),
            ptkp_status: nullable(d.ptkp_status),
            employment_status: nullable(d.employment_status),
            position_id: asId(d.position_id),
            grade_id: asId(d.grade_id),
            org_unit_id: asId(d.org_unit_id),
            direct_manager_employee_id: asId(d.direct_manager_employee_id),
            branch_id: asId(d.branch_id),
            nik_ktp: d.nik_ktp || null,
            npwp: d.npwp || null,
            email: d.email || null,
            birth_date: d.birth_date || null,
            join_date: d.join_date || null,
            custom_fields: customData,
        }));

        if (employee) {
            form.put(update(employee.id).url);
        } else {
            form.post(store().url);
        }
    };

    const text = (key: Key, label: string, type = 'text') => (
        <div className="grid gap-2">
            <Label htmlFor={key}>{label}</Label>
            <Input
                id={key}
                type={type}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
            />
            {form.errors[key] && (
                <p className="text-sm text-red-600">{form.errors[key]}</p>
            )}
        </div>
    );

    const select = (
        key: Key,
        label: string,
        opts: Option[],
        placeholder = '-',
    ) => (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select
                value={form.data[key] || NONE}
                onValueChange={(v) => form.setData(key, v)}
            >
                <SelectTrigger>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>{placeholder}</SelectItem>
                    {opts.map((o) => (
                        <SelectItem key={o.value} value={o.value}>
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {form.errors[key] && (
                <p className="text-sm text-red-600">{form.errors[key]}</p>
            )}
        </div>
    );

    const positionOpts = positions.map((p) => ({
        value: String(p.id),
        label: p.name,
    }));
    const gradeOpts = grades.map((g) => ({
        value: String(g.id),
        label: `${g.code} — ${g.name}`,
    }));
    const orgOpts = orgUnits.map((o) => ({
        value: String(o.id),
        label: o.name ?? '',
    }));
    const branchOpts = branches.map((b) => ({
        value: String(b.id),
        label: b.name ?? '',
    }));
    const managerOpts = managers.map((m) => ({
        value: String(m.id),
        label: `${m.full_name} (${m.employee_code})`,
    }));

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title={employee ? 'Edit Karyawan' : 'Tambah Karyawan'} />

            <PageBreadcrumb
                items={[
                    { title: 'Karyawan', href: index().url },
                    { title: employee ? 'Edit' : 'Tambah' },
                ]}
            />
            <PageHeader
                title={
                    employee ? `Edit: ${employee.full_name}` : 'Tambah Karyawan'
                }
                description={
                    employee
                        ? `Kode: ${employee.employee_code}`
                        : 'Employee ID dibuat otomatis saat disimpan'
                }
            />

            <div className="grid max-w-4xl gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Data Pribadi</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            {text('full_name', 'Nama Lengkap')}
                        </div>
                        {text('nik_ktp', 'NIK KTP')}
                        {text('npwp', 'NPWP')}
                        {text('email', 'Email', 'email')}
                        {text('phone', 'Telepon')}
                        {text('birth_date', 'Tanggal Lahir', 'date')}
                        {select('gender', 'Jenis Kelamin', options.gender)}
                        {select(
                            'marital_status',
                            'Status Pernikahan',
                            options.marital_status,
                        )}
                        {select(
                            'ptkp_status',
                            'Status PTKP',
                            options.ptkp_status,
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Kepegawaian</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        {select('position_id', 'Posisi', positionOpts)}
                        {select('grade_id', 'Grade', gradeOpts)}
                        {select('org_unit_id', 'Unit Organisasi', orgOpts)}
                        {select('branch_id', 'Cabang Utama', branchOpts)}
                        {select(
                            'direct_manager_employee_id',
                            'Atasan Langsung',
                            managerOpts,
                        )}
                        {select(
                            'employment_status',
                            'Status Kepegawaian',
                            options.employment_status,
                        )}
                        {text('join_date', 'Tanggal Masuk', 'date')}
                        {employee &&
                            select('status', 'Status', [
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ])}
                    </CardContent>
                </Card>

                {customFields.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Tambahan</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {customFields.map((f) => {
                                const err = (
                                    form.errors as Record<string, string>
                                )[`custom_fields.${f.id}`];

                                return (
                                    <div key={f.id} className="grid gap-2">
                                        <Label htmlFor={`cf-${f.id}`}>
                                            {f.label}
                                            {f.is_required && ' *'}
                                        </Label>
                                        {f.field_type === 'select' ? (
                                            <Select
                                                value={customData[f.id] || NONE}
                                                onValueChange={(v) =>
                                                    setCustomData((p) => ({
                                                        ...p,
                                                        [f.id]:
                                                            v === NONE ? '' : v,
                                                    }))
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="-" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={NONE}>
                                                        -
                                                    </SelectItem>
                                                    {f.options.map((o) => (
                                                        <SelectItem
                                                            key={o}
                                                            value={o}
                                                        >
                                                            {o}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Input
                                                id={`cf-${f.id}`}
                                                type={
                                                    f.field_type === 'number'
                                                        ? 'number'
                                                        : f.field_type ===
                                                            'date'
                                                          ? 'date'
                                                          : 'text'
                                                }
                                                value={customData[f.id] ?? ''}
                                                onChange={(e) =>
                                                    setCustomData((p) => ({
                                                        ...p,
                                                        [f.id]: e.target.value,
                                                    }))
                                                }
                                            />
                                        )}
                                        {err && (
                                            <p className="text-sm text-red-600">
                                                {err}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Payroll & Bank</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        {text('bank_name', 'Nama Bank')}
                        {text('bank_account', 'No. Rekening')}
                        {text('bank_account_name', 'Nama Pemilik Rekening')}
                        <div />
                        {text('bpjs_kes_no', 'No. BPJS Kesehatan')}
                        {text('bpjs_tk_no', 'No. BPJS Ketenagakerjaan')}
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
        </div>
    );
}
