import { Head, Link } from '@inertiajs/react';
import { Card, Descriptions, Table, Tag, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';
import type { ReactNode } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import AttachmentsPanel, { type AttachmentRow } from '@/Components/AttachmentsPanel';
import CommentsPanel, { type CommentRow, type MentionableUser } from '@/Components/CommentsPanel';
import FollowButton from '@/Components/FollowButton';
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
    item_amount: string | null;
    unit_no: string | null;
    remark1: string | null;
    remark2: string | null;
}

interface PlantRequestLink {
    id: number;
    request_no: string;
}

interface PurchaseOrder {
    id: number;
    sap_doc_entry: number;
    doc_num: number | null;
    doc_date: string | null;
    delivery_date: string | null;
    po_eta: string | null;
    vendor_name: string | null;
    dept_name: string | null;
    project_code: string | null;
    currency: string | null;
    total_amount: string | null;
    vat_amount: string | null;
    disc_amount: string | null;
    delivery_status: string | null;
    budget_type: string | null;
    pr_no: string | null;
    origin: string;
    synced_at: string | null;
    lines: LineRow[];
    plant_request: PlantRequestLink | null;
}

interface RelatedPurchaseRequest {
    id: number;
    doc_num: number;
}

interface Props {
    purchaseOrder: PurchaseOrder;
    relatedPurchaseRequest: RelatedPurchaseRequest | null;
    attachments: AttachmentRow[];
    comments: CommentRow[];
    mentionableUsers: MentionableUser[];
    isFollowed: boolean;
    followerCount: number;
    can: {
        attach: boolean;
        comment: boolean;
    };
}

function formatDateTime(value: string | null | undefined): string {
    return value ? dayjs(value).format('DD MMM YYYY HH:mm') : '—';
}

function formatDate(value: string | null | undefined): string {
    return value ? dayjs(value).format('DD MMM YYYY') : '—';
}

function hasLineData(lines: LineRow[], field: keyof LineRow): boolean {
    return lines.some((line) => {
        const value = line[field];
        return value !== null && value !== undefined && value !== '';
    });
}

