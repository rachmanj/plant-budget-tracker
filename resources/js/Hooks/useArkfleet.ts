import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';

interface ArkfleetState<T> {
    data: T;
    loading: boolean;
    stale: boolean;
    error: string | null;
}

export function useArkfleetEquipment(projectCode?: string) {
    const [state, setState] = useState<ArkfleetState<unknown[]>>({
        data: [],
        loading: true,
        stale: false,
        error: null,
    });

    const fetchEquipment = useCallback(async () => {
        setState((s) => ({ ...s, loading: true, error: null }));
        try {
            const response = await axios.get('/api/equipment', {
                params: { project_code: projectCode },
            });
            setState({
                data: response.data.data ?? [],
                loading: false,
                stale: Boolean(response.data.stale),
                error: null,
            });
        } catch (e) {
            setState({
                data: [],
                loading: false,
                stale: false,
                error: e instanceof Error ? e.message : 'Gagal memuat equipment',
            });
        }
    }, [projectCode]);

    useEffect(() => {
        fetchEquipment();
    }, [fetchEquipment]);

    return { ...state, refetch: fetchEquipment };
}

export function useArkfleetProjects() {
    const [state, setState] = useState<ArkfleetState<unknown[]>>({
        data: [],
        loading: true,
        stale: false,
        error: null,
    });

    useEffect(() => {
        axios
            .get('/api/projects')
            .then((response) => {
                setState({
                    data: response.data ?? [],
                    loading: false,
                    stale: false,
                    error: null,
                });
            })
            .catch((e) => {
                setState({
                    data: [],
                    loading: false,
                    stale: false,
                    error: e instanceof Error ? e.message : 'Gagal memuat proyek',
                });
            });
    }, []);

    return state;
}
