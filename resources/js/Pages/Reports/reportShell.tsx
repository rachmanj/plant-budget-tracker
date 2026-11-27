import { Card, theme } from 'antd';
import type { ReactNode } from 'react';
import { AppPage, type AppPageKpi } from '@/Components/AppPage';
import { hairlineCardStyle } from '@/Pages/Procurement/registerIndexUi';

export type ReportKpi = AppPageKpi;

export { formatReportPeriodSubtitle } from '@/Pages/Reports/formatReportPeriodSubtitle';

export function ReportPage({
    title,
    subtitle,
    kpis,
    actions,
    children,
}: {
    title: string;
    subtitle?: ReactNode;
    kpis?: ReportKpi[];
    actions?: ReactNode;
    children: ReactNode;
}) {
    const { token } = theme.useToken();

    return (
        <AppPage title={title} subtitle={subtitle} kpis={kpis} actions={actions}>
            <Card style={hairlineCardStyle(token)} styles={{ body: { padding: 16 } }}>
                {children}
            </Card>
        </AppPage>
    );
}