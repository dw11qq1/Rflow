<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { AlertDialogCancel } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <AlertDialogCancel
    data-slot="alert-dialog-cancel"
    :class="
      cn(
        'inline-flex h-9 items-center justify-center gap-1.5 rounded-md border-2 border-border bg-background px-4 text-sm font-semibold text-foreground outline-none transition-all hover:bg-accent disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0',
        props.class,
      )
    "
  >
    <slot />
  </AlertDialogCancel>
</template>
