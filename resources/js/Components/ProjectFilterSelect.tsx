import { usePage } from '@inertiajs/react';
import { Select, Space, Tag, Tooltip } from 'antd';

export interface ProjectFilterSelectProps {
    value?: string | null;
    projects: { project_code: string; project_name: string }[];
    onChange: (value: string | undefined) => void;
    width?: number;
    allowAll?: boolean;
    allValue?: string;
    lockedValue?: string | null;
}

const FILTER_TOOLTIP =
    'Narrows this page only. The project in the topbar sets the app-wide context.';

export default function ProjectFilterSelect({
    value,
    projects,
    onChange,
    width = 220,
    allowAll = false,
    allValue = 'all',
    lockedValue,
}: ProjectFilterSelectProps) {
    const page = usePage();
    const nav = (page.props as { nav?: { currentProject?: string } }).nav;
    const contextProject = nav?.currentProject;

    if (lockedValue != null && lockedValue !== '') {
        return null;
    }

    const options = [
        ...(allowAll ? [{ value: allValue, label: 'All projects' }] : []),
        ...projects.map((p) => ({
            value: p.project_code,
            label: `${p.project_code} — ${p.project_name}`,
        })),
    ];

    const selectValue = value ?? undefined;

    const showContextTag =
        !!selectValue &&
        !!contextProject &&
        contextProject !== 'all' &&
        selectValue !== contextProject;

    return (
        <Space wrap size="small">
            <Tooltip title={FILTER_TOOLTIP}>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="Filter project"
                    style={{ width }}
                    value={selectValue}
                    options={options}
                    onChange={(next) => onChange(next ?? undefined)}
                />
            </Tooltip>
            {showContextTag && (
                <Tooltip
                    title={`The topbar project context is ${contextProject}; this page is filtered to ${selectValue}.`}
                >
                    <Tag color="warning">context: {contextProject}</Tag>
                </Tooltip>
            )}
        </Space>
    );
}