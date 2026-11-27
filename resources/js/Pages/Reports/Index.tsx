import { Head, Link } from '@inertiajs/react';
import { Button, Card, Col, Row, Space, theme, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import { hairlineCardStyle } from '@/Pages/Procurement/registerIndexUi';

interface ReportItem {
    key: string;
    title: string;
    description: string;
    href: string;
}

interface Props {
    reports: ReportItem[];
    can?: { export?: boolean };
}

export default function Index({ reports }: Props) {
    const { token } = theme.useToken();

    return (
        <AppLayout title="Reports">
            <Head title="Reports" />
            <Space direction="vertical" size={4} style={{ width: '100%', marginBottom: 18 }}>
                <Typography.Title level={3} style={{ margin: 0 }}>
                    Reports
                </Typography.Title>
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                    Select a report to view details and download data.
                </Typography.Text>
            </Space>
            <Row gutter={[12, 12]}>
                {reports.map((item) => (
                    <Col xs={24} md={12} key={item.key}>
                        <Card
                            style={hairlineCardStyle(token, { heightFill: true })}
                            styles={{ body: { padding: 16, display: 'flex', flexDirection: 'column', gap: 12 } }}
                        >
                            <Space direction="vertical" size={4} style={{ flex: 1 }}>
                                <Typography.Text strong style={{ fontSize: 15 }}>
                                    {item.title}
                                </Typography.Text>
                                <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                                    {item.description}
                                </Typography.Text>
                            </Space>
                            <div>
                                <Link href={item.href}>
                                    <Button type="primary" style={{ minHeight: 44 }}>
                                        Open
                                    </Button>
                                </Link>
                            </div>
                        </Card>
                    </Col>
                ))}
            </Row>
        </AppLayout>
    );
}