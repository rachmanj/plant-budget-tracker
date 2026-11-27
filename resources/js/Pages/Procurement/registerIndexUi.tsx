import { Button, Card, Col, Row, Space, theme, Tooltip, Typography } from 'antd';
import type { CSSProperties, ReactNode } from 'react';
import { formatIdr } from '@/hooks/useCurrency';

export type PillTone = 'blue' | 'green' | 'orange';

export function formatDocCount(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

export function hairlineCardStyle(
    token: ReturnType<typeof theme.useToken>['token'],
    options?: { heightFill?: boolean },
): CSSProperties {
    return {
        border: `1px solid ${token.colorBorderSecondary}`,
        boxShadow: 'none',
        ...(options?.heightFill ? { height: '100%' } : {}),
    };
}

export function StatusPill({ tone, children }: { tone: PillTone; children: ReactNode }) {
    const { token } = theme.useToken();

    const palette: Record<PillTone, { color: string; background: string }> = {
        blue: { color: token.colorPrimary, background: token.colorPrimaryBg },
        green: { color: token.colorSuccess, background: token.colorSuccessBg },
        orange: { color: token.colorWarning, background: token.colorWarningBg },
    };

    const { color, background } = palette[tone];

    return (
        <span
            style={{
                display: 'inline-block',
                fontSize: 11,
                fontWeight: 600,
                letterSpacing: '0.02em',
                padding: '3px 9px',
                borderRadius: 999,
                whiteSpace: 'nowrap',
                color,
                background,
                border: `1px solid color-mix(in srgb, ${color} 34%, transparent)`,
            }}
        >
            {children}
        </span>
    );
}

export function deliveryLabel(status: string): string {
    const normalized = status.trim().toLowerCase();

    if (normalized === 'n' || normalized === 'open') {
        return 'Open';
    }

    if (normalized === 'p' || normalized === 'partial') {
        return 'Partial';
    }

    if (normalized === 'c' || normalized === 'closed' || normalized === 'y') {
        return 'Closed';
    }

    if (status.trim() === '') {
        return 'Unknown';
    }

    return status;
}

export function deliveryTone(status: string): PillTone {
    const label = deliveryLabel(status).toLowerCase();

    if (label === 'closed') {
        return 'green';
    }

    if (label === 'open') {
        return 'blue';
    }

    return 'orange';
}

export function prStatusTone(status: string): PillTone {
    const normalized = status.trim().toLowerCase();

    if (normalized === 'c' || normalized === 'closed' || normalized === 'y') {
        return 'green';
    }

    if (normalized === 'o' || normalized === 'open' || normalized === 'n') {
        return 'blue';
    }

    return 'orange';
}

export function emptyCell(): string {
    return '-';
}

export function RegisterKpiCard({
    label,
    value,
    meta,
    valueNode,
}: {
    label: string;
    value?: string | number;
    meta?: ReactNode;
    valueNode?: ReactNode;
}) {
    const { token } = theme.useToken();

    return (
        <Card style={hairlineCardStyle(token, { heightFill: true })} styles={{ body: { padding: 16 } }}>
            <Space direction="vertical" size={6} style={{ width: '100%' }}>
                <Typography.Text
                    type="secondary"
                    style={{ fontSize: 11, textTransform: 'uppercase', letterSpacing: '0.06em', display: 'block' }}
                >
                    {label}
                </Typography.Text>
                <div
                    style={{
                        fontSize: 26,
                        fontWeight: 600,
                        lineHeight: 1.1,
                        letterSpacing: '-0.02em',
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {valueNode ??
                        (typeof value === 'number' ? formatDocCount(value) : value)}
                </div>
                {meta ? (
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {meta}
                    </Typography.Text>
                ) : null}
            </Space>
        </Card>
    );
}

export function RegisterKpiRow({ children }: { children: ReactNode }) {
    return (
        <Row gutter={[12, 12]} style={{ marginBottom: 12 }}>
            {children}
        </Row>
    );
}

export function RegisterKpiCol({ children }: { children: ReactNode }) {
    return (
        <Col xs={24} sm={12} lg={6}>
            {children}
        </Col>
    );
}

export function RegisterPageHeader({
    title,
    subtitle,
}: {
    title: string;
    subtitle: string;
}) {
    return (
        <Space direction="vertical" size={4} style={{ width: '100%', marginBottom: 18 }}>
            <Typography.Title level={3} style={{ margin: 0 }}>
                {title}
            </Typography.Title>
            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                {subtitle}
            </Typography.Text>
        </Space>
    );
}

export function RegisterFiltersCard({
    onReset,
    children,
}: {
    onReset: () => void;
    children: ReactNode;
}) {
    const { token } = theme.useToken();

    return (
        <Card
            style={{ ...hairlineCardStyle(token), marginBottom: 12 }}
            styles={{ body: { padding: 16 } }}
            title={
                <Typography.Text strong style={{ fontSize: 14 }}>
                    Filters
                </Typography.Text>
            }
            extra={
                <Button type="link" onClick={onReset} style={{ minHeight: 44 }}>
                    Reset
                </Button>
            }
        >
            <Space wrap size="middle" style={{ width: '100%' }}>
                {children}
            </Space>
        </Card>
    );
}

export function RegisterTableCard({
    showing,
    total,
    children,
}: {
    showing: number;
    total: number;
    children: ReactNode;
}) {
    const { token } = theme.useToken();

    return (
        <Card
            style={hairlineCardStyle(token)}
            styles={{ body: { padding: 16 } }}
            title={
                <div>
                    <Typography.Text strong style={{ fontSize: 14 }}>
                        Register
                    </Typography.Text>
                    <div>
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            {formatDocCount(showing)} of {formatDocCount(total)}
                        </Typography.Text>
                    </div>
                </div>
            }
        >
            {children}
        </Card>
    );
}

export function AmountCell({ value }: { value: string | null }) {
    if (!value) {
        return <span>{emptyCell()}</span>;
    }

    return (
        <Tooltip title={formatIdr(value)}>
            <span style={{ fontVariantNumeric: 'tabular-nums' }}>{formatIdr(value)}</span>
        </Tooltip>
    );
}