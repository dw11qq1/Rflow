<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import {
  AlertDialogContent,
  AlertDialogOverlay,
  AlertDialogPortal,
} from "reka-ui"
import { cn } from "@/lib/utils"

defineOptions({ inheritAttrs: false })

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <AlertDialogPortal>
    <AlertDialogOverlay
      data-slot="alert-dialog-overlay"
      class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-foreground/45 backdrop-blur-[2px]"
    />
    <AlertDialogContent
      data-slot="alert-dialog-content"
      v-bind="$attrs"
      :class="
        cn(
          'bg-card text-card-foreground border shadow-lg fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-5 rounded-xl p-0 duration-200 sm:max-w-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 overflow-hidden',
          props.class,
        )
      "
    >
      <slot />
    </AlertDialogContent>
  </AlertDialogPortal>
</template>
