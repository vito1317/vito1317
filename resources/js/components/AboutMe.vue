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
              你好，我是 vito1317 (柯瑋宸)，一位專注於資訊安全與 AI 應用的全端工程師。目前就讀於 <span class="text-teal-300">中華科技大學</span>，同時擔任 <span class="text-teal-300">智能悅信資訊 (IntelliTrust)</span> 的資安暨研發經理，主導企業級 SOC 與下一代 WAF 的研發，並在 <span class="text-teal-300">聚騰科技 (GTBooster)</span> 擔任前端工程師。
            </p>
            <p>
              我的技術路線橫跨從使用者介面（Vue.js、Three.js）到伺服器端邏輯（Laravel、Spring Boot、Python），再到滲透測試、WAF 架構與 CVE 漏洞研究。對我而言，寫程式不只是實作功能，更是用工程思維打造安全、流暢且可持續演進的系統。
            </p>
            <p>
              近年來我深耕於 AI 資安領域——將 Gemma / Llama 等大型語言模型進行 Fine-tuning 與 RAG 架構落地部署，打造能自動識別漏洞並提出修補建議的 AI 助手。我也曾獨立完成校園系統滲透測試並通報 CVSS 10 等級漏洞，獲延攬擔任 <span class="text-teal-300">中華科技大學</span> 資訊技術顧問。
            </p>
          </div>
        </div>

        <div data-aos="fade-left" data-aos-delay="400">
          <h3 class="text-2xl font-semibold mb-4 text-purple-400">我的技術棧</h3>
          <div class="space-y-6">
            <div>
              <h4 class="font-bold mb-2 text-gray-400">資訊安全 (Security)</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">滲透測試</span>
                <span class="skill-tag">WAF 架構設計</span>
                <span class="skill-tag">CVE 漏洞研究</span>
                <span class="skill-tag">SOC 建置</span>
                <span class="skill-tag">IDS / IPS</span>
              </div>
            </div>
            <div>
              <h4 class="font-bold mb-2 text-gray-400">AI &amp; LLM</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">LLM Fine-tuning</span>
                <span class="skill-tag">Gemma / Llama</span>
                <span class="skill-tag">RAG 架構</span>
                <span class="skill-tag">地端 AI 部署</span>
                <span class="skill-tag">Google Gemini</span>
              </div>
            </div>
            <div>
              <h4 class="font-bold mb-2 text-gray-400">前端 (Frontend)</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">Vue.js</span>
                <span class="skill-tag">JavaScript</span>
                <span class="skill-tag">Three.js</span>
                <span class="skill-tag">Tailwind CSS</span>
                <span class="skill-tag">Bootstrap</span>
              </div>
            </div>
            <div>
              <h4 class="font-bold mb-2 text-gray-400">後端 (Backend)</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">PHP / Laravel</span>
                <span class="skill-tag">Java / Spring Boot</span>
                <span class="skill-tag">Python</span>
                <span class="skill-tag">MySQL</span>
              </div>
            </div>
            <div>
              <h4 class="font-bold mb-2 text-gray-400">基礎設施 (Infrastructure)</h4>
              <div class="flex flex-wrap gap-2">
                <span class="skill-tag">Linux / Apache</span>
                <span class="skill-tag">NVIDIA GPU 配置</span>
                <span class="skill-tag">RAID / JBOD</span>
                <span class="skill-tag">Git / GitHub</span>
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