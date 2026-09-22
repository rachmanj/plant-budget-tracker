export function formatIdr(value: string | number): string {
    const num = typeof value === 'string' ? parseFloat(value) : value;

    if (Number.isNaN(num)) {
        return 'Rp 0,00';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

export function formatIdrCompact(value: string | number): string {
    const num = typeof value === 'string' ? parseFloat(value) : value;

    if (Number.isNaN(num)) {
        return 'Rp 0';
    }

    const sign = num < 0 ? '-' : '';
    const abs = Math.abs(num);

    const tiers: Array<[number, string]> = [
        [1_000_000_000_000, 'T'],
        [1_000_000_000, 'M'],
        [1_000_000, 'jt'],
        [1_000, 'rb'],
    ];

    for (const [threshold, suffix] of tiers) {
        if (abs >= threshold) {
            const scaled = abs / threshold;
            const formatted = scaled.toLocaleString('id-ID', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
            });

            return `${sign}Rp ${formatted} ${suffix}`;
        }
    }

    return `${sign}Rp ${abs.toLocaleString('id-ID')}`;
}

export function parseIdrInput(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '0.00';
    }

    const normalized = String(value).replace(/[^\d.,-]/g, '').replace(',', '.');

    return Number.parseFloat(normalized).toFixed(2);
}

export function utilizationColor(pct: string | number): 'success' | 'warning' | 'exception' {
    const value = typeof pct === 'string' ? parseFloat(pct) : pct;

    if (value > 110) {
        return 'exception';
    }

    if (value >= 90) {
        return 'warning';
    }

    return 'success';
}
