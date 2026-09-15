<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";
import { Button } from "@/components/ui/button";
import { LocateFixed } from "lucide-vue-next";

// Fix del ícono default de Leaflet: los paths relativos que trae el paquete
// no resuelven bien con el bundler, así que se sobreescriben con los
// assets ya procesados por Vite.
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

// Centro por defecto: Lima, Perú.
const DEFAULT_CENTER: [number, number] = [-12.0464, -77.0428];

const latitude = defineModel<number | null>("latitude", { default: null });
const longitude = defineModel<number | null>("longitude", { default: null });

const mapContainer = ref<HTMLDivElement | null>(null);
const locating = ref(false);
const geolocationError = ref("");

let map: L.Map | null = null;
let marker: L.Marker | null = null;
let suppressModelWatch = false;

function placeMarker(lat: number, lng: number) {
    if (!map) return;
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on("dragend", () => {
            const pos = marker!.getLatLng();
            applyPosition(pos.lat, pos.lng);
        });
    }
}

function removeMarker() {
    marker?.remove();
    marker = null;
}

// suppressModelWatch evita que el watch de abajo reaccione a cambios que
// este mismo componente originó (click/drag/geolocalización) — solo debe
// disparar cuando latitude/longitude cambian desde AFUERA (ej. el padre
// carga otra sede al navegar con el RecordNavigator). El reset del flag
// se hace en nextTick porque el watch corre en el siguiente flush, no de
// forma síncrona.
function applyPosition(lat: number, lng: number) {
    suppressModelWatch = true;
    latitude.value = Number(lat.toFixed(7));
    longitude.value = Number(lng.toFixed(7));
    placeMarker(lat, lng);
    nextTick(() => {
        suppressModelWatch = false;
    });
}

function useMyLocation() {
    if (!navigator.geolocation) {
        geolocationError.value = "Tu navegador no soporta geolocalización.";
        return;
    }
    geolocationError.value = "";
    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const { latitude: lat, longitude: lng } = pos.coords;
            applyPosition(lat, lng);
            map?.setView([lat, lng], 16);
            locating.value = false;
        },
        () => {
            geolocationError.value = "No se pudo obtener tu ubicación.";
            locating.value = false;
        },
    );
}

onMounted(() => {
    if (!mapContainer.value) return;

    const hasInitialCoords = latitude.value != null && longitude.value != null;
    const initialCenter: [number, number] = hasInitialCoords
        ? [latitude.value as number, longitude.value as number]
        : DEFAULT_CENTER;

    map = L.map(mapContainer.value).setView(initialCenter, hasInitialCoords ? 16 : 6);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    if (hasInitialCoords) {
        placeMarker(latitude.value as number, longitude.value as number);
    }

    map.on("click", (e: L.LeafletMouseEvent) => {
        const isFirstPlacement = !marker;
        applyPosition(e.latlng.lat, e.latlng.lng);
        // Al primer clic (sin pin previo), acerca el zoom para que el
        // usuario pueda afinar la posición arrastrando el pin.
        if (isFirstPlacement) {
            map?.setView(e.latlng, 16);
        }
    });

    // El contenedor puede montarse dentro de un layout que todavía no
    // terminó de asentar su tamaño (grid/card recién renderizado).
    requestAnimationFrame(() => map?.invalidateSize());
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    marker = null;
});

watch([latitude, longitude], ([lat, lng]) => {
    if (suppressModelWatch || !map) return;
    if (lat == null || lng == null) {
        removeMarker();
        map.setView(DEFAULT_CENTER, 6);
        return;
    }
    placeMarker(lat, lng);
    map.setView([lat, lng], 16);
});
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs text-muted-foreground">
                Haz clic en el mapa o arrastra el pin para fijar la ubicación exacta.
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="locating"
                @click="useMyLocation"
            >
                <LocateFixed class="mr-2 h-4 w-4" />
                {{ locating ? "Buscando…" : "Usar mi ubicación" }}
            </Button>
        </div>

        <div
            ref="mapContainer"
            class="h-72 w-full rounded-md border border-input"
        />

        <p v-if="geolocationError" class="text-sm text-destructive">
            {{ geolocationError }}
        </p>

        <p class="text-xs text-muted-foreground">
            <template v-if="latitude != null && longitude != null">
                Coordenadas: {{ latitude }}, {{ longitude }}
            </template>
            <template v-else> Aún no se ha fijado una ubicación en el mapa. </template>
        </p>
    </div>
</template>
