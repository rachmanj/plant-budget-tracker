import { StarFilled, StarOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { Button, Space, Typography } from 'antd';

interface FollowButtonProps {
    purchaseOrderId: number;
    isFollowed: boolean;
    followerCount: number;
}

export default function FollowButton({
    purchaseOrderId,
    isFollowed,
    followerCount,
}: FollowButtonProps) {
    const toggle = () => {
        router.post(
            `/procurement/purchase-orders/${purchaseOrderId}/follow`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <Space>
            <Button
                type={isFollowed ? 'primary' : 'default'}
                icon={isFollowed ? <StarFilled /> : <StarOutlined />}
                onClick={toggle}
            >
                {isFollowed ? 'Following' : 'Follow'}
            </Button>
            <Typography.Text type="secondary">
                {followerCount} follower{followerCount === 1 ? '' : 's'}
            </Typography.Text>
        </Space>
    );
}
