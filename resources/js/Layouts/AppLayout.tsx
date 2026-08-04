import { usePage, Link, router } from '@inertiajs/react';
import { Layout, Menu, Avatar, Badge, Dropdown, Typography, Button } from 'antd';
import {
    DashboardOutlined,
    TeamOutlined,
    SafetyOutlined,
    ProjectOutlined,
    BellOutlined,
    LogoutOutlined,
    UserOutlined,
    DollarOutlined,
} from '@ant-design/icons';
import type { MenuProps } from 'antd';
import type { ReactNode } from 'react';

const { Header, Sider, Content } = Layout;

interface AuthUser {
    id: number;
    name: string;
    email: string;
    division?: string;
    project_code_scope?: string;
    roles: string[];
}

interface PageProps {
    auth: {
        user: AuthUser | null;
        can: string[];
    };
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
    const { auth, features } = usePage<PageProps>().props;
    const can = auth.can ?? [];

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
            label: <Link href="/budget">Anggaran</Link>,
        });
    }

    if (can.includes('plant_request.create')) {
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

    if (can.includes('tabulation_bid.create') || can.includes('tabulation_bid.review')) {
        menuItems.push({
            key: 'tabulation-bids',
            label: <Link href="/tabulation-bids">Tabulation Bid</Link>,
        });
    }

    if (can.includes('reports.view')) {
        menuItems.push({
            key: 'reports',
            label: <Link href="/reports/budget-consumption">Reports</Link>,
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
                label: <Link href="/admin/users">Pengguna</Link>,
            },
            {
                key: 'admin-roles',
                icon: <SafetyOutlined />,
                label: <Link href="/admin/roles">Role & Permission</Link>,
            },
            {
                key: 'admin-projects',
                icon: <ProjectOutlined />,
                label: <Link href="/admin/projects">Proyek</Link>,
            }
        );
    }

    const userMenu: MenuProps['items'] = [
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: 'Keluar',
            onClick: () => router.post('/logout'),
        },
    ];

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider breakpoint="lg" collapsedWidth={0} theme="light">
                <div style={{ padding: '16px', fontWeight: 700, fontSize: 16 }}>
                    PMB
                </div>
                <Menu mode="inline" items={menuItems} defaultSelectedKeys={['dashboard']} />
            </Sider>
            <Layout>
                <Header
                    style={{
                        background: '#fff',
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
