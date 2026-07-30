// resources/js/components/Navbar.vue
<template>
  <nav class="fixed top-0 left-0 w-full z-50 bg-gray-900/50 backdrop-blur-sm border-b border-gray-800/50">
    <div class="container mx-auto px-3 sm:px-4 flex items-center gap-3 sm:justify-between sm:gap-6 h-14 sm:h-20">
      <router-link to="/" class="shrink-0 text-lg sm:text-2xl font-bold hover:text-teal-400 transition-colors">
        vito1317
      </router-link>
      <div class="nav-links flex min-w-0 flex-1 items-center justify-start space-x-4 sm:flex-none sm:justify-end sm:space-x-8 text-xs sm:text-base overflow-x-auto whitespace-nowrap">
        <router-link to="/" class="nav-link">首頁</router-link>
        <router-link to="/news" class="nav-link">新聞報導</router-link>
        <router-link to="/github" class="nav-link">GitHub</router-link>
        <router-link to="/algorithms" class="nav-link">演算法</router-link>
        <router-link to="/contact" class="nav-link">聯繫我</router-link>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.nav-links {
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.nav-links::-webkit-scrollbar { display: none; }
.nav-link {
  @apply text-gray-400 hover:text-white transition-colors duration-200;
}
.router-link-exact-active {
  @apply text-teal-400;
}
@media (max-width: 380px) {
  .nav-links { @apply space-x-3 text-[11px]; }
}
</style>
