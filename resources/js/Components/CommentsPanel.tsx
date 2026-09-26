import { DeleteOutlined, UserAddOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { Button, Dropdown, Empty, Input, List, Popconfirm, Space, Tag, Typography } from 'antd';
import type { MenuProps } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';

export interface CommentRow {
    id: number;
    author_name: string | null;
    created_at: string | null;
    body: string;
    mentioned_names: string[];
    can_delete: boolean;
}

export interface MentionableUser {
    id: number;
    name: string;
    email: string;
}

interface CommentsPanelProps {
    purchaseOrderId: number;
    comments: CommentRow[];
    canComment: boolean;
    mentionableUsers: MentionableUser[];
}

function formatCommentTime(value: string | null): string {
    return value ? dayjs(value).format('DD MMM YYYY HH:mm') : '—';
}

function mentionHandle(email: string): string {
    const local = email.split('@')[0] ?? email;
    return `@${local}`;
}

export default function CommentsPanel({
    purchaseOrderId,
    comments,
    canComment,
    mentionableUsers,
}: CommentsPanelProps) {
    const [body, setBody] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const insertMention = (email: string) => {
        const token = mentionHandle(email);
        const trimmed = body.trimEnd();
        setBody(trimmed === '' ? `${token} ` : `${trimmed} ${token} `);
    };

    const mentionMenu: MenuProps = {
        items: mentionableUsers.map((user) => ({
            key: String(user.id),
            label: `${user.name} (${user.email})`,
            onClick: () => insertMention(user.email),
        })),
    };

    const submitComment = () => {
        const trimmed = body.trim();
        if (!trimmed || submitting) {
            return;
        }
        setSubmitting(true);
        router.post(
            `/procurement/purchase-orders/${purchaseOrderId}/comments`,
            { body: trimmed },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
                onSuccess: () => setBody(''),
            },
        );
    };

    return (
        <>
            {comments.length === 0 ? (
                <Empty
                    description="No comments yet"
                    image={Empty.PRESENTED_IMAGE_SIMPLE}
                    style={{ marginBottom: canComment ? 16 : 0 }}
                />
            ) : (
                <List
                    itemLayout="horizontal"
                    dataSource={comments}
                    style={{ marginBottom: canComment ? 16 : 0 }}
                    renderItem={(item) => (
                        <List.Item
                            actions={
                                item.can_delete
                                    ? [
                                          <Popconfirm
                                              key="delete"
                                              title="Remove this comment?"
                                              okText="Remove"
                                              okButtonProps={{ danger: true }}
                                              onConfirm={() =>
                                                  router.delete(
                                                      `/procurement/purchase-orders/${purchaseOrderId}/comments/${item.id}`,
                                                      { preserveScroll: true },
                                                  )
                                              }
                                          >
                                              <Button
                                                  type="link"
                                                  danger
                                                  size="small"
                                                  icon={<DeleteOutlined />}
                                              >
                                                  Delete
                                              </Button>
                                          </Popconfirm>,
                                      ]
                                    : undefined
                            }
                        >
                            <List.Item.Meta
                                title={
                                    <Space wrap size="small">
                                        <Typography.Text strong>
                                            {item.author_name ?? 'Unknown'}
                                        </Typography.Text>
                                        <Typography.Text type="secondary">
                                            {formatCommentTime(item.created_at)}
                                        </Typography.Text>
                                    </Space>
                                }
                                description={
                                    <>
                                        <Typography.Paragraph
                                            style={{ marginBottom: item.mentioned_names.length ? 8 : 0 }}
                                        >
                                            {item.body}
                                        </Typography.Paragraph>
                                        {item.mentioned_names.length > 0 && (
                                            <Space wrap size={[4, 4]}>
                                                {item.mentioned_names.map((name) => (
                                                    <Tag key={name} color="cyan">
                                                        @{name}
                                                    </Tag>
                                                ))}
                                            </Space>
                                        )}
                                    </>
                                }
                            />
                        </List.Item>
                    )}
                />
            )}

            {canComment && (
                <Space direction="vertical" style={{ width: '100%' }} size="small">
                    <Input.TextArea
                        rows={3}
                        maxLength={2000}
                        showCount
                        placeholder="Write a comment. Use @ to mention colleagues."
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                    />
                    <Space>
                        <Dropdown menu={mentionMenu} trigger={['click']} disabled={mentionableUsers.length === 0}>
                            <Button icon={<UserAddOutlined />}>Mention</Button>
                        </Dropdown>
                        <Button type="primary" loading={submitting} onClick={submitComment}>
                            Post comment
                        </Button>
                    </Space>
                </Space>
            )}
        </>
    );
}
