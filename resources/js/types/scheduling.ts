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

export type PublicBusiness = {
    name: string;
    brand_color: string | null;
    logo_url: string | null;
    timezone: string;
};

export type PublicService = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price_formatted: string;
    staff_profile_ids: number[];
};

export type PublicStaff = {
    id: number;
    name: string;
    bio: string | null;
    color: string;
};

export type PublicSlot = {
    starts_at: string;
    label: string;
};

export type AppointmentSummary = {
    id: string;
    service: string;
    staff: string;
    date: string;
    time: string;
    ends_at_time: string;
    duration_minutes: number;
    price: string;
    status: string;
    status_label: string;
    starts_at: string;
};

export type AgendaBusinessHours = {
    daysOfWeek: number[];
    startTime: string;
    endTime: string;
};

export type AgendaStaff = {
    id: number;
    name: string;
    color: string;
    business_hours: AgendaBusinessHours[];
};

export type AgendaService = {
    id: number;
    name: string;
    duration_minutes: number;
    buffer_after_minutes: number;
    is_active: boolean;
};

export type AppointmentStatus =
    'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show';

/** extendedProps of an appointment event in the agenda feed. */
export type AgendaAppointment = {
    kind: 'appointment';
    status: AppointmentStatus;
    status_label: string;
    service: string;
    staff_profile_id: number;
    staff: string;
    customer: { name: string; email: string; phone: string | null };
    service_ends_at: string;
    duration_minutes: number;
    price: string;
    notes: string | null;
    cancellation_reason: string | null;
    has_started: boolean;
    can: { update: boolean; confirm: boolean };
};

/** An appointment selected in the agenda, with its times in the business timezone. */
export type SelectedAppointment = AgendaAppointment & {
    id: string;
    /** "YYYY-MM-DDTHH:mm", wall-clock time of the business. */
    local_start: string;
    local_service_end: string;
};
