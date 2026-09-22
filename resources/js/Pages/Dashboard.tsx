import { Head, Link, router, usePage } from '@inertiajs/react';
import { Alert, Card, Col, Progress, Row, Space, Statistic, Tag, Typography } from 'antd';
import type { CSSProperties, ReactNode } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdrCompact, utilizationColor } from '@/hooks/useCurrency';

interface Widget {
    key: string;
    title: string;
    description: string;
}

interface PeriodInfo {
    month: string;
    label: string;
    status: string;
    exists: boolean;
}

interface BudgetMetrics {
    allocated: string;
    used: string;
    remaining: string;
    usedPct: number;
    allocationCount: number;
    available: boolean;
}

interface RequestMetrics {
    draft: number;
    waitingApproval: number;
    approvedThisMonth: number;
    rejected: number;
    awaitingMyDecision: number;
}

interface ProcurementMetrics {
    bidsAwaitingReview: number;
    bidsAwaitingPo: number;
    poCreatedThisMonth: number;
    poValueThisMonth: string;
}

interface PendingMetrics {
    overbudget: number;
    cancellation: number;
    interchange: number;
}

interface DmbdMetrics {
    rfu: number;
    standby: number;
    breakdown: number;
    recorded: number;
    unitsActive: number | null;
    available: boolean;
}

interface Metrics {
    projectCode: string | null;
    period: PeriodInfo;
    budget: BudgetMetrics;
    requests: RequestMetrics;
    procurement: ProcurementMetrics;
    pending: PendingMetrics;
    dmbd: DmbdMetrics;
}

interface CanFlags {
    'budget.view': boolean;
    'plant_request.create': boolean;
    'dmbd.view': boolean;
    'tabulation_bid.view': boolean;
    'reports.view': boolean;
    'user.manage': boolean;
}

interface DashboardProps {
    widgets: Widget[];
    roleNames: string[];
    metrics: Metrics;
    projectCode: string | null;
    today: string;
    can: CanFlags;
}

interface AuthUser {
    name: string;
}

interface NavProps {
    isApprover: boolean;
}

interface PageProps {
    auth: { user: AuthUser | null };
    nav?: NavProps;
}

function getGreeting(): string {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Selamat pagi';
    }

    if (hour < 15) {
        return 'Selamat siang';
    }

    if (hour < 18) {
        return 'Selamat sore';
    }

    return 'Selamat malam';
}

function StatCard({
    title,
    value,
    suffix,
    href,
    footer,
    valueStyle,
}: {
    title: string;
    value: string | number;
    suffix?: string;
    href?: string;
    footer?: ReactNode;
    valueStyle?: CSSProperties;
}) {
    return (
        <Card
            hoverable={!!href}
            onClick={href ? () => router.visit(href) : undefined}
            style={{ height: '100%' }}
        >
            <Statistic title={title} value={value} suffix={suffix} valueStyle={valueStyle} />
            {footer}
        </Card>
    );
}

