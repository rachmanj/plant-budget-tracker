import { DownloadOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { Button, DatePicker, Select, Space } from 'antd';
import type { Dayjs } from 'dayjs';
import dayjs from 'dayjs';

const { RangePicker } = DatePicker;

export interface ProjectOption {
    project_code: string;
    project_name: string;
}

export interface DepartmentOption {
    dept_code: string;
    dept_name: string;
}

export interface ProcurementReportFilters {
    from: string;
    to: string;
    project_code: string | null;
    dept_code: string | null;
}

interface Props {
    reportPath: string;
    reportType: string;
    filters: ProcurementReportFilters;
    projects: ProjectOption[];
    departments: DepartmentOption[];
    projectScope: string | null;
    canExport: boolean;
}

function exportUrl(reportType: string, format: 'pdf' | 'csv', filters: ProcurementReportFilters): string {
    const params = new URLSearchParams();
    params.set('from', filters.from);
    params.set('to', filters.to);
    if (filters.project_code) {
        params.set('project_code', filters.project_code);
    }
    if (filters.dept_code) {
        params.set('dept_code', filters.dept_code);
    }

    return `/reports/${reportType}/export/${format}?${params.toString()}`;
}

export default function ProcurementReportToolbar({
    reportPath,
    reportType,
    filters,
    projects,
    departments,
    projectScope,
    canExport,
}: Props) {
    const projectLocked = projectScope != null && projectScope !== '';

    const navigate = (overrides: Partial<ProcurementReportFilters>) => {
        router.get(
            reportPath,
            {
                from: filters.from,
                to: filters.to,
                project_code: filters.project_code ?? undefined,
                dept_code: filters.dept_code ?? undefined,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const rangeValue: [Dayjs, Dayjs] | null =
        filters.from && filters.to ? [dayjs(filters.from), dayjs(filters.to)] : null;

    return (
        <Space wrap style={{ marginBottom: 16 }} size="middle">
            <RangePicker
                value={rangeValue}
                onChange={(values) => {
                    if (!values?.[0] || !values[1]) {
                        return;
                    }
                    navigate({
                        from: values[0].format('YYYY-MM-DD'),
                        to: values[1].format('YYYY-MM-DD'),
                    });
                }}
            />
            <Select
                allowClear={!projectLocked}
                disabled={projectLocked}
                placeholder="Project"
                style={{ minWidth: 180 }}
                value={filters.project_code ?? undefined}
                options={projects.map((p) => ({
                    value: p.project_code,
                    label: `${p.project_code} — ${p.project_name}`,
                }))}
                onChange={(value) => navigate({ project_code: value ?? null })}
            />
            <Select
                allowClear
                placeholder="Department"
                style={{ minWidth: 200 }}
                value={filters.dept_code ?? undefined}
                options={departments.map((d) => ({
                    value: d.dept_code,
                    label: d.dept_name,
                }))}
                onChange={(value) => navigate({ dept_code: value ?? null })}
            />
            {canExport && (
                <>
                    <Button
                        type="primary"
                        icon={<DownloadOutlined />}
                        href={exportUrl(reportType, 'pdf', filters)}
                    >
                        Download PDF
                    </Button>
                    <Button icon={<DownloadOutlined />} href={exportUrl(reportType, 'csv', filters)}>
                        Download CSV
                    </Button>
                </>
            )}
        </Space>
    );
}
