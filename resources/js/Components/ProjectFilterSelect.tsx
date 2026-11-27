import { Select, Tooltip } from 'antd';

export interface ProjectFilterSelectProps {
    value?: string | null;
    projects: { project_code: string; project_name: string }[];
    onChange: (value: string | undefined) => void;
    width?: number;
    allowAll?: boolean;
    allValue?: string;
    lockedValue?: string | null;
    tooltip?: string;
}

const DEFAULT_TOOLTIP = 'Narrows this view only.';

export default function ProjectFilterSelect({
    value,
    projects,
    onChange,
    width = 220,
    allowAll = false,
    allValue = 'all',
    lockedValue,
    tooltip = DEFAULT_TOOLTIP,
}: ProjectFilterSelectProps) {
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

    return (
        <Tooltip title={tooltip}>
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
    );
}