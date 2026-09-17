<template>
  <div class="pt-20">
    <header class="container mx-auto px-4 py-16 sm:py-24 text-center">
      <h1 v-decrypt class="text-4xl sm:text-5xl md:text-7xl font-bold mb-3 sm:mb-4">vito1317</h1>
      <p class="text-base sm:text-xl md:text-2xl text-gray-400 leading-relaxed">
        資安研發經理 × 全端工程師 × AI 應用實踐者
      </p>
    </header>

    <Fixed3DScroll />

    <AboutMe />

    <PinnedStory />

    <Experience />

    <CodeFieldScroll />

    <PortfolioFieldScroll />

    <main class="container mx-auto px-4 pb-16 sm:pb-24">
      <h2 class="text-3xl sm:text-4xl font-bold mb-8 sm:mb-12 text-center">作品集</h2>

      <div v-if="projects && projects.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
        <ProjectCard v-for="project in projects" :key="project.id" :project="project" />
      </div>

      <div v-else class="text-center text-gray-500">
        作品載入中...
      </div>

    </main>

    <footer class="text-center py-10 sm:py-12 mt-8 sm:mt-12 bg-black/20 backdrop-blur-sm">
      <div class="flex justify-center items-center space-x-6 sm:space-x-8 mb-6">
        <a href="https://github.com/vito1317" target="_blank" rel="noopener noreferrer" title="GitHub" class="text-gray-500 hover:text-white transition-colors duration-300">
          <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.168 6.839 9.492.5.092.682-.217.682-.482 0-.237-.009-.868-.014-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.031-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.378.203 2.398.1 2.651.64.7 1.03 1.595 1.03 2.688 0 3.848-2.338 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.001 10.001 0 0022 12c0-5.523-4.477-10-10-10z" clip-rule="evenodd" /></svg>
        </a>
        <a href="mailto:service@vito1317.com" title="Email" class="text-gray-500 hover:text-white transition-colors duration-300">
          <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M1.75 3A1.75 1.75 0 000 4.75v14.5C0 20.216.784 21 1.75 21h20.5A1.75 1.75 0 0024 19.25V4.75A1.75 1.75 0 0022.25 3H1.75zM1.5 4.75a.25.25 0 01.25-.25h20.5a.25.25 0 01.25.25v.54l-10.72 6.8L1.5 5.29v-.54zm.25 14.5a.25.25 0 01-.25-.25V7.32l10.25 6.54a.75.75 0 00.94 0L22.5 7.32v12.18a.25.25 0 01-.25-.25H1.75z"/></svg>
        </a>
        <a href="https://www.instagram.com/vito095311" target="_blank" rel="noopener noreferrer" title="Instagram" class="text-gray-500 hover:text-white transition-colors duration-300">
          <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85s-.012 3.584-.07 4.85c-.148 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07s-3.584-.012-4.85-.07c-3.252-.148-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.85s.012-3.584.07-4.85C2.253 3.862 3.769 2.316 7.021 2.17c1.266-.058 1.644-.069 4.85-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948s.014 3.667.072 4.947c.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072s3.667-.014 4.947-.072c4.358-.2 6.78-2.618 6.98-6.98.059-1.281.073-1.689.073-4.948s-.014-3.667-.072-4.947c-.2-4.358-2.618-6.78-6.98-6.98C15.667.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.88 1.44 1.44 0 000-2.88z"></path>
          </svg>
        </a>
      </div>
      <p class="text-gray-600 text-sm">
        © 2026 vito1317. All Rights Reserved.
      </p>
    </footer>

    <transition name="news-hint-fade">
      <div v-if="nearBottom" class="news-jump-hint" aria-hidden="true">
        <span class="news-jump-hint__label">繼續下滑前往 · 新聞報導</span>
        <span class="news-jump-hint__track"><span class="news-jump-hint__fill" :style="{ width: overscrollPct + '%' }"></span></span>
        <span class="news-jump-hint__arrow">↓</span>
      </div>
    </transition>

  </div>
</template>

<script setup>
defineOptions({
  name: 'HomePage'
});
import { onMounted, onUnmounted, computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useProjectStore } from '../stores/projectStore';
import ProjectCard from '../components/ProjectCard.vue';
import AboutMe from '../components/AboutMe.vue';
import Experience from '../components/Experience.vue';
import CodeFieldScroll from '../components/CodeFieldScroll.vue';
import PortfolioFieldScroll from '../components/PortfolioFieldScroll.vue';
import PinnedStory from '../components/PinnedStory.vue';
import Fixed3DScroll from '../components/Fixed3DScroll.vue';

