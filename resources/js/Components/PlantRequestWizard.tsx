import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Card,
    Col,
    Descriptions,
    Form,
    Input,
    InputNumber,
    Row,
    Select,
    Space,
    Tag,
    Tooltip,
    Typography,
    message,
} from 'antd';
import { MinusCircleOutlined, PlusOutlined, SearchOutlined } from '@ant-design/icons';
import { useMemo, useState } from 'react';
import BudgetProgressBar from '@/Components/BudgetProgressBar';
import { formatIdr } from '@/hooks/useCurrency';

interface Prefill {
    dmbd_entry_id?: number;
    equipment_id?: number;
    unit_code_cache?: string;
    project_code?: string;
}

interface Project {
    project_code: string;
    project_name: string;
    is_active: boolean;
}

interface Equipment {
    id: number;
    unit_code: string;
    description: string;
    plant_type: string;
    unitstatus: string;
}

interface ProjectBudget {
    allocation_id: number;
    allocated_amount: string;
    carry_forward_in: string;
    pagu: string;
    tolerance_pct: string;
    committed_amount: string;
    actual_amount: string;
    tolerance_cap: string;
    utilization_pct: string;
    remaining: string;
}

type PriceSource = 'tabulation_bid' | 'sap_price' | 'manual' | 'none';

interface LineItem {
    part_number: string;
    material_name: string;
    uom: string;
    qty: number;
    unit_price_est: string | number;
    price_source: PriceSource;
    price_reference?: string | null;
}

interface EditRequest {
    id: number;
    sap_mr_id: number;
    unit_code_cache: string;
    dmbd_entry_id: number | null;
    equipment_id: number;
    lines: Array<{
        part_number: string;
        material_name: string;
        uom: string;
        qty: number;
        unit_price_est: string;
        price_source: PriceSource;
    }>;
}

export interface PlantRequestWizardProps {
    mode: 'create' | 'edit';
    request?: EditRequest;
    prefill?: Prefill;
    projectCode: string;
    projects: Project[];
    equipment: Equipment[];
    projectBudget: ProjectBudget | null;
}

const UOM_OPTIONS = ['EA', 'PCS', 'SET', 'LITER', 'KG', 'ROLL', 'BOX'].map((u) => ({
    value: u,
    label: u,
}));

const PRICE_SOURCE_LABELS: Record<PriceSource, string> = {
    sap_price: 'SAP Price',
    tabulation_bid: 'Tabulation',
    manual: 'Manual',
    none: 'None',
};

function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function lineTotal(qty: number, price: string | number): string {
    const unitPrice = typeof price === 'string' ? parseFloat(price) : price;

    if (!price || Number.isNaN(unitPrice) || unitPrice <= 0) {
        return '0.00';
    }

    return (qty * unitPrice).toFixed(2);
}

function sumLinesTotal(lines: LineItem[]): string {
    const total = lines.reduce((sum, line) => sum + parseFloat(lineTotal(line.qty, line.unit_price_est)), 0);

    return total.toFixed(2);
}

function initialLines(
    mode: 'create' | 'edit',
    request?: EditRequest,
): LineItem[] {
    if (mode === 'edit' && request?.lines?.length) {
        return request.lines.map((line) => ({
            part_number: line.part_number,
            material_name: line.material_name,
            uom: line.uom,
            qty: line.qty,
            unit_price_est: line.unit_price_est,
            price_source: line.price_source,
            price_reference: null,
        }));
    }

    return [
        {
            part_number: '',
            material_name: '',
            uom: 'EA',
            qty: 1,
            unit_price_est: '',
            price_source: 'none',
            price_reference: null,
        },
    ];
}

