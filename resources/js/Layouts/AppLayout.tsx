import { usePage, Link, router } from '@inertiajs/react';
import {
    Layout,
    Menu,
    Avatar,
    Badge,
    Dropdown,
    Typography,
    Button,
    theme,
    Select,
    Tag,
    Space,
    Tooltip,
} from 'antd';
import './AppLayout.css';
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
import { useEffect, useState } from 'react';
import { useTheme } from '@/Hooks/useTheme';

function normalizePathname(url: string): string {
    const withoutQuery = url.split('?')[0];
    const trimmed = withoutQuery.replace(/\/+$/, '');
    return trimmed === '' ? '/' : trimmed;
}

function resolveMenuState(pathname: string): { selectedKeys: string[]; openKeys: string[] } {
    const path = normalizePathname(pathname);

    if (path === '/reports') {
        return { selectedKeys: ['reports-index'], openKeys: ['reports'] };
    }
    if (path.startsWith('/reports/budget-consumption')) {
        return { selectedKeys: ['reports-budget-consumption'], openKeys: ['reports'] };
    }
    if (path.startsWith('/reports/vendor-performance')) {
        return { selectedKeys: ['reports-vendor-performance'], openKeys: ['reports'] };
    }
    if (path.startsWith('/reports/equipment-cost')) {
        return { selectedKeys: ['reports-equipment-cost'], openKeys: ['reports'] };
    }

    if (path.startsWith('/admin/users')) {
        return { selectedKeys: ['admin-users'], openKeys: ['admin'] };
    }
    if (path.startsWith('/admin/roles')) {
        return { selectedKeys: ['admin-roles'], openKeys: ['admin'] };
    }
    if (path.startsWith('/admin/projects')) {
        return { selectedKeys: ['admin-projects'], openKeys: ['admin'] };
    }

    if (path === '/dashboard' || path.startsWith('/dashboard/')) {
        return { selectedKeys: ['dashboard'], openKeys: [] };
    }
    if (path === '/budget' || path.startsWith('/budget/')) {
        return { selectedKeys: ['budget'], openKeys: [] };
    }
    if (path.startsWith('/plant-requests')) {
        return { selectedKeys: ['plant-requests'], openKeys: [] };
    }
    if (path.startsWith('/dmbd')) {
        return { selectedKeys: ['dmbd'], openKeys: [] };
    }
    if (path.startsWith('/approvals')) {
        return { selectedKeys: ['approvals'], openKeys: [] };
    }
    if (path.startsWith('/overbudget')) {
        return { selectedKeys: ['overbudget'], openKeys: [] };
    }
    if (path.startsWith('/cancellation')) {
        return { selectedKeys: ['cancellation'], openKeys: [] };
    }
    if (path.startsWith('/interchange')) {
        return { selectedKeys: ['interchange'], openKeys: [] };
    }
    if (path.startsWith('/sap')) {
        return { selectedKeys: ['sap-sync'], openKeys: [] };
    }
    if (path.startsWith('/tabulation-bids')) {
        return { selectedKeys: ['tabulation-bids'], openKeys: [] };
    }
    if (path.startsWith('/components')) {
        return { selectedKeys: ['components'], openKeys: [] };
    }

    return { selectedKeys: [], openKeys: [] };
}

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
    const page = usePage<PageProps>();
    const { auth, nav, features } = page.props;
    const pathname = normalizePathname(page.url);
    const { selectedKeys } = resolveMenuState(pathname);
    const [openKeys, setOpenKeys] = useState<string[]>(() => resolveMenuState(pathname).openKeys);

    useEffect(() => {
        setOpenKeys(resolveMenuState(pathname).openKeys);
    }, [pathname]);
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

    const activeProjects = nav?.activeProjects ?? [];
    const projectSelectOptions = activeProjects.map((p) => ({
        value: p.project_code,
        label: `${p.project_code} — ${p.project_name}`,
    }));

    const projectLabelForCode = (code: string | undefined) => {
        if (!code) {
            return '';
        }
        const match = activeProjects.find((p) => p.project_code === code);
        return match ? `${match.project_code} — ${match.project_name}` : code;
    };

    const projectEllipsis = {
        display: 'block',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap' as const,
    };

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider breakpoint="lg" collapsedWidth={0} theme={isDark ? 'dark' : 'light'}>
                <div style={{ padding: '16px', fontWeight: 700, fontSize: 16 }}>
                    PMB
                </div>
                <Menu
                    mode="inline"
                    items={menuItems}
                    selectedKeys={selectedKeys}
                    openKeys={openKeys}
                    onOpenChange={setOpenKeys}
                />
            </Sider>
            <Layout>
                <Header
                    className="app-layout-header"
                    style={{
                        background: token.colorBgContainer,
                        padding: '0 24px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 16,
                    }}
                >
                    <Typography.Title level={4} className="app-layout-header-title" ellipsis style={{ margin: 0 }}>
                        {title ?? 'Plant Budget Tracker'}
                    </Typography.Title>
                    <div className="app-layout-header-right">
                        {nav?.canSwitchProject ? (
                            <Space align="center" size={8} className="app-layout-project-row">
                                <Tooltip title="Applies to all pages">
                                    <Typography.Text
                                        type="secondary"
                                        className="app-layout-project-label"
                                        style={{ fontSize: 12, whiteSpace: 'nowrap' }}
                                    >
                                        Project
                                    </Typography.Text>
                                </Tooltip>
                                <Tooltip title={projectLabelForCode(nav.currentProject)}>
                                    <Select
                                        showSearch
                                        optionFilterProp="label"
                                        className="app-layout-project-select"
                                        value={nav.currentProject || undefined}
                                        options={projectSelectOptions}
                                        labelRender={({ value }) => {
                                            const full = projectLabelForCode(value as string);
                                            return <span style={projectEllipsis}>{full}</span>;
                                        }}
                                        optionRender={(option) => (
                                            <Tooltip title={String(option.label ?? '')}>
                                                <div style={projectEllipsis}>{option.label}</div>
                                            </Tooltip>
                                        )}
                                        onChange={(code) =>
                                            router.post('/project-context', { project_code: code }, { preserveScroll: true })
                                        }
                                    />
                                </Tooltip>
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
                            <div
                                className="app-layout-user-block"
                                style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}
                            >
                                <Avatar icon={<UserOutlined />} />
                                <span className="app-layout-user-name">{auth.user?.name}</span>
                            </div>
                        </Dropdown>
                    </div>
                </Header>
                <Content style={{ margin: 24 }}>{children}</Content>
            </Layout>
        </Layout>
    );
}
