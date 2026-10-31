<script setup>
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { AlertDialogAction } from "reka-ui";

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
  <AlertDialogAction
    data-slot="alert-dialog-action"
    :class="cn(buttonVariants(), props.class)"
    v-bind="{ ...props, class: undefined }"
  >
    <slot />
  </AlertDialogAction>
</template>