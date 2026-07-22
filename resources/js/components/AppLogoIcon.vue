<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <!-- 背景：项目主色靛蓝圆角方块 #5C5CD6 -->
        <rect x="0" y="0" width="24" height="24" rx="6" fill="#5C5CD6" />
        <!-- 飘动卡片 1（上，半透白、左旋） -->
        <g transform="rotate(-8 11 10)">
            <rect x="6.5" y="4.5" width="9" height="11" rx="2" fill="#FFFFFF" fill-opacity="0.9" />
        </g>
        <!-- 飘动卡片 2（下，更透、右旋） -->
        <g transform="rotate(7 13 12)">
            <rect x="9" y="7" width="7.5" height="10" rx="2" fill="#FFFFFF" fill-opacity="0.6" />
        </g>
        <!-- 底部飘带：呼应「流动」 -->
        <path
            d="M6 18 Q12 20.2 18 18"
            stroke="#FFFFFF"
            stroke-width="1.4"
            stroke-linecap="round"
            stroke-opacity="0.55"
        />
    </svg>
</template>
