import { forwardRef } from 'react';
import { groupDigits, parseRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = Omit<React.ComponentProps<'input'>, 'value' | 'onChange'> & {
    value: number;
    onChange: (value: number) => void;
};

/**
 * Rupiah input: displays grouped digits (5.000.000) with a fixed "Rp" prefix,
 * emits the raw integer (Design System 05 B5.8).
 */
export const CurrencyInput = forwardRef<HTMLInputElement, Props>(
    function CurrencyInput({ value, onChange, className, ...props }, ref) {
        return (
            <div className="relative">
                <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                    Rp
                </span>
                <input
                    ref={ref}
                    inputMode="numeric"
                    value={value ? groupDigits(String(value)) : ''}
                    onChange={(event) =>
                        onChange(parseRupiah(event.target.value))
                    }
                    className={cn(
                        'flex h-9 w-full rounded-md border border-input bg-transparent py-1 pr-3 pl-9 text-right text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                        className,
                    )}
                    {...props}
                />
            </div>
        );
    },
);
