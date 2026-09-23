import { Head, Link } from '@inertiajs/react';
import { Card, List, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

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
    return (
        <AppLayout title="Reports">
            <Head title="Reports" />
            <Card title="Reports">
                <Typography.Paragraph type="secondary">
                    Select a report to view details and download data.
                </Typography.Paragraph>
                <List
                    itemLayout="horizontal"
                    dataSource={reports}
                    renderItem={(item) => (
                        <List.Item>
                            <List.Item.Meta
                                title={<Link href={item.href}>{item.title}</Link>}
                                description={item.description}
                            />
                        </List.Item>
                    )}
                />
            </Card>
        </AppLayout>
    );
}
