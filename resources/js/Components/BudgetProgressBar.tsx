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
            <div>Utilization: {pct.toFixed(2)}%</div>
            {pagu !== undefined && <div>Project ceiling: {formatIdr(pagu)}</div>}
            {committed !== undefined && <div>Committed: {formatIdr(committed)}</div>}
            {actual !== undefined && <div>Actual: {formatIdr(actual)}</div>}
            {additionalAmount !== undefined && (
                <div>+ this request: {formatIdr(additionalAmount)}</div>
            )}
            {remaining !== undefined && <div>Remaining: {formatIdr(remaining)}</div>}
            {cap !== undefined && <div>Tolerance cap: {formatIdr(cap)}</div>}
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
                        {pct < 90 ? 'Within limit' : pct <= 110 ? 'Near limit' : 'Over limit'}
                    </Typography.Text>
                )}
            </div>
        </Tooltip>
    );
}
