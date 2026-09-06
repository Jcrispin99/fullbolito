export interface SportOption {
    value: string;
    label: string;
}

export const SPORT_OPTIONS: SportOption[] = [
    { value: "futbol", label: "Fútbol" },
    { value: "futbol_7", label: "Fútbol 7" },
    { value: "futsal", label: "Futsal" },
    { value: "padel", label: "Pádel" },
    { value: "tenis", label: "Tenis" },
    { value: "voley", label: "Vóley" },
    { value: "basket", label: "Básquet" },
    { value: "otro", label: "Otro" },
];

export const SURFACE_OPTIONS: SportOption[] = [
    { value: "gras_natural", label: "Gras natural" },
    { value: "gras_sintetico", label: "Gras sintético" },
    { value: "arcilla", label: "Arcilla" },
    { value: "cemento", label: "Cemento" },
    { value: "parquet", label: "Parquet" },
    { value: "polvo_ladrillo", label: "Polvo de ladrillo" },
    { value: "otro", label: "Otro" },
];

export function sportLabel(value: string | null | undefined): string {
    if (!value) return "-";
    const opt = SPORT_OPTIONS.find((o) => o.value === value);
    return opt ? opt.label : value;
}

export function surfaceLabel(value: string | null | undefined): string {
    if (!value) return "-";
    const opt = SURFACE_OPTIONS.find((o) => o.value === value);
    return opt ? opt.label : value;
}
