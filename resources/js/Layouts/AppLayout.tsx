import { usePage, Link, router } from '@inertiajs/react';
import { Layout, Menu, Avatar, Badge, Dropdown, Typography, Button, theme, Select, Tag, Space } from 'antd';
import {
    DashboardOutlined,
    TeamOutlined,
    SafetyOutlined,
    ProjectOutlined,
    BellOutlined,
    LogoutOutlined,
    UserOutlined,
    DollarOutlined,
    BulbOutlined,
    BulbFilled,
} from '@ant-design/icons';
import type { MenuProps } from 'antd';
import type { ReactNode } from 'react';
import { useTheme } from '@/Hooks/useTheme';

const { Header, Sider, Content } = Layout;

interface AuthUser {
    id: number;
    name: string;
    email: string;
    division?: string;
    project_code_scope?: string;
    roles: string[];
}

interface NavProps {
    pendingApprovals: number;
    isApprover: boolean;
    canViewApprovals: boolean;
    canViewTabulationBids: boolean;
    canViewOverbudget: boolean;
    canViewCancellation: boolean;
    canViewInterchange: boolean;
    canCreatePlantRequest: boolean;
    viewSapDashboard: boolean;
    roles: string[];
    roleLabels?: Record<string, string>;
    canSwitchProject?: boolean;
    currentProject?: string;
    currentProjectName?: string;
    activeProjects?: { project_code: string; project_name: string }[];
}

