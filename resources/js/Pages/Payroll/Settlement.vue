<script setup>
defineProps(['settlements', 'grandTotal', 'startDate', 'endDate']);
</script>

<template>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-4">Cálculo de Nómina</h1>
        <div class="mb-4">
            Del {{ startDate }} al {{ endDate }}
        </div>
        
        <div v-for="item in settlements" :key="item.employee.id" class="mb-6 p-4 border rounded shadow">
            <h2 class="text-xl font-semibold">{{ item.employee.first_name }} {{ item.employee.last_name }}</h2>
            <p>Sueldo Base Diario: ${{ item.employee.base_salary }}</p>
            <p>Días trabajados: {{ item.days_worked }}</p>
            <p>Bonos: ${{ item.total_bonuses }}</p>
            <p class="text-lg font-bold text-green-600">Total a Pagar: ${{ item.total_pay }}</p>
        </div>

        <div class="mt-8 text-2xl font-bold">
            Total General: ${{ grandTotal }}
        </div>
    </div>
</template>