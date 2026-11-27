import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, Card, Col, Row, Space, Table, theme, Typography } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';
import { useMemo, useState, type CSSProperties, type ReactNode } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { formatIdr, formatIdrCompact } from '@/hooks/useCurrency';

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

interface Metrics {
    projectCode: string | null;
    period: PeriodInfo;
    budget: BudgetMetrics;
    requests: RequestMetrics;
    procurement: ProcurementMetrics;
    pending: PendingMetrics;
    dmbd: DmbdMetrics;
}

interface DmbdMetrics {
    rfu: number;
    standby: number;
    breakdown: number;
    recorded: number;
    unitsActive: number | null;
    available: boolean;
}

interface RegisterTotals {
    po_docs: number;
    po_value: string;
    pr_docs: number;
    pr_value: string;
    po_lines: number;
    suppliers: number;
}

interface RegisterMonthlyRow {
    month: string;
    label: string;
    po_docs: number;
    pr_docs: number;
    po_value: string;
}

interface RegisterProjectRow {
    code: string;
    docs: number;
}

interface RegisterRecentRow {
    doc_num: string;
    vendor_code: string;
    vendor_name: string;
    dept_name: string;
    project_code: string;
    doc_date: string | null;
    total_amount: string;
    delivery_status: string;
}

interface RegisterHealth {
    last_sync_status: string | null;
    last_sync_at: string | null;
    failed_jobs: number;
    latest_doc_date: string | null;
}

interface RegisterMetricsData {
    totals: RegisterTotals;
    monthly: RegisterMonthlyRow[];
    projects: RegisterProjectRow[];
    recent: RegisterRecentRow[];
    health: RegisterHealth;
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
    register: RegisterMetricsData | null;
    sapCompany: string | null;
    projectCode: string | null;
    today: string;
    can: CanFlags;
}

interface AuthUser {
    name: string;
    email: string;
}

interface NavProps {
    isApprover: boolean;
}

interface PageProps {
    auth: { user: AuthUser | null };
    nav?: NavProps;
}

type PillTone = 'blue' | 'green' | 'orange';

const WIDGET_HREFS: Record<string, string> = {
    budget: '/budget',
    plant_request: '/plant-requests',
    admin: '/admin/users',
    reports: '/reports',
};

