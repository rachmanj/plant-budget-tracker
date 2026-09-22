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

interface Allocation {
    id: number;
    unit_code_cache: string;
    plant_type_cache: string;
    allocated_amount: string;
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
}

interface EditRequest {
    id: number;
    sap_mr_id: number;
    unit_code_cache: string;
    budget_allocation_id: number;
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
    allocations: Allocation[];
}

const UOM_OPTIONS = ['EA', 'PCS', 'SET', 'LITER', 'KG', 'ROLL', 'BOX'].map((u) => ({
    value: u,
    label: u,
}));

const PRICE_SOURCE_LABELS: Record<PriceSource, string> = {
    sap_price: 'SAP Price',
    tabulation_bid: 'Tabulation',
    manual: 'Manual',
    none: 'Belum ada',
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
    allocations,
}: PlantRequestWizardProps) {
    const projectName = projects.find((p) => p.project_code === projectCode)?.project_name ?? projectCode;
    const prefillEquipment = equipment.find((e) => e.id === prefill.equipment_id);
    const editEquipment = request ? equipment.find((e) => e.id === request.equipment_id) : undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        budget_allocation_id:
            mode === 'edit' && request
                ? request.budget_allocation_id
                : allocations.length === 1
                  ? allocations[0].id
                  : (null as number | null),
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
    const selectedAllocation = allocations.find((a) => a.id === data.budget_allocation_id);

    const equipmentGroups = useMemo(() => {
        const groups = new Map<string, Equipment[]>();

        for (const item of equipment) {
            const type = item.plant_type || 'Lainnya';
            const list = groups.get(type) ?? [];
            list.push(item);
            groups.set(type, list);
        }

        return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [equipment]);

    const linesTotal = useMemo(() => sumLinesTotal(data.lines), [data.lines]);

    const projectedUtilization = useMemo(() => {
        if (!selectedAllocation) {
            return '0.00';
        }

        const allocated = parseFloat(selectedAllocation.allocated_amount);
        const committed = parseFloat(selectedAllocation.committed_amount);
        const actual = parseFloat(selectedAllocation.actual_amount);
        const linesAmt = parseFloat(linesTotal);

        if (allocated <= 0) {
            return '0.00';
        }

        return (((committed + actual + linesAmt) / allocated) * 100).toFixed(2);
    }, [selectedAllocation, linesTotal]);

    const toleranceCapPct = selectedAllocation
        ? 100 + parseFloat(selectedAllocation.tolerance_pct)
        : 110;

    const exceedsTolerance = parseFloat(projectedUtilization) > toleranceCapPct;

    const hasEquipment = equipment.length > 0;
    const hasAllocations = allocations.length > 0;
    const canSubmit = hasAllocations && data.budget_allocation_id;

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
            message.error('Isi part number terlebih dahulu');

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
                message.error(result.message ?? 'Gagal mencari harga');

                return;
            }

            updateLine(index, {
                unit_price_est: result.unit_price,
                price_source: result.source as PriceSource,
            });
        } catch {
            message.error('Gagal menghubungi server');
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

    const submitLabel = mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Draft';

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
                            message="Data unit belum tersedia (cek koneksi ARKFLEET)"
                            style={{ marginBottom: 16 }}
                        />
                    )}

                    <Form.Item label="Pilih Unit" required>
                        <Select
                            showSearch
                            placeholder="Cari unit code atau deskripsi"
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
                        help="Diisi setelah MR dibuat di SAP — 0 untuk draft"
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
                    {sectionTitle(2, 'Budget Allocation')}

                    {!hasAllocations && (
                        <Alert
                            type="warning"
                            showIcon
                            message={`Belum ada alokasi budget untuk project ${projectCode} — Finance Director harus membuat periode budget dulu`}
                        />
                    )}

                    {hasAllocations && (
                        <>
                            <Form.Item label="Alokasi Budget" required>
                                <Select
                                    placeholder="Pilih alokasi budget"
                                    value={data.budget_allocation_id}
                                    onChange={(v) => setData('budget_allocation_id', v)}
                                    optionLabelProp="label"
                                >
                                    {allocations.map((a) => (
                                        <Select.Option
                                            key={a.id}
                                            value={a.id}
                                            label={`${a.unit_code_cache} · ${a.plant_type_cache}`}
                                        >
                                            <div>
                                                <div>
                                                    {a.unit_code_cache} · {a.plant_type_cache}
                                                </div>
                                                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                                    {formatIdr(a.allocated_amount)} · Sisa{' '}
                                                    {formatIdr(a.remaining)}
                                                </Typography.Text>
                                            </div>
                                        </Select.Option>
                                    ))}
                                </Select>
                                {errors.budget_allocation_id && (
                                    <Typography.Text type="danger">
                                        {errors.budget_allocation_id}
                                    </Typography.Text>
                                )}
                            </Form.Item>

                            {selectedAllocation && (
                                <Descriptions size="small" column={2} bordered style={{ marginBottom: 16 }}>
                                    <Descriptions.Item label="Alokasi">
                                        {formatIdr(selectedAllocation.allocated_amount)}
                                    </Descriptions.Item>
                                    <Descriptions.Item label="Komitmen + Aktual">
                                        {formatIdr(
                                            (
                                                parseFloat(selectedAllocation.committed_amount) +
                                                parseFloat(selectedAllocation.actual_amount)
                                            ).toFixed(2),
                                        )}
                                    </Descriptions.Item>
                                    <Descriptions.Item label="Sisa">
                                        {formatIdr(selectedAllocation.remaining)}
                                    </Descriptions.Item>
                                    <Descriptions.Item label="Penggunaan">
                                        <BudgetProgressBar
                                            utilizationPct={selectedAllocation.utilization_pct}
                                            cap={selectedAllocation.tolerance_cap}
                                            committed={selectedAllocation.committed_amount}
                                            actual={selectedAllocation.actual_amount}
                                            showLabel={false}
                                        />
                                    </Descriptions.Item>
                                </Descriptions>
                            )}
                        </>
                    )}
                </Card>

                <Card style={{ marginBottom: 16 }} styles={{ body: { opacity: hasAllocations ? 1 : 0.5 } }}>
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
                                        disabled={!hasAllocations}
                                    />
                                ) : null
                            }
                        >
                            <Row gutter={[12, 0]}>
                                <Col xs={24} md={6}>
                                    <Form.Item label="Part Number" required>
                                        <Input
                                            value={line.part_number}
                                            disabled={!hasAllocations}
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
                                    <Form.Item label="Nama Material" required>
                                        <Input
                                            value={line.material_name}
                                            disabled={!hasAllocations}
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
                                            disabled={!hasAllocations}
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
                                            disabled={!hasAllocations}
                                            onChange={(v) => updateLine(index, { qty: v ?? 1 })}
                                        />
                                    </Form.Item>
                                </Col>
                                <Col xs={12} md={4}>
                                    <Form.Item label="Harga Estimasi">
                                        <InputNumber
                                            min={0}
                                            precision={2}
                                            style={{ width: '100%' }}
                                            value={line.unit_price_est === '' ? undefined : line.unit_price_est}
                                            disabled={!hasAllocations}
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
                                        disabled={!hasAllocations}
                                        onClick={() => estimatePrice(index)}
                                    >
                                        Cari harga
                                    </Button>
                                    <Tag>
                                        {PRICE_SOURCE_LABELS[line.price_source] ?? 'Belum ada'}
                                    </Tag>
                                </Space>
                                <Typography.Text type="secondary">
                                    Total baris: {formatIdr(lineTotal(line.qty, line.unit_price_est))}
                                </Typography.Text>
                            </Row>
                        </Card>
                    ))}

                    <Button
                        type="dashed"
                        icon={<PlusOutlined />}
                        onClick={addLine}
                        disabled={!hasAllocations}
                        block
                    >
                        Tambah baris
                    </Button>
                </Card>

                <Card style={{ marginBottom: 16 }} styles={{ body: { opacity: hasAllocations ? 1 : 0.5 } }}>
                    {sectionTitle(4, 'Ringkasan')}

                    <Descriptions column={1} bordered size="small">
                        <Descriptions.Item label="Estimasi Total">
                            <Typography.Text strong>{formatIdr(linesTotal)}</Typography.Text>
                        </Descriptions.Item>
                        {selectedAllocation && (
                            <Descriptions.Item label="Proyeksi Penggunaan Budget">
                                <BudgetProgressBar
                                    utilizationPct={projectedUtilization}
                                    cap={selectedAllocation.tolerance_cap}
                                    committed={selectedAllocation.committed_amount}
                                    actual={selectedAllocation.actual_amount}
                                />
                                {exceedsTolerance && (
                                    <Typography.Text type="danger" style={{ display: 'block', marginTop: 8 }}>
                                        Melebihi batas {toleranceCapPct.toFixed(0)}% — submit akan masuk alur
                                        Overbudget
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
                    {mode === 'create' && (
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            Draft disimpan — submit & approval dilakukan dari daftar Plant Request
                        </Typography.Text>
                    )}
                </Space>
            </Form>
        </>
    );
}
