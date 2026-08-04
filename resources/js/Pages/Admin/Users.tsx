import { Head, router, useForm } from '@inertiajs/react';
import { ProTable } from '@ant-design/pro-components';
import { Button, Modal, Form, Input, Select, Switch, Tag } from 'antd';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

interface UserRole {
    name: string;
    project_code?: string;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    employee_no?: string;
    division?: string;
    project_code_scope?: string;
    is_active: boolean;
    roles: UserRole[];
}

interface UsersProps {
    users: UserRow[];
    roles: string[];
    divisions: string[];
}

export default function Users({ users, roles, divisions }: UsersProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [roleModalUser, setRoleModalUser] = useState<UserRow | null>(null);

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        employee_no: '',
        division: '',
        project_code_scope: '',
        is_active: true,
    });

    const roleForm = useForm<{ roles: UserRole[] }>({
        roles: [],
    });

    const openRoleModal = (user: UserRow) => {
        setRoleModalUser(user);
        roleForm.setData('roles', user.roles.length ? user.roles : [{ name: '', project_code: '' }]);
    };

    return (
        <AppLayout title="Pengguna">
            <Head title="Pengguna" />
            <ProTable<UserRow>
                rowKey="id"
                search={false}
                toolBarRender={() => [
                    <Button key="create" type="primary" onClick={() => setCreateOpen(true)}>
                        Tambah User
                    </Button>,
                ]}
                columns={[
                    { title: 'Nama', dataIndex: 'name' },
                    { title: 'Email', dataIndex: 'email' },
                    { title: 'Divisi', dataIndex: 'division' },
                    { title: 'Scope Proyek', dataIndex: 'project_code_scope' },
                    {
                        title: 'Role',
                        dataIndex: 'roles',
                        render: (_, row) =>
                            row.roles.map((r) => (
                                <Tag key={`${r.name}-${r.project_code}`}>
                                    {r.name}
                                    {r.project_code ? `@${r.project_code}` : ''}
                                </Tag>
                            )),
                    },
                    {
                        title: 'Aksi',
                        valueType: 'option',
                        render: (_, row) => [
                            <Button key="roles" type="link" onClick={() => openRoleModal(row)}>
                                Role
                            </Button>,
                        ],
                    },
                ]}
                dataSource={users}
            />

            <Modal
                title="Tambah User"
                open={createOpen}
                onCancel={() => setCreateOpen(false)}
                onOk={() => createForm.post('/admin/users', { onSuccess: () => setCreateOpen(false) })}
                confirmLoading={createForm.processing}
            >
                <Form layout="vertical">
                    <Form.Item label="Nama" required>
                        <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Email" required>
                        <Input value={createForm.data.email} onChange={(e) => createForm.setData('email', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Password" required>
                        <Input.Password value={createForm.data.password} onChange={(e) => createForm.setData('password', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Divisi">
                        <Select
                            allowClear
                            options={divisions.map((d) => ({ label: d, value: d }))}
                            value={createForm.data.division || undefined}
                            onChange={(v) => createForm.setData('division', v ?? '')}
                        />
                    </Form.Item>
                    <Form.Item label="Scope Proyek">
                        <Input value={createForm.data.project_code_scope} onChange={(e) => createForm.setData('project_code_scope', e.target.value)} />
                    </Form.Item>
                    <Form.Item label="Aktif">
                        <Switch checked={createForm.data.is_active} onChange={(v) => createForm.setData('is_active', v)} />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Assign Role — ${roleModalUser?.name ?? ''}`}
                open={!!roleModalUser}
                onCancel={() => setRoleModalUser(null)}
                onOk={() => {
                    if (!roleModalUser) return;
                    roleForm.post(`/admin/users/${roleModalUser.id}/roles`, {
                        onSuccess: () => setRoleModalUser(null),
                    });
                }}
                confirmLoading={roleForm.processing}
            >
                <Form layout="vertical">
                    {roleForm.data.roles.map((role, index) => (
                        <div key={index} style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
                            <Select
                                style={{ flex: 1 }}
                                placeholder="Role"
                                options={roles.map((r) => ({ label: r, value: r }))}
                                value={role.name || undefined}
                                onChange={(v) => {
                                    const next = [...roleForm.data.roles];
                                    next[index] = { ...next[index], name: v };
                                    roleForm.setData('roles', next);
                                }}
                            />
                            <Input
                                style={{ width: 120 }}
                                placeholder="Proyek"
                                value={role.project_code}
                                onChange={(e) => {
                                    const next = [...roleForm.data.roles];
                                    next[index] = { ...next[index], project_code: e.target.value };
                                    roleForm.setData('roles', next);
                                }}
                            />
                        </div>
                    ))}
                    <Button
                        type="dashed"
                        block
                        onClick={() => roleForm.setData('roles', [...roleForm.data.roles, { name: '', project_code: '' }])}
                    >
                        Tambah Role
                    </Button>
                </Form>
            </Modal>
        </AppLayout>
    );
}