function formatDocCount(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function userInitials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function deliveryLabel(status: string): string {
    const normalized = status.trim().toLowerCase();

    if (normalized === 'n' || normalized === 'open') {
        return 'Open';
    }

    if (normalized === 'c' || normalized === 'closed' || normalized === 'y') {
        return 'Closed';
    }

    if (status.trim() === '') {
        return 'Unknown';
    }

    return status;
}

function deliveryTone(status: string): PillTone {
    const label = deliveryLabel(status).toLowerCase();

    if (label === 'closed') {
        return 'green';
    }

    if (label === 'open') {
        return 'blue';
    }

    return 'orange';
}

function syncTone(status: string | null): PillTone {
    if (status === 'success') {
        return 'green';
    }

    if (status === 'failed' || status === 'pending') {
        return 'orange';
    }

    return 'blue';
}

function StatusPill({ tone, children }: { tone: PillTone; children: ReactNode }) {
    const { token } = theme.useToken();

    const palette: Record<PillTone, { color: string; background: string }> = {
        blue: { color: token.colorPrimary, background: token.colorPrimaryBg },
        green: { color: token.colorSuccess, background: token.colorSuccessBg },
        orange: { color: token.colorWarning, background: token.colorWarningBg },
    };

    const { color, background } = palette[tone];

    return (
        <span
            style={{
                display: 'inline-block',
                fontSize: 11,
                fontWeight: 600,
                letterSpacing: '0.02em',
                padding: '3px 9px',
                borderRadius: 999,
                whiteSpace: 'nowrap',
                color,
                background,
                border: `1px solid color-mix(in srgb, ${color} 34%, transparent)`,
            }}
        >
            {children}
        </span>
    );
}

function ToggleGroup({
    label,
    options,
    value,
    onChange,
}: {
    label: string;
    options: Array<{ key: string; label: string }>;
    value: string;
    onChange: (key: string) => void;
}) {
    const { token } = theme.useToken();

    return (
        <div role="group" aria-label={label} style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
            {options.map((option) => {
                const pressed = value === option.key;

                return (
                    <Button
                        key={option.key}
                        type={pressed ? 'default' : 'text'}
                        aria-pressed={pressed}
                        onClick={() => onChange(option.key)}
                        style={{
                            minHeight: 44,
                            borderColor: pressed ? token.colorBorderSecondary : token.colorBorder,
                            background: pressed ? token.colorFillTertiary : token.colorFillQuaternary,
                        }}
                    >
                        {option.label}
                    </Button>
                );
            })}
        </div>
    );
}

function hairlineCardStyle(token: ReturnType<typeof theme.useToken>['token']): CSSProperties {
    return {
        border: `1px solid ${token.colorBorderSecondary}`,
        boxShadow: 'none',
        height: '100%',
    };
}

function KpiCard({
    dotTone,
    label,
    value,
    valueSuffix,
    meta,
    onClick,
}: {
    dotTone: PillTone;
    label: string;
    value: string | number;
    valueSuffix?: string;
    meta?: ReactNode;
    onClick?: () => void;
}) {
    const { token } = theme.useToken();

    const dotColors: Record<PillTone, string> = {
        blue: token.colorPrimary,
        green: token.colorSuccess,
        orange: token.colorWarning,
    };

    return (
        <Card
            hoverable={!!onClick}
            onClick={onClick}
            style={hairlineCardStyle(token)}
            styles={{ body: { padding: 16 } }}
        >
            <Space direction="vertical" size={6} style={{ width: '100%' }}>
                <Space size={7} align="center">
                    <span
                        style={{
                            width: 6,
                            height: 6,
                            borderRadius: '50%',
                            background: dotColors[dotTone],
                            display: 'inline-block',
                        }}
                    />
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {label}
                    </Typography.Text>
                </Space>
                <Typography.Text
                    style={{
                        fontSize: 26,
                        fontWeight: 600,
                        lineHeight: 1.1,
                        letterSpacing: '-0.02em',
                    }}
                >
                    {value}
                    {valueSuffix ? (
                        <Typography.Text
                            type="secondary"
                            style={{ fontSize: 13, fontWeight: 500, marginLeft: 6 }}
                        >
                            {valueSuffix}
                        </Typography.Text>
                    ) : null}
                </Typography.Text>
                {meta ? (
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {meta}
                    </Typography.Text>
                ) : null}
            </Space>
        </Card>
    );
}

function MonthlyTrendChart({
    rows,
    metric,
    rangeMonths,
    isDark,
}: {
    rows: RegisterMonthlyRow[];
    metric: 'documents' | 'value';
    rangeMonths: 3 | 6;
    isDark: boolean;
}) {
    const { token } = theme.useToken();
    const visible = rows.slice(rangeMonths === 3 ? -3 : 0);

    const primaryValues =
        metric === 'documents'
            ? visible.map((row) => row.po_docs)
            : visible.map((row) => parseFloat(row.po_value) || 0);

    const secondaryValues = metric === 'documents' ? visible.map((row) => row.pr_docs) : null;

    const max = Math.max(...primaryValues, ...(secondaryValues ?? [0]), 1);

    const hatch = isDark
        ? `repeating-linear-gradient(135deg, rgba(255,255,255,0.02) 0 4px, rgba(255,255,255,0.26) 4px 5px)`
        : `repeating-linear-gradient(135deg, rgba(16,24,32,0.03) 0 4px, rgba(16,24,32,0.3) 4px 5px)`;

    return (
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 14, height: 190, padding: '6px 2px 0' }}>
            {visible.map((row, index) => {
                const primary = primaryValues[index] ?? 0;
                const secondary = secondaryValues?.[index] ?? 0;

                return (
                    <div
                        key={row.month}
                        style={{
                            flex: '1 1 0',
                            display: 'flex',
                            flexDirection: 'column',
                            justifyContent: 'flex-end',
                            height: '100%',
                            gap: 6,
                            minWidth: 0,
                        }}
                    >
                        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height: '100%' }}>
                            <div
                                title={
                                    metric === 'documents'
                                        ? `Purchase orders ${row.label}: ${formatDocCount(row.po_docs)} documents`
                                        : `Purchase orders ${row.label}: ${formatIdrCompact(row.po_value)}`
                                }
                                style={{
                                    flex: '1 1 0',
                                    borderRadius: '3px 3px 0 0',
                                    minHeight: 2,
                                    height: `${Math.max(2, (primary / max) * 100)}%`,
                                    background: token.colorPrimary,
                                }}
                            />
                            {secondaryValues ? (
                                <div
                                    title={`Purchase requests ${row.label}: ${formatDocCount(row.pr_docs)} documents`}
                                    style={{
                                        flex: '1 1 0',
                                        borderRadius: '3px 3px 0 0',
                                        minHeight: 2,
                                        height: `${Math.max(2, (secondary / max) * 100)}%`,
                                        backgroundColor: token.colorFillTertiary,
                                        backgroundImage: hatch,
                                        border: `1px solid ${token.colorBorderSecondary}`,
                                        borderBottom: 'none',
                                    }}
                                />
                            ) : null}
                        </div>
                        <Typography.Text
                            type="secondary"
                            style={{ textAlign: 'center', fontSize: 11, whiteSpace: 'nowrap' }}
                        >
                            {row.label}
                        </Typography.Text>
                    </div>
                );
            })}
        </div>
    );
}

