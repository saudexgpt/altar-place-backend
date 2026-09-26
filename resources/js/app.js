import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import { UNAUTHORIZED_EVENT } from './services/api';

const app = createApp(App);

app.use(createPinia());
app.use(router);

const PUBLIC_ROUTE_NAMES = ['landing', 'login', 'register', 'forgot-password', 'reset-password', 'maintenance', 'oauth-callback'];

window.addEventListener(UNAUTHORIZED_EVENT, () => {
  const wasAuthenticated = useAuthStore().forceLogout();

  if (wasAuthenticated && !PUBLIC_ROUTE_NAMES.includes(router.currentRoute.value.name)) {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
  }
});

app.mount('#app');
