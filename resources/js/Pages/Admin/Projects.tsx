import { Head, router } from '@inertiajs/react';
import { ProTable } from '@ant-design/pro-components';
import { Alert, Button, Switch, Tag, Typography, message } from 'antd';
import { SyncOutlined } from '@ant-design/icons';
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
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString('id-ID');
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
                        isActive ? `Proyek ${projectCode} diaktifkan.` : `Proyek ${projectCode} dinonaktifkan.`,
                    );
                },
                onError: () => message.error('Gagal memperbarui status proyek.'),
            },
        );
    };

    return (
        <AppLayout title="Proyek">
            <Head title="Proyek" />
            {!arkfleetReachable && (
                <Alert
                    type="warning"
                    message="ARKFLEET tidak terjangkau"
                    description="Daftar di bawah tetap ditampilkan dari data tersimpan di database. Sinkronisasi akan memperbarui nama dan lokasi saat ARKFLEET kembali online."
                    style={{ marginBottom: 16 }}
                    showIcon
                />
            )}
            <Typography.Paragraph type="secondary" style={{ marginBottom: 16 }}>
                Proyek aktif menandai proyek yang dipakai dalam operasi (termasuk pemuatan cache unit harian) dan{' '}
                <strong>tidak</strong> membatasi pembuatan anggaran untuk proyek lain.
                {lastSyncedAt ? (
                    <>
                        {' '}
                        Terakhir disinkronkan: {formatSyncedAt(lastSyncedAt)}.
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
                        Sinkronkan dari ARKFLEET
                    </Button>,
                ]}
                columns={[
                    { title: 'Kode', dataIndex: 'project_code', width: 100 },
                    { title: 'Nama Proyek', dataIndex: 'project_name' },
                    {
                        title: 'Lokasi',
                        dataIndex: 'location',
                        render: (_, row) => row.location ?? '—',
                    },
                    {
                        title: 'Status',
                        dataIndex: 'is_active',
                        render: (_, row) =>
                            row.is_active ? (
                                <Tag color="success">Aktif</Tag>
                            ) : (
                                <Tag color="default">Non-aktif</Tag>
                            ),
                    },
                    {
                        title: 'Terakhir Sinkron',
                        dataIndex: 'synced_at',
                        render: (_, row) => formatSyncedAt(row.synced_at),
                    },
                    {
                        title: 'Aksi',
                        valueType: 'option',
                        render: (_, row) => [
                            <Switch
                                key="toggle"
                                checked={row.is_active}
                                checkedChildren="Aktif"
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
