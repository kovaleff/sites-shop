<script setup>
import { reactive, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    DESCRIPTION_MAX,
    DESCRIPTION_MIN,
    TITLE_MAX,
    TITLE_MIN,
} from '@/lib/cabinetConfig';

const props = defineProps({
    initial: {
        type: Object,
        default: () => ({
            title: '',
            domain: '',
            description: '',
            price: '',
            monthlyRevenue: '',
            hasTrademark: false,
            categories: [],
        }),
    },
    categories: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    submitting: { type: Boolean, default: false },
    submitLabel: { type: String, default: 'Сохранить' },
});

const emit = defineEmits(['submit', 'cancel']);

const form = reactive({
    ...props.initial,
    categories: [...(props.initial.categories ?? [])],
});

watch(
    () => props.initial,
    (value) => {
        Object.assign(form, value, { categories: [...(value.categories ?? [])] });
    },
    { deep: true },
);

/**
 * @param {string} field
 * @returns {string | null}
 */
function errorFor(field) {
    const messages = props.errors?.[field];

    return Array.isArray(messages) && messages.length > 0 ? messages[0] : null;
}

/**
 * @param {number} id
 * @returns {void}
 */
function toggleCategory(id) {
    const index = form.categories.indexOf(id);

    if (index === -1) {
        form.categories.push(id);
    } else {
        form.categories.splice(index, 1);
    }
}

/**
 * @returns {void}
 */
function submit() {
    emit('submit', {
        title: form.title,
        domain: form.domain,
        description: form.description,
        price: Number(form.price),
        monthlyRevenue: Number(form.monthlyRevenue),
        hasTrademark: Boolean(form.hasTrademark),
        categories: [...form.categories],
    });
}
</script>

<template>
    <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <div class="flex flex-col gap-1.5">
            <Label for="site-title">Название</Label>
            <Input
                id="site-title"
                v-model="form.title"
                :aria-invalid="Boolean(errorFor('title'))"
                :minlength="TITLE_MIN"
                :maxlength="TITLE_MAX"
                placeholder="Например, магазин товаров для дома"
            />
            <p class="text-xs text-muted-foreground">От {{ TITLE_MIN }} до {{ TITLE_MAX }} символов</p>
            <p v-if="errorFor('title')" class="text-xs text-destructive">{{ errorFor('title') }}</p>
        </div>

        <div class="flex flex-col gap-1.5">
            <Label for="site-domain">Домен</Label>
            <Input
                id="site-domain"
                v-model="form.domain"
                :aria-invalid="Boolean(errorFor('domain'))"
                inputmode="url"
                placeholder="example.ru"
            />
            <p class="text-xs text-muted-foreground">Без http:// и www. — только домен и зона</p>
            <p v-if="errorFor('domain')" class="text-xs text-destructive">{{ errorFor('domain') }}</p>
        </div>

        <div class="flex flex-col gap-1.5">
            <Label for="site-description">Описание</Label>
            <Textarea
                id="site-description"
                v-model="form.description"
                :aria-invalid="Boolean(errorFor('description'))"
                :minlength="DESCRIPTION_MIN"
                :maxlength="DESCRIPTION_MAX"
                rows="6"
            />
            <p class="text-xs text-muted-foreground">
                {{ form.description.length }} / {{ DESCRIPTION_MAX }} символов (минимум {{ DESCRIPTION_MIN }})
            </p>
            <p v-if="errorFor('description')" class="text-xs text-destructive">
                {{ errorFor('description') }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5">
                <Label for="site-price">Цена, ₽</Label>
                <Input
                    id="site-price"
                    v-model="form.price"
                    :aria-invalid="Boolean(errorFor('price'))"
                    inputmode="numeric"
                    type="number"
                    min="0"
                />
                <p v-if="errorFor('price')" class="text-xs text-destructive">{{ errorFor('price') }}</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="site-revenue">Месячный доход, ₽</Label>
                <Input
                    id="site-revenue"
                    v-model="form.monthlyRevenue"
                    :aria-invalid="Boolean(errorFor('monthlyRevenue'))"
                    inputmode="numeric"
                    type="number"
                    min="0"
                />
                <p v-if="errorFor('monthlyRevenue')" class="text-xs text-destructive">
                    {{ errorFor('monthlyRevenue') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Switch id="site-trademark" v-model="form.hasTrademark" />
            <Label for="site-trademark">Есть товарный знак</Label>
        </div>

        <fieldset class="flex flex-col gap-2">
            <legend class="text-sm font-medium">Тематики</legend>

            <p v-if="categories.length === 0" class="text-xs text-muted-foreground">
                Справочник тематик недоступен — создание объявления сейчас невозможно.
            </p>

            <div v-else class="grid max-h-64 gap-1.5 overflow-y-auto sm:grid-cols-2">
                <label
                    v-for="category in categories"
                    :key="category.id"
                    class="flex cursor-pointer items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        :checked="form.categories.includes(category.id)"
                        @change="toggleCategory(category.id)"
                    />
                    {{ category.name }}
                </label>
            </div>

            <p v-if="errorFor('categories')" class="text-xs text-destructive">
                {{ errorFor('categories') }}
            </p>
        </fieldset>

        <p v-if="errors.siteId" class="text-xs text-destructive">{{ errors.siteId[0] }}</p>

        <div class="flex flex-wrap gap-2">
            <Button type="submit" :disabled="submitting">
                {{ submitting ? 'Сохраняем…' : submitLabel }}
            </Button>
            <Button type="button" variant="ghost" @click="emit('cancel')">Отмена</Button>
        </div>
    </form>
</template>