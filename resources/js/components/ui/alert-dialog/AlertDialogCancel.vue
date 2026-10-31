<script setup>
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { AlertDialogCancel } from "reka-ui";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
</script>

<template>
  <AlertDialogCancel
    data-slot="alert-dialog-cancel"
    :class="cn(buttonVariants({ variant: 'outline' }), props.class)"
    v-bind="{ ...props, class: undefined }"
  >
    <slot />
  </AlertDialogCancel>
</template>