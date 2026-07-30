import './bootstrap';
import '../css/app.css';
import '../css/tech-motion.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import { initTechReveal } from './utils/techReveal';
import { decryptDirective } from './utils/decryptDirective';

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.directive('decrypt', decryptDirective);
app.mount('#app');

initTechReveal(document.getElementById('app'));
