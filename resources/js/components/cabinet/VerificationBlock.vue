<script setup>
import { toast } from 'vue-sonner';
import { Check, Copy } from '@lucide/vue';

import { Button } from '@/components/ui/button';

const props = defineProps({
    filename: { type: String, required: true },
    verified: { type: Boolean, default: false },
});

/**
 * Скопировать имя файла проверки в буфер обмена.
 *
 * @returns {Promise<void>}
 */
async function copyFilename() {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.filename);
        } else {
            const input = document.createElement('textarea');

            input.value = props.filename;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }

        toast.success('Имя файла скопировано');
    } catch {
        toast.error('Не удалось скопировать имя файла');
    }
}
</script>

<template>
    <div class="flex flex-col gap-1.5 rounded-lg border border-border bg-secondary/40 p-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                Файл проверки
            </span>

            <span
                v-if="verified"
                class="inline-flex items-center gap-1 rounded-full border border-verified/40 bg-verified/10 px-2 py-0.5 text-[11px] font-semibold text-verified"
            >
                <Check class="size-3" aria-hidden="true" />
                Проверен
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <code class="rounded bg-background px-2 py-1 text-xs break-all">{{ filename }}</code>

            <Button type="button" variant="outline" size="xs" @click="copyFilename">
                <Copy class="size-3" aria-hidden="true" />
                Скопировать
            </Button>
        </div>

        <p class="text-xs text-muted-foreground">
            Разместите этот файл в корне вашего сайта, чтобы администратор мог подтвердить владение.
        </p>
    </div>
</template>