import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Form, Input, InputNumber, Select, Space } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import BudgetProgressBar from '@/Components/BudgetProgressBar';

interface Prefill {
    dmbd_entry_id?: number;
    equipment_id?: number;
    unit_code_cache?: string;
}

interface Props {
    prefill?: Prefill;
}

export default function Create({ prefill = {} }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        budget_allocation_id: 1,
        equipment_id: prefill.equipment_id ?? 0,
        unit_code_cache: prefill.unit_code_cache ?? '',
        dmbd_entry_id: prefill.dmbd_entry_id ?? null,
        sap_mr_id: 0,
        lines: [{ part_number: '', material_name: '', uom: 'EA', qty: 1, unit_price_est: '0' }],
    });

    return (
        <AppLayout title="Buat Plant Request">
            <Head title="Buat Plant Request" />
            <Card title="Wizard Plant Request">
                <Form layout="vertical" onFinish={() => post('/plant-requests')}>
                    <Form.Item label="Equipment ID" required>
                        <InputNumber
                            style={{ width: '100%' }}
                            value={data.equipment_id}
                            onChange={(v) => setData('equipment_id', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Unit Code" required>
                        <Input
                            value={data.unit_code_cache}
                            onChange={(e) => setData('unit_code_cache', e.target.value)}
                        />
                    </Form.Item>
                    <Form.Item label="SAP MR ID" required>
                        <InputNumber
                            style={{ width: '100%' }}
                            value={data.sap_mr_id}
                            onChange={(v) => setData('sap_mr_id', v ?? 0)}
                        />
                    </Form.Item>
                    <Form.Item label="Part Number">
                        <Input
                            value={data.lines[0].part_number}
                            onChange={(e) => {
                                const lines = [...data.lines];
                                lines[0] = { ...lines[0], part_number: e.target.value };
                                setData('lines', lines);
                            }}
                        />
                    </Form.Item>
                    <Form.Item label="Material Name">
                        <Input
                            value={data.lines[0].material_name}
                            onChange={(e) => {
                                const lines = [...data.lines];
                                lines[0] = { ...lines[0], material_name: e.target.value };
                                setData('lines', lines);
                            }}
                        />
                    </Form.Item>
                    <BudgetProgressBar utilizationPct={0} capPct={110} />
                    <Space style={{ marginTop: 16 }}>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            Simpan Draft
                        </Button>
                    </Space>
                    {errors.budget_allocation_id && <div>{errors.budget_allocation_id}</div>}
                </Form>
            </Card>
        </AppLayout>
    );
}
