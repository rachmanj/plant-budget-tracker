import { Head, router } from '@inertiajs/react';
import {
    Alert,
    Button,
    Card,
    Descriptions,
    Input,
    Space,
    Table,
    Tag,
    Typography,
    Upload,
    message,
} from 'antd';
import type { UploadProps } from 'antd';
import type { TablePaginationConfig } from 'antd/es/table';
import dayjs from 'dayjs';
import { useState, type ReactNode } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';

const MAX_ERROR_ROWS = 30;

interface ItemPriceRow {
    id: number;
    item_code: string;
    vendor_code: string;
    uom: string | null;
    price: string;
    currency: string;
    effective_date: string | null;
    source: string | null;
    updated_at: string | null;
    updated_by_name: string | null;
}

interface ImportRow {
    id: number;
    original_name: string;
    imported_by_name: string | null;
    imported_at: string | null;
    rows_total: number;
    rows_created: number;
    rows_updated: number;
    rows_unchanged: number;
    rows_failed: number;
    errors: Array<{ line?: number; message: string }>;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface ImportSummary {
    rows_total: number;
    rows_created: number;
    rows_updated: number;
    rows_unchanged: number;
    rows_failed: number;
}

interface Props {
    itemPrices: Paginator<ItemPriceRow>;
    importHistory: Paginator<ImportRow>;
    filters: { search: string | null };
    perPage: number;
    can: { import: boolean };
    csvTemplateUrl: string;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function formatDateTime(value: string | null): string {
    return value ? dayjs(value).format('DD MMM YYYY HH:mm') : '—';
}

function formatDate(value: string | null): string {
    return value ? dayjs(value).format('DD MMM YYYY') : '—';
}

function vendorLabel(vendorCode: string): ReactNode {
    if (!vendorCode || vendorCode.trim() === '') {
        return <Tag color="cyan">General (all vendors)</Tag>;
    }

    return vendorCode;
}

function renderImportErrors(errors: ImportRow['errors']) {
    if (!errors?.length) {
        return <Typography.Text type="secondary">No row errors.</Typography.Text>;
    }

    const visible = errors.slice(0, MAX_ERROR_ROWS);
    const truncated = errors.length > MAX_ERROR_ROWS;

    return (
        <>
            <Table
                size="small"
                rowKey={(row, index) => `${row.line ?? 'x'}-${index}`}
                pagination={false}
                dataSource={visible}
                columns={[
                    {
                        title: 'Line',
                        dataIndex: 'line',
                        width: 72,
                        render: (line: number | undefined) => line ?? '—',
                    },
                    { title: 'Message', dataIndex: 'message' },
                ]}
            />
            {truncated && (
                <Typography.Text type="secondary" style={{ display: 'block', marginTop: 8 }}>
                    Showing {MAX_ERROR_ROWS} of {errors.length} errors.
                </Typography.Text>
            )}
        </>
    );
}

export default function Index({
    itemPrices,
    importHistory,
    filters,
    perPage,
    can,
    csvTemplateUrl,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search ?? '');
    const [importing, setImporting] = useState(false);
    const [lastImportSummary, setLastImportSummary] = useState<ImportSummary | null>(null);

    const navigatePrices = (overrides: Record<string, unknown>) => {
        router.get(
            '/procurement/item-prices',
            {
                search: filters.search ?? undefined,
                per_page: perPage,
                import_page: importHistory.current_page,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearch = (value: string) => navigatePrices({ search: value || undefined, page: 1 });

    const handlePriceTableChange = (pagination: TablePaginationConfig) => {
        navigatePrices({ page: pagination.current, per_page: pagination.pageSize });
    };

    const handleImportHistoryChange = (pagination: TablePaginationConfig) => {
        navigatePrices({ import_page: pagination.current });
    };

    const uploadProps: UploadProps = {
        accept: '.csv',
        maxCount: 1,
        showUploadList: false,
        beforeUpload: (file) => {
            const isCsv = file.name.toLowerCase().endsWith('.csv');
            if (!isCsv) {
                message.error('Only CSV files are accepted.');
                return Upload.LIST_IGNORE;
            }
            if (file.size > 5 * 1024 * 1024) {
                message.error('CSV file must not exceed 5 MB.');
                return Upload.LIST_IGNORE;
            }

            const formData = new FormData();
            formData.append('file', file);
            setImporting(true);

            fetch('/procurement/item-prices/import', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: formData,
            })
                .then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok) {
                        message.error(payload.message ?? 'Import failed.');
                        return;
                    }
                    setLastImportSummary({
                        rows_total: payload.rows_total,
                        rows_created: payload.rows_created,
                        rows_updated: payload.rows_updated,
                        rows_unchanged: payload.rows_unchanged,
                        rows_failed: payload.rows_failed,
                    });
                    message.success('Import completed.');
                    router.reload({ only: ['itemPrices', 'importHistory'] });
                })
                .catch(() => message.error('Import failed.'))
                .finally(() => setImporting(false));

            return false;
        },
    };

