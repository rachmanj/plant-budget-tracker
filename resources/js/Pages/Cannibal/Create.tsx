import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Form, Input, InputNumber } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function Create() {
    const { data, setData, post, processing } = useForm({
        source_equipment_id: 0,
        target_equipment_id: 0,
        dmbd_entry_id: 0,
        reason: '',
    });

    return (
        <AppLayout title="Buat Cannibal Request">
            <Head title="Cannibal Request" />
            <Card title="Cannibal Request">
                <Form layout="vertical" onFinish={() => post('/cannibal-requests')}>
                    <Form.Item label="Source Equipment ID"><InputNumber style={{ width: '100%' }} value={data.source_equipment_id} onChange={(v) => setData('source_equipment_id', v ?? 0)} /></Form.Item>
                    <Form.Item label="Target Equipment ID"><InputNumber style={{ width: '100%' }} value={data.target_equipment_id} onChange={(v) => setData('target_equipment_id', v ?? 0)} /></Form.Item>
                    <Form.Item label="DMBD Entry ID"><InputNumber style={{ width: '100%' }} value={data.dmbd_entry_id} onChange={(v) => setData('dmbd_entry_id', v ?? 0)} /></Form.Item>
                    <Form.Item label="Reason"><Input.TextArea value={data.reason} onChange={(e) => setData('reason', e.target.value)} /></Form.Item>
                    <Button type="primary" htmlType="submit" loading={processing}>Submit</Button>
                </Form>
            </Card>
        </AppLayout>
    );
}
