export type ServiceItem = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    buffer_after_minutes: number;
    price: string;
    price_formatted: string;
    is_active: boolean;
    staff_profile_ids?: number[];
};

export type StaffOption = {
    id: number;
    name: string;
    color: string;
};

export type WorkingInterval = {
    weekday: number;
    starts_at: string;
    ends_at: string;
};

export type TimeOffItem = {
    id: number;
    starts_at: string;
    ends_at: string;
    reason: string | null;
};
