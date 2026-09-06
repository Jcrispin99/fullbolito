import type { ReservationStatus } from "@tenant/stores/reservation";

export interface StatusMeta {
    label: string;
    badgeClass: string;
}

const META: Record<ReservationStatus, StatusMeta> = {
    held: {
        label: "En espera",
        badgeClass: "bg-yellow-500 text-white",
    },
    confirmed: {
        label: "Confirmada",
        badgeClass: "bg-blue-500 text-white",
    },
    paid: {
        label: "Pagada",
        badgeClass: "bg-green-500 text-white",
    },
    played: {
        label: "Jugada",
        badgeClass: "bg-gray-500 text-white",
    },
    cancelled: {
        label: "Cancelada",
        badgeClass: "bg-red-500 text-white",
    },
    no_show: {
        label: "No se presentó",
        badgeClass: "bg-orange-500 text-white",
    },
};

export function statusMeta(value: ReservationStatus | string): StatusMeta {
    return (
        META[value as ReservationStatus] || {
            label: value,
            badgeClass: "bg-gray-400 text-white",
        }
    );
}

export const ALL_STATUSES: ReservationStatus[] = [
    "held",
    "confirmed",
    "paid",
    "played",
    "cancelled",
    "no_show",
];

/**
 * Devuelve la lista de transiciones de estado válidas desde un estado actual.
 * El backend valida igualmente; esto es para que la UI muestre solo lo aplicable.
 */
export function availableTransitions(
    current: ReservationStatus,
): Array<{ action: "confirm" | "markPaid" | "markPlayed" | "markNoShow" | "cancel"; label: string }> {
    switch (current) {
        case "held":
            return [
                { action: "confirm", label: "Confirmar" },
                { action: "markPaid", label: "Marcar pagada" },
                { action: "cancel", label: "Cancelar" },
            ];
        case "confirmed":
            return [
                { action: "markPaid", label: "Marcar pagada" },
                { action: "markPlayed", label: "Marcar jugada" },
                { action: "markNoShow", label: "Marcar no-show" },
                { action: "cancel", label: "Cancelar" },
            ];
        case "paid":
            return [
                { action: "markPlayed", label: "Marcar jugada" },
                { action: "markNoShow", label: "Marcar no-show" },
                { action: "cancel", label: "Cancelar" },
            ];
        case "played":
        case "cancelled":
        case "no_show":
        default:
            return [];
    }
}

export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleString("es", {
        dateStyle: "medium",
        timeStyle: "short",
    });
}

export function formatTime(iso: string | null | undefined): string {
    if (!iso) return "-";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "-";
    return d.toLocaleTimeString("es", { hour: "2-digit", minute: "2-digit" });
}

export function formatPrice(value: number | string | null | undefined): string {
    if (value == null) return "-";
    const num = typeof value === "string" ? parseFloat(value) : value;
    if (Number.isNaN(num)) return "-";
    return `S/ ${num.toFixed(2)}`;
}
