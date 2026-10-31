<script setup>
import { useAttrs } from "vue";
import { cn } from "@/lib/utils";

defineOptions({ inheritAttrs: false });

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
  modelValue: { type: [String, Number], required: false },
});

const emits = defineEmits(["update:modelValue"]);
const attrs = useAttrs();
</script>

<template>
  <textarea
    data-slot="textarea"
    :value="modelValue"
    :class="
      cn(
        'placeholder:text-muted-foreground border-input flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50',
        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-3',
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
        props.class,
      )
    "
    v-bind="attrs"
    @input="emits('update:modelValue', $event.target.value)"
  />
</template>