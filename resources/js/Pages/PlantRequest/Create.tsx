import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import PlantRequestWizard from '@/Components/PlantRequestWizard';

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

interface Props {
    prefill?: Prefill;
    projectCode: string;
    projects: Project[];
    equipment: Equipment[];
    projectBudget: ProjectBudget | null;
}

export default function Create({ prefill, projectCode, projects, equipment, projectBudget }: Props) {
    return (
        <AppLayout title="Buat Plant Request">
            <Head title="Buat Plant Request" />
            <PlantRequestWizard
                mode="create"
                prefill={prefill}
                projectCode={projectCode}
                projects={projects}
                equipment={equipment}
                projectBudget={projectBudget}
            />
        </AppLayout>
    );
}
