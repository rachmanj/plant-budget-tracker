import { Head, router } from '@inertiajs/react';
import { Button, Card, Descriptions, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import LifecycleStepper from '@/Components/LifecycleStepper';
import BudgetProgressBar from '@/Components/BudgetProgressBar';

interface Props {
    request: {
        id: number;
        request_no: string;
        status: string;
        unit_code_cache: string;
        estimated_total: string;
        sap_mr_id: number;
        sap_pr_no?: string;
        lines: Array<{ part_number: string; material_name: string; qty: number; line_total: string }>;
        approvals: Array<{ step_order: number; required_role: string; decision: string }>;
    };
    tolerance: { projected_pct: string; within_tolerance: boolean; cap: string };
}

export default function Show({ request, tolerance }: Props) {
    return (
        <AppLayout title={request.request_no}>
            <Head title={request.request_no} />
            <Card title={request.request_no}>
                <LifecycleStepper status={request.status} sapPrNo={request.sap_pr_no} />
                <Descriptions style={{ marginTop: 16 }} column={2}>
                    <Descriptions.Item label="Unit">{request.unit_code_cache}</Descriptions.Item>
                    <Descriptions.Item label="Status">
                        <Tag>{request.status}</Tag>
                    </Descriptions.Item>
                    <Descriptions.Item label="SAP MR">{request.sap_mr_id}</Descriptions.Item>
                    <Descriptions.Item label="Total Est.">{request.estimated_total}</Descriptions.Item>
                </Descriptions>
                <BudgetProgressBar
                    utilizationPct={parseFloat(tolerance.projected_pct)}
                    capPct={110}
                />
                <Table
                    style={{ marginTop: 16 }}
                    rowKey="part_number"
                    dataSource={request.lines}
                    columns={[
                        { title: 'P/N', dataIndex: 'part_number' },
                        { title: 'Material', dataIndex: 'material_name' },
                        { title: 'Qty', dataIndex: 'qty' },
                        { title: 'Total', dataIndex: 'line_total' },
                    ]}
                    pagination={false}
                />
                {request.status === 'draft' && (
                    <Button
                        type="primary"
                        style={{ marginTop: 16 }}
                        onClick={() => router.post(`/plant-requests/${request.id}/submit`)}
                    >
                        Submit
                    </Button>
                )}
            </Card>
        </AppLayout>
    );
}
