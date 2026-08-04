import { Head } from '@inertiajs/react';
import { Card, Col, Row, Statistic, Typography, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

interface Widget {
    key: string;
    title: string;
    description: string;
}

interface DashboardProps {
    widgets: Widget[];
    roleNames: string[];
}

export default function Dashboard({ widgets, roleNames }: DashboardProps) {
    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <Typography.Paragraph>
                Selamat datang. Peran Anda:{' '}
                {roleNames.map((role) => (
                    <Tag key={role}>{role}</Tag>
                ))}
            </Typography.Paragraph>

            <Row gutter={[16, 16]}>
                {widgets.map((widget) => (
                    <Col xs={24} sm={12} lg={8} key={widget.key}>
                        <Card>
                            <Statistic title={widget.title} value="—" />
                            <Typography.Text type="secondary">{widget.description}</Typography.Text>
                        </Card>
                    </Col>
                ))}
            </Row>
        </AppLayout>
    );
}
