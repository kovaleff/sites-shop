<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
    siteKey: { type: String, required: true },
});

const emit = defineEmits(['update:token']);

const CALLBACK_NAME = '__sitesShopSmartCaptcha';

onMounted(() => {
    window[CALLBACK_NAME] = (token) => emit('update:token', token ?? '');

    if (document.querySelector('script[data-smartcaptcha]')) {
        return;
    }

    const script = document.createElement('script');

    script.src = 'https://smartcaptcha.yandexcloud.net/captcha.js';
    script.async = true;
    script.defer = true;
    script.dataset.smartcaptcha = 'true';

    document.head.appendChild(script);
});

onBeforeUnmount(() => {
    delete window[CALLBACK_NAME];
});
</script>

<template>
    <div class="smart-captcha" :data-sitekey="siteKey" :data-callback="CALLBACK_NAME"></div>
</template>
