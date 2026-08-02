<template>
  <div class="bg-gray-900 min-h-screen text-white font-sans">
    
    <Transition name="fade">
      <LoadingScreen v-if="!projectsLoaded" />
    </Transition>
    
    <TechBackground />

    <TechHUD v-if="projectsLoaded" />

    <Navbar />

    <div v-if="projectsLoaded" class="relative z-10 pt-16 sm:pt-20">
      <router-view v-slot="{ Component }">
        <Transition name="page-warp" mode="out-in">
          <component :is="Component" />
        </Transition>
      </router-view>
    </div>

  </div>
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useProjectStore } from './stores/projectStore';
import { useUiStore } from './stores/uiStore';

import TechBackground from './components/TechBackground.vue';
import LoadingScreen from './components/LoadingScreen.vue';
import Navbar from './components/Navbar.vue';
import TechHUD from './components/TechHUD.vue';

const projectStore = useProjectStore();
const uiStore = useUiStore();

const { loaded: projectsLoaded } = storeToRefs(projectStore);

const route = useRoute();

watch(() => route.fullPath, () => {
  uiStore.triggerWarp();
});

onMounted(() => {
  projectStore.fetchProjects();
});
</script>

<style>
/* 科技感頁面切換：退場比進場快，模糊 + 縱向位移呼應 3D 背景的 warp 效果 */
.page-warp-enter-active {
  transition: opacity 0.32s cubic-bezier(0.16, 1, 0.3, 1),
    transform 0.32s cubic-bezier(0.16, 1, 0.3, 1),
    filter 0.32s ease-out;
}
.page-warp-leave-active {
  transition: opacity 0.18s ease-in, transform 0.18s ease-in, filter 0.18s ease-in;
}
.page-warp-enter-from {
  opacity: 0;
  transform: translateY(14px);
  filter: blur(6px);
}
.page-warp-leave-to {
  opacity: 0;
  transform: translateY(-10px);
  filter: blur(4px);
}

/* 手機省去全頁 blur 濾鏡（GPU 成本高），只保留透明度 + 位移 */
@media (max-width: 767px) {
  .page-warp-enter-from,
  .page-warp-leave-to {
    filter: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .page-warp-enter-active,
  .page-warp-leave-active {
    transition: opacity 0.2s ease;
  }
  .page-warp-enter-from,
  .page-warp-leave-to {
    transform: none;
    filter: none;
  }
}

.fade-leave-active {
  transition: opacity 0.75s ease-in-out;
}
.fade-leave-to {
  opacity: 0;
}
</style>
