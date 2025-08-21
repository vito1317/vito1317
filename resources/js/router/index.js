import { createRouter, createWebHistory } from 'vue-router';
import HomePage from '../pages/HomePage.vue';
import NewsPage from '../pages/NewsPage.vue';
import ContactPage from '../pages/ContactPage.vue';

const routes = [
  {
    path: '/',
    name: 'Home',
    component: HomePage,
    meta: { title: 'vito1317 (柯瑋宸) - 全端開發者 & AI 愛好者 | 個人網站' }
  },
  {
    path: '/news',
    name: 'News',
    component: NewsPage,
    meta: { title: 'vito1317 (柯瑋宸) - 新聞報導 | 個人網站' }
  },
  {
    path: '/contact',
    name: 'Contact',
    component: ContactPage,
    meta: { title: 'vito1317 (柯瑋宸) - 聯繫我 | 個人網站' }
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    return { top: 0 };
  },
});

router.afterEach((to, from) => {
  if (to.meta.title) {
    document.title = to.meta.title;
  } else {
    document.title = 'vito1317 (柯瑋宸) - 個人網站';
  }
});

export default router;