<template>
  <Line :data="chartData" :options="options" class="max-h-72" />
</template>

<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  LineElement,
  PointElement,
  LineController,
  CategoryScale,
  LinearScale,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js';

ChartJS.register(LineElement, PointElement, LineController, CategoryScale, LinearScale, Filler, Tooltip, Legend);

const props = defineProps({
  labels: { type: Array, default: () => [] },
  musicPlays: { type: Array, default: () => [] },
  wordPlays: { type: Array, default: () => [] },
});

const chartData = computed(() => ({
  labels: props.labels,
  datasets: [
    {
      label: 'Music Plays',
      data: props.musicPlays,
      borderColor: '#f0b030',
      backgroundColor: 'rgba(240, 176, 48, 0.12)',
      pointBackgroundColor: '#f0b030',
      tension: 0.4,
      fill: true,
      borderWidth: 2,
    },
    {
      label: 'Word Views',
      data: props.wordPlays,
      borderColor: '#0036d6',
      backgroundColor: 'rgba(0, 54, 214, 0.12)',
      pointBackgroundColor: '#0036d6',
      tension: 0.4,
      fill: true,
      borderWidth: 2,
    },
  ],
}));

const options = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: {
      position: 'top',
      align: 'start',
      labels: { color: '#9aa3b8', usePointStyle: true, boxHeight: 8 },
    },
    tooltip: { backgroundColor: '#000e38', titleColor: '#f5f5f7', bodyColor: '#f5f5f7' },
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#9aa3b8' } },
    y: { grid: { color: 'rgba(154,163,184,0.1)' }, ticks: { color: '#9aa3b8' } },
  },
};
</script>
