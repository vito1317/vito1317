<template>
  <div>
  <div class="container mx-auto px-4 py-12 sm:py-24 pt-20">
    <h1 v-decrypt class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4 text-center" data-aos="fade-down">GitHub 專區</h1>
    <p class="text-gray-400 text-center mb-10 sm:mb-14 max-w-2xl mx-auto" data-aos="fade-down" data-aos-delay="100">
      自動追蹤我在 GitHub 上的原創專案，依最近 {{ recentDays }} 天的 commit 活躍度排序
    </p>

    <GithubGraphScroll :repos="repos" />

    <!-- 統計摘要 -->
    <div v-if="!loading && !error && repos.length" class="flex flex-wrap justify-center gap-4 sm:gap-6 mb-10 sm:mb-14" data-aos="fade-up">
      <div class="stat-chip">
        <span class="text-2xl font-bold text-teal-400">{{ activeRepos.length }}</span>
        <span class="text-gray-400 text-sm">活躍專案</span>
      </div>
      <div class="stat-chip">
        <span class="text-2xl font-bold text-teal-400">{{ totalRecentCommits }}</span>
        <span class="text-gray-400 text-sm">近 {{ recentDays }} 天 commits</span>
      </div>
      <div class="stat-chip">
        <span class="text-2xl font-bold text-teal-400">{{ repos.length }}</span>
        <span class="text-gray-400 text-sm">原創專案</span>
      </div>
    </div>

    <!-- 載入中骨架 -->
    <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
      <div v-for="n in 6" :key="n" class="p-6 rounded-2xl bg-gray-800/50 border border-gray-700/50 animate-pulse">
        <div class="h-6 bg-gray-700/70 rounded w-2/3 mb-4"></div>
        <div class="h-4 bg-gray-700/50 rounded w-full mb-2"></div>
        <div class="h-4 bg-gray-700/50 rounded w-5/6 mb-6"></div>
        <div class="flex gap-3">
          <div class="h-4 bg-gray-700/50 rounded w-16"></div>
          <div class="h-4 bg-gray-700/50 rounded w-12"></div>
          <div class="h-4 bg-gray-700/50 rounded w-12"></div>
        </div>
      </div>
    </div>

    <!-- 錯誤狀態 -->
    <div v-else-if="error" class="text-center py-16">
      <p class="text-gray-400 mb-6">無法載入 GitHub 資料，請稍後再試。</p>
      <button @click="fetchRepos" class="px-6 py-2 rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/40 hover:bg-teal-500/30 transition-colors">
        重新載入
      </button>
    </div>

    <template v-else>
      <!-- 活躍專案 -->
      <section v-if="activeRepos.length" class="mb-14 sm:mb-20">
        <h2 class="section-title" data-aos="fade-right">
          <span class="relative flex h-3 w-3 mr-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-teal-500"></span>
          </span>
          最近活躍
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
          <RepoCard
            v-for="(repo, index) in activeRepos"
            :key="repo.full_name"
            :repo="repo"
            :recent-days="recentDays"
            :max-commits="maxCommits"
            :index="index"
            active
          />
        </div>
      </section>

      <!-- 其他原創專案 -->
      <section v-if="quietRepos.length">
        <h2 class="section-title" data-aos="fade-right">
          <span class="inline-flex rounded-full h-3 w-3 bg-gray-500 mr-3"></span>
          其他原創專案
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
          <RepoCard
            v-for="(repo, index) in quietRepos"
            :key="repo.full_name"
            :repo="repo"
            :recent-days="recentDays"
            :max-commits="maxCommits"
            :index="index"
          />
        </div>
      </section>

      <p v-if="generatedAt" class="text-center text-gray-600 text-xs mt-12">
        資料來源：GitHub API・每小時自動更新・上次更新 {{ formatDateTime(generatedAt) }}
      </p>
    </template>
  </div>

  <footer class="text-center py-12 mt-12 bg-black/20 backdrop-blur-sm">
    <a
      href="https://github.com/vito1317"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition-colors mb-4"
    >
      <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.168 6.839 9.492.5.092.682-.217.682-.482 0-.237-.009-.868-.014-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.031-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.378.203 2.398.1 2.651.64.7 1.03 1.595 1.03 2.688 0 3.848-2.338 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.001 10.001 0 0022 12c0-5.523-4.477-10-10-10z" clip-rule="evenodd" /></svg>
      追蹤我的 GitHub
    </a>
    <p class="text-gray-600 text-sm">
      返回 <router-link to="/" class="text-teal-400 hover:underline">首頁</router-link>
    </p>
  </footer>
  </div>
</template>

<script setup>
defineOptions({
  name: 'GithubPage',
});
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import RepoCard from '../components/RepoCard.vue';
import GithubGraphScroll from '../components/GithubGraphScroll.vue';

const repos = ref([]);
const recentDays = ref(30);
const generatedAt = ref(null);
const loading = ref(true);
const error = ref(false);

const activeRepos = computed(() => repos.value.filter((r) => r.recent_commits > 0));
const quietRepos = computed(() => repos.value.filter((r) => r.recent_commits === 0));
const totalRecentCommits = computed(() => repos.value.reduce((sum, r) => sum + r.recent_commits, 0));
const maxCommits = computed(() => Math.max(1, ...repos.value.map((r) => r.recent_commits)));

const fetchRepos = async () => {
  loading.value = true;
  error.value = false;
  try {
    const res = await axios.get('/api/github/repos');
    repos.value = res.data.repos ?? [];
    recentDays.value = res.data.recent_days ?? 30;
    generatedAt.value = res.data.generated_at;
    if (!repos.value.length) error.value = true;
  } catch (err) {
    console.error('[GithubPage] 載入 GitHub 資料失敗', err);
    error.value = true;
  } finally {
    loading.value = false;
  }
};

const formatDateTime = (iso) => {
  try {
    return new Date(iso).toLocaleString('zh-TW', { dateStyle: 'medium', timeStyle: 'short', hour12: false });
  } catch {
    return iso;
  }
};

onMounted(fetchRepos);
</script>

<style scoped>
.stat-chip {
  @apply flex flex-col items-center px-6 py-3 rounded-2xl bg-gray-800/50 backdrop-blur-sm border border-gray-700/50 min-w-[8.5rem];
}
.section-title {
  @apply flex items-center text-xl sm:text-2xl font-bold mb-6 sm:mb-8 text-gray-200;
}
</style>
