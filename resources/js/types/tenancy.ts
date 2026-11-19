export type Role = 'owner' | 'admin' | 'staff';

export type SharedTenant = {
    name: string;
    slug: string;
};

export type RoleOption = {
    value: Role;
    label: string;
};
