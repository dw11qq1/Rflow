<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { AlertDialogDescription } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <AlertDialogDescription
    data-slot="alert-dialog-description"
    :class="cn('text-sm text-muted-foreground leading-relaxed', props.class)"
  >
    <slot />
  </AlertDialogDescription>
</template>
