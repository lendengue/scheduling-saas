import type { Role } from '@/types/tenancy';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    tenant_id: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    role: Role | null;
    can: {
        manageTeam: boolean;
    };
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
