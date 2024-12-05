<script setup>
import {Head} from '@inertiajs/vue3'

defineProps({user: Object})
</script>

<template>
    <Head title="Welcome"/>
    <h1>Welcome</h1>
    <p>Hello {{ user.name }}, welcome to your first Inertia app!</p>
</template>
