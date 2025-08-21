<template>
  <section class="container mx-auto px-4 py-20" id="about-me">
    <div 
      class="bg-gray-800/50 backdrop-blur-sm rounded-2xl p-8 md:p-12 border border-gray-700/50"
      data-aos="fade-up" 
      data-aos-duration="1000"
    >
      <h2 class="text-4xl font-bold mb-12 text-center">關於我 & 技能</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-16">
        
        <div data-aos="fade-right" data-aos-delay="200">
          <h3 class="text-2xl font-semibold mb-4 text-teal-400">我的開發之旅</h3>
          <div class="space-y-4 text-gray-300 leading-relaxed">
            <p>
              你好，我是 vito1317(柯瑋宸)。我的程式設計之旅始於對「解決問題」的純粹熱情。從打造實用的自動化工具，到建構完整的全端應用，我享受將一個想法從零到一，透過程式碼變為現實的整個過程。
            </p>
            <p>
              我的經驗涵蓋了從使用者眼前的介面（Vue.js, JavaScript）到伺服器端的穩固邏輯（Laravel, Java Spring Boot），再到與資料庫的順暢溝通（MySQL）。我不僅僅是寫程式，更是打造流暢、高效的使用者體驗。
            </p>
            <p>
              近年來，我對人工智慧的潛力深深著迷。從開發整合 Gemini 模型的 Discord 機器人，到探索大型語言模型的應用，我正積極地將 AI 技術融入我的技能樹中，渴望在下一個專案中，創造出真正智慧、能與人深度互動的產品。
            </p>
          </div>
        </div>

        <div data-aos="fade-left" data-aos-delay="400">
          <h3 class="text-2xl font-semibold mb-4 text-purple-400">我的技術棧</h3>
          <div class="space-y-6">
            <div>
              <h4 class="font-bold mb-2 text-gray-400">前端 (Frontend)</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">Vue.js</span>
                <span class="skill-tag">JavaScript</span>
                <span class="skill-tag">jQuery</span>
                <span class="skill-tag">Bootstrap</span>
                <span class="skill-tag">HTML5</span>
                <span class="skill-tag">CSS3 / Tailwind</span>
                <span class="skill-tag">Three.js</span>
              </div>
            </div>
            <div>
              <h4 class="font-bold mb-2 text-gray-400">後端 (Backend)</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">PHP / Laravel</span>
                <span class="skill-tag">Java / Spring Boot</span>
                <span class="skill-tag">Python</span>
              </div>
            </div>
            <div>
              <h4 class="font-bold mb-2 text-gray-400">AI & 資料庫</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">Google Gemini</span>
                <span class="skill-tag">MySQL</span>
                <span class="skill-tag">SQLite</span>
              </div>
            </div>
            <div>
              <h4 class="font-bold mb-2 text-gray-400">其他工具 & 平台</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">Git / GitHub</span>
                <span class="skill-tag">Discord API</span>
                <span class="skill-tag">Chrome Extension</span>
                <span class="skill-tag">Linux / Apache</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
</script>

<style scoped>
.skill-tag {
  @apply bg-gray-700 text-gray-300 px-3 py-1 rounded-full text-sm font-medium transition-colors duration-200 hover:bg-gray-600;
}
</style>