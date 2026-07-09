import type { ComponentProps } from 'react';
import { Label } from '@/components/ui/label';

export function RequiredLabel({
    children,
    ...props
}: ComponentProps<typeof Label>) {
    return (
        <Label {...props}>
            {children} <span className="text-red-500">*</span>
        </Label>
    );
}
