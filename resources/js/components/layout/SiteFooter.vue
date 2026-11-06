<script setup>
const year = new Date().getFullYear();

const legalLinks = [
    { href: '/offer', label: 'Публичная оферта' },
    { href: '/privacy', label: 'Политика конфиденциальности' },
    { href: '/offer#section-10', label: 'Реквизиты' },
    { href: '/pricing', label: 'Тарифы' },
];
</script>

<template>
    <footer class="mt-16 border-t border-border/70">
        <div
            class="mx-auto flex w-full max-w-[1440px] flex-col gap-3 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
        >
            <p>© {{ year }} Sites-shop. Маркетплейс готовых сайтов.</p>

            <nav class="flex flex-wrap items-center gap-x-4 gap-y-2" aria-label="Юридическая информация">
                <a
                    v-for="link in legalLinks"
                    :key="link.href"
                    :href="link.href"
                    class="transition-colors hover:text-foreground"
                >
                    {{ link.label }}
                </a>
            </nav>
        </div>
    </footer>
</template>
