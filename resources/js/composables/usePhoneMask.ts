/**
 * Brazilian phone mask: "(11) 98765-4321" or "(11) 3456-7890".
 * Numbers starting with "+" are left alone (international format); the
 * server normalizes everything to E.164.
 */
export function maskBrazilianPhone(value: string): string {
    if (value.trim().startsWith('+')) {
        return value;
    }

    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 2) {
        return digits.length ? `(${digits}` : '';
    }

    const area = digits.slice(0, 2);
    const rest = digits.slice(2);
    const split = rest.length > 8 ? 5 : 4;

    return rest.length > split
        ? `(${area}) ${rest.slice(0, split)}-${rest.slice(split)}`
        : `(${area}) ${rest}`;
}
