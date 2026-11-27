import { Card, Col, Flex, Row, Space, theme, Typography } from 'antd';
import type { ReactNode } from 'react';
import { formatDocCount, hairlineCardStyle } from '@/Pages/Procurement/registerIndexUi';

export interface AppPageKpi {
    label: string;
    value: ReactNode;
    hint?: string;
}

function kpiColLg(count: number): number {
    if (count <= 1) {
        return 24;
    }

    if (count === 2) {
        return 12;
    }

    if (count === 3) {
        return 8;
    }

    return 6;
}

function AppPageKpiCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: ReactNode;
    hint?: string;
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
                    {value}
                </div>
                {hint ? (
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {hint}
                    </Typography.Text>
                ) : null}
            </Space>
        </Card>
    );
}

export function AppPage({
    title,
    subtitle,
    kpis,
    actions,
    children,
}: {
    title: string;
    subtitle?: ReactNode;
    kpis?: AppPageKpi[];
    actions?: ReactNode;
    children: ReactNode;
}) {
    const kpiCount = kpis?.length ?? 0;
    const lgSpan = kpiColLg(kpiCount);
    const useFlexRow = kpiCount > 4;

    return (
        <>
            <Flex justify="space-between" align="flex-start" wrap="wrap" gap={12} style={{ marginBottom: 18 }}>
                <Space direction="vertical" size={4}>
                    <Typography.Title level={3} style={{ margin: 0 }}>
                        {title}
                    </Typography.Title>
                    {subtitle ? (
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            {subtitle}
                        </Typography.Text>
                    ) : null}
                </Space>
                {actions ? <div>{actions}</div> : null}
            </Flex>

            {kpis && kpis.length > 0 ? (
                <Row gutter={[12, 12]} style={{ marginBottom: 12 }}>
                    {kpis.map((kpi) => (
                        <Col
                            key={kpi.label}
                            xs={24}
                            sm={12}
                            lg={useFlexRow ? undefined : lgSpan}
                            flex={useFlexRow ? '1 1 0' : undefined}
                            style={useFlexRow ? { minWidth: 0 } : undefined}
                        >
                            <AppPageKpiCard label={kpi.label} value={kpi.value} hint={kpi.hint} />
                        </Col>
                    ))}
                </Row>
            ) : null}

            {children}
        </>
    );
}