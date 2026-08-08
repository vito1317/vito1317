<template>
  <div>
  <div class="container mx-auto px-4 py-12 sm:py-24 pt-20">
    <h1 v-decrypt class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4 text-center" data-aos="fade-down">演算法研究</h1>
    <p class="text-gray-400 text-center mb-12 sm:mb-16 max-w-3xl mx-auto leading-relaxed" data-aos="fade-down" data-aos-delay="100">
      原創演算法的研究紀錄——包含預先登記的證偽條件、誠實發表的負面結果，
      以及在生產環境持續運作的機器學習引擎
    </p>

    <AlgorithmFieldScroll />

    <!-- 載入中骨架 -->
    <div v-if="loading" class="max-w-5xl mx-auto space-y-8">
      <div v-for="n in 3" :key="n" class="p-8 rounded-2xl bg-gray-800/50 border border-gray-700/50 animate-pulse">
        <div class="h-7 bg-gray-700/70 rounded w-1/2 mb-3"></div>
        <div class="h-4 bg-gray-700/50 rounded w-2/3 mb-6"></div>
        <div class="h-4 bg-gray-700/50 rounded w-full mb-2"></div>
        <div class="h-4 bg-gray-700/50 rounded w-full mb-2"></div>
        <div class="h-4 bg-gray-700/50 rounded w-4/5"></div>
      </div>
    </div>

    <!-- 錯誤狀態 -->
    <div v-else-if="error" class="text-center py-16">
      <p class="text-gray-400 mb-6">無法載入演算法資料，請稍後再試。</p>
      <button @click="fetchAlgorithms" class="px-6 py-2 rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/40 hover:bg-teal-500/30 transition-colors">
        重新載入
      </button>
    </div>

    <div v-else class="max-w-5xl mx-auto space-y-10 sm:space-y-14">
      <article
        v-for="(algo, index) in algorithms"
        :key="algo.id"
        class="rounded-2xl overflow-hidden bg-gray-800/50 backdrop-blur-sm border border-gray-700/50 shadow-lg transition-all duration-300 hover:shadow-cyan-500/10"
        data-aos="fade-up"
        :data-aos-delay="index === 0 ? 0 : 100"
      >
        <!-- 標題列 -->
        <header class="p-6 sm:p-8 pb-0">
          <div class="flex flex-wrap items-center gap-3 mb-3">
            <span class="text-xs font-medium tracking-wider uppercase text-gray-500">{{ algo.category }}</span>
            <span
              class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border"
              :class="statusClass(algo.status_type)"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(algo.status_type)"></span>
              {{ algo.status_label }}
            </span>
          </div>
          <h2 class="text-2xl sm:text-3xl font-bold text-gray-100">{{ algo.title }}</h2>
          <p class="text-teal-400/90 mt-2">{{ algo.tagline }}</p>
        </header>

        <div class="p-6 sm:p-8 space-y-6">
          <!-- 問題與方法 -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl bg-gray-900/40 border border-gray-700/40 p-5">
              <h3 class="algo-block-title text-rose-300/90">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                要解決的問題
              </h3>
              <p class="text-gray-300 text-sm leading-relaxed">{{ algo.problem }}</p>
            </div>
            <div class="rounded-xl bg-gray-900/40 border border-gray-700/40 p-5">
              <h3 class="algo-block-title text-teal-300/90">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                核心構想
              </h3>
              <p class="text-gray-300 text-sm leading-relaxed">{{ algo.approach }}</p>
            </div>
          </div>

          <!-- 公式 -->
          <div v-if="algo.formula" class="rounded-xl bg-black/40 border border-teal-500/20 p-5 overflow-x-auto">
            <pre class="text-teal-200/90 text-xs sm:text-sm font-mono leading-relaxed whitespace-pre">{{ algo.formula }}</pre>
          </div>

          <!-- 關鍵數據 -->
          <div v-if="algo.metrics && algo.metrics.length" class="flex flex-wrap gap-3">
            <div
              v-for="metric in algo.metrics"
              :key="metric.label"
              class="flex flex-col px-4 py-2.5 rounded-xl bg-gray-900/50 border border-gray-700/40"
            >
              <span class="text-teal-400 font-mono font-semibold text-sm sm:text-base">{{ metric.value }}</span>
              <span class="text-gray-500 text-xs mt-0.5">{{ metric.label }}</span>
            </div>
          </div>

          <!-- 研究亮點 -->
          <ul v-if="algo.highlights && algo.highlights.length" class="space-y-2.5">
            <li
              v-for="(point, i) in algo.highlights"
              :key="i"
              class="flex items-start gap-3 text-sm text-gray-300 leading-relaxed"
            >
              <svg class="w-4 h-4 mt-0.5 shrink-0 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              <span>{{ point }}</span>
            </li>
          </ul>

          <!-- 標籤 + 連結 -->
          <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-gray-700/50">
            <span
              v-for="tag in tagList(algo.tags)"
              :key="tag"
              class="inline-block bg-gray-700/80 text-gray-300 px-3 py-1 rounded-full text-xs"
            >
              {{ tag }}
            </span>
            <span class="flex-grow"></span>
            <a
              v-for="link in algo.links || []"
              :key="link.url"
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              class="text-sm text-teal-400 hover:text-teal-300 transition-colors duration-200 whitespace-nowrap"
            >
              {{ link.label }} ↗
            </a>
          </div>
        </div>
      </article>
    </div>
  </div>

  <footer class="text-center py-12 mt-12 bg-black/20 backdrop-blur-sm">
    <p class="text-gray-600 text-sm">
      返回 <router-link to="/" class="text-teal-400 hover:underline">首頁</router-link>
    </p>
  </footer>
  </div>
</template>

<script setup>
defineOptions({
  name: 'AlgorithmsPage',
});
import { ref, onMounted } from 'vue';
import axios from 'axios';
import AlgorithmFieldScroll from '../components/AlgorithmFieldScroll.vue';

const algorithms = ref([]);
const loading = ref(true);
const error = ref(false);

const fetchAlgorithms = async () => {
  loading.value = true;
  error.value = false;
  try {
    const res = await axios.get('/api/algorithms');
    algorithms.value = res.data ?? [];
    if (!algorithms.value.length) error.value = true;
  } catch (err) {
    console.error('[AlgorithmsPage] 載入演算法資料失敗', err);
    error.value = true;
  } finally {
    loading.value = false;
  }
};

const STATUS_CLASSES = {
  positive: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
  negative: 'bg-amber-500/15 text-amber-300 border-amber-500/30',
  production: 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
  research: 'bg-gray-500/15 text-gray-300 border-gray-500/30',
};
const STATUS_DOT_CLASSES = {
  positive: 'bg-emerald-400',
  negative: 'bg-amber-400',
  production: 'bg-cyan-400 animate-pulse',
  research: 'bg-gray-400',
};

const statusClass = (type) => STATUS_CLASSES[type] || STATUS_CLASSES.research;
const statusDotClass = (type) => STATUS_DOT_CLASSES[type] || STATUS_DOT_CLASSES.research;

const tagList = (tags) => (tags ? tags.split(',').map((t) => t.trim()).filter(Boolean) : []);

onMounted(fetchAlgorithms);
</script>

<style scoped>
.algo-block-title {
  @apply flex items-center gap-2 text-sm font-semibold mb-2.5;
}
</style>
