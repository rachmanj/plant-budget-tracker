import { Head, Link, router } from '@inertiajs/react';
import { Button, Card, Select, Table, Tag } from 'antd';
import AppLayout from '@/Layouts/AppLayout';
import LifecycleStepper from '@/Components/LifecycleStepper';

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
    { value: '', label: 'Semua' },
    { value: 'draft', label: 'Draf' },
    { value: 'pending_pm', label: 'Menunggu Approval' },
    { value: 'pending_plant_mgr', label: 'Menunggu Approval' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'pr_created', label: 'PR Dibuat' },
    { value: 'po_created', label: 'PO Dibuat' },
    { value: 'received', label: 'Diterima' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'cancelled', label: 'Dibatalkan' },
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
            render: (status: string) => <Tag>{status}</Tag>,
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
                        <Button type="primary">Buat Request</Button>
                    </Link>
                }
            >
                <Select
                    style={{ width: 240, marginBottom: 16 }}
                    value={filters.status ?? ''}
                    options={STATUS_OPTIONS}
                    onChange={handleStatusChange}
                    placeholder="Filter Status"
                />
                <Table rowKey="id" columns={columns} dataSource={requests.data} pagination={false} />
            </Card>
        </AppLayout>
    );
}