    return (
        <AppLayout title="Item Prices">
            <Head title="Item Prices" />
            <Card title="Import item prices" style={{ marginBottom: 16 }}>
                <Space wrap>
                    <Button href={csvTemplateUrl}>Download CSV template</Button>
                    {can.import && (
                        <Upload {...uploadProps}>
                            <Button type="primary" loading={importing}>Upload CSV</Button>
                        </Upload>
                    )}
                </Space>
                {lastImportSummary && (
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginTop: 16 }}
                        message="Last import summary"
                        description={
                            <Descriptions size="small" column={2}>
                                <Descriptions.Item label="Rows read">
                                    {lastImportSummary.rows_total}
                                </Descriptions.Item>
                                <Descriptions.Item label="Created">
                                    {lastImportSummary.rows_created}
                                </Descriptions.Item>
                                <Descriptions.Item label="Updated">
                                    {lastImportSummary.rows_updated}
                                </Descriptions.Item>
                                <Descriptions.Item label="Unchanged">
                                    {lastImportSummary.rows_unchanged}
                                </Descriptions.Item>
                                <Descriptions.Item label="Failed">
                                    {lastImportSummary.rows_failed}
                                </Descriptions.Item>
                            </Descriptions>
                        }
                    />
                )}
            </Card>

            <Card title="Item price master" style={{ marginBottom: 16 }}>
                <Space wrap style={{ marginBottom: 16 }}>
                    <Input.Search
                        allowClear
                        placeholder="Search item code or vendor"
                        style={{ width: 320 }}
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        onSearch={handleSearch}
                    />
                </Space>
                <Table<ItemPriceRow>
                    rowKey="id"
                    dataSource={itemPrices.data}
                    onChange={handlePriceTableChange}
                    pagination={{
                        current: itemPrices.current_page,
                        pageSize: itemPrices.per_page,
                        total: itemPrices.total,
                        showSizeChanger: true,
                        pageSizeOptions: ['25', '50', '100'],
                    }}
                    columns={[
                        { title: 'Item Code', dataIndex: 'item_code', width: 140 },
                        {
                            title: 'Vendor',
                            dataIndex: 'vendor_code',
                            width: 160,
                            render: (value: string) => vendorLabel(value),
                        },
                        {
                            title: 'UOM',
                            dataIndex: 'uom',
                            width: 80,
                            render: (value: string | null) => value ?? '—',
                        },
                        {
                            title: 'Price',
                            dataIndex: 'price',
                            width: 160,
                            render: (value: string) => formatIdr(value),
                        },
                        { title: 'Currency', dataIndex: 'currency', width: 90 },
                        {
                            title: 'Effective Date',
                            dataIndex: 'effective_date',
                            width: 130,
                            render: (value: string | null) => formatDate(value),
                        },
                        {
                            title: 'Source',
                            dataIndex: 'source',
                            width: 120,
                            render: (value: string | null) => value ?? '—',
                        },
                        {
                            title: 'Last Updated',
                            dataIndex: 'updated_at',
                            width: 200,
                            render: (value: string | null, row) => (
                                <span>
                                    {formatDateTime(value)}
                                    {row.updated_by_name ? (
                                        <Typography.Text type="secondary" style={{ display: 'block' }}>
                                            by {row.updated_by_name}
                                        </Typography.Text>
                                    ) : null}
                                </span>
                            ),
                        },
                    ]}
                />
            </Card>

            <Card title="Import history">
                <Table<ImportRow>
                    rowKey="id"
                    dataSource={importHistory.data}
                    onChange={handleImportHistoryChange}
                    pagination={{
                        current: importHistory.current_page,
                        pageSize: importHistory.per_page,
                        total: importHistory.total,
                    }}
                    expandable={{
                        expandedRowRender: (record) => renderImportErrors(record.errors),
                        rowExpandable: (record) => (record.errors?.length ?? 0) > 0,
                    }}
                    columns={[
                        { title: 'File', dataIndex: 'original_name', ellipsis: true },
                        {
                            title: 'Imported by',
                            dataIndex: 'imported_by_name',
                            width: 160,
                            render: (value: string | null) => value ?? '—',
                        },
                        {
                            title: 'Imported at',
                            dataIndex: 'imported_at',
                            width: 170,
                            render: (value: string | null) => formatDateTime(value),
                        },
                        {
                            title: 'Summary',
                            key: 'summary',
                            render: (_, row) =>
                                `${row.rows_total} read · ${row.rows_created} created · ${row.rows_updated} updated · ${row.rows_unchanged} unchanged · ${row.rows_failed} failed`,
                        },
                    ]}
                />
            </Card>
        </AppLayout>
    );
}
