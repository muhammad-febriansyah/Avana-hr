import type { SVGAttributes } from 'react';

/**
 * AvanaHR monogram (A + stem + person swoosh). Uses currentColor so it adapts
 * to context — white on the navy sidebar, brand blue on light surfaces.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 120 92"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M16 84 L52 12"
                stroke="currentColor"
                strokeWidth="14"
                strokeLinecap="round"
            />
            <path
                d="M52 12 L88 84"
                stroke="currentColor"
                strokeWidth="14"
                strokeLinecap="round"
            />
            <path
                d="M104 12 L104 84"
                stroke="currentColor"
                strokeWidth="14"
                strokeLinecap="round"
            />
            <path
                d="M70 50 L104 50"
                stroke="currentColor"
                strokeWidth="12"
                strokeLinecap="round"
            />
            <path
                d="M20 72 Q52 48 96 68"
                stroke="currentColor"
                strokeWidth="7"
                strokeLinecap="round"
                opacity="0.55"
            />
            <circle cx="52" cy="50" r="8" fill="currentColor" opacity="0.75" />
        </svg>
    );
}
