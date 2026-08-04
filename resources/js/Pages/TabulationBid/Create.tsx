import { Head, useForm, Link } from '@inertiajs/react';
import { Button, Card, Form, Input, InputNumber, Select, Space } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

export default function Create() {
    const { data, setData, post, processing } = useForm({
        sap_pr_id: '',
        vendors: [
            { vendor_code: '', vendor_name: '', price: 0, stock_availability: 'ready' },
            { vendor_code: '', vendor_name: '', price: 0, stock_availability: 'ready' },
        ],
    });

    return (
        <AppLayout title="Buat Tabulation Bid">
            <Head title="Buat Tabulation Bid" />
            <Card title="Tabulation Bid">
                <Form layout="vertical" onFinish={() => post('/tabulation-bids')}>
                    <Form.Item label="SAP PR ID" required>
                        <Input value={data.sap_pr_id} onChange={(e) => setData('sap_pr_id', e.target.value)} />
                    </Form.Item>
                    {data.vendors.map((v, i) => (
                        <Space key={i} style={{ display: 'flex', marginBottom: 8 }}>
                            <Input placeholder="Vendor Code" value={v.vendor_code} onChange={(e) => {
                                const vendors = [...data.vendors];
                                vendors[i] = { ...v, vendor_code: e.target.value };
                                setData('vendors', vendors);
                            }} />
                            <Input placeholder="Vendor Name" value={v.vendor_name} onChange={(e) => {
                                const vendors = [...data.vendors];
                                vendors[i] = { ...v, vendor_name: e.target.value };
                                setData('vendors', vendors);
                            }} />
                            <InputNumber placeholder="Price" value={v.price} onChange={(val) => {
                                const vendors = [...data.vendors];
                                vendors[i] = { ...v, price: val ?? 0 };
                                setData('vendors', vendors);
                            }} />
                        </Space>
                    ))}
                    <Button type="primary" htmlType="submit" loading={processing}>Simpan</Button>
                </Form>
            </Card>
        </AppLayout>
    );
}
