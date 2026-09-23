import { useEffect } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Alert, Button, Card, DatePicker, Form, Input, InputNumber, Select, Typography } from 'antd';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout';

interface ProjectOption {
    project_code: string;
    project_name: string;
}

interface ExistingAllocation {
    allocated_amount: string;
    tolerance_pct: string;
    memo: string | null;
}

interface BudgetSettingProps {
    projects: ProjectOption[];
    defaultProjectCode?: string | null;
    defaultPeriodMonth: string;
    existingAllocation: ExistingAllocation | null;
}

export default function BudgetSetting({
    projects,
    defaultProjectCode,
    defaultPeriodMonth,
    existingAllocation,
}: BudgetSettingProps) {
    const { data, setData, post, processing, errors } = useForm({
        project_code: defaultProjectCode ?? projects[0]?.project_code ?? '',
        period_month: defaultPeriodMonth,
        status: 'open',
        allocated_amount: existingAllocation
            ? parseFloat(existingAllocation.allocated_amount)
            : 0,
        tolerance_pct: existingAllocation ? parseFloat(existingAllocation.tolerance_pct) : 10,
        memo: existingAllocation?.memo ?? '',
    });

    useEffect(() => {
        setData({
            project_code: defaultProjectCode ?? projects[0]?.project_code ?? '',
            period_month: defaultPeriodMonth,
            status: 'open',
            allocated_amount: existingAllocation
                ? parseFloat(existingAllocation.allocated_amount)
                : 0,
            tolerance_pct: existingAllocation ? parseFloat(existingAllocation.tolerance_pct) : 10,
            memo: existingAllocation?.memo ?? '',
        });
    }, [defaultProjectCode, defaultPeriodMonth, existingAllocation]);

    const isRevision = existingAllocation !== null;

    const submit = () => {
        post('/budget');
    };

    const reloadContext = (projectCode: string, periodMonth: string) => {
        router.get(
            '/budget/setting',
            { project_code: projectCode, period_month: periodMonth },
            { preserveState: true, replace: true },
        );
    };

    const changeProject = (projectCode: string) => {
        setData('project_code', projectCode);
        reloadContext(projectCode, data.period_month);
    };

    const changePeriodMonth = (value: dayjs.Dayjs | null) => {
        const periodMonth = value ? value.startOf('month').format('YYYY-MM-DD') : '';
        setData('period_month', periodMonth);
        if (periodMonth) {
            reloadContext(data.project_code, periodMonth);
        }
    };

    return (
        <AppLayout title="Set Budget Ceiling">
            <Head title="Set Budget Ceiling" />
            <Card>
                <Typography.Paragraph type="secondary" style={{ marginBottom: 16 }}>
                    Set the global project ceiling for the selected period month. Budget is not split per
                    equipment unit.
                </Typography.Paragraph>

                {isRevision && (
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message="This period already has a ceiling. Saving will revise that period (not add a new row)."
                    />
                )}

                <Form layout="vertical" onFinish={submit}>
                    <Form.Item
                        label="Project"
                        validateStatus={errors.project_code ? 'error' : undefined}
                        help={errors.project_code}
                    >
                        <Select
                            value={data.project_code}
                            onChange={changeProject}
                            options={projects.map((p) => ({
                                value: p.project_code,
                                label: `${p.project_code} — ${p.project_name}`,
                            }))}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Period Month"
                        validateStatus={errors.period_month ? 'error' : undefined}
                        help={errors.period_month}
                    >
                        <DatePicker
                            picker="month"
                            style={{ width: '100%' }}
                            value={data.period_month ? dayjs(data.period_month) : null}
                            onChange={changePeriodMonth}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Total Project Budget (IDR)"
                        validateStatus={errors.allocated_amount ? 'error' : undefined}
                        help={errors.allocated_amount}
                        required
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.allocated_amount}
                            formatter={(v) => `${v}`.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}
                            parser={(v) => Number(v?.replace(/\./g, '') ?? 0)}
                            onChange={(v) => setData('allocated_amount', Number(v ?? 0))}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Tolerance (%)"
                        validateStatus={errors.tolerance_pct ? 'error' : undefined}
                        help={errors.tolerance_pct}
                        required
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            max={100}
                            value={data.tolerance_pct}
                            onChange={(v) => setData('tolerance_pct', Number(v ?? 10))}
                        />
                    </Form.Item>

                    <Form.Item
                        label="Notes (optional)"
                        validateStatus={errors.memo ? 'error' : undefined}
                        help={errors.memo}
                    >
                        <Input.TextArea
                            rows={3}
                            value={data.memo}
                            onChange={(e) => setData('memo', e.target.value)}
                            maxLength={500}
                        />
                    </Form.Item>

                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={processing}>
                            {isRevision ? 'Save Ceiling Revision' : 'Save Budget Ceiling'}
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </AppLayout>
    );
}
