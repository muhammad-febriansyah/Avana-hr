import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Inbox, XCircle } from 'lucide-react';
import { EmptyState } from '@/components/shared/empty-state';
import { PageBreadcrumb } from '@/components/shared/page-breadcrumb';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import { approve, reject } from '@/routes/approvals';

type Item = {
    approval_id: number;
    type: string;
    requester: string;
    step: number;
    created_at: string | null;
};

export default function ApprovalsIndex({ items }: { items: Item[] }) {
    const act = (id: number, kind: 'approve' | 'reject') => {
        const target = kind === 'approve' ? approve(id) : reject(id);
        router.post(target.url, {}, { preserveScroll: true });
    };

    return (
        <div className="flex flex-col gap-5 p-6">
            <Head title="Persetujuan Saya" />

            <PageBreadcrumb
                items={[
                    { title: 'Dashboard', href: dashboard().url },
                    { title: 'Persetujuan Saya' },
                ]}
            />
            <PageHeader
                title="Persetujuan Saya"
                description="Pengajuan yang menunggu tindakan Anda"
            />

            <Card>
                <CardContent>
                    {items.length === 0 ? (
                        <EmptyState
                            icon={Inbox}
                            title="Tidak ada persetujuan menunggu"
                            description="Semua pengajuan sudah Anda proses."
                        />
                    ) : (
                        <div className="flex flex-col divide-y divide-border">
                            {items.map((item) => (
                                <div
                                    key={item.approval_id}
                                    className="flex items-center gap-4 py-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium text-foreground">
                                            {item.type} · Langkah {item.step}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            Diajukan oleh {item.requester}
                                            {item.created_at
                                                ? ` · ${formatDateTime(item.created_at)}`
                                                : ''}
                                        </div>
                                    </div>
                                    <div className="flex flex-none gap-2">
                                        <Button
                                            variant="approve"
                                            size="sm"
                                            onClick={() =>
                                                act(item.approval_id, 'approve')
                                            }
                                        >
                                            <CheckCircle2 className="size-4" />{' '}
                                            Setujui
                                        </Button>
                                        <Button
                                            variant="reject"
                                            size="sm"
                                            onClick={() =>
                                                act(item.approval_id, 'reject')
                                            }
                                        >
                                            <XCircle className="size-4" /> Tolak
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
