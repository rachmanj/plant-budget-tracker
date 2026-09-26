import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Card, Form, Input, Typography, Alert, theme, Tag } from 'antd';
import dayjs from 'dayjs';

type TrialUpdateType = 'feature' | 'improvement' | 'fix';

interface TrialUpdateEntry {
    date: string;
    type: TrialUpdateType;
    title: string;
    summary: string;
}

interface TrialUpdatesPayload {
    note: string;
    updates: TrialUpdateEntry[];
}

interface LoginPageProps {
    appName: string;
    trialUpdates: TrialUpdatesPayload;
}

const TYPE_LABELS: Record<TrialUpdateType, string> = {
    feature: 'Feature',
    improvement: 'Improvement',
    fix: 'Fix',
};

function updateTypeColor(type: TrialUpdateType, token: ReturnType<typeof theme.useToken>['token']): string {
    switch (type) {
        case 'feature':
            return token.colorPrimary;
        case 'improvement':
            return token.colorSuccess;
        case 'fix':
            return token.colorWarning;
        default:
            return token.colorTextSecondary;
    }
}

function formatUpdateDate(isoDate: string): string {
    const parsed = dayjs(isoDate);
    if (!parsed.isValid()) {
        return isoDate;
    }

    return parsed.format('D MMM YYYY');
}

export default function Login() {
    const { token } = theme.useToken();
    const { appName, trialUpdates } = usePage<LoginPageProps>().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        post('/login');
    };

    const updates = trialUpdates?.updates ?? [];
    const showTrialPanel = updates.length > 0;
    const trialNote = trialUpdates?.note ?? '';

    return (
        <div
            style={{
                minHeight: '100vh',
                display: 'flex',
                background: token.colorBgLayout,
            }}
        >
            <Head title="Sign In" />

            {showTrialPanel && (
                <aside
                    style={{
                        display: 'none',
                        width: '50%',
                        maxWidth: 560,
                        flexShrink: 0,
                        minHeight: '100vh',
                        maxHeight: '100vh',
                        overflow: 'hidden',
                        borderRight: `1px solid ${token.colorBorderSecondary}`,
                        background: token.colorBgContainer,
                        flexDirection: 'column',
                        padding: '48px 40px 24px',
                        boxSizing: 'border-box',
                    }}
                    className="login-trial-panel"
                >
                    <Typography.Title level={3} style={{ marginTop: 0, marginBottom: 4 }}>
                        What&apos;s new
                    </Typography.Title>
                    <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 24 }}>
                        Updates released during the trial
                    </Typography.Text>

                    <div
                        style={{
                            flex: 1,
                            minHeight: 0,
                            overflowY: 'auto',
                            paddingRight: 8,
                            marginBottom: 16,
                        }}
                    >
                        {updates.map((entry, index) => {
                            const type = (entry.type ?? 'feature') as TrialUpdateType;
                            const label = TYPE_LABELS[type] ?? entry.type;

                            return (
                                <div
                                    key={`${entry.date}-${entry.title}-${index}`}
                                    style={{
                                        marginBottom: 20,
                                        paddingBottom: 20,
                                        borderBottom:
                                            index < updates.length - 1
                                                ? `1px solid ${token.colorBorderSecondary}`
                                                : undefined,
                                    }}
                                >
                                    <div style={{ marginBottom: 8 }}>
                                        <Tag
                                            style={{
                                                marginInlineEnd: 8,
                                                borderColor: updateTypeColor(type, token),
                                                color: updateTypeColor(type, token),
                                                background: `${updateTypeColor(type, token)}14`,
                                            }}
                                        >
                                            {label}
                                        </Tag>
                                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                            {formatUpdateDate(entry.date)}
                                        </Typography.Text>
                                    </div>
                                    <Typography.Text strong style={{ display: 'block', marginBottom: 6 }}>
                                        {entry.title}
                                    </Typography.Text>
                                    <Typography.Paragraph
                                        type="secondary"
                                        style={{ marginBottom: 0, fontSize: 13, lineHeight: 1.5 }}
                                    >
                                        {entry.summary}
                                    </Typography.Paragraph>
                                </div>
                            );
                        })}
                    </div>

                    {trialNote ? (
                        <Alert type="info" message={trialNote} showIcon style={{ flexShrink: 0 }} />
                    ) : null}
                </aside>
            )}

            <div
                style={{
                    flex: 1,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: 24,
                }}
            >
                <Card style={{ width: '100%', maxWidth: 400 }}>
                    <Typography.Title level={3}>{appName}</Typography.Title>
                    <Typography.Paragraph type="secondary">
                        Sign in to the plant budgeting system
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
                        <Form.Item label="Password" required>
                            <Input.Password
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="current-password"
                            />
                        </Form.Item>
                        <Button type="primary" htmlType="submit" block loading={processing}>
                            Sign In
                        </Button>
                    </Form>

                    {showTrialPanel && (
                        <Typography.Paragraph
                            type="secondary"
                            style={{ marginTop: 16, marginBottom: 0, textAlign: 'center', fontSize: 13 }}
                            className="login-trial-mobile-summary"
                        >
                            {updates.length} trial update{updates.length === 1 ? '' : 's'} (visible on larger
                            screens)
                        </Typography.Paragraph>
                    )}
                </Card>
            </div>

            <style>{`
                @media (min-width: 992px) {
                    .login-trial-panel {
                        display: flex !important;
                    }
                    .login-trial-mobile-summary {
                        display: none !important;
                    }
                }
            `}</style>
        </div>
    );
}
