import { Head, Link, router, useForm } from '@inertiajs/react';
import { Alert, Button, Card, Form, InputNumber, Typography } from 'antd';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr } from '@/hooks/useCurrency';

interface LastUpdated {
    name: string | null;
    at: string | null;
}

interface Props {
    threshold: string;
    lastUpdated: LastUpdated | null;
}

export default function Settings({ threshold, lastUpdated }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        po_director_threshold_idr: parseFloat(threshold),
    });

    const submit = () => {
        post('/procurement/settings');
    };

    return (
        <AppLayout title="Procurement Settings">
            <Head title="Procurement Settings" />
            <Card title="PO President Director threshold">
                <Typography.Paragraph type="secondary">
                    Purchase orders from tabulation bids at or below this amount require only Procurement Manager
                    approval. Amounts above this threshold also require President Director approval before SAP PO
                    creation.
                </Typography.Paragraph>

                <Alert
                    type="info"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message={`Current threshold: ${formatIdr(threshold)}`}
                    description={
                        lastUpdated?.at
                            ? `Last updated by ${lastUpdated.name ?? 'Unknown'} on ${dayjs(lastUpdated.at).format('D MMM YYYY HH:mm')}`
                            : 'Default threshold applies until changed.'
                    }
                />

                <Form layout="vertical" onFinish={submit} style={{ maxWidth: 420 }}>
                    <Form.Item
                        label="Threshold (IDR)"
                        validateStatus={errors.po_director_threshold_idr ? 'error' : undefined}
                        help={errors.po_director_threshold_idr}
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.po_director_threshold_idr}
                            onChange={(value) =>
                                setData('po_director_threshold_idr', typeof value === 'number' ? value : 0)
                            }
                        />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" loading={processing}>
                        Save settings
                    </Button>
                </Form>
            </Card>
        </AppLayout>
    );
}
