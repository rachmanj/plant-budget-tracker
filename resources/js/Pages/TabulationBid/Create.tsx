import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Form, Input, InputNumber, Select, Space, Typography } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

type StockAvailability = 'ready' | 'indent' | 'partial';

interface VendorRow {
    vendor_code: string;
    vendor_name: string;
    price: number;
    stock_availability: StockAvailability;
    payment_terms: string;
    remarks: string;
}

const emptyVendor = (): VendorRow => ({
    vendor_code: '',
    vendor_name: '',
    price: 0,
    stock_availability: 'ready',
    payment_terms: '',
    remarks: '',
});

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        sap_pr_id: '',
        vendors: [emptyVendor(), emptyVendor()] as VendorRow[],
    });

    const updateVendor = (index: number, patch: Partial<VendorRow>) => {
        const vendors = [...data.vendors];
        vendors[index] = { ...vendors[index], ...patch };
        setData('vendors', vendors);
    };

    const addVendor = () => {
        if (data.vendors.length >= 3) {
            return;
        }
        setData('vendors', [...data.vendors, emptyVendor()]);
    };

    const removeVendor = (index: number) => {
        if (data.vendors.length <= 2) {
            return;
        }
        setData('vendors', data.vendors.filter((_, i) => i !== index));
    };

    const vendorFieldError = (index: number, field: keyof VendorRow): string | undefined => {
        const key = `vendors.${index}.${field}` as keyof typeof errors;
        return errors[key];
    };

    return (
        <AppLayout title="Buat Tabulation Bid">
            <Head title="Buat Tabulation Bid" />
            <Card title="Tabulation Bid">
                <Form layout="vertical" onFinish={() => post('/tabulation-bids')}>
                    <Form.Item
                        label="SAP PR ID"
                        required
                        validateStatus={errors.sap_pr_id ? 'error' : undefined}
                        help={errors.sap_pr_id}
                    >
                        <Input value={data.sap_pr_id} onChange={(e) => setData('sap_pr_id', e.target.value)} />
                    </Form.Item>
                    {errors.vendors && (
                        <Typography.Text type="danger" style={{ display: 'block', marginBottom: 8 }}>
                            {errors.vendors}
                        </Typography.Text>
                    )}
                    {data.vendors.map((v, i) => (
                        <Card
                            key={i}
                            size="small"
                            title={`Vendor ${i + 1}`}
                            style={{ marginBottom: 12 }}
                            extra={
                                data.vendors.length > 2 ? (
                                    <Button type="link" danger onClick={() => removeVendor(i)}>
                                        Hapus
                                    </Button>
                                ) : null
                            }
                        >
                            <Space direction="vertical" style={{ width: '100%' }}>
                                <Form.Item
                                    label="Kode Vendor"
                                    required
                                    validateStatus={vendorFieldError(i, 'vendor_code') ? 'error' : undefined}
                                    help={vendorFieldError(i, 'vendor_code')}
                                >
                                    <Input
                                        value={v.vendor_code}
                                        onChange={(e) => updateVendor(i, { vendor_code: e.target.value })}
                                    />
                                </Form.Item>
                                <Form.Item
                                    label="Nama Vendor"
                                    required
                                    validateStatus={vendorFieldError(i, 'vendor_name') ? 'error' : undefined}
                                    help={vendorFieldError(i, 'vendor_name')}
                                >
                                    <Input
                                        value={v.vendor_name}
                                        onChange={(e) => updateVendor(i, { vendor_name: e.target.value })}
                                    />
                                </Form.Item>
                                <Form.Item
                                    label="Harga"
                                    required
                                    validateStatus={vendorFieldError(i, 'price') ? 'error' : undefined}
                                    help={vendorFieldError(i, 'price')}
                                >
                                    <InputNumber
                                        style={{ width: '100%' }}
                                        value={v.price}
                                        min={0}
                                        onChange={(val) => updateVendor(i, { price: val ?? 0 })}
                                    />
                                </Form.Item>
                                <Form.Item
                                    label="Ketersediaan Stok"
                                    required
                                    validateStatus={vendorFieldError(i, 'stock_availability') ? 'error' : undefined}
                                    help={vendorFieldError(i, 'stock_availability')}
                                >
                                    <Select
                                        value={v.stock_availability}
                                        onChange={(val: StockAvailability) =>
                                            updateVendor(i, { stock_availability: val })
                                        }
                                        options={[
                                            { value: 'ready', label: 'Ready' },
                                            { value: 'indent', label: 'Indent' },
                                            { value: 'partial', label: 'Partial' },
                                        ]}
                                    />
                                </Form.Item>
                                <Form.Item
                                    label="Syarat Pembayaran"
                                    validateStatus={vendorFieldError(i, 'payment_terms') ? 'error' : undefined}
                                    help={vendorFieldError(i, 'payment_terms')}
                                >
                                    <Input
                                        value={v.payment_terms}
                                        onChange={(e) => updateVendor(i, { payment_terms: e.target.value })}
                                    />
                                </Form.Item>
                                <Form.Item
                                    label="Catatan"
                                    validateStatus={vendorFieldError(i, 'remarks') ? 'error' : undefined}
                                    help={vendorFieldError(i, 'remarks')}
                                >
                                    <Input.TextArea
                                        rows={2}
                                        value={v.remarks}
                                        onChange={(e) => updateVendor(i, { remarks: e.target.value })}
                                    />
                                </Form.Item>
                            </Space>
                        </Card>
                    ))}
                    {data.vendors.length < 3 && (
                        <Button type="dashed" onClick={addVendor} style={{ marginBottom: 16 }}>
                            Tambah Vendor
                        </Button>
                    )}
                    <Button type="primary" htmlType="submit" loading={processing}>
                        Simpan
                    </Button>
                </Form>
            </Card>
        </AppLayout>
    );
}
