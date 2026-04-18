<template>
  <section class="container mx-auto px-4 py-20" id="experience">
    <div
      class="bg-gray-800/50 backdrop-blur-sm rounded-2xl p-8 md:p-12 border border-gray-700/50"
      data-aos="fade-up"
      data-aos-duration="1000"
    >
      <h2 class="text-4xl font-bold mb-12 text-center">經歷 &amp; 活動</h2>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">

        <div data-aos="fade-right" data-aos-delay="200">
          <h3 class="text-2xl font-semibold mb-6 text-teal-400">專業經歷</h3>
          <div class="relative border-l-2 border-teal-500/40 pl-6 space-y-8">
            <div
              v-for="(job, index) in experiences"
              :key="job.company"
              class="relative"
              data-aos="fade-up"
              :data-aos-delay="150 * index"
            >
              <span class="absolute -left-[34px] top-1 w-4 h-4 rounded-full bg-teal-400 ring-4 ring-teal-500/20"></span>
              <p class="text-sm text-gray-400 mb-1">{{ job.period }}</p>
              <h4 class="text-lg font-bold text-white">{{ job.title }}</h4>
              <a
                v-if="job.url"
                :href="job.url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-teal-300 hover:text-teal-200 transition-colors text-sm"
              >
                {{ job.company }} ↗
              </a>
              <p v-else class="text-teal-300 text-sm">{{ job.company }}</p>
              <p class="text-gray-300 mt-2 leading-relaxed text-sm">{{ job.description }}</p>
            </div>
          </div>
        </div>

        <div data-aos="fade-left" data-aos-delay="400">
          <h3 class="text-2xl font-semibold mb-6 text-purple-400">演講 &amp; 活動</h3>
          <div class="space-y-6">
            <a
              v-for="(event, index) in events"
              :key="event.title"
              :href="event.url"
              target="_blank"
              rel="noopener noreferrer"
              class="block p-5 rounded-xl bg-gray-900/50 border border-gray-700/50 hover:border-purple-400/60 hover:-translate-y-1 transition-all duration-300"
              data-aos="fade-up"
              :data-aos-delay="150 * index"
            >
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-purple-300 bg-purple-500/10 px-2 py-1 rounded-full">{{ event.role }}</span>
                <span class="text-xs text-gray-400">{{ event.date }}</span>
              </div>
              <h4 class="text-lg font-bold text-white mb-1">{{ event.title }}</h4>
              <p class="text-sm text-gray-400 mb-2">{{ event.organizer }}</p>
              <p class="text-sm text-gray-300 leading-relaxed">{{ event.description }}</p>
            </a>
          </div>

          <h3 class="text-2xl font-semibold mt-10 mb-4 text-pink-400">教育背景</h3>
          <div class="p-5 rounded-xl bg-gray-900/50 border border-gray-700/50">
            <p class="text-sm text-gray-400 mb-1">2025 – 至今</p>
            <h4 class="text-lg font-bold text-white">中華科技大學</h4>
            <p class="text-sm text-gray-300 mt-1">資訊工程相關系所（進修部）· 大一在學中</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const experiences = [
  {
    period: '2025.12 – 至今',
    title: '資安暨研發經理 (Security & R&D Manager)',
    company: '智能悅信資訊 (IntelliTrust)',
    url: 'https://intellitrustme.com/',
    description: '主導企業級資安監控平台 (SOC) 與 AI 防護技術的研發與架構規劃，推動次世代 WAF 與 AI 偵測機制結合，提升系統對未知威脅的防禦效率。',
  },
  {
    period: '2025.11 – 至今',
    title: '資訊技術顧問 (IT Consultant)',
    company: '中華科技大學 (CUST)',
    url: 'https://www.cust.edu.tw/',
    description: '通報校內系統重大資安漏洞（含 CVSS 10 RCE 與 SQL Injection），獲校方延攬擔任技術顧問，協助強化校園資安防禦體系與系統修補策略。',
  },
  {
    period: '2025.03 – 至今',
    title: '前端工程師 (Front-end Engineer)',
    company: '聚騰科技 (GTBooster)',
    url: 'https://www.gtbooster.com/',
    description: '負責開發高互動性 Web 應用程式，專精 Vue.js 與 Three.js 視覺化技術，優化前端效能與使用者體驗，支援公司核心產品的快速迭代。',
  },
];

const events = [
  {
    date: '2026.02',
    role: '特邀講師 / 技術導師',
    title: 'Campfire Taiwan 青年遊戲開發黑客松 (HackIt)',
    organizer: 'HackIt × 國立嘉義大學',
    description: '受邀擔任 32 小時遊戲黑客松的特邀講師與技術導師，指導參賽者進行系統架構規劃與資安防護實務。',
    url: 'https://www.ncyu.edu.tw/ncyu/Subject/Detail/235725?nodeId=835',
  },
  {
    date: '2025',
    role: '特邀講者',
    title: '實踐力勝過學歷：19 歲柯瑋宸的技術自學與職場突圍',
    organizer: 'GDG on Campus · 國立臺灣師範大學',
    description: '分享如何透過技術實戰與持續自學，在求學階段即進入職場核心，並深入剖析資安技術的學習路徑。',
    url: 'https://gdg.community.dev/events/details/google-gdg-on-campus-national-taiwan-normal-university-taipei-taiwan-presents-shi-jian-li-sheng-guo-xue-li-19sui-ke-wei-chen-de-ji-shu-zi-xue-yu-zhi-chang-tu-wei/',
  },
];
</script>
