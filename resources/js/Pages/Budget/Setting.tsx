import { useMemo } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Alert, Button, Card, DatePicker, Form, InputNumber, Select, Space, Tag, Typography } from 'antd';
import { MinusCircleOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout';

interface ProjectOption {
    project_code: string;
    project_name: string;
}

interface EquipmentOption {
    id: number;
    unit_code: string | null;
    description: string | null;
    plant_type: string | null;
    unitstatus: string | null;
    plant_type_mapped: 'DIGGER' | 'HAULER' | 'SUPPORT' | null;
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
    equipment: EquipmentOption[];
    stale?: boolean;
}

const DIVISION_VALUE = '';

function equipmentOptionLabel(item: EquipmentOption): string {
    return `${item.unit_code ?? '—'} — ${item.description ?? '—'} (${item.unitstatus ?? '—'})`;
}

export default function BudgetSetting({ projects, defaultProjectCode, equipment, stale }: BudgetSettingProps) {
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
                equipment_id: null,
                unit_code_cache: null,
                allocated_amount: 0,
                tolerance_pct: 10,
                plant_type_cache: 'DIGGER',
            },
        ],
    });

    const equipmentGroups = useMemo(() => {
        const groups = new Map<string, EquipmentOption[]>();
        for (const item of equipment) {
            const groupLabel = item.plant_type ?? 'Tidak diketahui';
            if (!groups.has(groupLabel)) {
                groups.set(groupLabel, []);
            }
            groups.get(groupLabel)!.push(item);
        }
        return Array.from(groups.entries());
    }, [equipment]);

    const equipmentById = useMemo(() => {
        const map = new Map<number, EquipmentOption>();
        for (const item of equipment) {
            map.set(item.id, item);
        }
        return map;
    }, [equipment]);

    const submit = () => {
        post('/budget');
    };

    const changeProject = (projectCode: string) => {
        setData('project_code', projectCode);
        router.get('/budget/setting', { project_code: projectCode }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Buat Alokasi Anggaran">
            <Head title="Buat Alokasi" />
            <Card>
                <Form layout="vertical" onFinish={submit}>
                    <Form.Item label="Proyek" validateStatus={errors.project_code ? 'error' : undefined}>
                        <Select
                            value={data.project_code}
                            onChange={changeProject}
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

                    {stale && (
                        <Alert
                            type="warning"
                            showIcon
                            style={{ marginBottom: 16 }}
                            message="Data unit dari ARKFLEET belum bisa diambil — alokasi per unit tidak bisa dipilih sekarang. Gunakan alokasi tingkat divisi atau coba lagi nanti."
                        />
                    )}

                    <Typography.Title level={5}>Baris Alokasi</Typography.Title>

                    {data.allocations.map((row, index) => {
                        const isPerUnit = row.equipment_id !== null && row.equipment_id !== undefined;

                        return (
                            <Space key={index} align="start" style={{ display: 'flex', marginBottom: 16 }} wrap>
                                <Form.Item label="Unit Code">
                                    <Select
                                        showSearch
                                        optionFilterProp="label"
                                        disabled={stale}
                                        style={{ width: 260 }}
                                        value={isPerUnit ? String(row.equipment_id) : DIVISION_VALUE}
                                        onChange={(v) => {
                                            const next = [...data.allocations];
                                            if (v === DIVISION_VALUE) {
                                                next[index] = {
                                                    ...next[index],
                                                    equipment_id: null,
                                                    unit_code_cache: null,
                                                };
                                            } else {
                                                const eq = equipmentById.get(Number(v));
                                                next[index] = {
                                                    ...next[index],
                                                    equipment_id: eq?.id ?? null,
                                                    unit_code_cache: eq?.unit_code ?? null,
                                                    plant_type_cache:
                                                        eq?.plant_type_mapped ?? next[index].plant_type_cache,
                                                };
                                            }
                                            setData('allocations', next);
                                        }}
                                    >
                                        <Select.Option value={DIVISION_VALUE} label="— Divisi (tanpa unit) —">
                                            — Divisi (tanpa unit) —
                                        </Select.Option>
                                        {equipmentGroups.map(([groupLabel, items]) => (
                                            <Select.OptGroup key={groupLabel} label={groupLabel}>
                                                {items.map((item) => (
                                                    <Select.Option
                                                        key={item.id}
                                                        value={String(item.id)}
                                                        label={equipmentOptionLabel(item)}
                                                    >
                                                        {equipmentOptionLabel(item)}
                                                    </Select.Option>
                                                ))}
                                            </Select.OptGroup>
                                        ))}
                                    </Select>
                                </Form.Item>
                                <Form.Item label="Tingkat">
                                    <Tag color={isPerUnit ? 'blue' : 'default'}>
                                        {isPerUnit ? 'Per unit' : 'Divisi'}
                                    </Tag>
                                </Form.Item>
                                <Form.Item label="Tipe Plant">
                                    <Select
                                        style={{ width: 120 }}
                                        value={row.plant_type_cache ?? undefined}
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
                        );
                    })}

                    <Button
                        type="dashed"
                        icon={<PlusOutlined />}
                        onClick={() =>
                            setData('allocations', [
                                ...data.allocations,
                                {
                                    equipment_id: null,
                                    unit_code_cache: null,
                                    allocated_amount: 0,
                                    tolerance_pct: 10,
                                    plant_type_cache: 'DIGGER',
                                },
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