const store = useProjectStore();
const projects = computed(() => store.projects);

// 首頁滑到底、繼續往下滑 → 自動跳到「新聞報導」頁
const router = useRouter();
const nearBottom = ref(false);
const overscroll = ref(0);
const OVERSCROLL_THRESHOLD = 260; // 到底後仍需累積這麼多下滑量才跳頁，避免一到底就誤觸
const overscrollPct = computed(() => Math.min(100, (overscroll.value / OVERSCROLL_THRESHOLD) * 100));
let navigating = false;

const isAtBottom = () => {
  const doc = document.documentElement;
  // 頁面本身要夠長（可捲動）才啟用，避免內容過短時一載入就在底部
  return doc.scrollHeight > window.innerHeight + 200
    && window.innerHeight + window.scrollY >= doc.scrollHeight - 4;
};

const goToNews = () => {
  if (navigating) return;
  navigating = true;
  overscroll.value = OVERSCROLL_THRESHOLD;
  router.push('/news');
};

const onScroll = () => {
  nearBottom.value = isAtBottom();
  if (!nearBottom.value) overscroll.value = 0;
};

const onWheel = (e) => {
  if (navigating) return;
  if (e.deltaY > 0 && isAtBottom()) {
    overscroll.value += e.deltaY;
    if (overscroll.value >= OVERSCROLL_THRESHOLD) goToNews();
  } else if (e.deltaY < 0) {
    overscroll.value = 0;
  }
};

let touchY = null;
const onTouchStart = (e) => { touchY = e.touches[0].clientY; };
const onTouchMove = (e) => {
  if (navigating || touchY === null) return;
  const y = e.touches[0].clientY;
  const dy = touchY - y; // 正值 = 手指往上滑 = 頁面往下捲
  touchY = y;
  if (dy > 0 && isAtBottom()) {
    overscroll.value += dy * 2.4; // 觸控位移較小，放大累積速度
    if (overscroll.value >= OVERSCROLL_THRESHOLD) goToNews();
  } else if (dy < 0) {
    overscroll.value = 0;
  }
};
const onTouchEnd = () => { touchY = null; if (!navigating) overscroll.value = 0; };

onMounted(async () => {
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('wheel', onWheel, { passive: true });
  window.addEventListener('touchstart', onTouchStart, { passive: true });
  window.addEventListener('touchmove', onTouchMove, { passive: true });
  window.addEventListener('touchend', onTouchEnd, { passive: true });
  onScroll();

  if (!store.loaded || !projects.value.length) {
    try {
      await store.fetchProjects();
    } catch (err) {
      console.error('[HomePage] 載入作品失敗', err);
    }
  }
});

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('wheel', onWheel);
  window.removeEventListener('touchstart', onTouchStart);
  window.removeEventListener('touchmove', onTouchMove);
  window.removeEventListener('touchend', onTouchEnd);
});
</script>

<style scoped>
.news-jump-hint {
  position: fixed;
  left: 50%;
  bottom: calc(18px + env(safe-area-inset-bottom, 0px));
  transform: translateX(-50%);
  z-index: 60;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 18px;
  border-radius: 999px;
  background: rgba(9, 12, 22, 0.72);
  border: 1px solid rgba(45, 212, 191, 0.35);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45), 0 0 18px rgba(45, 212, 191, 0.12);
  backdrop-filter: blur(8px);
  pointer-events: none;
  font-size: 12px;
  letter-spacing: 0.08em;
  color: #cdeee7;
  white-space: nowrap;
}
.news-jump-hint__track {
  position: relative;
  width: 84px;
  height: 3px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  overflow: hidden;
}
.news-jump-hint__fill {
  position: absolute;
  inset: 0 auto 0 0;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #2dd4bf, #22d3ee);
  box-shadow: 0 0 10px rgba(45, 212, 191, 0.7);
  transition: width 0.12s linear;
}
.news-jump-hint__arrow {
  color: #2dd4bf;
  font-weight: 700;
  animation: news-hint-bob 1.4s ease-in-out infinite;
}
@keyframes news-hint-bob { 50% { transform: translateY(3px); } }

.news-hint-fade-enter-active, .news-hint-fade-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.news-hint-fade-enter-from, .news-hint-fade-leave-to { opacity: 0; transform: translate(-50%, 12px); }

@media (prefers-reduced-motion: reduce) {
  .news-jump-hint__arrow { animation: none; }
}
</style>
