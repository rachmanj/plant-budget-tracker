import { Head, Link, router } from '@inertiajs/react';
import { StarFilled } from '@ant-design/icons';
import { Checkbox, DatePicker, Input, Select, Space, Table, Tag, Tooltip, theme } from 'antd';
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
    deliveryLabel,
    deliveryTone,
    emptyCell,
    formatDocCount,
    totalValueKpiMeta,
} from '@/Pages/Procurement/registerIndexUi';

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
    isFollowed?: boolean;
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
    following?: boolean;
    per_page: number;
}

interface Summary {
    document_count: number;
    total_amount: string;
    documents_with_amount: number;
    this_month: number;
    suppliers: number;
    latest_doc_date: string | null;
}

interface Props {
    purchaseOrders: Paginator<PurchaseOrderRow>;
    filters: Filters;
    summary: Summary;
    projects: ProjectOption[];
    departments: DepartmentOption[];
    projectScope: string | null;
}

function originTag(origin: string, token: ReturnType<typeof theme.useToken>['token']) {
    const isPmb = origin === 'pmb';
    const color = isPmb ? 'cyan' : 'blue';

    return (
        <Tag
            color={color}
            style={{
                borderColor: isPmb
                    ? `color-mix(in srgb, ${token.colorWarning} 40%, transparent)`
                    : `color-mix(in srgb, ${token.colorPrimary} 40%, transparent)`,
            }}
        >
            {isPmb ? 'PMB' : 'SAP'}
        </Tag>
    );
}

export default function Index({
    purchaseOrders,
    filters,
    summary,
    departments,
}: Props) {
    const { token } = theme.useToken();
    const [searchValue, setSearchValue] = useState(filters.q ?? '');

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
                following: filters.following ? 1 : undefined,
                per_page: filters.per_page,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const resetFilters = () => {
        router.get(
            '/procurement/purchase-orders',
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
        <AppLayout title="Purchase Orders">
            <Head title="Purchase Orders" />

            <RegisterPageHeader title="Purchase Orders" subtitle={headerSubtitle} />

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
                        meta={totalValueKpiMeta(summary.document_count, summary.documents_with_amount)}
                    />
                </RegisterKpiCol>
                <RegisterKpiCol>
                    <RegisterKpiCard
                        label="Suppliers"
                        value={summary.suppliers}
                        meta="Distinct vendor codes"
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
                    placeholder="Search PO, PR, vendor, item code"
                    style={{ width: 280, ...controlStyle }}
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
                    placeholder="Delivery status"
                    style={{ width: 160, ...controlStyle }}
                    value={filters.delivery_status ?? undefined}
                    options={[
                        { value: 'N', label: 'N: Open' },
                        { value: 'P', label: 'P: Partial' },
                        { value: 'C', label: 'C: Closed' },
                    ]}
                    onChange={(value) => navigate({ delivery_status: value ?? undefined, page: 1 })}
                />
                <Select
                    allowClear
                    placeholder="Origin"
                    style={{ width: 120, ...controlStyle }}
                    value={filters.origin ?? undefined}
                    options={[
                        { value: 'sap', label: 'SAP' },
                        { value: 'pmb', label: 'PMB' },
                    ]}
                    onChange={(value) => navigate({ origin: value ?? undefined, page: 1 })}
                />
                <Checkbox
                    style={controlStyle}
                    checked={Boolean(filters.following)}
                    onChange={(e) =>
                        navigate({ following: e.target.checked ? 1 : undefined, page: 1 })
                    }
                >
                    Only POs I follow
                </Checkbox>
            </RegisterFiltersCard>

            <RegisterTableCard showing={purchaseOrders.data.length} total={purchaseOrders.total}>
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
                    scroll={{ x: 'max-content' }}
                    columns={[
                        {
                            title: 'PO No',
                            dataIndex: 'doc_num',
                            render: (docNum: number | null, row: PurchaseOrderRow) => (
                                <Space size="small">
                                    {row.isFollowed && (
                                        <Tooltip title="Following">
                                            <StarFilled style={{ color: token.colorWarning }} />
                                        </Tooltip>
                                    )}
                                    {docNum ? (
                                        <Link href={`/procurement/purchase-orders/${row.id}`}>{docNum}</Link>
                                    ) : (
                                        emptyCell()
                                    )}
                                </Space>
                            ),
                        },
                        {
                            title: 'Doc Date',
                            dataIndex: 'doc_date',
                            render: (value: string | null) =>
                                value ? dayjs(value).format('DD MMM YYYY') : emptyCell(),
                        },
                        {
                            title: 'Project',
                            dataIndex: 'project_code',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Department',
                            dataIndex: 'dept_name',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Vendor',
                            dataIndex: 'vendor_name',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Currency',
                            dataIndex: 'currency',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Total',
                            dataIndex: 'total_amount',
                            align: 'right' as const,
                            render: (value: string | null) => <AmountCell value={value} />,
                        },
                        {
                            title: 'Delivery Status',
                            dataIndex: 'delivery_status',
                            render: (value: string | null) =>
                                value ? (
                                    <StatusPill tone={deliveryTone(value)}>
                                        {deliveryLabel(value)}
                                    </StatusPill>
                                ) : (
                                    emptyCell()
                                ),
                        },
                        {
                            title: 'PR No',
                            dataIndex: 'pr_no',
                            render: (v: string | null) => v ?? emptyCell(),
                        },
                        {
                            title: 'Origin',
                            dataIndex: 'origin',
                            render: (value: string) => originTag(value ?? 'sap', token),
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
            </RegisterTableCard>
        </AppLayout>
    );
}