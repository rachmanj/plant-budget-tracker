import { Head, router } from '@inertiajs/react';
import { Alert, Button, Card, Input, Space, Table, Tag, Typography } from 'antd';
import type { TablePaginationConfig } from 'antd/es/table';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

interface SupplierRow {
    id: number;
    card_code: string;
    card_name: string;
    payment_terms: string | null;
    currency: string | null;
    is_active: boolean;
    synced_at: string | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface SyncSummary {
    created: number;
    updated: number;
    deactivated?: number;
}

interface Props {
    suppliers: Paginator<SupplierRow>;
    filters: { search: string | null };
    perPage: number;
    can: { sync: boolean };
    syncSummary?: SyncSummary | null;
}

function formatSyncedAt(value: string | null): string {
    return value ? dayjs(value).format('DD MMM YYYY HH:mm') : '—';
}

export default function Index({ suppliers, filters, perPage, can, syncSummary }: Props) {
    const [searchValue, setSearchValue] = useState(filters.search ?? '');
    const [syncing, setSyncing] = useState(false);

    useEffect(() => {
        setSearchValue(filters.search ?? '');
    }, [filters.search]);

    const navigate = (overrides: Record<string, unknown>) => {
        router.get(
            '/procurement/suppliers',
            {
                search: filters.search ?? undefined,
                per_page: perPage,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearch = (value: string) => navigate({ search: value || undefined, page: 1 });

    const handleTableChange = (pagination: TablePaginationConfig) => {
        navigate({ page: pagination.current, per_page: pagination.pageSize });
    };

    const runSync = () => {
        setSyncing(true);
        router.post(
            '/procurement/suppliers/sync',
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            }
        );
    };

    return (
        <AppLayout title="Suppliers">
            <Head title="Suppliers" />
            {syncSummary != null && (
                <Alert
                    type="success"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="Supplier sync completed"
                    description={`${syncSummary.created} created, ${syncSummary.updated} updated.`}
                />
            )}
            <Card
                title="SAP Suppliers"
                extra={
                    can.sync ? (
                        <Button type="primary" loading={syncing} onClick={runSync}>
                            Sync now
                        </Button>
                    ) : null
                }
            >
                <Space wrap style={{ marginBottom: 16 }}>
                    <Input.Search
                        allowClear
                        placeholder="Search card code or name"
                        style={{ width: 320 }}
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        onSearch={handleSearch}
                    />
                </Space>
                <Table<SupplierRow>
                    rowKey="id"
                    dataSource={suppliers.data}
                    onChange={handleTableChange}
                    pagination={{
                        current: suppliers.current_page,
                        pageSize: suppliers.per_page,
                        total: suppliers.total,
                        showSizeChanger: true,
                        pageSizeOptions: ['25', '50', '100'],
                    }}
                    columns={[
                        { title: 'Card Code', dataIndex: 'card_code', width: 140 },
                        { title: 'Name', dataIndex: 'card_name', ellipsis: true },
                        {
                            title: 'Payment Terms',
                            dataIndex: 'payment_terms',
                            render: (value: string | null) => value ?? '—',
                        },
                        {
                            title: 'Currency',
                            dataIndex: 'currency',
                            width: 100,
                            render: (value: string | null) => value ?? '—',
                        },
                        {
                            title: 'Status',
                            dataIndex: 'is_active',
                            width: 110,
                            render: (active: boolean) =>
                                active ? <Tag color="green">Active</Tag> : <Tag>Inactive</Tag>,
                        },
                        {
                            title: 'Last Synced',
                            dataIndex: 'synced_at',
                            width: 170,
                            render: (value: string | null) => formatSyncedAt(value),
                        },
                    ]}
                />
                <Typography.Text type="secondary" style={{ display: 'block', marginTop: 8 }}>
                    {suppliers.total} supplier(s)
                </Typography.Text>
            </Card>
        </AppLayout>
    );
}
