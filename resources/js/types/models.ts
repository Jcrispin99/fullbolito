export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles?: string[];
    permissions?: string[];
    companies?: Array<{ id: number; business_name: string }>;
    tenants?: Array<{ id: string; business_name?: string | null }>;
    plan?: string | null;
    features?: string[];
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    is_system: boolean;
    permissions?: string[];
    users_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

export interface Tenant {
    id: string;
    user_id: number | null;
    data: Record<string, any> | null;
    created_at: string;
    updated_at: string;
    domains?: Domain[];
    subscription?: Subscription;
    subscriptions?: Subscription[];
}

export interface Payment {
    id: number;
    subscription_id: number;
    amount: string;
    currency: string;
    method: string;
    status: string;
    transaction_id: string | null;
    created_at: string;
    updated_at: string;
}

export interface Domain {
    id: number;
    domain: string;
    tenant_id: string;
    created_at: string;
    updated_at: string;
}

export interface Subscription {
    id: number;
    tenant_id: string;
    plan_id: number;
    status: string;
    provider: string | null;
    provider_id: string | null;
    provider_status: string | null;
    external_reference: string | null;
    next_billing_at: string | null;
    starts_at: string;
    ends_at: string | null;
    trial_ends_at: string | null;
    created_at: string;
    updated_at: string;
    plan?: Plan;
    payments?: Payment[];
}

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    currency: string;
    duration_days: number;
    is_active: boolean;
    includes_all_modules?: boolean;
    module_ids?: number[];
    modules?: Array<{ id: number; key: string; label: string; icon: string | null }>;
    created_at: string;
    updated_at: string;
}

export interface Module {
    id: number;
    key: string;
    label: string;
    description: string | null;
    icon: string | null;
    addon_price: number;
    is_active: boolean;
    sort_order: number;
    is_addon: boolean;
    created_at: string;
    updated_at: string;
}

export interface Company {
    id: number;
    business_name: string;
    trade_name: string | null;
    ruc: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    ubigeo: string | null;
    active: boolean;
    parent_id: number | null;
    branch_code: string | null;
    is_main: boolean;
    created_at: string;
    updated_at: string;
}
