import { Head, Link, router } from '@inertiajs/react';
import { Card, DatePicker, Input, Select, Space, Table, Tag, Tooltip, Typography } from 'antd';
import type { TablePaginationConfig } from 'antd/es/table';
import dayjs, { Dayjs } from 'dayjs';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';

const { RangePicker } = DatePicker;

interface PurchaseRequestRow {
    id: number;
    doc_num: number | null;
    doc_date: string | null;
    pr_type: string | null;
    department_name: string | null;
    project_code: string | null;
    requester: string | null;
    mr_no: string | null;
    required_date: string | null;
    line_count: number;
    total_amount: string | null;
    pr_status: string | null;
    closed_status: string | null;
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

interface StatusOption {
    value: string;
    label: string;
}

interface Filters {
    q: string | null;
    project_code: string | null;
    dept_code: string | null;
    from: string | null;
    to: string | null;
    pr_status: string | null;
    per_page: number;
}

interface Summary {
    document_count: number;
    total_amount: string;
}

interface Props {
    purchaseRequests: Paginator<PurchaseRequestRow>;
    filters: Filters;
    summary: Summary;
    projects: ProjectOption[];
    departments: DepartmentOption[];
    prStatusOptions: StatusOption[];
    projectScope: string | null;
}

export default function Index({
    purchaseRequests,
    filters,
    summary,
    projects,
    departments,
    prStatusOptions,
    projectScope,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.q ?? '');
    const projectLocked = projectScope != null && projectScope !== '';

    const navigate = (overrides: Record<string, unknown>) => {
        router.get(
            '/procurement/purchase-requests',
            {
                q: filters.q ?? undefined,
                project_code: filters.project_code ?? undefined,
                dept_code: filters.dept_code ?? undefined,
                from: filters.from ?? undefined,
                to: filters.to ?? undefined,
                pr_status: filters.pr_status ?? undefined,
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
        <AppLayout title="Purchase Requests">
            <Head title="Purchase Requests" />
            <Card title="Purchase Requests">
                <Space wrap style={{ marginBottom: 16 }} size="middle">
                    <Input.Search
                        allowClear
                        placeholder="Search PR, MR, requester, item code"
                        style={{ width: 300 }}
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
                        placeholder="PR status"
                        style={{ width: 160 }}
                        value={filters.pr_status ?? undefined}
                        options={prStatusOptions}
                        onChange={(value) => navigate({ pr_status: value ?? undefined, page: 1 })}
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
                    dataSource={purchaseRequests.data}
                    pagination={{
                        current: purchaseRequests.current_page,
                        pageSize: purchaseRequests.per_page,
                        total: purchaseRequests.total,
                        showSizeChanger: true,
                        pageSizeOptions: [25, 50, 100],
                    }}
                    onChange={handleTableChange}
                    scroll={{ x: true }}
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
                            title: 'PR Type',
                            dataIndex: 'pr_type',
                            render: (value: string | null) => (value ? <Tag>{value}</Tag> : '—'),
                        },
                        {
                            title: 'Department',
                            dataIndex: 'department_name',
                            render: (v: string | null) => v ?? '—',
                        },
                        { title: 'Project', dataIndex: 'project_code', render: (v: string | null) => v ?? '—' },
                        { title: 'Requester', dataIndex: 'requester', render: (v: string | null) => v ?? '—' },
                        { title: 'MR No', dataIndex: 'mr_no', render: (v: string | null) => v ?? '—' },
                        {
                            title: 'Required Date',
                            dataIndex: 'required_date',
                            render: (value: string | null) =>
                                value ? dayjs(value).format('DD MMM YYYY') : '—',
                        },
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
                        {
                            title: 'Closed',
                            dataIndex: 'closed_status',
                            render: (value: string | null) => value ?? '—',
                        },
                        {
                            title: 'Actions',
                            key: 'actions',
                            render: (_: unknown, row: PurchaseRequestRow) => (
                                <Link href={`/procurement/purchase-requests/${row.id}`}>View</Link>
                            ),
                        },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
