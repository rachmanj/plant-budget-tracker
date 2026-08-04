import { Head, useForm } from '@inertiajs/react';
import { Button, Card, DatePicker, Form, InputNumber, Select, Space, Typography } from 'antd';
import { MinusCircleOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout';

interface ProjectOption {
    project_code: string;
    project_name: string;
}

interface AllocationInput {
    equipment_id?: number | null;
    unit_code_cache?: string | null;
    plant_type_cache?: 'DIGGER' | 'HAULER' | 'SUPPORT' | null;
    allocated_amount: number;
    tolerance_pct?: number;
    memo?: string;
}

interface BudgetSettingProps {
    projects: ProjectOption[];
    defaultProjectCode?: string | null;
}

export default function BudgetSetting({ projects, defaultProjectCode }: BudgetSettingProps) {
    const { data, setData, post, processing, errors } = useForm<{
        project_code: string;
        period_month: string;
        status: string;
        allocations: AllocationInput[];
    }>({
        project_code: defaultProjectCode ?? projects[0]?.project_code ?? '',
        period_month: dayjs().startOf('month').format('YYYY-MM-DD'),
        status: 'open',
        allocations: [
            {
                allocated_amount: 0,
                tolerance_pct: 10,
                plant_type_cache: 'DIGGER',
            },
        ],
    });

    const submit = () => {
        post('/budget');
    };

    return (
        <AppLayout title="Buat Alokasi Anggaran">
            <Head title="Buat Alokasi" />
            <Card>
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item label="Proyek" validateStatus={errors.project_code ? 'error' : undefined}>
                        <Select
                            value={data.project_code}
                            onChange={(v) => setData('project_code', v)}
                            options={projects.map((p) => ({
                                value: p.project_code,
                                label: `${p.project_code} — ${p.project_name}`,
                            }))}
                        />
                    </Form.Item>

                    <Form.Item label="Bulan Periode" validateStatus={errors.period_month ? 'error' : undefined}>
                        <DatePicker
                            picker="month"
                            style={{ width: '100%' }}
                            value={dayjs(data.period_month)}
                            onChange={(d) =>
                                setData('period_month', d ? d.startOf('month').format('YYYY-MM-DD') : '')
                            }
                        />
                    </Form.Item>

                    <Typography.Title level={5}>Baris Alokasi</Typography.Title>

                    {data.allocations.map((row, index) => (
                        <Space key={index} align="start" style={{ display: 'flex', marginBottom: 16 }} wrap>
                            <Form.Item label="Unit Code">
                                <Select
                                    allowClear
                                    placeholder="Divisi (kosongkan)"
                                    style={{ width: 140 }}
                                    value={row.unit_code_cache ?? undefined}
                                    onChange={(v) => {
                                        const next = [...data.allocations];
                                        next[index] = { ...next[index], unit_code_cache: v ?? null };
                                        setData('allocations', next);
                                    }}
                                    options={[
                                        { value: 'E-001', label: 'E-001' },
                                        { value: 'E-002', label: 'E-002' },
                                    ]}
                                />
                            </Form.Item>
                            <Form.Item label="Tipe Plant">
                                <Select
                                    style={{ width: 120 }}
                                    value={row.plant_type_cache ?? 'DIGGER'}
                                    onChange={(v) => {
                                        const next = [...data.allocations];
                                        next[index] = { ...next[index], plant_type_cache: v };
                                        setData('allocations', next);
                                    }}
                                    options={[
                                        { value: 'DIGGER', label: 'DIGGER' },
                                        { value: 'HAULER', label: 'HAULER' },
                                        { value: 'SUPPORT', label: 'SUPPORT' },
                                    ]}
                                />
                            </Form.Item>
                            <Form.Item label="Jumlah (IDR)">
                                <InputNumber
                                    style={{ width: 180 }}
                                    min={0}
                                    value={row.allocated_amount}
                                    onChange={(v) => {
                                        const next = [...data.allocations];
                                        next[index] = { ...next[index], allocated_amount: Number(v ?? 0) };
                                        setData('allocations', next);
                                    }}
                                />
                            </Form.Item>
                            <Form.Item label="Toleransi %">
                                <InputNumber
                                    min={0}
                                    max={100}
                                    value={row.tolerance_pct ?? 10}
                                    onChange={(v) => {
                                        const next = [...data.allocations];
                                        next[index] = { ...next[index], tolerance_pct: Number(v ?? 10) };
                                        setData('allocations', next);
                                    }}
                                />
                            </Form.Item>
                            {data.allocations.length > 1 && (
                                <Button
                                    type="text"
                                    danger
                                    icon={<MinusCircleOutlined />}
                                    onClick={() =>
                                        setData(
                                            'allocations',
                                            data.allocations.filter((_, i) => i !== index)
                                        )
                                    }
                                />
                            )}
                        </Space>
                    ))}

                    <Button
                        type="dashed"
                        icon={<PlusOutlined />}
                        onClick={() =>
                            setData('allocations', [
                                ...data.allocations,
                                { allocated_amount: 0, tolerance_pct: 10, plant_type_cache: 'DIGGER' },
                            ])
                        }
                        style={{ marginBottom: 24 }}
                    >
                        Tambah Baris
                    </Button>

                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Simpan Anggaran
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </AppLayout>
    );
}