function ProjectHorizontalBars({ rows, isDark }: { rows: RegisterProjectRow[]; isDark: boolean }) {
    const { token } = theme.useToken();
    const max = Math.max(...rows.map((row) => row.docs), 1);

    const hatch = isDark
        ? `repeating-linear-gradient(135deg, rgba(255,255,255,0.02) 0 4px, rgba(255,255,255,0.26) 4px 5px)`
        : `repeating-linear-gradient(135deg, rgba(16,24,32,0.03) 0 4px, rgba(16,24,32,0.3) 4px 5px)`;

    if (rows.length === 0) {
        return (
            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                No purchase orders with a project code yet.
            </Typography.Text>
        );
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {rows.map((row) => (
                <div
                    key={row.code}
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '56px minmax(0, 1fr) 62px',
                        alignItems: 'center',
                        gap: 10,
                    }}
                >
                    <Typography.Text type="secondary" style={{ fontSize: 12, fontWeight: 500 }}>
                        {row.code}
                    </Typography.Text>
                    <div
                        style={{
                            height: 14,
                            borderRadius: 3,
                            background: token.colorFillQuaternary,
                            overflow: 'hidden',
                        }}
                    >
                        <div
                            style={{
                                height: '100%',
                                width: `${(row.docs / max) * 100}%`,
                                backgroundColor: token.colorFillTertiary,
                                backgroundImage: hatch,
                                borderRight: `1px solid ${token.colorBorderSecondary}`,
                            }}
                        />
                    </div>
                    <Typography.Text
                        type="secondary"
                        style={{ textAlign: 'right', fontSize: 12, fontVariantNumeric: 'tabular-nums' }}
                    >
                        {formatDocCount(row.docs)}
                    </Typography.Text>
                </div>
            ))}
        </div>
    );
}

