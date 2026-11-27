import { Head } from '@inertiajs/react';
import { Alert, Card, Empty, Tabs, Tag, Typography, theme } from 'antd';
import dayjs from 'dayjs';
import { useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';

type UpdateType = 'feature' | 'improvement' | 'fix';

interface UpdateEntry {
    date: string;
    type: UpdateType | string;
    title: string;
    summary: string;
}

interface Counts {
    all: number;
    feature: number;
    fix: number;
    improvement: number;
    latest: number;
}

interface Props {
    note: string;
    updates: UpdateEntry[];
    counts: Counts;
}

const TYPE_LABELS: Record<UpdateType, string> = {
    feature: 'Feature',
    improvement: 'Improvement',
    fix: 'Fix',
};

function formatUpdateDate(isoDate: string): string {
    const parsed = dayjs(isoDate);
    if (!parsed.isValid()) {
        return isoDate;
    }

    return parsed.format('D MMM YYYY');
}

function normalizeType(type: string | undefined): UpdateType {
    if (type === 'fix' || type === 'improvement' || type === 'feature') {
        return type;
    }

    return 'feature';
}

function updateTypeColor(type: UpdateType, token: ReturnType<typeof theme.useToken>['token']): string {
    switch (type) {
        case 'feature':
            return token.colorPrimary;
        case 'improvement':
            return token.colorSuccess;
        case 'fix':
            return token.colorWarning;
        default:
            return token.colorTextSecondary;
    }
}

function UpdateEntryCard({
    entry,
    showDate = true,
}: {
    entry: UpdateEntry;
    showDate?: boolean;
}) {
    const { token } = theme.useToken();
    const type = normalizeType(entry.type);
    const label = TYPE_LABELS[type] ?? entry.type;
    const color = updateTypeColor(type, token);

    return (
        <div
            style={{
                padding: '16px 0',
                borderBottom: `1px solid ${token.colorBorderSecondary}`,
            }}
        >
            <div
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    alignItems: 'center',
                    gap: 8,
                    marginBottom: 8,
                }}
            >
                <Tag
                    style={{
                        marginInlineEnd: 0,
                        borderColor: color,
                        color,
                        background: `${color}14`,
                    }}
                >
                    {label}
                </Tag>
                {showDate ? (
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        {formatUpdateDate(entry.date)}
                    </Typography.Text>
                ) : null}
            </div>
            <Typography.Text strong style={{ display: 'block', marginBottom: 6, wordBreak: 'break-word' }}>
                {entry.title}
            </Typography.Text>
            <Typography.Paragraph
                type="secondary"
                style={{ marginBottom: 0, fontSize: 13, lineHeight: 1.5, wordBreak: 'break-word' }}
            >
                {entry.summary}
            </Typography.Paragraph>
        </div>
    );
}

function EntryList({ entries, showDate = true, emptyText }: { entries: UpdateEntry[]; showDate?: boolean; emptyText: string }) {
    if (entries.length === 0) {
        return <Empty description={emptyText} style={{ padding: '32px 0' }} />;
    }

    return (
        <div>
            {entries.map((entry, index) => (
                <UpdateEntryCard
                    key={`${entry.date}-${entry.title}-${index}`}
                    entry={entry}
                    showDate={showDate}
                />
            ))}
        </div>
    );
}

function HistoryByDate({ entries }: { entries: UpdateEntry[] }) {
    const { token } = theme.useToken();

    const grouped = useMemo(() => {
        const map = new Map<string, UpdateEntry[]>();
        for (const entry of entries) {
            const key = entry.date || 'Unknown date';
            const list = map.get(key) ?? [];
            list.push(entry);
            map.set(key, list);
        }
        const keys = [...map.keys()].sort((a, b) => b.localeCompare(a));

        return keys.map((date) => ({ date, items: map.get(date) ?? [] }));
    }, [entries]);

    if (grouped.length === 0) {
        return <Empty description="No update entries recorded yet." style={{ padding: '32px 0' }} />;
    }

    return (
        <div>
            {grouped.map(({ date, items }) => (
                <div key={date} style={{ marginBottom: 24 }}>
                    <Typography.Title
                        level={5}
                        style={{
                            marginTop: 0,
                            marginBottom: 12,
                            color: token.colorTextSecondary,
                            fontWeight: 600,
                        }}
                    >
                        {formatUpdateDate(date)}
                    </Typography.Title>
                    <div>
                        {items.map((entry, index) => (
                            <UpdateEntryCard
                                key={`${date}-${entry.title}-${index}`}
                                entry={entry}
                                showDate={false}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function Index({ note, updates, counts }: Props) {
    const latestCutoff = dayjs().subtract(7, 'day').startOf('day');

    const whatsNewEntries = useMemo(() => {
        const recent = updates.filter((entry) => {
            const parsed = dayjs(entry.date);
            return parsed.isValid() && (parsed.isAfter(latestCutoff) || parsed.isSame(latestCutoff, 'day'));
        });
        if (recent.length > 0) {
            return recent;
        }

        return updates.slice(0, 5);
    }, [updates, latestCutoff]);

    const fixEntries = useMemo(
        () => updates.filter((e) => normalizeType(String(e.type)) === 'fix'),
        [updates]
    );

    const featureEntries = useMemo(
        () =>
            updates.filter((e) => {
                const t = normalizeType(String(e.type));
                return t === 'feature' || t === 'improvement';
            }),
        [updates]
    );

    const featuresTabCount = counts.feature + counts.improvement;

    const tabItems = [
        {
            key: 'whats-new',
            label: `What's New (${whatsNewEntries.length})`,
            children: (
                <div>
                    {note ? <Alert type="info" message={note} showIcon style={{ marginBottom: 16 }} /> : null}
                    <EntryList
                        entries={whatsNewEntries}
                        emptyText="No recent updates in the last 7 days."
                    />
                </div>
            ),
        },
        {
            key: 'history',
            label: `History (${counts.all})`,
            children: <HistoryByDate entries={updates} />,
        },
        {
            key: 'fixes',
            label: `Fixes (${counts.fix})`,
            children: <EntryList entries={fixEntries} emptyText="No fix entries yet." />,
        },
        {
            key: 'features',
            label: `Features (${featuresTabCount})`,
            children: (
                <EntryList entries={featureEntries} emptyText="No feature or improvement entries yet." />
            ),
        },
    ];

    return (
        <AppLayout title="Update History">
            <Head title="Update History" />
            <Card>
                <Typography.Title level={4} style={{ marginTop: 0 }}>
                    Update History
                </Typography.Title>
                <Typography.Paragraph type="secondary" style={{ marginBottom: 24, maxWidth: 720 }}>
                    All product changes logged since the trial began, including features, improvements, and fixes.
                </Typography.Paragraph>
                <Tabs items={tabItems} />
            </Card>
        </AppLayout>
    );
}