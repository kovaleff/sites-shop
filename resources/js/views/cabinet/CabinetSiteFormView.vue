<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import SiteForm from '@/components/cabinet/SiteForm.vue';
import VerificationBlock from '@/components/cabinet/VerificationBlock.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { createSite, fetchSite, updateSite } from '@/services/cabinetService';
import { fetchCategories } from '@/services/categoriesService';

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => route.name === 'cabinet-site-edit');
const siteId = computed(() => Number(route.params.id));

const categories = ref([]);
const categoriesError = ref(false);
const initial = ref(blankSite());
const errors = ref({});
const submitting = ref(false);
const created = ref(null);
const loading = ref(true);

/**
 * @returns {object}
 */
function blankSite() {
    return {
        title: '',
        domain: '',
        description: '',
        price: '',
        monthlyRevenue: '',
        hasTrademark: false,
        categories: [],
    };
}

onMounted(async () => {
    await loadCategories();

    if (isEdit.value) {
        await loadSite();
    }

    loading.value = false;
});

/**
 * @returns {Promise<void>}
 */
async function loadCategories() {
    try {
        categories.value = await fetchCategories();
    } catch {
        categoriesError.value = true;
    }
}

/**
 * @returns {Promise<void>}
 */
async function loadSite() {
    try {
        const payload = await fetchSite(siteId.value);
        const site = payload?.data;

        if (!site) {
            throw new Error('Объявление не найдено');
        }

        if (site.status === 'sold') {
            toast.error('Проданное объявление нельзя редактировать');
            router.replace({ name: 'cabinet-sites' });

            return;
        }

        initial.value = {
            title: site.title,
            domain: site.domain,
            description: site.description,
            price: site.price,
            monthlyRevenue: site.monthlyRevenue,
            hasTrademark: site.hasTrademark,
            categories: (site.categories ?? []).map((category) => category.id),
        };
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось загрузить объявление');
        router.replace({ name: 'cabinet-sites' });
    }
}

/**
 * @param {object} payload
 * @returns {Promise<void>}
 */
async function submit(payload) {
    errors.value = {};
    submitting.value = true;

    try {
        if (isEdit.value) {
            await updateSite(siteId.value, payload);
            toast.success('Изменения сохранены');
            router.push({ name: 'cabinet-sites' });

            return;
        }

        const response = await createSite(payload);

        created.value = response?.data ?? null;
        toast.success('Объявление создано');
    } catch (requestError) {
        if (requestError?.status === 422) {
            errors.value = requestError?.data?.errors ?? {};
            toast.error(requestError?.message ?? 'Проверьте заполнение формы');
        } else if (requestError?.status !== 401) {
            toast.error(requestError?.message ?? 'Не удалось сохранить объявление');
        }
    } finally {
        submitting.value = false;
    }
}

/**
 * @returns {void}
 */
function cancel() {
    router.push({ name: 'cabinet-sites' });
}
</script>

<template>
    <CabinetLayout>
        <div>
            <h1 class="text-2xl font-extrabold text-foreground">
                {{ isEdit ? 'Редактирование объявления' : 'Новое объявление' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Объявление появится в каталоге после оплаты подписки.
            </p>
        </div>

        <Skeleton v-if="loading" class="h-96 w-full rounded-xl" />

        <div
            v-else-if="created"
            class="flex max-w-2xl flex-col gap-4 rounded-xl border border-border p-6"
        >
            <h2 class="text-lg font-bold text-foreground">Объявление создано</h2>

            <VerificationBlock
                :filename="created.verificationFilename"
                :verified="created.verified"
            />

            <div class="flex flex-wrap gap-2">
                <Button type="button" @click="router.push({ name: 'cabinet-sites' })">
                    К списку объявлений
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="created = null; initial = blankSite()"
                >
                    Добавить ещё
                </Button>
            </div>
        </div>

        <div v-else class="max-w-2xl">
            <p
                v-if="categoriesError"
                class="mb-3 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                Не удалось загрузить справочник тематик. Обновление страницы может помочь.
            </p>

            <SiteForm
                :initial="initial"
                :categories="categories"
                :errors="errors"
                :submitting="submitting"
                :submit-label="isEdit ? 'Сохранить изменения' : 'Сохранить объявление'"
                @submit="submit"
                @cancel="cancel"
            />
        </div>
    </CabinetLayout>
</template>