interface PageProps {
    auth: {
        user: AuthUser | null;
        can: string[];
    };
    nav?: NavProps;
    features?: {
        cannibal_beta?: boolean;
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

interface AppLayoutProps {
    children: ReactNode;
    title?: string;
}

export default function AppLayout({ children, title }: AppLayoutProps) {
    const { auth, nav, features } = usePage<PageProps>().props;
    const can = auth.can ?? [];
    const { isDark, toggleTheme } = useTheme();
    const { token } = theme.useToken();

    const menuItems: MenuProps['items'] = [
        {
            key: 'dashboard',
            icon: <DashboardOutlined />,
            label: <Link href="/dashboard">Dashboard</Link>,
        },
    ];

    if (can.includes('budget.view')) {
        menuItems.push({
            key: 'budget',
            icon: <DollarOutlined />,
            label: <Link href="/budget">Budget</Link>,
        });
    }

    if (nav?.canCreatePlantRequest) {
        menuItems.push({
            key: 'plant-requests',
            label: <Link href="/plant-requests">Plant Requests</Link>,
        });
    }

    if (can.includes('dmbd.view') || can.includes('dmbd.update')) {
        menuItems.push({
            key: 'dmbd',
            label: <Link href="/dmbd">DMBD</Link>,
        });
    }

    if (nav?.canViewApprovals) {
        menuItems.push({
            key: 'approvals',
            label: (
                <Link href="/approvals">
                    <Badge count={nav.pendingApprovals} size="small" offset={[8, 0]}>
                        Approvals
                    </Badge>
                </Link>
            ),
        });
    }

    if (nav?.canViewOverbudget) {
        menuItems.push({
            key: 'overbudget',
            label: <Link href="/overbudget">Overbudget</Link>,
        });
    }

    if (nav?.canViewCancellation) {
        menuItems.push({
            key: 'cancellation',
            label: <Link href="/cancellation">Cancellation</Link>,
        });
    }

    if (nav?.canViewInterchange) {
        menuItems.push({
            key: 'interchange',
            label: <Link href="/interchange">Interchange</Link>,
        });
    }

    if (nav?.viewSapDashboard) {
        menuItems.push({
            key: 'sap-sync',
            label: <Link href="/sap/sync-dashboard">SAP Sync</Link>,
        });
    }

    if (nav?.canViewTabulationBids) {
        menuItems.push({
            key: 'tabulation-bids',
            label: <Link href="/tabulation-bids">Tabulation Bids</Link>,
        });
    }

    if (can.includes('reports.view')) {
        menuItems.push({
            key: 'reports',
            label: <Link href="/reports">Reports</Link>,
            children: [
                {
                    key: 'reports-index',
                    label: <Link href="/reports">Report List</Link>,
                },
                {
                    key: 'reports-budget-consumption',
                    label: <Link href="/reports/budget-consumption">Budget Consumption</Link>,
                },
                {
                    key: 'reports-vendor-performance',
                    label: <Link href="/reports/vendor-performance">Vendor Performance</Link>,
                },
                {
                    key: 'reports-equipment-cost',
                    label: <Link href="/reports/equipment-cost">Equipment Cost</Link>,
                },
            ],
        });
    }

    if (features?.cannibal_beta && can.includes('component.view')) {
        menuItems.push({
            key: 'components',
            label: <Link href="/components">Components</Link>,
        });
    }

    if (can.includes('user.manage')) {
        menuItems.push(
            {
                key: 'admin-users',
                icon: <TeamOutlined />,
                label: <Link href="/admin/users">Users</Link>,
            },
            {
                key: 'admin-roles',
                icon: <SafetyOutlined />,
                label: <Link href="/admin/roles">Role & Permission</Link>,
            },
            {
                key: 'admin-projects',
                icon: <ProjectOutlined />,
                label: <Link href="/admin/projects">Projects</Link>,
            }
        );
    }

    const userMenu: MenuProps['items'] = [
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: 'Logout',
            onClick: () => router.post('/logout'),
        },
    ];

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider breakpoint="lg" collapsedWidth={0} theme={isDark ? 'dark' : 'light'}>
                <div style={{ padding: '16px', fontWeight: 700, fontSize: 16 }}>
                    PMB
                </div>
                <Menu mode="inline" items={menuItems} defaultSelectedKeys={['dashboard']} />
            </Sider>
            <Layout>
                <Header
                    style={{
                        background: token.colorBgContainer,
                        padding: '0 24px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <Typography.Title level={4} style={{ margin: 0 }}>
                        {title ?? 'Plant Budget Tracker'}
                    </Typography.Title>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                        {nav?.canSwitchProject ? (
                            <Space direction="vertical" size={0} style={{ alignItems: 'flex-end' }}>
                                <Typography.Text type="secondary" style={{ fontSize: 11 }}>
                                    Project context (all pages)
                                </Typography.Text>
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    style={{ minWidth: 220 }}
                                    value={nav.currentProject || undefined}
                                    options={(nav.activeProjects ?? []).map((p) => ({
                                        value: p.project_code,
                                        label: `${p.project_code} — ${p.project_name}`,
                                    }))}
                                    onChange={(code) =>
                                        router.post('/project-context', { project_code: code }, { preserveScroll: true })
                                    }
                                />
                            </Space>
                        ) : (
                            nav?.currentProject &&
                            nav.currentProject !== 'all' && (
                                <Tag color="#0d9488">
                                    {nav.currentProject}
                                    {nav.currentProjectName &&
                                    nav.currentProjectName !== nav.currentProject
                                        ? ` — ${nav.currentProjectName}`
                                        : ''}
                                </Tag>
                            )
                        )}
                        <Button
                            type="text"
                            icon={isDark ? <BulbOutlined /> : <BulbFilled />}
                            onClick={toggleTheme}
                            aria-label={isDark ? 'Light mode' : 'Dark mode'}
                        />
                        <Badge count={0} size="small">
                            <Button type="text" icon={<BellOutlined />} />
                        </Badge>
                        <Dropdown menu={{ items: userMenu }} placement="bottomRight">
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                                <Avatar icon={<UserOutlined />} />
                                <span>{auth.user?.name}</span>
                            </div>
                        </Dropdown>
                    </div>
                </Header>
                <Content style={{ margin: 24 }}>{children}</Content>
            </Layout>
        </Layout>
    );
}
