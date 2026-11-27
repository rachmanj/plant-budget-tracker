import { Head, Link, router } from '@inertiajs/react';
import { DatePicker, Input, Select, Space, Table, Tag, Tooltip } from 'antd';
import type { TablePaginationConfig } from 'antd/es/table';
import dayjs, { Dayjs } from 'dayjs';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';
import {
    AmountCell,
    RegisterFiltersCard,
    RegisterKpiCard,
    RegisterKpiCol,
    RegisterKpiRow,
    RegisterPageHeader,
    RegisterTableCard,
    StatusPill,
    emptyCell,
    formatDocCount,
    prStatusTone,
} from '@/Pages/Procurement/registerIndexUi';

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
    this_month: number;
    departments: number;
    latest_doc_date: string | null;
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
    departments,
    prStatusOptions,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.q ?? '');

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

    const resetFilters = () => {
        router.get(
            '/procurement/purchase-requests',
            { per_page: filters.per_page },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearch = (value: string) => navigate({ q: value || undefined, page: 1 });

    const handleTableChange = (pagination: TablePaginationConfig) => {
        navigate({ page: pagination.current, per_page: pagination.pageSize });
    };

    const dateRangeValue: [Dayjs, Dayjs] | null =
        filters.from && filters.to ? [dayjs(filters.from), dayjs(filters.to)] : null;

    const headerSubtitle = useMemo(() => {
        const parts = [
            `${formatDocCount(summary.document_count)} document${summary.document_count === 1 ? '' : 's'}`,
            formatIdrCompact(summary.total_amount),
        ];
        if (summary.latest_doc_date) {
            parts.push(`latest ${dayjs(summary.latest_doc_date).format('D MMM YYYY')}`);
        }
        return parts.join(' · ');
    }, [summary]);

    const controlStyle = { minHeight: 44 };

    return (
        <AppLayout title="Purchase Requests">
            <Head title="Purchase Requests" />

            <RegisterPageHeader title="Purchase Requests" subtitle={headerSubtitle} />

            <RegisterKpiRow>
                <RegisterKpiCol>
                    <RegisterKpiCard
                        label="Documents"
                        value={summary.document_count}
                        meta="Matching current filters"
                    />
                </RegisterKpiCol>
                <RegisterKpiCol>
                    <RegisterKpiCard
                        label="Total value"
                        valueNode={
                            <Tooltip title={formatIdr(summary.total_amount)}>
                                <span>{formatIdrCompact(summary.total_amount)}</span>
                            </Tooltip>
                        }
                        meta="Sum of document totals"
                    />
                </RegisterKpiCol>
                <RegisterKpiCol>
                    <RegisterKpiCard
                        label="Departments"
                        value={summary.departments}
                        meta="Distinct department codes"
                    />
                </RegisterKpiCol>
                <RegisterKpiCol>
                    <RegisterKpiCard
                        label="This month"
                        value={summary.this_month}
                        meta={`Documents dated ${dayjs().startOf('month').format('D MMM YYYY')} onward`}
                    />
                </RegisterKpiCol>
            </RegisterKpiRow>

            <RegisterFiltersCard onReset={resetFilters}>
                <Input.Search
                    allowClear
                    placeholder="Search PR, MR, requester, item code"
                    style={{ width: 300, ...controlStyle }}
                    value={searchValue}
                    onChange={(e) => setSearchValue(e.target.value)}
                    onSearch={handleSearch}
                />
                <Select
                    allowClear
                    placeholder="Department"
                    style={{ width: 220, ...controlStyle }}
                    value={filters.dept_code ?? undefined}
                    options={departments.map((d) => ({
                        value: d.dept_code,
                        label: d.dept_name,
                    }))}
                    onChange={(value) => navigate({ dept_code: value ?? undefined, page: 1 })}
                />
                <RangePicker
                    style={controlStyle}
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
                    style={{ width: 160, ...controlStyle }}
                    value={filters.pr_status ?? undefined}
                    options={prStatusOptions}
                    onChange={(value) => navigate({ pr_status: value ?? undefined, page: 1 })}
                />
            </RegisterFiltersCard>

            <RegisterTableCard showing={purchaseRequests.data.length} total={purchaseRequests.total}>
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
                    scroll={{ x: 'max-content' }}
                    columns={[
                        {
                            title: 'Doc Num',
                            dataIndex: 'doc_num',
                            render: (docNum: number | null, row: PurchaseRequestRow) =>
                                docNum ? (
                                    <Link href={`/procurement/purchase-requests/${row.id}`}>{docNum}</Link>
                                ) : (
                                    emptyCell()
                                ),
                        },
                        {
                            title: 'Doc Date',
                            dataIndex: 'doc_date',
                            render: (value: string | null) =>
                                value ? dayjs(value).format('DD MMM YYYY') : emptyCell(),
                        },
                        {
                            title: 'PR Type',
                            dataIndex: 'pr_type',
                            render: (value: string | null) =>
                                value ? <Tag color="default">{value}</Tag> : emptyCell(),
                        },
                        {
                            title: 'Department',
                            dataIndex: 'department_name',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Project',
                            dataIndex: 'project_code',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Requester',
                            dataIndex: 'requester',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'MR No',
                            dataIndex: 'mr_no',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Required Date',
                            dataIndex: 'required_date',
                            render: (value: string | null) =>
                                value ? dayjs(value).format('DD MMM YYYY') : emptyCell(),
                        },
                        {
                            title: 'Lines',
                            dataIndex: 'line_count',
                            align: 'right' as const,
                            render: (value: number) => (
                                <span style={{ fontVariantNumeric: 'tabular-nums' }}>{value}</span>
                            ),
                        },
                        {
                            title: 'Total',
                            dataIndex: 'total_amount',
                            align: 'right' as const,
                            render: (value: string | null) => <AmountCell value={value} />,
                        },
                        {
                            title: 'Status',
                            dataIndex: 'pr_status',
                            render: (value: string | null) =>
                                value ? (
                                    <StatusPill tone={prStatusTone(value)}>{value}</StatusPill>
                                ) : (
                                    emptyCell()
                                ),
                        },
                        {
                            title: 'Closed',
                            dataIndex: 'closed_status',
                            render: (value: string | null) => value ?? emptyCell(),
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
            </RegisterTableCard>
        </AppLayout>
    );
}