export default function Dashboard({ metrics, projectCode, today, can }: DashboardProps) {
    const { auth, nav } = usePage<PageProps>().props;
    const { period, budget, requests, procurement, pending, dmbd } = metrics;
    const displayUsedPct = Math.min(budget.usedPct, 150);
    const canSeeRequests = can['plant_request.create'] || !!nav?.isApprover;

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <Space direction="vertical" size={4} style={{ width: '100%', marginBottom: 24 }}>
                <Space wrap align="center" size={12}>
                    <Typography.Title level={3} style={{ margin: 0 }}>
                        {getGreeting()}, {auth.user?.name ?? ''}
                    </Typography.Title>
                    {projectCode && <Tag color="#0d9488">{projectCode}</Tag>}
                </Space>
                <Typography.Text type="secondary">{today}</Typography.Text>
            </Space>

            {can['budget.view'] && (
                <Card title={`Anggaran ${period.label}`} style={{ marginBottom: 24 }}>
                    {!period.exists ? (
                        <Alert
                            type="info"
                            showIcon
                            message="Belum ada anggaran untuk bulan ini"
                            action={<Link href="/budget">Buka Anggaran</Link>}
                        />
                    ) : (
                        <Row gutter={[16, 16]}>
                            <Col xs={24} sm={12} lg={6}>
                                <StatCard
                                    title="Pagu"
                                    value={formatIdrCompact(budget.allocated)}
                                    href="/budget"
                                />
                            </Col>
                            <Col xs={24} sm={12} lg={6}>
                                <StatCard
                                    title="Terpakai"
                                    value={formatIdrCompact(budget.used)}
                                    href="/budget"
                                />
                            </Col>
                            <Col xs={24} sm={12} lg={6}>
                                <StatCard
                                    title="Sisa"
                                    value={formatIdrCompact(budget.remaining)}
                                    href="/budget"
                                />
                            </Col>
                            <Col xs={24} sm={12} lg={6}>
                                <Card style={{ height: '100%' }}>
                                    <Statistic
                                        title="% Terpakai"
                                        value={budget.usedPct.toFixed(1)}
                                        suffix="%"
                                    />
                                    <Progress
                                        percent={displayUsedPct}
                                        status={utilizationColor(budget.usedPct)}
                                        size="small"
                                        showInfo={false}
                                    />
                                </Card>
                            </Col>
                        </Row>
                    )}
                </Card>
            )}

            {canSeeRequests && (
                <Card title="Permintaan" style={{ marginBottom: 24 }}>
                    {requests.awaitingMyDecision > 0 && (
                        <Alert
                            style={{ marginBottom: 16, cursor: 'pointer' }}
                            type="warning"
                            showIcon
                            message={`Perlu keputusan Anda: ${requests.awaitingMyDecision}`}
                            onClick={() => router.visit('/approvals')}
                        />
                    )}
                    <Row gutter={[16, 16]}>
                        <Col xs={24} sm={12} lg={6}>
                            <StatCard title="Draf" value={requests.draft} href="/plant-requests" />
                        </Col>
                        <Col xs={24} sm={12} lg={6}>
                            <StatCard
                                title="Menunggu Approval"
                                value={requests.waitingApproval}
                                href="/plant-requests"
                            />
                        </Col>
                        <Col xs={24} sm={12} lg={6}>
                            <StatCard
                                title="Disetujui Bulan Ini"
                                value={requests.approvedThisMonth}
                                href="/plant-requests"
                            />
                        </Col>
                        <Col xs={24} sm={12} lg={6}>
                            <StatCard title="Ditolak" value={requests.rejected} href="/plant-requests" />
                        </Col>
                    </Row>
                </Card>
            )}

            {can['tabulation_bid.view'] && (
                <Card title="Pengadaan" style={{ marginBottom: 24 }}>
                    <Row gutter={[16, 16]}>
                        <Col xs={24} sm={12} lg={8}>
                            <StatCard
                                title="Bid Menunggu Review"
                                value={procurement.bidsAwaitingReview}
                                href="/tabulation-bids"
                            />
                        </Col>
                        <Col xs={24} sm={12} lg={8}>
                            <StatCard
                                title="Bid Menunggu PO"
                                value={procurement.bidsAwaitingPo}
                                href="/tabulation-bids"
                            />
                        </Col>
                        <Col xs={24} sm={12} lg={8}>
                            <StatCard
                                title="PO Bulan Ini"
                                value={procurement.poCreatedThisMonth}
                                href="/tabulation-bids"
                                footer={
                                    <Typography.Text type="secondary">
                                        {formatIdrCompact(procurement.poValueThisMonth)}
                                    </Typography.Text>
                                }
                            />
                        </Col>
                    </Row>
                </Card>
            )}

            {can['dmbd.view'] && (
                <Card
                    title="DMBD Hari Ini"
                    extra={
                        dmbd.unitsActive !== null ? (
                            <Typography.Text type="secondary">
                                {dmbd.unitsActive} unit aktif
                            </Typography.Text>
                        ) : null
                    }
                    style={{ marginBottom: 24 }}
                >
                    {!dmbd.available ? (
                        <Alert type="warning" showIcon message="Data unit sedang tidak tersedia" />
                    ) : (
                        <Row gutter={[16, 16]}>
                            <Col xs={24} sm={8}>
                                <StatCard title="Ready for Use" value={dmbd.rfu} href="/dmbd" />
                            </Col>
                            <Col xs={24} sm={8}>
                                <StatCard title="Standby" value={dmbd.standby} href="/dmbd" />
                            </Col>
                            <Col xs={24} sm={8}>
                                <StatCard title="Breakdown" value={dmbd.breakdown} href="/dmbd" />
                            </Col>
                        </Row>
                    )}
                </Card>
            )}

            <Card title="Tindakan Menunggu" style={{ marginBottom: 24 }}>
                <Row gutter={[16, 16]}>
                    <Col xs={24} sm={12} lg={8}>
                        <StatCard title="Overbudget" value={pending.overbudget} href="/overbudget" />
                    </Col>
                    <Col xs={24} sm={12} lg={8}>
                        <StatCard title="Pembatalan" value={pending.cancellation} href="/cancellation" />
                    </Col>
                    <Col xs={24} sm={12} lg={8}>
                        <StatCard title="Interchange" value={pending.interchange} href="/interchange" />
                    </Col>
                </Row>
            </Card>
        </AppLayout>
    );
}
