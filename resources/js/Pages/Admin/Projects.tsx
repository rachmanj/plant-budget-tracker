import { Head, router } from '@inertiajs/react';
import { ProTable } from '@ant-design/pro-components';
import { Button, Alert } from 'antd';
import { SyncOutlined } from '@ant-design/icons';
import AppLayout from '@/Layouts/AppLayout';

interface Project {
    code?: string;
    project_code?: string;
    name?: string;
    project_name?: string;
    is_active?: boolean;
}

interface ProjectsProps {
    projects: Project[];
    cachedProjects: Array<{ project_code: string; project_name: string; synced_at?: string }>;
    stale?: boolean;
}

export default function Projects({ projects, cachedProjects, stale }: ProjectsProps) {
    const dataSource = (projects.length ? projects : cachedProjects).map((p) => ({
        code: p.code ?? p.project_code ?? '',
        name: p.name ?? p.project_name ?? '',
        is_active: p.is_active ?? true,
    }));

    return (
        <AppLayout title="Proyek">
            <Head title="Proyek" />
            {stale && (
                <Alert
                    type="warning"
                    message="Data ARKFLEET tidak tersedia — menampilkan cache lokal."
                    style={{ marginBottom: 16 }}
                    showIcon
                />
            )}
            <ProTable
                rowKey="code"
                search={false}
                toolBarRender={() => [
                    <Button
                        key="sync"
                        type="primary"
                        icon={<SyncOutlined />}
                        onClick={() => router.post('/admin/projects/sync')}
                    >
                        Sinkron ARKFLEET
                    </Button>,
                ]}
                columns={[
                    { title: 'Kode', dataIndex: 'code' },
                    { title: 'Nama Proyek', dataIndex: 'name' },
                    {
                        title: 'Aktif',
                        dataIndex: 'is_active',
                        valueType: 'select',
                        valueEnum: {
                            true: { text: 'Ya', status: 'Success' },
                            false: { text: 'Tidak', status: 'Default' },
                        },
                    },
                ]}
                dataSource={dataSource}
                pagination={{ pageSize: 20 }}
            />
        </AppLayout>
    );
}