export default function Dashboard({
    widgets,
    metrics,
    register,
    sapCompany,
    projectCode,
    today,
    can,
}: DashboardProps) {
    const { auth, nav } = usePage<PageProps>().props;
    const { token } = theme.useToken();
    const [chartMetric, setChartMetric] = useState<'documents' | 'value'>('documents');
    const [chartRange, setChartRange] = useState<3 | 6>(6);

    const { budget, requests, procurement, pending } = metrics;
    const canSeeRequests = can['plant_request.create'] || !!nav?.isApprover;
    const pendingTotal = pending.overbudget + pending.cancellation + pending.interchange;

    const isDark = token.colorBgBase === '#0e1011' || token.colorBgBase === '#141414';

    const profileBannerStyle: CSSProperties = {
        borderRadius: 10,
        border: `1px solid ${token.colorBorderSecondary}`,
        background: isDark
            ? 'linear-gradient(104deg, #b6e2d4 0%, #c8d6f4 46%, #f4d8c4 100%)'
            : 'linear-gradient(104deg, #cdeee2 0%, #dbe5f9 46%, #f7e3d2 100%)',
        color: isDark ? '#1b2024' : '#17201f',
        padding: '18px 20px',
        display: 'flex',
        alignItems: 'center',
        gap: 16,
        flexWrap: 'wrap',
        marginBottom: 18,
    };

    const chipStyle: CSSProperties = {
        background: 'rgba(255, 255, 255, 0.5)',
        border: '1px solid rgba(23, 32, 31, 0.16)',
        borderRadius: 999,
        padding: '5px 11px',
        fontSize: 12,
        fontWeight: 500,
    };

    const recentColumns: ColumnsType<RegisterRecentRow> = useMemo(
        () => [
            {
                title: 'Document',
                dataIndex: 'doc_num',
                key: 'doc_num',
            },
            {
                title: 'Vendor',
                key: 'vendor',
                render: (_, row) => (
                    <span>
                        <span style={{ display: 'block', fontWeight: 500 }}>{row.vendor_code}</span>
                        <Typography.Text type="secondary" style={{ fontSize: 11.5 }}>
                            {row.vendor_name}
                        </Typography.Text>
                    </span>
                ),
            },
            {
                title: 'Department',
                dataIndex: 'dept_name',
                key: 'dept_name',
            },
            {
                title: 'Project',
                dataIndex: 'project_code',
                key: 'project_code',
            },
            {
                title: 'Date',
                key: 'doc_date',
                render: (_, row) =>
                    row.doc_date ? dayjs(row.doc_date).format('D MMM YYYY') : '',
            },
            {
                title: 'Value',
                key: 'total_amount',
                align: 'right',
                render: (_, row) => formatIdr(row.total_amount),
            },
            {
                title: 'Delivery',
                key: 'delivery_status',
                render: (_, row) => (
                    <StatusPill tone={deliveryTone(row.delivery_status)}>
                        {deliveryLabel(row.delivery_status)}
                    </StatusPill>
                ),
            },
        ],
        []
    );

    const trendNote = useMemo(() => {
        if (!register?.monthly.length) {
            return 'No monthly register data in range.';
        }

        const slice = register.monthly.slice(chartRange === 3 ? -3 : 0);
        const from = slice[0]?.label ?? '';
        const to = slice[slice.length - 1]?.label ?? '';

        if (chartMetric === 'value') {
            return `Purchase order value per month, ${from} to ${to}. PR values are not stored in the register.`;
        }

        return `Purchase orders and purchase requests per month, ${from} to ${to}.`;
    }, [register?.monthly, chartMetric, chartRange]);

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <Space direction="vertical" size={4} style={{ width: '100%', marginBottom: 18 }}>
                <Typography.Title level={3} style={{ margin: 0 }}>
                    Dashboard
                </Typography.Title>
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                    Plant procurement, register position {today}
                </Typography.Text>
            </Space>

            <section aria-label="Signed in user" style={profileBannerStyle}>
                <div
                    aria-hidden
                    style={{
                        width: 52,
                        height: 52,
                        borderRadius: '50%',
                        background: 'rgba(23, 32, 31, 0.16)',
                        border: '1px solid rgba(23, 32, 31, 0.22)',
                        display: 'grid',
                        placeItems: 'center',
                        fontWeight: 700,
                        fontSize: 17,
                        letterSpacing: '0.02em',
                        flexShrink: 0,
                    }}
                >
                    {userInitials(auth.user?.name ?? 'U')}
                </div>
                <div style={{ minWidth: 0 }}>
                    <Typography.Text strong style={{ display: 'block', fontSize: 16, color: 'inherit' }}>
                        {auth.user?.name ?? ''}
                    </Typography.Text>
                    <span style={{ display: 'block', fontSize: 12.5, opacity: 0.78 }}>{auth.user?.email ?? ''}</span>
                </div>
                <div style={{ marginLeft: 'auto', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {projectCode ? <span style={chipStyle}>Project context {projectCode}</span> : null}
                    {sapCompany ? <span style={chipStyle}>SAP company {sapCompany}</span> : null}
                </div>
            </section>

            <Row gutter={[12, 12]} style={{ marginBottom: 12 }}>
                {can['budget.view'] && budget.available ? (
                    <Col xs={24} sm={12} lg={6}>
                        <KpiCard
                            dotTone="blue"
                            label="Budget used"
                            value={formatIdrCompact(budget.used)}
                            meta={`${formatIdrCompact(budget.remaining)} remaining`}
                            onClick={() => router.visit('/budget')}
                        />
                    </Col>
                ) : null}
                {canSeeRequests ? (
                    <Col xs={24} sm={12} lg={6}>
                        <KpiCard
                            dotTone="orange"
                            label="Waiting for approval"
                            value={formatDocCount(requests.waitingApproval)}
                            valueSuffix="requests"
                            onClick={() => router.visit('/plant-requests')}
                        />
                    </Col>
                ) : null}
                {can['tabulation_bid.view'] ? (
                    <Col xs={24} sm={12} lg={6}>
                        <KpiCard
                            dotTone="green"
                            label="PO this month"
                            value={formatDocCount(procurement.poCreatedThisMonth)}
                            valueSuffix="documents"
                            meta={formatIdrCompact(procurement.poValueThisMonth)}
                            onClick={() => router.visit('/tabulation-bids')}
                        />
                    </Col>
                ) : null}
                <Col xs={24} sm={12} lg={6}>
                    <KpiCard
                        dotTone="orange"
                        label="Pending actions"
                        value={formatDocCount(pendingTotal)}
                        valueSuffix="items"
                        meta={`Overbudget ${pending.overbudget}, cancellation ${pending.cancellation}, interchange ${pending.interchange}`}
                    />
                </Col>
            </Row>

            {register === null ? (
                <Card style={{ ...hairlineCardStyle(token), marginBottom: 12 }} styles={{ body: { padding: 16 } }}>
                    <Typography.Text strong style={{ display: 'block', marginBottom: 6 }}>
                        Procurement register not available
                    </Typography.Text>
                    <Typography.Text type="secondary">
                        You do not have permission to view the procurement register. Contact an administrator if you
                        need reports or purchase order access.
                    </Typography.Text>
                </Card>
            ) : (
                <>
                    <Card
                        style={{ ...hairlineCardStyle(token), marginBottom: 12 }}
                        styles={{ body: { padding: 16 } }}
                        title={
                            <div>
                                <Typography.Text strong style={{ fontSize: 14 }}>
                                    Monthly documents
                                </Typography.Text>
                                <div>
                                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                        {trendNote}
                                    </Typography.Text>
                                </div>
                            </div>
                        }
                        extra={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
                                <ToggleGroup
                                    label="Metric"
                                    value={chartMetric}
                                    onChange={(key) => setChartMetric(key as 'documents' | 'value')}
                                    options={[
                                        { key: 'documents', label: 'Documents' },
                                        { key: 'value', label: 'Value' },
                                    ]}
                                />
                                <ToggleGroup
                                    label="Range"
                                    value={String(chartRange)}
                                    onChange={(key) => setChartRange(key === '3' ? 3 : 6)}
                                    options={[
                                        { key: '6', label: '6 months' },
                                        { key: '3', label: '3 months' },
                                    ]}
                                />
                            </div>
                        }
                    >
                        <MonthlyTrendChart
                            rows={register.monthly}
                            metric={chartMetric}
                            rangeMonths={chartRange}
                            isDark={isDark}
                        />
                        <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', marginTop: 14 }}>
                            <Space size={7}>
                                <span
                                    style={{
                                        width: 12,
                                        height: 12,
                                        borderRadius: 3,
                                        background: token.colorPrimary,
                                        display: 'inline-block',
                                    }}
                                />
                                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                    Purchase orders
                                </Typography.Text>
                            </Space>
                            {chartMetric === 'documents' ? (
                                <Space size={7}>
                                    <span
                                        style={{
                                            width: 12,
                                            height: 12,
                                            borderRadius: 3,
                                            backgroundColor: token.colorFillTertiary,
                                            backgroundImage: isDark
                                                ? 'repeating-linear-gradient(135deg, rgba(255,255,255,0.02) 0 4px, rgba(255,255,255,0.26) 4px 5px)'
                                                : 'repeating-linear-gradient(135deg, rgba(16,24,32,0.03) 0 4px, rgba(16,24,32,0.3) 4px 5px)',
                                            border: `1px solid ${token.colorBorderSecondary}`,
                                            display: 'inline-block',
                                        }}
                                    />
                                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                        Purchase requests
                                    </Typography.Text>
                                </Space>
                            ) : (
                                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                    Purchase request values are not stored in the register.
                                </Typography.Text>
                            )}
                        </div>
                    </Card>

                    <Row gutter={[12, 12]} style={{ marginBottom: 12 }}>
                        <Col xs={24} lg={14}>
                            <Card
                                style={hairlineCardStyle(token)}
                                styles={{ body: { padding: 16 } }}
                                title={
                                    <div>
                                        <Typography.Text strong style={{ fontSize: 14 }}>
                                            Purchase orders by project
                                        </Typography.Text>
                                        <div>
                                            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                                Documents registered per project code
                                            </Typography.Text>
                                        </div>
                                    </div>
                                }
                            >
                                <ProjectHorizontalBars rows={register.projects} isDark={isDark} />
                            </Card>
                        </Col>
                        <Col xs={24} lg={10}>
                            <Card
                                style={hairlineCardStyle(token)}
                                styles={{ body: { padding: 16 } }}
                                title={
                                    <div>
                                        <Typography.Text strong style={{ fontSize: 14 }}>
                                            Register health
                                        </Typography.Text>
                                        <div>
                                            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                                {sapCompany ? `SAP company ${sapCompany}` : 'Local procurement register'}
                                            </Typography.Text>
                                        </div>
                                    </div>
                                }
                            >
                                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 10,
                                            padding: '10px 12px',
                                            borderRadius: 6,
                                            border: `1px solid ${token.colorBorderSecondary}`,
                                            background: token.colorFillQuaternary,
                                            fontSize: 13,
                                        }}
                                    >
                                        <Space size={9} wrap>
                                            <StatusPill tone={syncTone(register.health.last_sync_status)}>
                                                {register.health.last_sync_status === 'success'
                                                    ? 'Healthy'
                                                    : register.health.last_sync_status ?? 'Unknown'}
                                            </StatusPill>
                                            <Typography.Text type="secondary">Last register sync</Typography.Text>
                                        </Space>
                                        <StatusPill tone={syncTone(register.health.last_sync_status)}>
                                            {register.health.last_sync_at ?? 'Never'}
                                        </StatusPill>
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 10,
                                            padding: '10px 12px',
                                            borderRadius: 6,
                                            border: `1px solid ${token.colorBorderSecondary}`,
                                            background: token.colorFillQuaternary,
                                            fontSize: 13,
                                        }}
                                    >
                                        <Space size={9} wrap>
                                            <StatusPill tone="blue">Registered</StatusPill>
                                            <Typography.Text type="secondary">Purchase order lines</Typography.Text>
                                        </Space>
                                        <StatusPill tone="blue">{formatDocCount(register.totals.po_lines)}</StatusPill>
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 10,
                                            padding: '10px 12px',
                                            borderRadius: 6,
                                            border: `1px solid ${token.colorBorderSecondary}`,
                                            background: token.colorFillQuaternary,
                                            fontSize: 13,
                                        }}
                                    >
                                        <Space size={9} wrap>
                                            <StatusPill tone="blue">Registered</StatusPill>
                                            <Typography.Text type="secondary">Suppliers with SAP code</Typography.Text>
                                        </Space>
                                        <StatusPill tone="blue">{formatDocCount(register.totals.suppliers)}</StatusPill>
                                    </div>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            gap: 10,
                                            padding: '10px 12px',
                                            borderRadius: 6,
                                            border: `1px solid ${token.colorBorderSecondary}`,
                                            background: token.colorFillQuaternary,
                                            fontSize: 13,
                                        }}
                                    >
                                        <Space size={9} wrap>
                                            <StatusPill tone={register.health.failed_jobs > 0 ? 'orange' : 'green'}>
                                                {register.health.failed_jobs > 0 ? 'Attention' : 'Clear'}
                                            </StatusPill>
                                            <Typography.Text type="secondary">Failed queue jobs</Typography.Text>
                                        </Space>
                                        <StatusPill tone={register.health.failed_jobs > 0 ? 'orange' : 'green'}>
                                            {formatDocCount(register.health.failed_jobs)}
                                        </StatusPill>
                                    </div>
                                    {register.health.latest_doc_date ? (
                                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                            Latest PO document date: {register.health.latest_doc_date}
                                        </Typography.Text>
                                    ) : null}
                                </Space>
                            </Card>
                        </Col>
                    </Row>

                    <Card
                        style={{ ...hairlineCardStyle(token), marginBottom: 12 }}
                        styles={{ body: { padding: 16 } }}
                        title={
                            <div>
                                <Typography.Text strong style={{ fontSize: 14 }}>
                                    Recent purchase orders
                                </Typography.Text>
                                <div>
                                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                        Newest documents carrying a project code
                                    </Typography.Text>
                                </div>
                            </div>
                        }
                    >
                        <Table<RegisterRecentRow>
                            rowKey="doc_num"
                            columns={recentColumns}
                            dataSource={register.recent}
                            pagination={false}
                            size="small"
                            scroll={{ x: 'max-content' }}
                            locale={{ emptyText: 'No purchase orders in the register yet.' }}
                        />
                    </Card>
                </>
            )}

            {widgets.length > 0 ? (
                <Card
                    title="Quick access"
                    style={hairlineCardStyle(token)}
                    styles={{ body: { padding: 16 } }}
                >
                    <Row gutter={[12, 12]}>
                        {widgets.map((widget) => {
                            const href = WIDGET_HREFS[widget.key];

                            return (
                                <Col xs={24} sm={12} md={8} key={widget.key}>
                                    <Card
                                        hoverable={!!href}
                                        onClick={href ? () => router.visit(href) : undefined}
                                        style={hairlineCardStyle(token)}
                                        styles={{ body: { padding: 14 } }}
                                    >
                                        <Typography.Text strong style={{ display: 'block' }}>
                                            {widget.title}
                                        </Typography.Text>
                                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                            {widget.description}
                                        </Typography.Text>
                                        {href ? (
                                            <Link href={href} style={{ fontSize: 12, marginTop: 8, display: 'block' }}>
                                                Open
                                            </Link>
                                        ) : null}
                                    </Card>
                                </Col>
                            );
                        })}
                    </Row>
                </Card>
            ) : null}
        </AppLayout>
    );
}