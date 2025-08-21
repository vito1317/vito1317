<template>
  <div
    class="flex flex-col rounded-2xl overflow-hidden transform hover:-translate-y-2 transition-all duration-300 bg-gray-800/50 backdrop-blur-sm border border-gray-700/50 shadow-lg hover:shadow-cyan-500/10"
    data-aos="fade-up"
    data-aos-duration="800"
  >
    
    <img
      v-if="project.image"
      :src="project.image"
      :alt="project.title"
      class="w-full h-48 object-cover"
    />

    <div class="p-6 flex flex-col flex-grow">
      
      <div class="flex-grow">
        <h3 class="text-2xl font-bold mb-2">{{ project.title }}</h3>
        <p class="text-gray-300 mb-4 text-sm leading-relaxed">{{ project.description }}</p>
        
        <div v-if="project.technologies" class="mb-4 flex flex-wrap gap-2">
          <span v-for="tech in project.technologies.split(',')" :key="tech"
            class="inline-block bg-gray-700/80 text-gray-200 px-3 py-1 rounded-full text-sm font-medium transition-colors duration-200 hover:bg-gray-600/80">
            {{ tech.trim() }}
          </span>
        </div>
      </div>
      
      <div class="flex justify-end space-x-4 mt-auto pt-4 border-t border-gray-700/50">
        <a 
          v-if="project.github_url" 
          :href="project.github_url" 
          target="_blank" 
          class="text-gray-400 hover:text-white transition-colors duration-200"
        >
          GitHub
        </a>
        <a 
          v-if="project.live_url" 
          :href="project.live_url" 
          target="_blank" 
          class="text-teal-400 hover:text-teal-300 transition-colors duration-200"
        >
          線上預覽
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  project: Object,
});
</script>