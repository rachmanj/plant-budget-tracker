import type { ThemeConfig } from 'antd';
import { theme } from 'antd';

const { darkAlgorithm, defaultAlgorithm } = theme;

const componentOverrides: ThemeConfig['components'] = {
    Card: {
        boxShadowTertiary: 'none',
        boxShadowSecondary: 'none',
        headerBg: 'transparent',
    },
    Table: {
        headerBg: 'transparent',
        headerSplitColor: 'rgba(255, 255, 255, 0.075)',
        borderColor: 'rgba(255, 255, 255, 0.075)',
        headerColor: '#8b959c',
    },
    Layout: {
        headerBg: '#16191b',
        siderBg: '#16191b',
        bodyBg: '#0e1011',
        triggerBg: '#16191b',
    },
    Select: {
        borderRadius: 6,
        controlHeight: 36,
    },
    Input: {
        borderRadius: 6,
        controlHeight: 36,
    },
    InputNumber: {
        borderRadius: 6,
        controlHeight: 36,
    },
    Tag: {
        borderRadiusSM: 999,
    },
};

const darkComponents: ThemeConfig['components'] = {
    ...componentOverrides,
    Table: {
        headerBg: 'transparent',
        headerSplitColor: 'rgba(255, 255, 255, 0.075)',
        borderColor: 'rgba(255, 255, 255, 0.075)',
        headerColor: '#8b959c',
    },
    Layout: {
        headerBg: '#16191b',
        siderBg: '#16191b',
        bodyBg: '#0e1011',
        triggerBg: '#16191b',
    },
};

const lightComponents: ThemeConfig['components'] = {
    ...componentOverrides,
    Table: {
        headerBg: 'transparent',
        headerSplitColor: 'rgba(16, 24, 32, 0.09)',
        borderColor: 'rgba(16, 24, 32, 0.09)',
        headerColor: '#616b73',
    },
    Layout: {
        headerBg: '#ffffff',
        siderBg: '#ffffff',
        bodyBg: '#eef0f1',
        triggerBg: '#ffffff',
    },
};

export function pmbTheme(isDark: boolean): ThemeConfig {
    if (isDark) {
        return {
            algorithm: darkAlgorithm,
            token: {
                colorPrimary: '#4f92ff',
                colorSuccess: '#37d391',
                colorWarning: '#ffa24d',
                colorError: '#ff5d5d',
                colorInfo: '#4f92ff',
                colorBgBase: '#0e1011',
                colorBgContainer: '#16191b',
                colorBgElevated: '#1b1f22',
                colorBgLayout: '#0e1011',
                colorBorder: 'rgba(255, 255, 255, 0.14)',
                colorBorderSecondary: 'rgba(255, 255, 255, 0.075)',
                colorText: '#e9edf0',
                colorTextSecondary: '#a9b3ba',
                colorTextTertiary: '#8b959c',
                borderRadius: 10,
                borderRadiusSM: 6,
                wireframe: false,
                fontFamily:
                    'Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
            },
            components: darkComponents,
        };
    }

    return {
        algorithm: defaultAlgorithm,
        token: {
            colorPrimary: '#1858c0',
            colorSuccess: '#0d6a4b',
            colorWarning: '#9c5306',
            colorError: '#b42318',
            colorInfo: '#1858c0',
            colorBgBase: '#eef0f1',
            colorBgContainer: '#ffffff',
            colorBgElevated: '#ffffff',
            colorBgLayout: '#eef0f1',
            colorBorder: 'rgba(16, 24, 32, 0.16)',
            colorBorderSecondary: 'rgba(16, 24, 32, 0.09)',
            colorText: '#14181b',
            colorTextSecondary: '#4d5861',
            colorTextTertiary: '#616b73',
            borderRadius: 10,
            borderRadiusSM: 6,
            wireframe: false,
            fontFamily:
                'Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        },
        components: lightComponents,
    };
}