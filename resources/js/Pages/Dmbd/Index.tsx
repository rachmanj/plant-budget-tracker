import { Head, router } from '@inertiajs/react';
import {
    Button,
    Card,
    Form,
    Input,
    Modal,
    Select,
    Space,
    Table,
    Tag,
    Tooltip,
    Typography,
    message,
} from 'antd';
import type { TablePaginationConfig } from 'antd/es/table';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { statusLabel } from '@/utils/labels';

interface EquipmentRow {
    id: number;
    unit_code: string;
    description?: string | null;
    project_code?: string | null;
}

interface EntryInfo {
    operational_status: string;
    breakdown_note: string | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface ProjectOption {
    project_code: string;
    project_name: string;
}

interface Filters {
    search: string | null;
    status: string | null;
    project_code: string | null;
}

interface Props {
    units: Paginator<EquipmentRow>;
    entries: Record<number, EntryInfo>;
    statusSummary: { rfu: number; standby: number; breakdown: number };
    filters: Filters;
    perPage: number;
    projectCode: string | null;
    reportDate: string;
    projects: ProjectOption[];
    can: { update: boolean };
}

const statusColor: Record<string, string> = { rfu: 'green', standby: 'gold', breakdown: 'red' };

export default function Index({
    units,
    entries,
    statusSummary,
    filters,
    perPage,
    projectCode,
    reportDate,
    projects,
    can,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search ?? '');
    const [noteForm] = Form.useForm<{ breakdown_note: string }>();
    const [noteModal, setNoteModal] = useState<{
        open: boolean;
        equipmentId: number;
        unitCode: string;
        status: string;
    } | null>(null);

    const navigate = (overrides: Record<string, unknown>) => {
        router.get(
            '/dmbd',
            {
                search: filters.search ?? undefined,
                status: filters.status ?? undefined,
                project_code: projectCode ?? undefined,
                per_page: perPage,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearch = (value: string) => navigate({ search: value || undefined, page: 1 });
    const handleStatusFilterChange = (value: string) =>
        navigate({ status: value === 'all' ? undefined : value, page: 1 });
    const handleProjectChange = (value: string) => navigate({ project_code: value, page: 1 });

    const handleTableChange = (pagination: TablePaginationConfig) => {
        navigate({ page: pagination.current, per_page: pagination.pageSize });
    };

    const openNoteModal = (row: EquipmentRow, status: string) => {
        const current = entries[row.id];
        setNoteModal({ open: true, equipmentId: row.id, unitCode: row.unit_code, status });
        noteForm.setFieldsValue({ breakdown_note: current?.breakdown_note ?? '' });
    };

    const closeNoteModal = () => {
        setNoteModal(null);
        noteForm.resetFields();
    };

    const submitStatus = (row: EquipmentRow, status: string) => {
        router.post(
            '/dmbd',
            {
                equipment_id: row.id,
                unit_code_cache: row.unit_code,
                operational_status: status,
            },
            {
                preserveScroll: true,
                onError: () => message.error('Failed to save status.'),
            }
        );
    };

    const handleStatusChange = (row: EquipmentRow, status: string) => {
        if (status === 'breakdown') {
            openNoteModal(row, 'breakdown');
            return;
        }
        submitStatus(row, status);
    };

    const submitNote = (values: { breakdown_note: string }) => {
        if (!noteModal) {
            return;
        }
        router.post(
            '/dmbd',
            {
                equipment_id: noteModal.equipmentId,
                unit_code_cache: noteModal.unitCode,
                operational_status: noteModal.status,
                breakdown_note: values.breakdown_note || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    message.success('Note saved.');
                    closeNoteModal();
                },
                onError: () => message.error('Failed to save note.'),
            }
        );
    };

    const currentProjectName = (): string => {
        if (!projectCode || projectCode === 'all') {
            return 'All Projects';
        }
        const match = projects.find((p) => p.project_code === projectCode);
        return match ? `${match.project_code} — ${match.project_name}` : projectCode;
    };

    const columns = [
        { title: 'Unit', dataIndex: 'unit_code', key: 'unit_code' },
        ...(!projectCode || projectCode === 'all'
            ? [{ title: 'Project', dataIndex: 'project_code', key: 'project_code', render: (v: string | null) => v ?? '—' }]
            : []),
        {
            title: 'Status',
            key: 'status',
            width: 200,
            render: (_: unknown, row: EquipmentRow) => {
                const current = entries[row.id]?.operational_status ?? 'rfu';
                if (!can.update) {
                    return <Tag color={statusColor[current]}>{statusLabel(current)}</Tag>;
                }
                return (
                    <Select
                        value={current}
                        style={{ width: 180 }}
                        onChange={(v) => handleStatusChange(row, v)}
                        options={[
                            { value: 'rfu', label: statusLabel('rfu') },
                            { value: 'standby', label: statusLabel('standby') },
                            { value: 'breakdown', label: statusLabel('breakdown') },
                        ]}
                    />
                );
            },
        },
        {
            title: 'Breakdown Notes',
            key: 'breakdown_note',
            render: (_: unknown, row: EquipmentRow) => {
                const note = entries[row.id]?.breakdown_note;
                if (!note) {
                    return '—';
                }
                if (note.length <= 60) {
                    return note;
                }
                return (
                    <Tooltip title={note}>
                        <span>{note.slice(0, 60)}…</span>
                    </Tooltip>
                );
            },
        },
        ...(can.update
            ? [
                  {
                      title: 'Actions',
                      key: 'action',
                      width: 160,
                      render: (_: unknown, row: EquipmentRow) => {
                          const current = entries[row.id]?.operational_status ?? 'rfu';
                          return (
                              <Button size="small" onClick={() => openNoteModal(row, current)}>
                                  {entries[row.id]?.breakdown_note ? 'Edit Note' : 'Add Note'}
                              </Button>
                          );
                      },
                  },
              ]
            : []),
    ];

    return (
        <AppLayout title="DMBD">
            <Head title="DMBD" />
            <Card title={`Daily Monitoring — ${reportDate} — ${currentProjectName()}`}>
                <Space direction="vertical" size="middle" style={{ width: '100%' }}>
                    <Space wrap>
                        <Input.Search
                            placeholder="Search unit code / description"
                            allowClear
                            style={{ width: 260 }}
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            onSearch={handleSearch}
                        />
                        <Select
                            style={{ width: 180 }}
                            value={filters.status ?? 'all'}
                            onChange={handleStatusFilterChange}
                            options={[
                                { value: 'all', label: 'All Statuses' },
                                { value: 'rfu', label: statusLabel('rfu') },
                                { value: 'standby', label: statusLabel('standby') },
                                { value: 'breakdown', label: statusLabel('breakdown') },
                            ]}
                        />
                        {projects.length > 1 && (
                            <Select
                                style={{ width: 220 }}
                                value={projectCode ?? 'all'}
                                onChange={handleProjectChange}
                                options={[
                                    { value: 'all', label: 'All Projects' },
                                    ...projects.map((p) => ({
                                        value: p.project_code,
                                        label: `${p.project_code} — ${p.project_name}`,
                                    })),
                                ]}
                            />
                        )}
                    </Space>
                    <Space size="middle">
                        <Typography.Text>Today:</Typography.Text>
                        <Tag color="green">{statusLabel('rfu')}: {statusSummary.rfu}</Tag>
                        <Tag color="gold">{statusLabel('standby')}: {statusSummary.standby}</Tag>
                        <Tag color="red">{statusLabel('breakdown')}: {statusSummary.breakdown}</Tag>
                    </Space>
                    <Table
                        rowKey="id"
                        dataSource={units.data}
                        columns={columns}
                        onChange={handleTableChange}
                        pagination={{
                            current: units.current_page,
                            pageSize: units.per_page,
                            total: units.total,
                            showSizeChanger: true,
                            pageSizeOptions: [25, 50, 100],
                        }}
                    />
                </Space>
            </Card>
            <Modal
                title={noteModal?.status === 'breakdown' ? 'Breakdown Cause Note' : 'Unit Note'}
                open={!!noteModal?.open}
                onCancel={closeNoteModal}
                onOk={() => noteForm.submit()}
                okText="Save"
                cancelText="Cancel"
            >
                <Form form={noteForm} layout="vertical" onFinish={submitNote}>
                    <Form.Item
                        name="breakdown_note"
                        label="Note"
                        rules={
                            noteModal?.status === 'breakdown'
                                ? [
                                      { required: true, message: 'Note is required for Breakdown status' },
                                      { min: 5, message: 'Note must be at least 5 characters' },
                                      { max: 500, message: 'Note must be at most 500 characters' },
                                  ]
                                : [{ max: 500, message: 'Note must be at most 500 characters' }]
                        }
                    >
                        <Input.TextArea rows={4} maxLength={500} showCount />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
