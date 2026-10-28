<script setup>
import { computed } from 'vue';

import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { MAX_PAGE_LINKS, PAGE_SIZE } from '@/lib/catalogConfig';
import { cn } from '@/lib/utils';

const props = defineProps({
    page: { type: Number, required: true },
    lastPage: { type: Number, required: true },
});

const emit = defineEmits(['update:page']);

const WINDOW_SIZE = MAX_PAGE_LINKS;

/**
 * @returns {Array<number | 'ellipsis'>}
 */
const pageItems = computed(() => {
    const items = [];
    let start = Math.max(1, props.page - Math.floor(WINDOW_SIZE / 2));
    let end = Math.min(props.lastPage, start + WINDOW_SIZE - 1);

    start = Math.max(1, end - WINDOW_SIZE + 1);

    const pages = new Set([1, props.lastPage]);

    for (let index = start; index <= end; index += 1) {
        pages.add(index);
    }

    const sorted = [...pages].sort((left, right) => left - right);
    let previous = 0;

    sorted.forEach((page) => {
        if (previous !== 0 && page - previous > 1) {
            items.push('ellipsis');
        }

        items.push(page);
        previous = page;
    });

    return items;
});

const isFirstPage = computed(() => props.page <= 1);
const isLastPage = computed(() => props.page >= props.lastPage);

/**
 * @param {number} page
 * @returns {void}
 */
function goToPage(page) {
    if (page < 1 || page > props.lastPage || page === props.page) {
        return;
    }

    emit('update:page', page);
}
</script>

<template>
    <Pagination
        v-if="lastPage > 1"
        as="nav"
        aria-label="Пагинация каталога"
        :page="page"
        :items-per-page="PAGE_SIZE"
        :total="lastPage * PAGE_SIZE"
        class="mt-6"
    >
        <PaginationContent class="flex-wrap gap-1">
            <PaginationPrevious
                aria-label="Предыдущая страница"
                :aria-disabled="isFirstPage"
                :disabled="isFirstPage"
                :class="cn('min-h-10', isFirstPage && 'pointer-events-none opacity-50')"
                @click.prevent="goToPage(page - 1)"
            >
                Назад
            </PaginationPrevious>

            <template v-for="(item, index) in pageItems" :key="`${item}-${index}`">
                <PaginationEllipsis v-if="item === 'ellipsis'" />

                <PaginationItem
                    v-else
                    :value="item"
                    :is-active="false"
                    :aria-label="`Страница ${item}`"
                    :aria-current="item === page ? 'page' : undefined"
                    :class="
                        cn(
                            'min-h-10 min-w-10',
                            item === page && 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 dark:bg-primary dark:text-primary-foreground',
                        )
                    "
                    @click="goToPage(item)"
                >
                    {{ item }}
                </PaginationItem>
            </template>

            <PaginationNext
                aria-label="Следующая страница"
                :aria-disabled="isLastPage"
                :disabled="isLastPage"
                :class="cn('min-h-10', isLastPage && 'pointer-events-none opacity-50')"
                @click.prevent="goToPage(page + 1)"
            >
                Вперёд
            </PaginationNext>
        </PaginationContent>
    </Pagination>
</template>
