import { formatDocCount } from '@/Pages/Procurement/registerIndexUi';

export function formatReportPeriodSubtitle(
    from: string,
    to: string,
    rowCount?: number,
): string {
    const parts: string[] = [];

    if (from && to) {
        parts.push(`${from} to ${to}`);
    }

    if (rowCount !== undefined) {
        parts.push(`${formatDocCount(rowCount)} rows`);
    }

    return parts.join(', ');
}