import { Head, router, usePage } from '@inertiajs/react';
import { ProTable } from '@ant-design/pro-components';
import { Button, Modal, Form, Input, Transfer } from 'antd';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { roleLabel } from '@/utils/labels';
interface RoleRow {
    id: number;
    name: string;
    permissions: string[];
}

interface RolesProps {
    roles: RoleRow[];
    permissions: string[];
}

interface NavPageProps {
    nav?: { roleLabels?: Record<string, string> };
}

export default function Roles({ roles, permissions }: RolesProps) {
    const { nav } = usePage<NavPageProps>().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [permRole, setPermRole] = useState<RoleRow | null>(null);
    const [newRoleName, setNewRoleName] = useState('');
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);

    const openPermissions = (role: RoleRow) => {
        setPermRole(role);
        setSelectedPermissions(role.permissions);
    };

    return (
        <AppLayout title="Roles & Permissions">
            <Head title="Roles & Permissions" />
            <ProTable<RoleRow>
                rowKey="id"
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={() => setCreateOpen(true)}>
                        Add Role
                    </Button>,
                ]}
                columns={[
                    {
                        title: 'Role',
                        dataIndex: 'name',
                        render: (_, row) => roleLabel(row.name, nav?.roleLabels),
                    },
                    {
                        title: 'Permission Count',
                        dataIndex: 'permissions',
                        render: (_, row) => row.permissions.length,
                    },
                    {
                        title: 'Actions',
                        valueType: 'option',
                        render: (_, row) => [
                            <Button key="perm" type="link" onClick={() => openPermissions(row)}>
                                Permissions
                            </Button>,
                        ],
                    },
                ]}
                dataSource={roles}
            />

            <Modal
                title="Add Role"
                open={createOpen}
                onCancel={() => setCreateOpen(false)}
                onOk={() => {
                    router.post('/admin/roles', { name: newRoleName }, { onSuccess: () => setCreateOpen(false) });
                }}
            >
                <Form layout="vertical">
                    <Form.Item label="Role Name" required>
                        <Input value={newRoleName} onChange={(e) => setNewRoleName(e.target.value)} />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Permissions — ${permRole ? roleLabel(permRole.name, nav?.roleLabels) : ''}`}
                open={!!permRole}
                onCancel={() => setPermRole(null)}
                onOk={() => {
                    if (!permRole) return;
                    router.post(`/admin/roles/${permRole.id}/permissions`, {
                        permissions: selectedPermissions,
                    }, { onSuccess: () => setPermRole(null) });
                }}
                width={720}
            >
                <Transfer
                    dataSource={permissions.map((p) => ({ key: p, title: p }))}
                    titles={['Available', 'Assigned']}
                    targetKeys={selectedPermissions}
                    onChange={(keys) => setSelectedPermissions(keys as string[])}
                    render={(item) => item.title ?? ''}
                    listStyle={{ width: 300, height: 360 }}
                    oneWay={false}
                />
            </Modal>
        </AppLayout>
    );
}
