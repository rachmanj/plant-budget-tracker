import { Progress, Tooltip, Typography } from 'antd';
import { formatIdr, utilizationColor } from '@/hooks/useCurrency';

interface BudgetProgressBarProps {
    utilizationPct: string | number;
    committed?: string | number;
    actual?: string | number;
    cap?: string | number;
    pagu?: string | number;
    remaining?: string | number;
    additionalAmount?: string | number;
    showLabel?: boolean;
}

export default function BudgetProgressBar({
    utilizationPct,
    committed,
    actual,
    cap,
    pagu,
    remaining,
    additionalAmount,
    showLabel = true,
}: BudgetProgressBarProps) {
    const pct = typeof utilizationPct === 'string' ? parseFloat(utilizationPct) : utilizationPct;
    const displayPct = Number.isNaN(pct) ? 0 : Math.min(pct, 150);
    const status = utilizationColor(utilizationPct);

    const tooltip = (
        <div>
            <div>Penggunaan: {pct.toFixed(2)}%</div>
            {pagu !== undefined && <div>Pagu proyek: {formatIdr(pagu)}</div>}
            {committed !== undefined && <div>Komitmen: {formatIdr(committed)}</div>}
            {actual !== undefined && <div>Aktual: {formatIdr(actual)}</div>}
            {additionalAmount !== undefined && (
                <div>+ permintaan ini: {formatIdr(additionalAmount)}</div>
            )}
            {remaining !== undefined && <div>Sisa pagu: {formatIdr(remaining)}</div>}
            {cap !== undefined && <div>Batas toleransi: {formatIdr(cap)}</div>}
        </div>
    );

    return (
        <Tooltip title={tooltip}>
            <div>
                <Progress
                    percent={displayPct}
                    status={status}
                    size="small"
                    format={() => `${pct.toFixed(1)}%`}
                />
                {showLabel && (
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {pct < 90 ? 'Aman' : pct <= 110 ? 'Mendekati batas' : 'Melebihi batas'}
                    </Typography.Text>
                )}
            </div>
        </Tooltip>
    );
}
