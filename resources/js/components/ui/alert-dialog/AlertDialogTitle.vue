<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { AlertDialogTitle } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <AlertDialogTitle
    data-slot="alert-dialog-title"
    :class="cn('text-lg font-bold tracking-tight', props.class)"
  >
    <slot />
  </AlertDialogTitle>
</template>
