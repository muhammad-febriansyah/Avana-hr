import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

// Design System 05 B7 — consistent status colors.
const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-green-100 text-green-800',
    active: 'bg-green-100 text-green-800',
    aktif: 'bg-green-100 text-green-800',
    present: 'bg-green-100 text-green-800',
    hadir: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    inactive: 'bg-red-100 text-red-800',
    nonaktif: 'bg-red-100 text-red-800',
    absent: 'bg-red-100 text-red-800',
    alfa: 'bg-red-100 text-red-800',
    draft: 'bg-slate-100 text-slate-700',
    locked: 'bg-blue-100 text-blue-800',
    paid: 'bg-blue-100 text-blue-800',
    late: 'bg-orange-100 text-orange-800',
    terlambat: 'bg-orange-100 text-orange-800',
};

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    const style =
        STATUS_STYLES[status.toLowerCase()] ?? 'bg-slate-100 text-slate-700';

    return (
        <Badge className={cn('border-transparent', style, className)}>
            {label ?? status}
        </Badge>
    );
}
