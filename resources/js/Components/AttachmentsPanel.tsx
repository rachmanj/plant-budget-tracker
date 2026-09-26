import { DeleteOutlined, DownloadOutlined, InboxOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { Button, Empty, List, Popconfirm, Space, Typography, Upload } from 'antd';
import type { UploadProps } from 'antd';
import dayjs from 'dayjs';

export interface AttachmentRow {
    id: number;
    original_name: string;
    size: number;
    mime: string | null;
    uploaded_by_name: string | null;
    created_at: string | null;
    download_url: string;
}

interface AttachmentsPanelProps {
    purchaseOrderId: number;
    attachments: AttachmentRow[];
    canAttach: boolean;
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatUploadedAt(value: string | null): string {
    return value ? dayjs(value).format('DD MMM YYYY HH:mm') : '—';
}

export default function AttachmentsPanel({
    purchaseOrderId,
    attachments,
    canAttach,
}: AttachmentsPanelProps) {
    const uploadUrl = `/procurement/purchase-orders/${purchaseOrderId}/attachments`;

    const uploadProps: UploadProps = {
        name: 'file',
        multiple: false,
        showUploadList: false,
        beforeUpload: (file) => {
            router.post(
                uploadUrl,
                { file },
                {
                    forceFormData: true,
                    preserveScroll: true,
                },
            );

            return false;
        },
    };

    return (
        <>
            {canAttach && (
                <Upload.Dragger {...uploadProps} style={{ marginBottom: attachments.length ? 16 : 0 }}>
                    <p className="ant-upload-drag-icon">
                        <InboxOutlined />
                    </p>
                    <p className="ant-upload-text">Drop a file here or click to upload</p>
                    <p className="ant-upload-hint">
                        PDF, Excel, Word, or image files up to 10 MB
                    </p>
                </Upload.Dragger>
            )}

            {attachments.length === 0 ? (
                <Empty
                    description="No attachments yet"
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    style={{ marginTop: canAttach ? 16 : 0 }}
                />
            ) : (
                <List
                    itemLayout="horizontal"
                    dataSource={attachments}
                    renderItem={(item) => (
                        <List.Item
                            actions={[
                                <Button
                                    key="download"
                                    type="link"
                                    icon={<DownloadOutlined />}
                                    href={item.download_url}
                                >
                                    Download
                                </Button>,
                                canAttach ? (
                                    <Popconfirm
                                        key="delete"
                                        title="Remove this attachment?"
                                        okText="Remove"
                                        okButtonProps={{ danger: true }}
                                        onConfirm={() =>
                                            router.delete(
                                                `/procurement/purchase-orders/${purchaseOrderId}/attachments/${item.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Button type="link" danger icon={<DeleteOutlined />}>
                                            Delete
                                        </Button>
                                    </Popconfirm>
                                ) : null,
                            ].filter(Boolean)}
                        >
                            <List.Item.Meta
                                title={item.original_name}
                                description={
                                    <Space split="·" wrap size="small">
                                        <Typography.Text type="secondary">
                                            {formatFileSize(item.size)}
                                        </Typography.Text>
                                        <Typography.Text type="secondary">
                                            {item.uploaded_by_name ?? 'Unknown uploader'}
                                        </Typography.Text>
                                        <Typography.Text type="secondary">
                                            {formatUploadedAt(item.created_at)}
                                        </Typography.Text>
                                    </Space>
                                }
                            />
                        </List.Item>
                    )}
                />
            )}
        </>
    );
}
