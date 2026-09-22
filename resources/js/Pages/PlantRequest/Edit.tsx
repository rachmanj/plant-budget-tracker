import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PlantRequestWizard from '@/Components/PlantRequestWizard';

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

interface Props {
    request: {
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
    };
    projectCode: string;
    projects: Project[];
    equipment: Equipment[];
    allocations: Allocation[];
}

export default function Edit({ request, projectCode, projects, equipment, allocations }: Props) {
    return (
        <AppLayout title="Ubah Plant Request">
            <Head title="Ubah Plant Request" />
            <PlantRequestWizard
                mode="edit"
                request={request}
                projectCode={projectCode}
                projects={projects}
                equipment={equipment}
                allocations={allocations}
            />
        </AppLayout>
    );
}
