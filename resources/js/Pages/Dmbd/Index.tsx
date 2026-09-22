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
const statusLabel: Record<string, string> = { rfu: 'RFU', standby: 'Standby', breakdown: 'Breakdown' };

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
                onError: () => message.error('Gagal menyimpan status.'),
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
                    message.success('Catatan tersimpan.');
                    closeNoteModal();
                },
                onError: () => message.error('Gagal menyimpan catatan.'),
            }
        );
    };

    const currentProjectName = (): string => {
        if (!projectCode || projectCode === 'all') {
            return 'Semua Proyek';
        }
        const match = projects.find((p) => p.project_code === projectCode);
        return match ? `${match.project_code} — ${match.project_name}` : projectCode;
    };

    const columns = [
        { title: 'Unit', dataIndex: 'unit_code', key: 'unit_code' },
        ...(!projectCode || projectCode === 'all'
            ? [{ title: 'Proyek', dataIndex: 'project_code', key: 'project_code', render: (v: string | null) => v ?? '—' }]
            : []),
        {
            title: 'Status',
            key: 'status',
            width: 160,
            render: (_: unknown, row: EquipmentRow) => {
                const current = entries[row.id]?.operational_status ?? 'rfu';
                if (!can.update) {
                    return <Tag color={statusColor[current]}>{statusLabel[current]}</Tag>;
                }
                return (
                    <Select
                        value={current}
                        style={{ width: 140 }}
                        onChange={(v) => handleStatusChange(row, v)}
                        options={[
                            { value: 'rfu', label: <Tag color="green">RFU</Tag> },
                            { value: 'standby', label: <Tag color="gold">Standby</Tag> },
                            { value: 'breakdown', label: <Tag color="red">Breakdown</Tag> },
                        ]}
                    />
                );
            },
        },
        {
            title: 'Catatan Breakdown',
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
                      title: 'Aksi',
                      key: 'action',
                      width: 160,
                      render: (_: unknown, row: EquipmentRow) => {
                          const current = entries[row.id]?.operational_status ?? 'rfu';
                          return (
                              <Button size="small" onClick={() => openNoteModal(row, current)}>
                                  {entries[row.id]?.breakdown_note ? 'Ubah Catatan' : 'Isi Catatan'}
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
                            placeholder="Cari kode unit / deskripsi"
                            allowClear
                            style={{ width: 260 }}
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            onSearch={handleSearch}
                        />
                        <Select
                            style={{ width: 160 }}
                            value={filters.status ?? 'all'}
                            onChange={handleStatusFilterChange}
                            options={[
                                { value: 'all', label: 'Semua Status' },
                                { value: 'rfu', label: 'RFU' },
                                { value: 'standby', label: 'Standby' },
                                { value: 'breakdown', label: 'Breakdown' },
                            ]}
                        />
                        {projects.length > 1 && (
                            <Select
                                style={{ width: 220 }}
                                value={projectCode ?? 'all'}
                                onChange={handleProjectChange}
                                options={[
                                    { value: 'all', label: 'Semua Proyek' },
                                    ...projects.map((p) => ({
                                        value: p.project_code,
                                        label: `${p.project_code} — ${p.project_name}`,
                                    })),
                                ]}
                            />
                        )}
                    </Space>
                    <Space size="middle">
                        <Typography.Text>Hari ini:</Typography.Text>
                        <Tag color="green">RFU: {statusSummary.rfu}</Tag>
                        <Tag color="gold">Standby: {statusSummary.standby}</Tag>
                        <Tag color="red">Breakdown: {statusSummary.breakdown}</Tag>
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
                title={noteModal?.status === 'breakdown' ? 'Catatan Penyebab Breakdown' : 'Catatan Unit'}
                open={!!noteModal?.open}
                onCancel={closeNoteModal}
                onOk={() => noteForm.submit()}
                okText="Simpan"
                cancelText="Batal"
            >
                <Form form={noteForm} layout="vertical" onFinish={submitNote}>
                    <Form.Item
                        name="breakdown_note"
                        label="Catatan"
                        rules={
                            noteModal?.status === 'breakdown'
                                ? [
                                      { required: true, message: 'Catatan wajib diisi untuk status Breakdown' },
                                      { min: 5, message: 'Catatan minimal 5 karakter' },
                                      { max: 500, message: 'Catatan maksimal 500 karakter' },
                                  ]
                                : [{ max: 500, message: 'Catatan maksimal 500 karakter' }]
                        }
                    >
                        <Input.TextArea rows={4} maxLength={500} showCount />
                    </Form.Item>
                </Form>
            </Modal>
        </AppLayout>
    );
}
