import { Head, router } from '@inertiajs/react';
import { ProTable } from '@ant-design/pro-components';
import { Alert, Button, Switch, Tag, Typography, message } from 'antd';
import { SyncOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout';

interface ProjectRow {
    project_code: string;
    project_name: string;
    location: string | null;
    is_active: boolean;
    synced_at: string | null;
}

interface ProjectsProps {
    projects: ProjectRow[];
    lastSyncedAt: string | null;
    arkfleetReachable: boolean;
}

function formatSyncedAt(value: string | null): string {
    if (!value) {
        return '—';
    }
    const parsed = dayjs(value);
    if (!parsed.isValid()) {
        return value;
    }
    return parsed.format('DD MMM YYYY, HH:mm');
}

export default function Projects({ projects, lastSyncedAt, arkfleetReachable }: ProjectsProps) {
    const toggleActive = (projectCode: string, isActive: boolean) => {
        router.patch(
            `/admin/projects/${encodeURIComponent(projectCode)}`,
            { is_active: isActive },
            {
                preserveScroll: true,
                onSuccess: () => {
                    message.success(
                        isActive ? `Project ${projectCode} activated.` : `Project ${projectCode} deactivated.`,
                    );
                },
                onError: () => message.error('Failed to update project status.'),
            },
        );
    };

    return (
        <AppLayout title="Projects">
            <Head title="Projects" />
            {!arkfleetReachable && (
                <Alert
                    type="warning"
                    message="ARKFLEET unreachable"
                    description="The list below is from cached database data. Sync will refresh names and locations when ARKFLEET is back online."
                    style={{ marginBottom: 16 }}
                    showIcon
                />
            )}
            <Typography.Paragraph type="secondary" style={{ marginBottom: 16 }}>
                Active projects are used in operations (including daily unit cache loading) and{' '}
                <strong>do not</strong> restrict budget creation for other projects.
                {lastSyncedAt ? (
                    <>
                        {' '}
                        Last synced: {formatSyncedAt(lastSyncedAt)}.
                    </>
                ) : null}
            </Typography.Paragraph>
            <ProTable<ProjectRow>
                rowKey="project_code"
                search={false}
                toolBarRender={() => [
                    <Button
                        key="sync"
                        type="primary"
                        icon={<SyncOutlined />}
                        onClick={() => router.post('/admin/projects/sync')}
                    >
                        Sync from ARKFLEET
                    </Button>,
                ]}
                columns={[
                    { title: 'Code', dataIndex: 'project_code', width: 100 },
                    { title: 'Project Name', dataIndex: 'project_name' },
                    {
                        title: 'Location',
                        dataIndex: 'location',
                        render: (_, row) => row.location ?? '—',
                    },
                    {
                        title: 'Status',
                        dataIndex: 'is_active',
                        render: (_, row) =>
                            row.is_active ? (
                                <Tag color="success">Active</Tag>
                            ) : (
                                <Tag color="default">Inactive</Tag>
                            ),
                    },
                    {
                        title: 'Last Sync',
                        dataIndex: 'synced_at',
                        render: (_, row) => formatSyncedAt(row.synced_at),
                    },
                    {
                        title: 'Actions',
                        valueType: 'option',
                        render: (_, row) => [
                            <Switch
                                key="toggle"
                                checked={row.is_active}
                                checkedChildren="Active"
                                unCheckedChildren="Off"
                                onChange={(checked) => toggleActive(row.project_code, checked)}
                            />,
                        ],
                    },
                ]}
                dataSource={projects}
                pagination={{ pageSize: 20 }}
            />
        </AppLayout>
    );
}
