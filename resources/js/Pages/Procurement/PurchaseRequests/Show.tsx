import { Head, Link } from '@inertiajs/react';
import { Card, Descriptions, Table, Tag, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout';
import AttachmentsPanel, { type AttachmentRow } from '@/Components/AttachmentsPanel';
import { formatIdr } from '@/hooks/useCurrency';

interface LineRow {
    id: number;
    line_num: number;
    vis_order: number;
    item_code: string | null;
    description: string | null;
    qty: string | null;
    uom: string | null;
    unit_price: string | null;
    line_amount: string | null;
    line_vendor_code: string | null;
}

interface RelatedPurchaseOrder {
    id: number;
    doc_num: number | null;
    doc_date: string | null;
    vendor_name: string | null;
    total_amount: string | null;
    po_status: string | null;
}

interface RelatedPlantRequest {
    id: number;
    request_no: string;
    status: string;
}

interface PurchaseRequest {
    id: number;
    sap_doc_entry: number;
    doc_num: number | null;
    doc_date: string | null;
    create_date: string | null;
    pr_type: string | null;
    department_code: string | null;
    department_name: string | null;
    requester: string | null;
    mr_no: string | null;
    required_date: string | null;
    remarks: string | null;
    pr_status: string | null;
    closed_status: string | null;
    pr_rev_no: string | null;
    unit_no: string | null;
    hours_meter: string | null;
    line_count: number;
    total_amount: string | null;
    project_code: string | null;
    synced_at: string | null;
    lines: LineRow[];
}

interface Props {
    purchaseRequest: PurchaseRequest;
    relatedPurchaseOrders: RelatedPurchaseOrder[];
    relatedPlantRequests: RelatedPlantRequest[];
    attachments: AttachmentRow[];
}

function formatDate(value: string | null | undefined): string {
    return value ? dayjs(value).format('DD MMM YYYY') : '—';
}

function formatDateTime(value: string | null | undefined): string {
    return value ? dayjs(value).format('DD MMM YYYY HH:mm') : '—';
}

const lineColumns: ColumnsType<LineRow> = [
    { title: 'Line', dataIndex: 'line_num' },
    { title: 'Vis Order', dataIndex: 'vis_order' },
    { title: 'Item Code', dataIndex: 'item_code', render: (v) => v ?? '—' },
    { title: 'Description', dataIndex: 'description', render: (v) => v ?? '—' },
    { title: 'Qty', dataIndex: 'qty', render: (v) => v ?? '—' },
    { title: 'UoM', dataIndex: 'uom', render: (v) => v ?? '—' },
    {
        title: 'Unit Price',
        dataIndex: 'unit_price',
        render: (v: string | null) => (v ? formatIdr(v) : '—'),
    },
    {
        title: 'Line Amount',
        dataIndex: 'line_amount',
        render: (v: string | null) => (v ? formatIdr(v) : '—'),
    },
    { title: 'Vendor', dataIndex: 'line_vendor_code', render: (v) => v ?? '—' },
];

export default function Show({
    purchaseRequest,
    relatedPurchaseOrders,
    relatedPlantRequests,
    attachments,
}: Props) {
    return (
        <AppLayout title={`PR ${purchaseRequest.doc_num ?? purchaseRequest.id}`}>
            <Head title={`PR ${purchaseRequest.doc_num ?? purchaseRequest.id}`} />
            <Typography.Title level={4} style={{ marginBottom: 16 }}>
                Purchase Request {purchaseRequest.doc_num ?? '—'}
            </Typography.Title>

            <Card title="Document header" style={{ marginBottom: 16 }}>
                <Descriptions column={{ xs: 1, sm: 2, lg: 3 }} size="small">
                    <Descriptions.Item label="SAP Doc Entry">{purchaseRequest.sap_doc_entry}</Descriptions.Item>
                    <Descriptions.Item label="Doc Num">{purchaseRequest.doc_num ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Doc Date">{formatDate(purchaseRequest.doc_date)}</Descriptions.Item>
                    <Descriptions.Item label="Created">{formatDateTime(purchaseRequest.create_date)}</Descriptions.Item>
                    <Descriptions.Item label="PR Type">
                        {purchaseRequest.pr_type ? <Tag>{purchaseRequest.pr_type}</Tag> : '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Department">
                        {purchaseRequest.department_name ?? purchaseRequest.department_code ?? '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Project">{purchaseRequest.project_code ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Requester">{purchaseRequest.requester ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="MR No">{purchaseRequest.mr_no ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Required Date">
                        {formatDate(purchaseRequest.required_date)}
                    </Descriptions.Item>
                    <Descriptions.Item label="Revision No">{purchaseRequest.pr_rev_no ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Unit">{purchaseRequest.unit_no ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Hours Meter">
                        {purchaseRequest.hours_meter ?? '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Status">
                        {purchaseRequest.pr_status ? <Tag>{purchaseRequest.pr_status}</Tag> : '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Closed">{purchaseRequest.closed_status ?? '—'}</Descriptions.Item>
                    <Descriptions.Item label="Lines">{purchaseRequest.line_count}</Descriptions.Item>
                    <Descriptions.Item label="Total">
                        {purchaseRequest.total_amount ? formatIdr(purchaseRequest.total_amount) : '—'}
                    </Descriptions.Item>
                    <Descriptions.Item label="Last synced">{formatDateTime(purchaseRequest.synced_at)}</Descriptions.Item>
                    <Descriptions.Item label="Remarks" span={3}>
                        {purchaseRequest.remarks ?? '—'}
                    </Descriptions.Item>
                </Descriptions>
            </Card>

            <Card title="Line items" style={{ marginBottom: 16 }}>
                <Table
                    rowKey="id"
                    dataSource={purchaseRequest.lines}
                    columns={lineColumns}
                    pagination={false}
                    scroll={{ x: true }}
                    size="small"
                />
            </Card>

            <Card title="Attachments" style={{ marginBottom: 16 }}>
                <AttachmentsPanel
                    documentKind="purchase_request"
                    documentId={purchaseRequest.id}
                    attachments={attachments ?? []}
                />
            </Card>

            <Card title="Related documents">
                <Typography.Text strong>Purchase orders (PR No match)</Typography.Text>
                {relatedPurchaseOrders.length === 0 ? (
                    <Typography.Paragraph type="secondary">No linked purchase orders.</Typography.Paragraph>
                ) : (
                    <ul>
                        {relatedPurchaseOrders.map((po) => (
                            <li key={po.id}>
                                <Link href={`/procurement/purchase-orders/${po.id}`}>
                                    PO {po.doc_num ?? po.id}
                                </Link>
                                {po.vendor_name ? ` — ${po.vendor_name}` : ''}
                                {po.doc_date ? ` · ${formatDate(po.doc_date)}` : ''}
                            </li>
                        ))}
                    </ul>
                )}

                <Typography.Text strong style={{ display: 'block', marginTop: 16 }}>
                    Plant requests (SAP PR No match)
                </Typography.Text>
                {relatedPlantRequests.length === 0 ? (
                    <Typography.Paragraph type="secondary">No linked plant requests.</Typography.Paragraph>
                ) : (
                    <ul>
                        {relatedPlantRequests.map((pr) => (
                            <li key={pr.id}>
                                <Link href={`/plant-requests/${pr.id}`}>{pr.request_no}</Link>
                                {` — ${pr.status}`}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </AppLayout>
    );
}
