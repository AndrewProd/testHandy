<script setup>
import { computed } from 'vue'
const props = defineProps({ value: [Object, Array, String, Number, null] })
const text = computed(() =>
  typeof props.value === 'string' ? props.value : JSON.stringify(props.value, null, 2),
)
</script>

<template>
  <pre class="pre-json">{{ text }}</pre>
</template>
