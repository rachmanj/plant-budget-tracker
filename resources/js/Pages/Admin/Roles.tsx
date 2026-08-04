import { Head, router } from '@inertiajs/react';
import { ProTable } from '@ant-design/pro-components';
import { Button, Modal, Form, Input, Transfer } from 'antd';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

interface RoleRow {
    id: number;
    name: string;
    permissions: string[];
}

interface RolesProps {
    roles: RoleRow[];
    permissions: string[];
}

export default function Roles({ roles, permissions }: RolesProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [permRole, setPermRole] = useState<RoleRow | null>(null);
    const [newRoleName, setNewRoleName] = useState('');
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);

    const openPermissions = (role: RoleRow) => {
        setPermRole(role);
        setSelectedPermissions(role.permissions);
    };

    return (
        <AppLayout title="Role & Permission">
            <Head title="Role & Permission" />
            <ProTable<RoleRow>
                rowKey="id"
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={() => setCreateOpen(true)}>
                        Tambah Role
                    </Button>,
                ]}
                columns={[
                    { title: 'Role', dataIndex: 'name' },
                    {
                        title: 'Jumlah Permission',
                        dataIndex: 'permissions',
                        render: (_, row) => row.permissions.length,
                    },
                    {
                        title: 'Aksi',
                        valueType: 'option',
                        render: (_, row) => [
                            <Button key="perm" type="link" onClick={() => openPermissions(row)}>
                                Permission
                            </Button>,
                        ],
                    },
                ]}
                dataSource={roles}
            />

            <Modal
                title="Tambah Role"
                open={createOpen}
                onCancel={() => setCreateOpen(false)}
                onOk={() => {
                    router.post('/admin/roles', { name: newRoleName }, { onSuccess: () => setCreateOpen(false) });
                }}
            >
                <Form layout="vertical">
                    <Form.Item label="Nama Role" required>
                        <Input value={newRoleName} onChange={(e) => setNewRoleName(e.target.value)} />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Permission — ${permRole?.name ?? ''}`}
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
                    titles={['Tersedia', 'Assigned']}
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
