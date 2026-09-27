import { Head, Link, router } from '@inertiajs/react';
import { Button, Card, DatePicker, Space, Table, Tag, Tooltip, Typography } from 'antd';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';

interface PurchaseRequestRow {
    id: number;
    doc_num: number | null;
    doc_date: string | null;
    department_name: string | null;
    project_code: string | null;
    requester: string | null;
    line_count: number;
    total_amount: string | null;
    pr_status: string | null;
}

interface Summary {
    document_count: number;
    total_amount: string;
}

interface Props {
    date: string;
    purchaseRequests: PurchaseRequestRow[];
    summary: Summary;
    projectScope: string | null;
    canExport: boolean;
}

export default function DailyPrIndex({ date, purchaseRequests, summary, canExport }: Props) {
    const selectedDate = dayjs(date);

    const onDateChange = (value: dayjs.Dayjs | null) => {
        if (!value) {
            return;
        }
        router.get(
            '/procurement/daily-pr',
            { date: value.format('YYYY-MM-DD') },
            { preserveState: true, preserveScroll: true }
        );
    };

    const exportUrl = `/procurement/daily-pr/export?date=${encodeURIComponent(date)}`;

    return (
        <AppLayout title="Daily PR">
            <Head title="Daily PR" />
            <Card title="Daily PR">
                <Space wrap style={{ marginBottom: 16 }} size="middle">
                    <DatePicker value={selectedDate} onChange={onDateChange} allowClear={false} />
                    {canExport && (
                        <Button type="primary" href={exportUrl}>
                            Export CSV
                        </Button>
                    )}
                </Space>

                <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
                    PRs created on {selectedDate.format('DD MMM YYYY')}: {summary.document_count} document
                    {summary.document_count === 1 ? '' : 's'} ·{' '}
                    <Tooltip title={formatIdr(summary.total_amount)}>
                        <span>{formatIdrCompact(summary.total_amount)} total</span>
                    </Tooltip>
                </Typography.Text>

                <Table
                    rowKey="id"
                    dataSource={purchaseRequests}
                    pagination={false}
                    columns={[
                        {
                            title: 'Doc Num',
                            dataIndex: 'doc_num',
                            render: (docNum: number | null, row: PurchaseRequestRow) =>
                                docNum ? (
                                    <Link href={`/procurement/purchase-requests/${row.id}`}>{docNum}</Link>
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            title: 'Doc Date',
                            dataIndex: 'doc_date',
                            render: (value: string | null) =>
                                value ? dayjs(value).format('DD MMM YYYY') : '—',
                        },
                        {
                            title: 'Department',
                            dataIndex: 'department_name',
                            render: (v: string | null) => v ?? '—',
                        },
                        { title: 'Project', dataIndex: 'project_code', render: (v: string | null) => v ?? '—' },
                        { title: 'Requester', dataIndex: 'requester', render: (v: string | null) => v ?? '—' },
                        { title: 'Lines', dataIndex: 'line_count' },
                        {
                            title: 'Total',
                            dataIndex: 'total_amount',
                            render: (value: string | null) => (value ? formatIdr(value) : '—'),
                        },
                        {
                            title: 'Status',
                            dataIndex: 'pr_status',
                            render: (value: string | null) => (value ? <Tag>{value}</Tag> : '—'),
                        },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
