<template>
  <div
    class="flex flex-col p-6 rounded-2xl transition-all duration-300 bg-gray-800/50 backdrop-blur-sm border shadow-lg hover:-translate-y-2"
    :class="active ? 'border-teal-500/30 hover:shadow-teal-500/10' : 'border-gray-700/50 hover:shadow-cyan-500/10'"
    data-aos="fade-up"
    :data-aos-delay="100 * (index % 3)"
  >
    <div class="flex items-start justify-between gap-3 mb-2">
      <a :href="repo.html_url" target="_blank" rel="noopener noreferrer" class="min-w-0">
        <h3 class="text-xl font-semibold text-gray-100 hover:text-teal-400 transition-colors break-words">
          {{ repo.name }}
        </h3>
      </a>
      <span
        v-if="repo.recent_commits > 0"
        class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-500/15 text-teal-300 border border-teal-500/30"
        :title="`最近 ${recentDays} 天有 ${repo.recent_commits} 筆 commit`"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3.5" />
          <path stroke-linecap="round" d="M12 2v6.5M12 15.5V22" />
        </svg>
        {{ repo.recent_commits }}{{ repo.recent_commits >= 100 ? '+' : '' }} commits
      </span>
    </div>

    <p class="text-gray-300 text-sm leading-relaxed flex-grow">
      {{ repo.description || '（尚無描述）' }}
    </p>

    <!-- 活躍度條 -->
    <div v-if="repo.recent_commits > 0" class="mt-4">
      <div class="h-1.5 rounded-full bg-gray-700/60 overflow-hidden">
        <div
          class="h-full rounded-full bg-gradient-to-r from-teal-500 to-cyan-400 transition-all duration-700"
          :style="{ width: activityPercent + '%' }"
        ></div>
      </div>
    </div>

    <div v-if="repo.topics && repo.topics.length" class="mt-4 flex flex-wrap gap-2">
      <span
        v-for="topic in repo.topics.slice(0, 5)"
        :key="topic"
        class="inline-block bg-gray-700/80 text-gray-300 px-2.5 py-0.5 rounded-full text-xs"
      >
        {{ topic }}
      </span>
    </div>

    <div class="flex items-center flex-wrap gap-x-4 gap-y-2 mt-4 pt-4 border-t border-gray-700/50 text-sm text-gray-400">
      <span v-if="repo.language" class="inline-flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: languageColor }"></span>
        {{ repo.language }}
      </span>
      <span class="inline-flex items-center gap-1" title="Stars">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16"><path d="M8 .25a.75.75 0 01.673.418l1.882 3.815 4.21.612a.75.75 0 01.416 1.279l-3.046 2.97.719 4.192a.75.75 0 01-1.088.791L8 12.347l-3.766 1.98a.75.75 0 01-1.088-.79l.72-4.194L.818 6.374a.75.75 0 01.416-1.28l4.21-.611L7.327.668A.75.75 0 018 .25z"/></svg>
        {{ repo.stargazers_count }}
      </span>
      <span class="inline-flex items-center gap-1" title="Forks">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16"><path d="M5 5.372v.878c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-.878a2.25 2.25 0 111.5 0v.878a2.25 2.25 0 01-2.25 2.25h-1.5v2.128a2.251 2.251 0 11-1.5 0V8.5h-1.5A2.25 2.25 0 013.5 6.25v-.878a2.25 2.25 0 111.5 0zM5 3.25a.75.75 0 10-1.5 0 .75.75 0 001.5 0zm6.75.75a.75.75 0 100-1.5.75.75 0 000 1.5zm-3 8.75a.75.75 0 10-1.5 0 .75.75 0 001.5 0z"/></svg>
        {{ repo.forks_count }}
      </span>
      <span class="ml-auto text-xs text-gray-500" :title="repo.pushed_at">
        {{ relativeTime(repo.pushed_at) }}
      </span>
    </div>

    <div class="flex justify-end gap-4 mt-4">
      <a
        :href="repo.html_url"
        target="_blank"
        rel="noopener noreferrer"
        class="text-gray-400 hover:text-white transition-colors duration-200 text-sm"
      >
        GitHub
      </a>
      <a
        v-if="repo.homepage"
        :href="repo.homepage"
        target="_blank"
        rel="noopener noreferrer"
        class="text-teal-400 hover:text-teal-300 transition-colors duration-200 text-sm"
      >
        線上預覽
      </a>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  repo: { type: Object, required: true },
  recentDays: { type: Number, default: 30 },
  maxCommits: { type: Number, default: 1 },
  index: { type: Number, default: 0 },
  active: { type: Boolean, default: false },
});

// GitHub linguist 官方語言色
const LANGUAGE_COLORS = {
  PHP: '#4F5D95',
  Python: '#3572A5',
  JavaScript: '#f1e05a',
  TypeScript: '#3178c6',
  Vue: '#41b883',
  Kotlin: '#A97BFF',
  Java: '#b07219',
  Shell: '#89e051',
  HTML: '#e34c26',
  CSS: '#563d7c',
  Blade: '#f7523f',
  'C++': '#f34b7d',
  C: '#555555',
  'C#': '#178600',
  Go: '#00ADD8',
  Rust: '#dea584',
  Swift: '#F05138',
  Dart: '#00B4AB',
  Ruby: '#701516',
};

const languageColor = computed(() => LANGUAGE_COLORS[props.repo.language] || '#8b949e');

const activityPercent = computed(() =>
  Math.max(8, Math.round((props.repo.recent_commits / props.maxCommits) * 100))
);

const relativeTime = (iso) => {
  const diffMs = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diffMs / 60000);
  if (minutes < 60) return `${Math.max(1, minutes)} 分鐘前更新`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} 小時前更新`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days} 天前更新`;
  const months = Math.floor(days / 30);
  if (months < 12) return `${months} 個月前更新`;
  return `${Math.floor(months / 12)} 年前更新`;
};
</script>
