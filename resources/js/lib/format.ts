/**
 * Formatting helpers — Rupiah + WIB dates (Design System 05 B7).
 */

const TZ = 'Asia/Jakarta';

/**
 * Format an integer amount of Rupiah, e.g. 1234567 -> "Rp 1.234.567".
 */
export function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

type DateInput = Date | string | number;

function toDate(value: DateInput): Date {
    return value instanceof Date ? value : new Date(value);
}

/**
 * "5 Juli 2026" in WIB.
 */
export function formatDate(value: DateInput): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: TZ,
    }).format(toDate(value));
}

/**
 * "5 Jul 2026, 08:30 WIB".
 */
export function formatDateTime(value: DateInput): string {
    const formatted = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: TZ,
    }).format(toDate(value));

    return `${formatted} WIB`;
}

/**
 * Minutes -> "2 jam 30 menit" / "45 menit" / "3 jam".
 */
export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    const parts: string[] = [];

    if (hours > 0) {
        parts.push(`${hours} jam`);
    }

    if (mins > 0 || hours === 0) {
        parts.push(`${mins} menit`);
    }

    return parts.join(' ');
}

/**
 * Strip non-digits from a formatted currency string -> integer.
 */
export function parseRupiah(value: string): number {
    const digits = value.replace(/\D/g, '');

    return digits ? parseInt(digits, 10) : 0;
}

/**
 * Group digits with dots for live display, e.g. "5000000" -> "5.000.000".
 */
export function groupDigits(value: string): string {
    const digits = value.replace(/\D/g, '');

    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
