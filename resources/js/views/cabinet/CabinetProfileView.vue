<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import { ExternalLink, ShieldCheck } from '@lucide/vue';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { fetchProfile, updateProfile } from '@/services/profileService';
import { useSessionStore } from '@/stores/session';

const session = useSessionStore();

const form = reactive({
    name: '',
    bio: '',
    city: '',
});

const user = ref(null);
const status = ref('loading');
const error = ref('');
const errors = ref({});
const saving = ref(false);

const initial = computed(() => (user.value?.displayName?.charAt(0) || '?').toUpperCase());
const profileUrl = computed(() =>
    user.value?.profileUrl ?? (user.value?.id ? `/seller/${user.value.id}` : '/seller'),
);
const providerLabel = computed(() => {
    if (user.value?.provider === 'vk') {
        return 'VK ID';
    }

    if (user.value?.provider === 'yandex') {
        return 'Яндекс ID';
    }

    return null;
});

/**
 * @param {object} profile
 * @returns {void}
 */
function applyUser(profile) {
    user.value = profile;
    form.name = profile.displayName ?? '';
    form.bio = profile.bio ?? '';
    form.city = profile.city ?? '';
}

/**
 * @returns {Promise<void>}
 */
async function load() {
    status.value = 'loading';
    error.value = '';

    try {
        const profile = await fetchProfile();

        if (profile) {
            applyUser(profile);
        } else if (session.user) {
            applyUser(session.user);
        }

        status.value = 'ready';
    } catch (requestError) {
        if (session.user) {
            applyUser(session.user);
            status.value = 'ready';

            return;
        }

        status.value = 'error';
        error.value = requestError?.message ?? 'Не удалось загрузить профиль.';
    }
}

/**
 * @param {string} field
 * @returns {string | null}
 */
function errorFor(field) {
    const messages = errors.value?.[field];

    return Array.isArray(messages) && messages.length > 0 ? messages[0] : null;
}

/**
 * @returns {Promise<void>}
 */
async function submit() {
    errors.value = {};
    saving.value = true;

    try {
        const profile = await updateProfile({
            name: form.name.trim(),
            bio: form.bio.trim() === '' ? null : form.bio.trim(),
            city: form.city.trim() === '' ? null : form.city.trim(),
        });

        if (profile) {
            applyUser(profile);
            session.applySession({ authenticated: true, user: profile });
        }

        toast.success('Профиль сохранён');
    } catch (requestError) {
        if (requestError?.status === 422) {
            errors.value = requestError?.data?.errors ?? {};
        } else if (requestError?.status !== 401) {
            toast.error(requestError?.message ?? 'Не удалось сохранить профиль');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <CabinetLayout>
        <div>
            <h1 class="text-2xl font-extrabold text-foreground">Профиль продавца</h1>
            <p class="text-sm text-muted-foreground">
                Эти данные видят покупатели в карточках лотов и на публичной странице продавца.
            </p>
        </div>

        <div :aria-busy="status === 'loading' ? 'true' : 'false'" class="flex flex-col gap-4">
            <template v-if="status === 'loading'">
                <Skeleton class="h-56 w-full rounded-xl" />
            </template>

            <div
                v-else-if="status === 'error'"
                class="flex flex-col items-start gap-2 rounded-xl border border-border p-6"
            >
                <p class="text-sm text-destructive">{{ error }}</p>
                <Button variant="outline" size="sm" @click="load">Повторить</Button>
            </div>

            <template v-else>
                <Card class="flex flex-wrap items-center gap-4 border-border bg-card p-5">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-secondary text-xl font-extrabold text-primary"
                    >
                        <img
                            v-if="user?.avatarUrl"
                            :src="user.avatarUrl"
                            :alt="user?.displayName"
                            class="size-full object-cover"
                        />
                        <span v-else aria-hidden="true">{{ initial }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-base font-bold text-foreground">
                            {{ user?.displayName }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="providerLabel">
                                Аватар и имя подставлены из {{ providerLabel }}.
                            </template>
                            <template v-else>
                                Аватар подставляется из сервиса входа.
                            </template>
                        </p>
                    </div>

                    <Button as-child variant="outline" size="sm">
                        <RouterLink :to="profileUrl" target="_blank" rel="noopener">
                            <ExternalLink class="size-4" aria-hidden="true" />
                            Открыть профиль
                        </RouterLink>
                    </Button>
                </Card>

                <Card class="border-border bg-card p-5">
                    <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                        <div class="flex flex-col gap-1.5">
                            <Label for="profile-name">Имя</Label>
                            <Input
                                id="profile-name"
                                v-model="form.name"
                                :aria-invalid="Boolean(errorFor('name'))"
                                minlength="2"
                                maxlength="255"
                                placeholder="Как к вам обращаться"
                            />
                            <p v-if="errorFor('name')" class="text-xs text-destructive">
                                {{ errorFor('name') }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label for="profile-city">Город</Label>
                            <Input
                                id="profile-city"
                                v-model="form.city"
                                :aria-invalid="Boolean(errorFor('city'))"
                                maxlength="100"
                                placeholder="Например, Москва"
                            />
                            <p class="text-xs text-muted-foreground">Необязательно</p>
                            <p v-if="errorFor('city')" class="text-xs text-destructive">
                                {{ errorFor('city') }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <Label for="profile-bio">Описание</Label>
                            <Textarea
                                id="profile-bio"
                                v-model="form.bio"
                                rows="5"
                                maxlength="1000"
                                placeholder="Расскажите о своём опыте и проектах"
                            />
                            <p class="text-xs text-muted-foreground">
                                До 1000 символов. Необязательно.
                            </p>
                            <p v-if="errorFor('bio')" class="text-xs text-destructive">
                                {{ errorFor('bio') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Button type="submit" :disabled="saving">
                                {{ saving ? 'Сохраняем…' : 'Сохранить' }}
                            </Button>

                            <span class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                <ShieldCheck class="size-3.5 text-verified" aria-hidden="true" />
                                Бейдж «Проверенный продавец» появляется после подтверждения домена.
                            </span>
                        </div>
                    </form>
                </Card>
            </template>
        </div>
    </CabinetLayout>
</template>