export default function PlantRequestWizard({
    mode,
    request,
    prefill = {},
    projectCode,
    projects,
    equipment,
    projectBudget,
}: PlantRequestWizardProps) {
    const projectName = projects.find((p) => p.project_code === projectCode)?.project_name ?? projectCode;
    const prefillEquipment = equipment.find((e) => e.id === prefill.equipment_id);
    const editEquipment = request ? equipment.find((e) => e.id === request.equipment_id) : undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        equipment_id:
            mode === 'edit' && request
                ? request.equipment_id
                : (prefill.equipment_id ?? 0),
        unit_code_cache:
            mode === 'edit' && request
                ? request.unit_code_cache
                : (prefill.unit_code_cache ?? prefillEquipment?.unit_code ?? ''),
        dmbd_entry_id:
            mode === 'edit' && request ? request.dmbd_entry_id : (prefill.dmbd_entry_id ?? null),
        sap_mr_id: mode === 'edit' && request ? request.sap_mr_id : 0,
        lines: initialLines(mode, request),
    });

    const [estimatingIndex, setEstimatingIndex] = useState<number | null>(null);
    const [selectedPlantType, setSelectedPlantType] = useState(
        editEquipment?.plant_type ?? prefillEquipment?.plant_type ?? '',
    );

    const selectedEquipment = equipment.find((e) => e.id === data.equipment_id);

    const equipmentGroups = useMemo(() => {
        const groups = new Map<string, Equipment[]>();

        for (const item of equipment) {
            const type = item.plant_type || 'Other';
            const list = groups.get(type) ?? [];
            list.push(item);
            groups.set(type, list);
        }

        return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [equipment]);

    const linesTotal = useMemo(() => sumLinesTotal(data.lines), [data.lines]);

    const projectedUtilization = useMemo(() => {
        if (!projectBudget) {
            return '0.00';
        }

        const pagu = parseFloat(projectBudget.pagu);
        const committed = parseFloat(projectBudget.committed_amount);
        const actual = parseFloat(projectBudget.actual_amount);
        const linesAmt = parseFloat(linesTotal);

        if (pagu <= 0) {
            return '0.00';
        }

        return (((committed + actual + linesAmt) / pagu) * 100).toFixed(2);
    }, [projectBudget, linesTotal]);

    const toleranceCapPct = projectBudget
        ? 100 + parseFloat(projectBudget.tolerance_pct)
        : 110;

    const exceedsTolerance = parseFloat(projectedUtilization) > toleranceCapPct;

    const hasEquipment = equipment.length > 0;
    const hasProjectBudget = projectBudget !== null;
    const canSubmit = hasProjectBudget && hasEquipment && data.equipment_id > 0;

    const handleEquipmentSelect = (equipmentId: number) => {
        const item = equipment.find((e) => e.id === equipmentId);

        if (item) {
            setData({
                ...data,
                equipment_id: item.id,
                unit_code_cache: item.unit_code,
            });
            setSelectedPlantType(item.plant_type);
        }
    };

    const updateLine = (index: number, patch: Partial<LineItem>) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], ...patch };
        setData('lines', lines);
    };

    const addLine = () => {
        setData('lines', [
            ...data.lines,
            {
                part_number: '',
                material_name: '',
                uom: 'EA',
                qty: 1,
                unit_price_est: '',
                price_source: 'none',
                price_reference: null,
            },
        ]);
    };

    const removeLine = (index: number) => {
        if (data.lines.length <= 1) {
            return;
        }

        setData('lines', data.lines.filter((_, i) => i !== index));
    };

    const estimatePrice = async (index: number) => {
        const partNumber = data.lines[index]?.part_number?.trim();

        if (!partNumber) {
            message.error('Enter part number first');

            return;
        }

        setEstimatingIndex(index);

        try {
            const response = await fetch('/plant-requests/estimate-part', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ part_number: partNumber }),
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                message.error(result.message ?? 'Price lookup failed');

                return;
            }

            updateLine(index, {
                unit_price_est: result.unit_price,
                price_source: result.source as PriceSource,
                price_reference: result.reference ?? null,
            });
        } catch {
            message.error('Could not reach server');
        } finally {
            setEstimatingIndex(null);
        }
    };

    const handlePartNumberChange = (index: number, partNumber: string) => {
        const lines = [...data.lines];
        lines[index] = {
            ...lines[index],
            part_number: partNumber,
            unit_price_est: '',
            price_source: 'none',
            price_reference: null,
        };
        setData('lines', lines);
    };

    const handleSubmit = () => {
        if (mode === 'edit' && request) {
            put(`/plant-requests/${request.id}`);
        } else {
            post('/plant-requests');
        }
    };

    const sectionTitle = (num: number, title: string) => (
        <Typography.Title level={5} style={{ marginBottom: 16 }}>
            {num}. {title}
        </Typography.Title>
    );

    const submitLabel = mode === 'edit' ? 'Save Changes' : 'Save Draft';

    return (
        <>
            <Typography.Paragraph type="secondary" style={{ marginBottom: 16 }}>
                Project: <Typography.Text strong>{projectCode}</Typography.Text>
                {projectName !== projectCode && ` — ${projectName}`}
            </Typography.Paragraph>

            <Form layout="vertical" onFinish={handleSubmit}>
                <Card style={{ marginBottom: 16 }}>
                    {sectionTitle(1, 'Unit / Equipment')}

                    {!hasEquipment && (
                        <Alert
                            type="warning"
                            showIcon
                            message="Unit data unavailable (check ARKFLEET connection)"
                            style={{ marginBottom: 16 }}
                        />
                    )}

                    <Form.Item label="Select Unit" required>
                        <Select
                            showSearch
                            placeholder="Search unit code or description"
                            value={data.equipment_id > 0 ? data.equipment_id : undefined}
                            onChange={handleEquipmentSelect}
                            disabled={!hasEquipment}
                            optionFilterProp="label"
                        >
                            {equipmentGroups.map(([plantType, items]) => (
                                <Select.OptGroup key={plantType} label={plantType}>
                                    {items.map((item) => (
                                        <Select.Option
                                            key={item.id}
                                            value={item.id}
                                            label={`${item.unit_code} — ${item.description}`}
                                        >
                                            {item.unit_code} — {item.description}
                                        </Select.Option>
                                    ))}
                                </Select.OptGroup>
                            ))}
                        </Select>
                    </Form.Item>

                    <Row gutter={16}>
                        <Col span={12}>
                            <Form.Item label="Unit Code">
                                <Input value={data.unit_code_cache} disabled />
                            </Form.Item>
                        </Col>
                        <Col span={12}>
                            <Form.Item label="Plant Type">
                                <Input
                                    value={selectedEquipment?.plant_type ?? selectedPlantType}
                                    disabled
                                />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.Item
                        label="SAP MR ID"
                        help="Enter after MR is created in SAP — use 0 for draft"
                        required
                    >
                        <InputNumber
                            style={{ width: '100%' }}
                            min={0}
                            value={data.sap_mr_id}
                            onChange={(v) => setData('sap_mr_id', v ?? 0)}
                        />
                    </Form.Item>
                </Card>

                <Card style={{ marginBottom: 16 }}>
                    {sectionTitle(2, 'Project Budget')}

                    {!hasProjectBudget && (
                        <Alert
                            type="warning"
                            showIcon
                            message={`No budget ceiling for project ${projectCode} — Finance Director must set project budget first`}
                        />
                    )}

                    {projectBudget && (
                        <Descriptions size="small" column={2} bordered>
                            <Descriptions.Item label="Ceiling (allocation + carry forward)">
                                {formatIdr(projectBudget.pagu)}
                            </Descriptions.Item>
                            <Descriptions.Item label="Committed + Actual">
                                {formatIdr(
                                    (
                                        parseFloat(projectBudget.committed_amount) +
                                        parseFloat(projectBudget.actual_amount)
                                    ).toFixed(2),
                                )}
                            </Descriptions.Item>
                            <Descriptions.Item label="Project budget remaining">
                                <Typography.Text strong>
                                    {formatIdr(projectBudget.remaining)}
                                </Typography.Text>
                                <Typography.Text type="secondary" style={{ marginLeft: 8 }}>
                                    ({projectBudget.utilization_pct}% utilized)
                                </Typography.Text>
                            </Descriptions.Item>
                            <Descriptions.Item label="Utilization">
                                <BudgetProgressBar
                                    utilizationPct={projectBudget.utilization_pct}
                                    cap={projectBudget.tolerance_cap}
                                    pagu={projectBudget.pagu}
                                    committed={projectBudget.committed_amount}
                                    actual={projectBudget.actual_amount}
                                    remaining={projectBudget.remaining}
                                    showLabel={false}
                                />
                            </Descriptions.Item>
                        </Descriptions>
                    )}
                </Card>

                <Card style={{ marginBottom: 16 }} styles={{ body: { opacity: hasProjectBudget ? 1 : 0.5 } }}>
                    {sectionTitle(3, 'Line Items')}

                    {data.lines.map((line, index) => (
                        <Card
                            key={index}
                            size="small"
                            style={{ marginBottom: 12 }}
                            extra={
                                data.lines.length > 1 ? (
                                    <Button
                                        type="text"
                                        danger
                                        icon={<MinusCircleOutlined />}
                                        onClick={() => removeLine(index)}
                                        disabled={!hasProjectBudget}
                                    />
                                ) : null
                            }
                        >
                            <Row gutter={[12, 0]}>
                                <Col xs={24} md={6}>
                                    <Form.Item label="Part Number" required>
                                        <Input
                                            value={line.part_number}
                                            disabled={!hasProjectBudget}
                                            onChange={(e) =>
                                                handlePartNumberChange(index, e.target.value)
                                            }
                                            onBlur={() => {
                                                if (line.part_number.trim() && !line.unit_price_est) {
                                                    estimatePrice(index);
                                                }
                                            }}
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={24} md={8}>
                                    <Form.Item label="Material Name" required>
                                        <Input
                                            value={line.material_name}
                                            disabled={!hasProjectBudget}
                                            onChange={(e) =>
                                                updateLine(index, { material_name: e.target.value })
                                            }
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={12} md={3}>
                                    <Form.Item label="UOM" required>
                                        <Select
                                            value={line.uom}
                                            disabled={!hasProjectBudget}
                                            onChange={(v) => updateLine(index, { uom: v })}
                                            options={UOM_OPTIONS}
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={12} md={3}>
                                    <Form.Item label="Qty" required>
                                        <InputNumber
                                            min={1}
                                            style={{ width: '100%' }}
                                            value={line.qty}
                                            disabled={!hasProjectBudget}
                                            onChange={(v) => updateLine(index, { qty: v ?? 1 })}
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={12} md={4}>
                                    <Form.Item label="Estimated Price">
                                        <InputNumber
                                            min={0}
                                            precision={2}
                                            style={{ width: '100%' }}
                                            value={line.unit_price_est === '' ? undefined : line.unit_price_est}
                                            disabled={!hasProjectBudget}
                                            onChange={(v) =>
                                                updateLine(index, {
                                                    unit_price_est: v ?? '',
                                                    price_source: 'manual',
                                                })
                                            }
                                        />
                                    </Form.Item>
                                </Col>
                            </Row>

                            <Row justify="space-between" align="middle">
                                <Space>
                                    <Button
                                        size="small"
                                        icon={<SearchOutlined />}
                                        loading={estimatingIndex === index}
                                        disabled={!hasProjectBudget}
                                        onClick={() => estimatePrice(index)}
                                    >
                                        Look up price
                                    </Button>
                                    <Tag>
                                        {PRICE_SOURCE_LABELS[line.price_source] ?? 'None'}
                                    </Tag>
                                    {line.price_source === 'none' ? (
                                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                            Price not found — enter manually
                                        </Typography.Text>
                                    ) : (
                                        line.price_reference && (
                                            <Tooltip title={line.price_reference}>
                                                <Typography.Text
                                                    type="secondary"
                                                    style={{ fontSize: 12, cursor: 'help' }}
                                                >
                                                    {line.price_reference}
                                                </Typography.Text>
                                            </Tooltip>
                                        )
                                    )}
                                </Space>
                                <Typography.Text type="secondary">
                                    Line total: {formatIdr(lineTotal(line.qty, line.unit_price_est))}
                                </Typography.Text>
                            </Row>
                        </Card>
                    ))}

                    <Button
                        type="dashed"
                        icon={<PlusOutlined />}
                        onClick={addLine}
                        disabled={!hasProjectBudget}
                        block
                    >
                        Add line
                    </Button>
                </Card>

                <Card style={{ marginBottom: 16 }} styles={{ body: { opacity: hasProjectBudget ? 1 : 0.5 } }}>
                    {sectionTitle(4, 'Summary')}

                    <Descriptions column={1} bordered size="small">
                        <Descriptions.Item label="Estimated Total">
                            <Typography.Text strong>{formatIdr(linesTotal)}</Typography.Text>
                        </Descriptions.Item>
                        {projectBudget && (
                            <Descriptions.Item label="Projected Budget Utilization">
                                <BudgetProgressBar
                                    utilizationPct={projectedUtilization}
                                    cap={projectBudget.tolerance_cap}
                                    pagu={projectBudget.pagu}
                                    committed={projectBudget.committed_amount}
                                    actual={projectBudget.actual_amount}
                                    additionalAmount={linesTotal}
                                    remaining={projectBudget.remaining}
                                />
                                {exceedsTolerance && (
                                    <Typography.Text type="danger" style={{ display: 'block', marginTop: 8 }}>
                                        Exceeds {toleranceCapPct.toFixed(0)}% cap — submit will trigger
                                        Overbudget workflow
                                    </Typography.Text>
                                )}
                            </Descriptions.Item>
                        )}
                    </Descriptions>
                </Card>

                <Space direction="vertical" style={{ width: '100%' }}>
                    <Button
                        type="primary"
                        htmlType="submit"
                        loading={processing}
                        disabled={!canSubmit}
                    >
                        {submitLabel}
                    </Button>
                    {(errors.equipment_id || errors.lines) && (
                        <Typography.Text type="danger">
                            {errors.equipment_id ?? errors.lines}
                        </Typography.Text>
                    )}
                    {mode === 'create' && (
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            Draft saved — submit and approval are done from the Plant Requests list
                        </Typography.Text>
                    )}
                </Space>
            </Form>
        </>
    );
}
