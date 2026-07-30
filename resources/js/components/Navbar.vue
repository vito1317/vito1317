// resources/js/components/Navbar.vue
<template>
  <nav class="fixed top-0 left-0 w-full z-50 bg-gray-900/50 backdrop-blur-sm border-b border-gray-800/50">
    <div class="container mx-auto px-3 sm:px-4 flex justify-between items-center h-16 sm:h-20">
      <router-link to="/" class="text-xl sm:text-2xl font-bold hover:text-teal-400 transition-colors">
        vito1317
      </router-link>
      <div class="flex items-center space-x-3 sm:space-x-8 text-sm sm:text-base overflow-x-auto whitespace-nowrap">
        <router-link to="/" class="nav-link">首頁</router-link>
        <router-link to="/news" class="nav-link">新聞報導</router-link>
        <router-link to="/github" class="nav-link">GitHub</router-link>
        <router-link to="/contact" class="nav-link">聯繫我</router-link>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.nav-link {
  @apply text-gray-400 hover:text-white transition-colors duration-200;
}
.router-link-exact-active {
  @apply text-teal-400;
}
</style>