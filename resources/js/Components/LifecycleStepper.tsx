import { Steps } from 'antd';

interface LifecycleStepperProps {
    status: string;
    sapPrNo?: string | null;
    sapPoId?: string | null;
}

const STATUS_STEP: Record<string, number> = {
    draft: 0,
    pending_pm: 1,
    pending_plant_mgr: 1,
    approved: 2,
    pr_created: 3,
    po_created: 4,
    received: 5,
    cancelled: 0,
    rejected: 0,
};

export default function LifecycleStepper({ status, sapPrNo, sapPoId }: LifecycleStepperProps) {
    let current = STATUS_STEP[status] ?? 0;
    if (sapPrNo) current = Math.max(current, 3);
    if (sapPoId) current = Math.max(current, 4);

    return (
        <Steps
            size="small"
            current={current}
            items={[
                { title: 'MR' },
                { title: 'Approval' },
                { title: 'PR' },
                { title: 'PO' },
                { title: 'GRPO' },
                { title: 'Issued' },
            ]}
        />
    );
}
