<template>
  <div class="pt-20">
    <header class="container mx-auto px-4 py-24 text-center">
      <h1 class="text-5xl md:text-7xl font-bold mb-4">
        vito1317
      </h1>
      <p class="text-xl md:text-2xl text-gray-400">
        資安研發經理 × 全端工程師 × AI 應用實踐者
      </p>
    </header>

    <AboutMe />

    <Experience />

    <main class="container mx-auto px-4 pb-24">
      <h2 class="text-4xl font-bold mb-12 text-center">作品集</h2>
      
      <div v-if="projects && projects.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <ProjectCard v-for="project in projects" :key="project.id" :project="project" />
      </div>

      <div v-else class="text-center text-gray-500">
        作品載入中...
      </div>

    </main>
    
    <footer class="text-center py-12 mt-12 bg-black/20 backdrop-blur-sm">
      <div class="flex justify-center items-center space-x-8 mb-6">
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

  </div>
</template>

<script setup>
defineOptions({
  name: 'HomePage'
});
import { onMounted, computed } from 'vue';
import { useProjectStore } from '../stores/projectStore';
import ProjectCard from '../components/ProjectCard.vue';
import AboutMe from '../components/AboutMe.vue';
import Experience from '../components/Experience.vue';

const store = useProjectStore();
const projects = computed(() => store.projects);

onMounted(async () => {
  if (!store.loaded || !projects.value.length) {
    try {
      await store.fetchProjects();
    } catch (err) {
      console.error('[HomePage] 載入作品失敗', err);
    }
  }
});
</script>
