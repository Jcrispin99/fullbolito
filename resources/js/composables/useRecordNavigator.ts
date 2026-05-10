import { computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import type { Ref } from "vue";

interface NavigableRecord {
    id: number | string;
}

interface NavigableStore {
    fetchAttributes?: (page: number, perPage: string | number) => Promise<void>;
    [key: string]: any;
}

/**
 * useRecordNavigator
 *
 * Provides previous/next navigation between records on a FormPage.
 *
 * @param list       - Reactive ref to the array of records from the store
 * @param currentId  - Reactive computed/ref holding the current record's ID
 * @param basePath   - Base route path, e.g. "/attributes"
 * @param fetchAll   - Optional function to load all records (fallback when list is empty)
 */
export function useRecordNavigator<T extends NavigableRecord>(
    list: Ref<T[]>,
    currentId: Ref<string | number>,
    basePath: string,
    fetchAll?: () => Promise<void>,
) {
    const router = useRouter();

    const currentIndex = computed(() =>
        list.value.findIndex((item) => String(item.id) === String(currentId.value)),
    );

    const prevRecord = computed(() =>
        currentIndex.value > 0 ? list.value[currentIndex.value - 1] : null,
    );

    const nextRecord = computed(() =>
        currentIndex.value !== -1 && currentIndex.value < list.value.length - 1
            ? list.value[currentIndex.value + 1]
            : null,
    );

    const navigatePrev = () => {
        if (prevRecord.value) {
            router.push(`${basePath}/${prevRecord.value.id}/edit`);
        }
    };

    const navigateNext = () => {
        if (nextRecord.value) {
            router.push(`${basePath}/${nextRecord.value.id}/edit`);
        }
    };

    // Fallback: if the user lands directly on an edit URL, load all records
    onMounted(async () => {
        if (list.value.length === 0 && fetchAll) {
            await fetchAll();
        }
    });

    return {
        currentIndex,
        prevRecord,
        nextRecord,
        navigatePrev,
        navigateNext,
    };
}
