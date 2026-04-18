<template>
  <div class="bg-gray-900 min-h-screen text-white font-sans">
    
    <Transition name="fade">
      <LoadingScreen v-if="!projectsLoaded" />
    </Transition>
    
    <ThreeDBackground />

    <TechHUD v-if="projectsLoaded" />

    <Navbar />

    <div v-if="projectsLoaded" class="relative z-10 pt-20"> 
      <router-view v-slot="{ Component }">
        <Transition name="page-fade">
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

import ThreeDBackground from './components/ThreeDBackground.vue';
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
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.3s ease;
}
.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}

.fade-leave-active {
  transition: opacity 0.75s ease-in-out;
}
.fade-leave-to {
  opacity: 0;
}
</style>