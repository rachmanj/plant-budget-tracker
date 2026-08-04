import { Head, useForm } from '@inertiajs/react';
import { Button, Card, Form, Input, Typography, Alert } from 'antd';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        post('/login');
    };

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                background: '#f5f5f5',
            }}
        >
            <Head title="Masuk" />
            <Card style={{ width: 400 }}>
                <Typography.Title level={3}>Plant Budget Tracker</Typography.Title>
                <Typography.Paragraph type="secondary">
                    Masuk ke sistem penganggaran plant
                </Typography.Paragraph>

                {errors.email && <Alert type="error" message={errors.email} style={{ marginBottom: 16 }} />}

                <Form layout="vertical" onFinish={submit}>
                    <Form.Item label="Email" required validateStatus={errors.email ? 'error' : ''}>
                        <Input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                        />
                    </Form.Item>
                    <Form.Item label="Kata Sandi" required>
                        <Input.Password
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                        />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" block loading={processing}>
                        Masuk
                    </Button>
                </Form>
            </Card>
        </div>
    );
}
