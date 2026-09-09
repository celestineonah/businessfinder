<script setup lang="ts">
import logoImage from '../../../images/brand/businessfinder-nigeria-logo.png';

withDefaults(
    defineProps<{
        alt?: string;
    }>(),
    {
        alt: 'BusinessFinder Nigeria',
    },
);
</script>

<template>
    <img
        :src="logoImage"
        :alt="alt"
        class="block object-contain"
        decoding="async"
    />
</template>
