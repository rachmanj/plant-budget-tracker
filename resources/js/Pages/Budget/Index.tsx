import { Head, router, useForm } from '@inertiajs/react';
import { Button, Card, InputNumber, Select, Space, Table, Tabs, Tag, Typography, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import BudgetProgressBar from '@/Components/BudgetProgressBar';
import { formatIdr } from '@/hooks/useCurrency';

interface AllocationRow {
    id: number;
    equipment_id: number | null;
    unit_code_cache: string | null;
    plant_type_cache: string | null;
    allocated_amount: string;
    tolerance_pct: string;
    carry_forward_in: string;
    committed_amount: string;
    actual_amount: string;
    is_editable: boolean;
    variance: string;
    utilization_pct: string;
    tolerance_cap: string;
}

interface PeriodRow {
    id: number;
    project_code: string;
    project_name_cache: string;
    period_month: string;
    status: string;
    is_editable: boolean;
    is_locked: boolean;
    allocations: AllocationRow[];
}

interface ProjectOption {
    project_code: string;
    project_name: string;
}

interface BudgetIndexProps {
    projectCode: string;
    projects: ProjectOption[];
    periods: PeriodRow[];
    canManage: boolean;
    isFinanceDirector: boolean;
}

const statusColors: Record<string, string> = {
    draft: 'default',
    open: 'processing',
    locked: 'warning',
    closed: 'error',
};

export default function BudgetIndex({
    projectCode,
    projects,
    periods,
    canManage,
    isFinanceDirector,
}: BudgetIndexProps) {
    const [activeMonth, setActiveMonth] = useState(
        () => periods.find((p) => p.status === 'open')?.period_month ?? periods[0]?.period_month ?? ''
    );
    const [editingId, setEditingId] = useState<number | null>(null);
    const { data, setData, patch, processing } = useForm({
        allocated_amount: '0',
        tolerance_pct: '10',
        memo: '',
    });

    const activePeriod = periods.find((p) => p.period_month === activeMonth);

    const handleProjectChange = (code: string) => {
        router.get('/budget', { project_code: code }, { preserveState: true });
    };

    const startEdit = (row: AllocationRow) => {
        setEditingId(row.id);
        setData({
            allocated_amount: row.allocated_amount,
            tolerance_pct: row.tolerance_pct,
            memo: '',
        });
    };

    const submitRevise = (allocationId: number) => {
        patch(`/budget/allocations/${allocationId}`, {
            onSuccess: () => {
                message.success('Alokasi direvisi');
                setEditingId(null);
            },
            onError: () => message.error('Gagal merevisi alokasi'),
        });
    };

    const columns: ColumnsType<AllocationRow> = useMemo(() => {
        const base: ColumnsType<AllocationRow> = [
            {
                title: 'Unit',
                dataIndex: 'unit_code_cache',
                render: (value, row) => value ?? (row.equipment_id ? `EQ-${row.equipment_id}` : 'Divisi'),
            },
            {
                title: 'Tipe',
                dataIndex: 'plant_type_cache',
                render: (value) => value ?? '—',
            },
            {
                title: 'Alokasi',
                dataIndex: 'allocated_amount',
                align: 'right',
                render: (value, row) =>
                    isFinanceDirector && row.is_editable && editingId === row.id ? (
                        <InputNumber
                            style={{ width: 160 }}
                            value={parseFloat(data.allocated_amount)}
                            formatter={(v) => `${v}`.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}
                            parser={(v) => Number(v?.replace(/\./g, '') ?? 0)}
                            onChange={(v) => setData('allocated_amount', String(v ?? 0))}
                        />
                    ) : (
                        formatIdr(value)
                    ),
            },
            {
                title: 'Carry Fwd',
                dataIndex: 'carry_forward_in',
                align: 'right',
                render: (value) => formatIdr(value),
            },
            {
                title: 'Komitmen',
                dataIndex: 'committed_amount',
                align: 'right',
                render: (value) => formatIdr(value),
            },
            {
                title: 'Aktual',
                dataIndex: 'actual_amount',
                align: 'right',
                render: (value) => formatIdr(value),
            },
            {
                title: 'Varians',
                dataIndex: 'variance',
                align: 'right',
                render: (value) => (
                    <Typography.Text type={parseFloat(value) < 0 ? 'danger' : undefined}>
                        {formatIdr(value)}
                    </Typography.Text>
                ),
            },
        ];

        if (isFinanceDirector) {
            base.push({
                title: 'Toleransi %',
                dataIndex: 'tolerance_pct',
                render: (value, row) =>
                    row.is_editable && editingId === row.id ? (
                        <InputNumber
                            min={0}
                            max={100}
                            value={parseFloat(data.tolerance_pct)}
                            onChange={(v) => setData('tolerance_pct', String(v ?? 10))}
                        />
                    ) : (
                        `${value}%`
                    ),
            });
        }

        if (!isFinanceDirector) {
            base.push({
                title: 'Penggunaan',
                key: 'utilization',
                width: 200,
                render: (_, row) => (
                    <BudgetProgressBar
                        utilizationPct={row.utilization_pct}
                        committed={row.committed_amount}
                        actual={row.actual_amount}
                        cap={row.tolerance_cap}
                    />
                ),
            });
        }

        if (isFinanceDirector && activePeriod?.is_editable) {
            base.push({
                title: 'Aksi',
                key: 'actions',
                render: (_, row) =>
                    row.is_editable ? (
                        editingId === row.id ? (
                            <Space>
                                <Button
                                    type="primary"
                                    size="small"
                                    loading={processing}
                                    onClick={() => submitRevise(row.id)}
                                >
                                    Simpan
                                </Button>
                                <Button size="small" onClick={() => setEditingId(null)}>
                                    Batal
                                </Button>
                            </Space>
                        ) : (
                            <Button size="small" onClick={() => startEdit(row)}>
                                Revisi
                            </Button>
                        )
                    ) : null,
            });
        }

        return base;
    }, [activePeriod, data, editingId, isFinanceDirector, processing, setData, patch]);

    const tabItems = periods.map((period) => ({
        key: period.period_month,
        label: (
            <span style={{ opacity: period.is_locked ? 0.5 : 1 }}>
                {period.period_month.slice(0, 7)}
                <Tag color={statusColors[period.status]} style={{ marginLeft: 8 }}>
                    {period.status}
                </Tag>
            </span>
        ),
        children: (
            <Card
                style={{ opacity: period.is_locked ? 0.75 : 1 }}
                extra={
                    canManage && period.status === 'open' ? (
                        <Button
                            size="small"
                            onClick={() =>
                                router.post(`/budget/${period.id}/carry-forward`, {}, { preserveScroll: true })
                            }
                        >
                            Jalankan Carry Forward
                        </Button>
                    ) : null
                }
            >
                <Table
                    rowKey="id"
                    columns={columns}
                    dataSource={period.allocations}
                    pagination={false}
                    size="small"
                    locale={{ emptyText: 'Belum ada alokasi untuk periode ini.' }}
                />
            </Card>
        ),
    }));

    return (
        <AppLayout title="Anggaran">
            <Head title="Anggaran" />
            <Space direction="vertical" size="large" style={{ width: '100%' }}>
                <Space wrap>
                    <Typography.Text>Proyek:</Typography.Text>
                    <Select
                        style={{ minWidth: 220 }}
                        value={projectCode}
                        onChange={handleProjectChange}
                        options={projects.map((p) => ({
                            value: p.project_code,
                            label: `${p.project_code} — ${p.project_name}`,
                        }))}
                    />
                    {canManage && (
                        <Button type="primary" href="/budget/setting">
                            Buat Alokasi
                        </Button>
                    )}
                </Space>

                {periods.length === 0 ? (
                    <Card>
                        <Typography.Text type="secondary">
                            Belum ada periode anggaran untuk proyek ini.
                            {canManage && ' Gunakan "Buat Alokasi" untuk memulai.'}
                        </Typography.Text>
                    </Card>
                ) : (
                    <Tabs
                        activeKey={activeMonth}
                        onChange={setActiveMonth}
                        items={tabItems}
                        type="card"
                    />
                )}
            </Space>
        </AppLayout>
    );
}
