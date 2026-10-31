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
  <input
    data-slot="input"
    :value="modelValue"
    :class="
      cn(
        'file:text-foreground placeholder:text-muted-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-3',
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
        props.class,
      )
    "
    v-bind="attrs"
    @input="emits('update:modelValue', $event.target.value)"
  />
</template>