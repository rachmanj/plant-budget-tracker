import { DownloadOutlined } from '@ant-design/icons';
import { Button, Space } from 'antd';

interface ReportExportButtonsProps {
    reportType: string;
    projectCode?: string;
    month?: string;
    canExport: boolean;
}

function exportUrl(reportType: string, format: 'pdf' | 'csv', projectCode?: string, month?: string): string {
    const params = new URLSearchParams();
    if (projectCode) {
        params.set('project_code', projectCode);
    }
    if (month) {
        params.set('month', month);
    }
    const query = params.toString();

    return `/reports/${reportType}/export/${format}${query ? `?${query}` : ''}`;
}

export default function ReportExportButtons({
    reportType,
    projectCode,
    month,
    canExport,
}: ReportExportButtonsProps) {
    if (!canExport) {
        return null;
    }

    return (
        <Space wrap style={{ marginBottom: 16 }}>
            <Button
                type="primary"
                icon={<DownloadOutlined />}
                href={exportUrl(reportType, 'pdf', projectCode, month)}
            >
                Download PDF
            </Button>
            <Button icon={<DownloadOutlined />} href={exportUrl(reportType, 'csv', projectCode, month)}>
                Download CSV
            </Button>
        </Space>
    );
}
