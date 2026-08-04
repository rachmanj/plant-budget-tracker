import { Table, Tag } from 'antd';

interface Vendor {
    id: number;
    vendor_code: string;
    vendor_name: string;
    price: string;
    rank: number;
    stock_availability: string;
}

interface Props {
    vendors: Vendor[];
}

const stockColor: Record<string, string> = {
    ready: 'green',
    indent: 'orange',
    partial: 'gold',
};

export default function VendorComparisonTable({ vendors }: Props) {
    const lowest = vendors.reduce((min, v) => (parseFloat(v.price) < parseFloat(min.price) ? v : min), vendors[0]);

    return (
        <Table
            rowKey="id"
            dataSource={vendors}
            columns={[
                { title: 'Rank', dataIndex: 'rank' },
                { title: 'Vendor', dataIndex: 'vendor_name' },
                {
                    title: 'Price',
                    dataIndex: 'price',
                    render: (price: string, row: Vendor) => (
                        <span style={{ color: row.id === lowest?.id ? 'green' : undefined, fontWeight: row.id === lowest?.id ? 600 : 400 }}>
                            {price}
                        </span>
                    ),
                },
                {
                    title: 'Stock',
                    dataIndex: 'stock_availability',
                    render: (s: string) => <Tag color={stockColor[s]}>{s}</Tag>,
                },
            ]}
            pagination={false}
        />
    );
}
