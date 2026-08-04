import { Head } from '@inertiajs/react';
import { Alert, Badge, Card, Table } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    logs: Array<{ id: number; operation: string; status: string; correlation_key: string }>;
    circuitBreaker?: { service_layer: boolean; sql_server: boolean };
    connectionTest?: { service_layer: boolean; sql_server: boolean };
}

export default function SyncDashboard({ logs, circuitBreaker, connectionTest }: Props) {
    return (
        <AppLayout title="SAP Sync Dashboard">
            <Head title="SAP Sync" />
            {circuitBreaker?.service_layer && <Alert type="warning" message="SAP Service Layer circuit breaker OPEN" />}
            <Card title="Sync Logs">
                <Table
                    rowKey="id"
                    dataSource={logs}
                    columns={[
                        { title: 'Operation', dataIndex: 'operation' },
                        { title: 'Key', dataIndex: 'correlation_key' },
                        { title: 'Status', dataIndex: 'status', render: (s: string) => <Badge status={s === 'success' ? 'success' : s === 'failed' ? 'error' : 'processing'} text={s} /> },
                    ]}
                />
            </Card>
            {connectionTest && (
                <Card title="Connection Test" style={{ marginTop: 16 }}>
                    Service Layer: {connectionTest.service_layer ? 'OK' : 'FAIL'} | SQL: {connectionTest.sql_server ? 'OK' : 'FAIL'}
                </Card>
            )}
        </AppLayout>
    );
}
