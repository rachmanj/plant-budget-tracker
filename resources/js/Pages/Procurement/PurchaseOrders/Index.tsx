import { Head, Link, router } from '@inertiajs/react';
import { Card, DatePicker, Input, Select, Space, Table, Tag, Tooltip, Typography } from 'antd';
import type { TablePaginationConfig } from 'antd/es/table';
import dayjs, { Dayjs } from 'dayjs';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';

const { RangePicker } = DatePicker;

interface PurchaseOrderRow {
    id: number;
    doc_num: number | null;
    doc_date: string | null;
    project_code: string | null;
    dept_name: string | null;
    vendor_name: string | null;
    currency: string | null;
    total_amount: string | null;
    delivery_status: string | null;
    pr_no: string | null;
    origin: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface ProjectOption {
    project_code: string;
    project_name: string;
}

interface DepartmentOption {
    dept_code: string;
    dept_name: string;
}

interface Filters {
    q: string | null;
    project_code: string | null;
    dept_code: string | null;
    from: string | null;
    to: string | null;
    delivery_status: string | null;
    origin: string | null;
    per_page: number;
}

interface Summary {
    document_count: number;
    total_amount: string;
}

interface Props {
    purchaseOrders: Paginator<PurchaseOrderRow>;
    filters: Filters;
    summary: Summary;
    projects: ProjectOption[];
    departments: DepartmentOption[];
    projectScope: string | null;
}

function originTag(origin: string) {
    const color = origin === 'pmb' ? 'cyan' : 'blue';
    const label = origin === 'pmb' ? 'PMB' : 'SAP';
    return <Tag color={color}>{label}</Tag>;
}

export default function Index({
    purchaseOrders,
    filters,
    summary,
    projects,
    departments,
    projectScope,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.q ?? '');
    const projectLocked = projectScope != null && projectScope !== '';

    const navigate = (overrides: Record<string, unknown>) => {
        router.get(
            '/procurement/purchase-orders',
            {
                q: filters.q ?? undefined,
                project_code: filters.project_code ?? undefined,
                dept_code: filters.dept_code ?? undefined,
                from: filters.from ?? undefined,
                to: filters.to ?? undefined,
                delivery_status: filters.delivery_status ?? undefined,
                origin: filters.origin ?? undefined,
                per_page: filters.per_page,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearch = (value: string) => navigate({ q: value || undefined, page: 1 });

    const handleTableChange = (pagination: TablePaginationConfig) => {
        navigate({ page: pagination.current, per_page: pagination.pageSize });
    };

    const dateRangeValue: [Dayjs, Dayjs] | null =
        filters.from && filters.to ? [dayjs(filters.from), dayjs(filters.to)] : null;

    return (
        <AppLayout title="Purchase Orders">
            <Head title="Purchase Orders" />
            <Card title="Purchase Orders">
                <Space wrap style={{ marginBottom: 16 }} size="middle">
                    <Input.Search
                        allowClear
                        placeholder="Search PO, PR, vendor, item code"
                        style={{ width: 280 }}
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        onSearch={handleSearch}
                    />
                    <Select
                        allowClear={!projectLocked}
                        disabled={projectLocked}
                        placeholder="Project"
                        style={{ width: 220 }}
                        value={
                            projectLocked
                                ? projectScope
                                : (filters.project_code ?? undefined)
                        }
                        options={projects.map((p) => ({
                            value: p.project_code,
                            label: `${p.project_code} — ${p.project_name}`,
                        }))}
                        onChange={(value) => navigate({ project_code: value ?? undefined, page: 1 })}
                    />
                    <Select
                        allowClear
                        placeholder="Department"
                        style={{ width: 220 }}
                        value={filters.dept_code ?? undefined}
                        options={departments.map((d) => ({
                            value: d.dept_code,
                            label: d.dept_name,
                        }))}
                        onChange={(value) => navigate({ dept_code: value ?? undefined, page: 1 })}
                    />
                    <RangePicker
                        value={dateRangeValue}
                        onChange={(dates) => {
                            if (!dates || !dates[0] || !dates[1]) {
                                navigate({ from: undefined, to: undefined, page: 1 });
                                return;
                            }
                            navigate({
                                from: dates[0].format('YYYY-MM-DD'),
                                to: dates[1].format('YYYY-MM-DD'),
                                page: 1,
                            });
                        }}
                    />
                    <Select
                        allowClear
                        placeholder="Delivery status"
                        style={{ width: 140 }}
                        value={filters.delivery_status ?? undefined}
                        options={[
                            { value: 'N', label: 'N — Open' },
                            { value: 'P', label: 'P — Partial' },
                            { value: 'C', label: 'C — Closed' },
                        ]}
                        onChange={(value) => navigate({ delivery_status: value ?? undefined, page: 1 })}
                    />
                    <Select
                        allowClear
                        placeholder="Origin"
                        style={{ width: 120 }}
                        value={filters.origin ?? undefined}
                        options={[
                            { value: 'sap', label: 'SAP' },
                            { value: 'pmb', label: 'PMB' },
                        ]}
                        onChange={(value) => navigate({ origin: value ?? undefined, page: 1 })}
                    />
                </Space>

                <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
                    {summary.document_count} document{summary.document_count === 1 ? '' : 's'} ·{' '}
                    <Tooltip title={formatIdr(summary.total_amount)}>
                        <span>{formatIdrCompact(summary.total_amount)} total</span>
                    </Tooltip>
                </Typography.Text>

                <Table
                    rowKey="id"
                    dataSource={purchaseOrders.data}
                    pagination={{
                        current: purchaseOrders.current_page,
                        pageSize: purchaseOrders.per_page,
                        total: purchaseOrders.total,
                        showSizeChanger: true,
                        pageSizeOptions: [25, 50, 100],
                    }}
                    onChange={handleTableChange}
                    columns={[
                        {
                            title: 'PO No',
                            dataIndex: 'doc_num',
                            render: (docNum: number | null, row: PurchaseOrderRow) =>
                                docNum ? (
                                    <Link href={`/procurement/purchase-orders/${row.id}`}>{docNum}</Link>
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
                        { title: 'Project', dataIndex: 'project_code', render: (v: string | null) => v ?? '—' },
                        { title: 'Department', dataIndex: 'dept_name', render: (v: string | null) => v ?? '—' },
                        { title: 'Vendor', dataIndex: 'vendor_name', render: (v: string | null) => v ?? '—' },
                        { title: 'Currency', dataIndex: 'currency', render: (v: string | null) => v ?? '—' },
                        {
                            title: 'Total',
                            dataIndex: 'total_amount',
                            render: (value: string | null) => (value ? formatIdr(value) : '—'),
                        },
                        {
                            title: 'Delivery Status',
                            dataIndex: 'delivery_status',
                            render: (value: string | null) =>
                                value ? <Tag>{value}</Tag> : '—',
                        },
                        { title: 'PR No', dataIndex: 'pr_no', render: (v: string | null) => v ?? '—' },
                        {
                            title: 'Origin',
                            dataIndex: 'origin',
                            render: (value: string) => originTag(value ?? 'sap'),
                        },
                        {
                            title: 'Actions',
                            key: 'actions',
                            render: (_: unknown, row: PurchaseOrderRow) => (
                                <Link href={`/procurement/purchase-orders/${row.id}`}>View</Link>
                            ),
                        },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
