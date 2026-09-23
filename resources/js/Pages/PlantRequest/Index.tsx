import { Head, Link, router } from '@inertiajs/react';
import { Button, Card, Select, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import LifecycleStepper from '@/Components/LifecycleStepper';
import { statusLabel } from '@/utils/labels';

interface PlantRequestRow {
    id: number;
    request_no: string;
    status: string;
    unit_code_cache: string;
    estimated_total: string;
    sap_pr_no?: string | null;
    sap_po_id?: string | null;
    sap_grpo_no?: string | null;
}

interface Props {
    requests: { data: PlantRequestRow[] };
    filters: { status?: string };
}

const STATUS_OPTIONS = [
    { value: '', label: 'All' },
    { value: 'draft', label: statusLabel('draft') },
    { value: 'pending_pm', label: statusLabel('pending_pm') },
    { value: 'pending_plant_mgr', label: statusLabel('pending_plant_mgr') },
    { value: 'approved', label: statusLabel('approved') },
    { value: 'pr_created', label: statusLabel('pr_created') },
    { value: 'po_created', label: statusLabel('po_created') },
    { value: 'received', label: statusLabel('received') },
    { value: 'rejected', label: statusLabel('rejected') },
    { value: 'cancelled', label: statusLabel('cancelled') },
];

export default function Index({ requests, filters }: Props) {
    const handleStatusChange = (status: string) => {
        router.get('/plant-requests', status ? { status } : {}, {
            preserveState: true,
            replace: true,
        });
    };

    const columns = [
        { title: 'No. Request', dataIndex: 'request_no', key: 'request_no' },
        { title: 'Unit', dataIndex: 'unit_code_cache', key: 'unit_code_cache' },
        { title: 'Total Est.', dataIndex: 'estimated_total', key: 'estimated_total' },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (status: string) => <Tag>{statusLabel(status)}</Tag>,
        },
        {
            title: 'No. PR',
            dataIndex: 'sap_pr_no',
            key: 'sap_pr_no',
            render: (value?: string | null) => value ?? '—',
        },
        {
            title: 'No. PO',
            dataIndex: 'sap_po_id',
            key: 'sap_po_id',
            render: (value?: string | null) => value ?? '—',
        },
        {
            title: 'No. GRPO',
            dataIndex: 'sap_grpo_no',
            key: 'sap_grpo_no',
            render: (value?: string | null) => value ?? '—',
        },
        {
            title: 'Lifecycle',
            key: 'lifecycle',
            render: (_: unknown, row: PlantRequestRow) => <LifecycleStepper status={row.status} />,
        },
        {
            title: 'Action',
            key: 'action',
            render: (_: unknown, row: PlantRequestRow) => (
                <Link href={`/plant-requests/${row.id}`}>Detail</Link>
            ),
        },
    ];

    return (
        <AppLayout title="Plant Requests">
            <Head title="Plant Requests" />
            <Card
                title="Plant Requests"
                extra={
                    <Link href="/plant-requests/create">
                        <Button type="primary">Create Request</Button>
                    </Link>
                }
            >
                <Select
                    style={{ width: 280, marginBottom: 16 }}
                    value={filters.status ?? ''}
                    options={STATUS_OPTIONS}
                    onChange={handleStatusChange}
                    placeholder="Filter status"
                />
                <Table rowKey="id" columns={columns} dataSource={requests.data} pagination={false} />
            </Card>
        </AppLayout>
    );
}
