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