export default function Show({
    purchaseOrder,
    relatedPurchaseRequest,
    attachments,
    comments,
    mentionableUsers,
    isFollowed,
    followerCount,
    can,
}: Props) {
    const lines = purchaseOrder.lines ?? [];
    const title = purchaseOrder.doc_num ? `PO ${purchaseOrder.doc_num}` : `PO #${purchaseOrder.id}`;

    const headerItems: Array<{ label: string; value: ReactNode | null }> = [
        { label: 'PO No', value: purchaseOrder.doc_num ?? '—' },
        { label: 'SAP DocEntry', value: purchaseOrder.sap_doc_entry },
        { label: 'Doc Date', value: formatDate(purchaseOrder.doc_date) },
        { label: 'Delivery Date', value: formatDateTime(purchaseOrder.delivery_date) },
        { label: 'PO ETA', value: formatDateTime(purchaseOrder.po_eta) },
        { label: 'Vendor', value: purchaseOrder.vendor_name ?? '—' },
        { label: 'Department', value: purchaseOrder.dept_name ?? '—' },
        { label: 'Project', value: purchaseOrder.project_code ?? '—' },
        { label: 'Currency', value: purchaseOrder.currency ?? '—' },
        {
            label: 'Total',
            value: purchaseOrder.total_amount ? formatIdr(purchaseOrder.total_amount) : '—',
        },
        {
            label: 'VAT',
            value:
                purchaseOrder.vat_amount && parseFloat(purchaseOrder.vat_amount) !== 0
                    ? formatIdr(purchaseOrder.vat_amount)
                    : null,
        },
        {
            label: 'Discount',
            value:
                purchaseOrder.disc_amount && parseFloat(purchaseOrder.disc_amount) !== 0
                    ? formatIdr(purchaseOrder.disc_amount)
                    : null,
        },
        {
            label: 'Delivery Status',
            value: purchaseOrder.delivery_status ? (
                <Tag>{purchaseOrder.delivery_status}</Tag>
            ) : null,
        },
        { label: 'Budget Type', value: purchaseOrder.budget_type ?? null },
        { label: 'PR No', value: purchaseOrder.pr_no ?? null },
        {
            label: 'Origin',
            value: (
                <Tag color={purchaseOrder.origin === 'pmb' ? 'cyan' : 'blue'}>
                    {purchaseOrder.origin === 'pmb' ? 'PMB' : 'SAP'}
                </Tag>
            ),
        },
        { label: 'Synced at', value: formatDateTime(purchaseOrder.synced_at) },
    ].filter((item) => item.value !== null && item.value !== undefined && item.value !== '');

    const lineColumns: ColumnsType<LineRow> = [
        { title: 'Line', dataIndex: 'line_num', key: 'line_num' },
    ];

    if (hasLineData(lines, 'item_code')) {
        lineColumns.push({
            title: 'Item Code',
            dataIndex: 'item_code',
            key: 'item_code',
            render: (v: string | null) => v ?? '—',
        });
    }
    if (hasLineData(lines, 'description')) {
        lineColumns.push({
            title: 'Description',
            dataIndex: 'description',
            key: 'description',
            render: (v: string | null) => v ?? '—',
        });
    }
    if (hasLineData(lines, 'qty')) {
        lineColumns.push({
            title: 'Qty',
            dataIndex: 'qty',
            key: 'qty',
            render: (v: string | null) => v ?? '—',
        });
    }
    if (hasLineData(lines, 'uom')) {
        lineColumns.push({
            title: 'UOM',
            dataIndex: 'uom',
            key: 'uom',
            render: (v: string | null) => v ?? '—',
        });
    }
    if (hasLineData(lines, 'unit_price')) {
        lineColumns.push({
            title: 'Unit Price',
            dataIndex: 'unit_price',
            key: 'unit_price',
            render: (v: string | null) => (v ? formatIdr(v) : '—'),
        });
    }
    if (hasLineData(lines, 'item_amount')) {
        lineColumns.push({
            title: 'Amount',
            dataIndex: 'item_amount',
            key: 'item_amount',
            render: (v: string | null) => (v ? formatIdr(v) : '—'),
        });
    }
    if (hasLineData(lines, 'unit_no')) {
        lineColumns.push({
            title: 'Unit No',
            dataIndex: 'unit_no',
            key: 'unit_no',
            render: (v: string | null) => v ?? '—',
        });
    }
    if (hasLineData(lines, 'remark1') || hasLineData(lines, 'remark2')) {
        lineColumns.push({
            title: 'Remark',
            key: 'remark',
            render: (_: unknown, row: LineRow) => {
                const parts = [row.remark1, row.remark2].filter(Boolean);
                return parts.length ? parts.join(' · ') : '—';
            },
        });
    }

    return (
        <AppLayout title={title}>
            <Head title={title} />
            <Card
                title={title}
                style={{ marginBottom: 16 }}
                extra={
                    <FollowButton
                        purchaseOrderId={purchaseOrder.id}
                        isFollowed={isFollowed}
                        followerCount={followerCount}
                    />
                }
            >
                <Descriptions bordered size="small" column={3}>
                    {headerItems.map((item) => (
                        <Descriptions.Item key={item.label} label={item.label}>
                            {item.value}
                        </Descriptions.Item>
                    ))}
                </Descriptions>
            </Card>

            {(relatedPurchaseRequest || purchaseOrder.plant_request) && (
                <Card title="Related documents" style={{ marginBottom: 16 }}>
                    <Typography.Paragraph style={{ marginBottom: 0 }}>
                        {relatedPurchaseRequest && (
                            <>
                                SAP PR register:{' '}
                                <Typography.Text strong>{relatedPurchaseRequest.doc_num}</Typography.Text>
                                {' · '}
                            </>
                        )}
                        {purchaseOrder.plant_request && (
                            <>
                                Plant request:{' '}
                                <Link href={`/plant-requests/${purchaseOrder.plant_request.id}`}>
                                    {purchaseOrder.plant_request.request_no}
                                </Link>
                            </>
                        )}
                    </Typography.Paragraph>
                </Card>
            )}

            <Card title="Line items" style={{ marginBottom: 16 }}>
                <Table rowKey="id" dataSource={lines} pagination={false} columns={lineColumns} />
            </Card>

            <Card title="Attachments" style={{ marginBottom: 16 }}>
                <AttachmentsPanel
                    purchaseOrderId={purchaseOrder.id}
                    attachments={attachments ?? []}
                    canAttach={can.attach}
                />
            </Card>

            <Card title="Comments">
                <CommentsPanel
                    purchaseOrderId={purchaseOrder.id}
                    comments={comments ?? []}
                    canComment={can.comment}
                    mentionableUsers={mentionableUsers ?? []}
                />
            </Card>
        </AppLayout>
    );
}
