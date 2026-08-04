import { Head } from '@inertiajs/react';
import { Card, Tree } from 'antd';
import AppLayout from '@/Layouts/AppLayout';

interface ComponentNode {
    id: number;
    component_code: string;
    description: string;
    level: string;
    children?: ComponentNode[];
}

function toTreeData(nodes: ComponentNode[]) {
    return nodes.map((n) => ({
        key: n.id,
        title: `${n.component_code} — ${n.description} (${n.level})`,
        children: n.children ? toTreeData(n.children) : undefined,
    }));
}

export default function Index({ components }: { components: ComponentNode[] }) {
    return (
        <AppLayout title="Components">
            <Head title="Components" />
            <Card title="Component Hierarchy">
                <Tree treeData={toTreeData(components)} defaultExpandAll />
            </Card>
        </AppLayout>
    );
}
