<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { AlertDialogAction } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <AlertDialogAction
    data-slot="alert-dialog-action"
    :class="
      cn(
        'inline-flex h-9 items-center justify-center gap-1.5 rounded-md px-4 text-sm font-semibold outline-none transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0',
        props.class,
      )
    "
  >
    <slot />
  </AlertDialogAction>
</template>
