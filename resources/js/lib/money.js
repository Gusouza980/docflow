/**
 * Format integer cents as a pt-BR currency input value (without R$ prefix).
 * Example: 125050 → "1.250,50"
 */
export function formatBrlFromCents(cents) {
    if (cents === null || cents === undefined || cents === '') {
        return '';
    }

    const amount = Number(cents) / 100;

    if (Number.isNaN(amount)) {
        return '';
    }

    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

/**
 * Normalize a free-typed money string to pt-BR input format on blur.
 * Returns '' for empty input; otherwise tries to format as "1.250,50".
 */
export function formatBrlInput(value) {
    if (value === null || value === undefined) {
        return '';
    }

    const trimmed = String(value).trim().replace(/^\s*R\$\s*/i, '').replace(/\s/g, '');

    if (trimmed === '') {
        return '';
    }

    let normalized = trimmed;
    const hasComma = normalized.includes(',');
    const hasDot = normalized.includes('.');

    if (hasComma && hasDot) {
        const lastComma = normalized.lastIndexOf(',');
        const lastDot = normalized.lastIndexOf('.');
        const decimalSeparator = lastComma > lastDot ? ',' : '.';
        const thousandSeparator = decimalSeparator === ',' ? '.' : ',';
        normalized = normalized.split(thousandSeparator).join('');
        normalized = normalized.replace(decimalSeparator, '.');
    } else if (hasComma) {
        const parts = normalized.split(',');
        if (parts.length === 2 && parts[1].length <= 2) {
            normalized = `${parts[0]}.${parts[1]}`;
        } else {
            return trimmed;
        }
    } else if (hasDot) {
        const parts = normalized.split('.');
        if (parts.length === 2 && parts[1].length === 3 && parts[0].length <= 3) {
            normalized = parts.join('');
        }
    }

    const amount = Number(normalized);

    if (Number.isNaN(amount)) {
        return trimmed;
    }

    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

/**
 * Display helper: integer cents → "R$ 1.250,50"
 */
export function formatBrlCurrency(cents) {
    if (cents === null || cents === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(cents) / 100);
}
