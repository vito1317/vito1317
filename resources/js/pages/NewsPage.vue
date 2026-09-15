<template>
  <div>
  <div class="container mx-auto px-4 py-12 sm:py-24 pt-20">
    <h1 v-decrypt class="text-3xl sm:text-4xl md:text-5xl font-bold mb-3 text-center" data-aos="fade-down">新聞報導</h1>
    <p class="text-center text-gray-400 text-sm mb-12 sm:mb-16" data-aos="fade-down" data-aos-delay="80">
      {{ outletCount }} 家媒體 · {{ stories.length }} 則原始報導 · 2025 年 6 月
    </p>

    <!--
      報導本身才是這一頁的內容，所以標題、摘要、日期放最前面。
      先前這裡只列得出媒體名稱（「Yahoo新聞」「PChome新聞」），
      讀的人得逐一點開才知道寫了什麼——而大部分人不會點。
    -->
    <div id="news-list" class="flex flex-col gap-6 sm:gap-8 scroll-mt-24">
      <article
        v-for="(story, index) in stories"
        :key="story.code"
        class="relative p-6 sm:p-8 rounded-2xl bg-gray-800/50 backdrop-blur-sm border border-gray-700/50 shadow-lg transition-all duration-300 hover:shadow-cyan-500/10"
        data-aos="fade-up"
        :data-aos-delay="60 * index"
      >
        <!-- 右邊留出分享鈕的位置，不然日期會被蓋掉 -->
        <div class="flex items-center gap-3 mb-3 pr-14 font-mono text-xs tracking-widest text-teal-400">
          <span>{{ story.code }}</span>
          <span class="h-px flex-grow bg-teal-400/25"></span>
          <time :datetime="story.datetime">{{ story.date }}</time>
        </div>

        <a :href="story.outlets[0].url" target="_blank" rel="noopener noreferrer" class="block group">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold leading-snug text-gray-100 group-hover:text-teal-400 transition-colors">
            {{ story.headline }}
          </h2>
        </a>

        <p class="mt-4 text-gray-300 leading-relaxed max-w-3xl">{{ story.summary }}</p>

        <!--
          十幾家媒體刊的是同一篇轉載，不是十幾則獨立報導。
          分開寫才不會把轉載數量講成報導數量。
        -->
        <p class="mt-5 text-xs text-gray-500">
          {{ story.outlets[0].name }} 原發<template v-if="story.outlets.length > 1">，另有 {{ story.outlets.length - 1 }} 家媒體轉載</template>
        </p>

        <div class="mt-3 flex flex-wrap gap-2">
          <a
            v-for="outlet in story.outlets"
            :key="outlet.name"
            :href="outlet.url"
            target="_blank"
            rel="noopener noreferrer"
            class="px-3 py-1.5 rounded-lg text-xs text-gray-300 bg-gray-900/50 border border-gray-700/60 hover:border-teal-400/70 hover:text-teal-300 transition-colors"
          >{{ outlet.name }} <span class="text-teal-400/80">↗</span></a>
        </div>

        <button
          @click.prevent.stop="shareStory(story)"
          class="absolute top-5 right-5 p-2 rounded-full text-gray-500 hover:bg-gray-700/80 hover:text-white transition-all"
          :title="`分享「${story.headline}」`"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12s-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.368a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
          </svg>
        </button>
      </article>
    </div>

    <Transition name="popup-fade">
      <div v-if="showCopiedMessage" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 bg-teal-500 text-white px-4 py-2 rounded-lg shadow-lg">
        連結已複製！
      </div>
    </Transition>
  </div>

  <!-- 動畫是配角：讀完報導之後才出現，也不再擋在內容前面 -->
  <NewsSignalScroll :stories="stories" />

  <footer class="text-center py-12 bg-black/20 backdrop-blur-sm">
    <p class="text-gray-600 text-sm">
      返回 <router-link to="/" class="text-teal-400 hover:underline">首頁</router-link>
    </p>
  </footer>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import NewsSignalScroll from '../components/NewsSignalScroll.vue';

/*
 * 報導資料。
 *
 * 以「報導」為單位而不是「媒體」為單位：這 19 個連結其實只有 4 則原始報導，
 * 其餘都是同一篇的轉載。照媒體列會讓人以為有十九則不同的報導，
 * 而點進去會發現內容一模一樣——那比少列幾家更傷可信度。
 *
 * 標題一律照各家網站上的原文，不自己改寫：掛著真實媒體名稱的標題只要有一個字
 * 是我們編的，整頁的可信度就沒了。
 */
const stories = [
  {
    code: 'NEWS_01',
    headline: '19歲高職肄業生自學程式　開發蝦皮物流自動化工具引起關注',
    date: '2025.06.20',
    datetime: '2025-06-20',
    summary: '從在 Facebook 技術社團分享 SQL Injection 概念圖開始，到進入科技公司工作；報導記錄他在蝦皮店到店服務期間，觀察門市重複流程後自行開發 Chrome 自動化工具的經過。',
    outlets: [
      { name: '台灣新聞聯播網', url: 'https://twnewshub.com/archives/89729' },
    ],
  },
  {
    code: 'NEWS_02',
    headline: '年僅19歲的程式奇才：從高中肄業到科技公司力邀，更為蝦皮開發內部工具',
    date: '2025.06.20',
    datetime: '2025-06-20',
    summary: '以專欄形式回顧自學歷程：從高中肄業、被科技公司延攬，到為蝦皮開發內部使用的工具。',
    outlets: [
      { name: 'ezPR 科技說書人', url: 'https://www.ezpr.com.tw/%E5%B9%B4%E5%83%8519%E6%AD%B2%E7%9A%84%E7%A8%8B%E5%BC%8F%E5%A5%87%E6%89%8D-%E5%BE%9E%E9%AB%98%E4%B8%AD%E8%82%84%E6%A5%AD%E5%88%B0%E7%A7%91%E6%8A%80%E5%85%AC%E5%8F%B8%E5%8A%9B%E9%82%80/' },
    ],
  },
  {
    code: 'NEWS_03',
    headline: '從肄業生到科技新貴　19歲奇才柯瑋宸的逆襲之路　獲蝦皮延攬入主總部',
    date: '2025.06',
    datetime: '2025-06',
    summary: '桃園電子報的專訪，完整記錄從技術圖解、前端工程經驗、門市流程觀察，到自動化工具通過蝦皮 IT 安全審核、最後獲總部延攬的整段過程。這一篇被十多家媒體同步轉載。',
    outlets: [
      { name: '桃園電子報', url: 'https://tyenews.com/2025/06/881588/' },
      { name: 'LINE TODAY', url: 'https://today.line.me/tw/v2/article/DRP2ZEg' },
      { name: 'Yahoo新聞', url: 'https://tw.news.yahoo.com/%E5%BE%9E%E8%82%84%E6%A5%AD%E7%94%9F%E5%88%B0%E7%A7%91%E6%8A%80%E6%96%B0%E8%B2%B4-19%E6%AD%B2%E5%A5%87%E6%89%8D%E6%9F%AF%E7%91%8B%E5%AE%B8%E7%9A%84%E9%80%86%E8%A5%B2%E4%B9%8B%E8%B7%AF-%E7%8D%B2%E8%9D%A6%E7%9A%AE%E5%BB%B6%E6%94%AC%E5%85%A5%E4%B8%BB%E7%B8%BD%E9%83%A8-084007989.html' },
      { name: 'PChome新聞', url: 'https://news.pchome.com.tw/society/tyenews/20250625/index-75084080724970343002.html' },
      { name: '蕃新聞', url: 'https://n.yam.com/Article/20250630373033' },
      { name: '奧丁丁新聞', url: 'https://news.owlting.com/articles/1055098' },
      { name: 'LIFE新聞', url: 'https://life.tw/?app=view&no=2752436' },
      { name: '享新聞', url: 'https://i-news.com.tw/2025/06/213691/' },
      { name: '台灣線報', url: 'https://twline365.com/2025/06/992595/' },
      { name: '台北郵報', url: 'https://taipeipost.org/289970/' },
      { name: '民生電子報', url: 'https://lifenews.com.tw/359200/' },
      { name: '是新聞', url: 'https://www.yesmedia.com.tw/%e5%be%9e%e8%82%84%e6%a5%ad%e7%94%9f%e5%88%b0%e7%a7%91%e6%8a%80%e6%96%b0%e8%b2%b4-19%e6%ad%b2%e5%a5%87%e6%89%8d%e6%9f%af%e7%91%8b%e5%ae%b8%e7%9a%84%e9%80%86%e8%a5%b2%e4%b9%8b%e8%b7%af-%e7%8d%b2/' },
      { name: '商傳媒', url: 'https://sunmedia.tw/news/collaborative/2KIaahyIUX5FlO0BI54c4GD1UnGBaluxVHE8UBQhSOJOO8SRQuLEt5GvvCjZwxMz4sNlDd7p8I4rdctN' },
      { name: '火報', url: 'https://firenews.com.tw/2025/06/25/%E5%BE%9E%E8%82%84%E6%A5%AD%E7%94%9F%E5%88%B0%E7%A7%91%E6%8A%80%E6%96%B0%E8%B2%B4-19%E6%AD%B2%E5%A5%87%E6%89%8D%E6%9F%AF%E7%91%8B%E5%AE%B8%E7%9A%84%E9%80%86%E8%A5%B2%E4%B9%8B%E8%B7%AF-%E7%8D%B2/' },
      { name: '享民頭條', url: 'https://www.twjinmedia.com/uncategorized/%e5%be%9e%e8%82%84%e6%a5%ad%e7%94%9f%e5%88%b0%e7%a7%91%e6%8a%80%e6%96%b0%e8%b2%b4-19%e6%ad%b2%e5%a5%87%e6%89%8d%e6%9f%af%e7%91%8b%e5%ae%b8%e7%9a%84%e9%80%86%e8%a5%b2%e4%b9%8b%e8%b7%af-%e7%8d%b2/' },
      { name: '獨家報導', url: 'https://www.scooptw.com/tyenews/375678/%e5%be%9e%e8%82%84%e6%a5%ad%e7%94%9f%e5%88%b0%e7%a7%91%e6%8a%80%e6%96%b0%e8%b2%b4-19%e6%ad%b2%e5%a5%87%e6%89%8d%e6%9f%af%e7%91%8b%e5%ae%b8%e7%9a%84%e9%80%86%e8%a5%b2%e4%b9%8b%e8%b7%af-%e7%8d%b2/' },
    ],
  },
  {
    code: 'NEWS_04',
    headline: '打工仔逆襲！19歲自學工程師柯瑋宸　從門市人員踏入技術核心',
    date: '2025.06.30',
    datetime: '2025-06-30',
    summary: '後續報導：蝦皮 IT 團隊完成工具的安全審核，他也因為這個專案的表現，從門市人員走進技術團隊。',
    outlets: [
      { name: '台灣新聞聯播網', url: 'https://twnewshub.com/archives/89871' },
    ],
  },
];

const outletCount = computed(
  () => new Set(stories.flatMap((story) => story.outlets.map((outlet) => outlet.name))).size,
);

const showCopiedMessage = ref(false);

const shareStory = async (story) => {
  const shareData = {
    title: story.headline,
    text: `柯瑋宸的媒體報導：${story.headline}`,
    url: story.outlets[0].url,
  };

  if (navigator.share) {
    try {
      await navigator.share(shareData);
    } catch (err) {
      console.error('分享失敗:', err);
    }

    return;
  }

  try {
    await navigator.clipboard.writeText(story.outlets[0].url);
    showCopiedMessage.value = true;
    setTimeout(() => { showCopiedMessage.value = false; }, 2000);
  } catch (err) {
    console.error('複製失敗:', err);
    alert('無法自動複製連結，請手動複製：' + story.outlets[0].url);
  }
};
</script>
<style scoped>
.popup-fade-enter-active,
.popup-fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.popup-fade-enter-from,
.popup-fade-leave-to {
  opacity: 0;
  transform: translateY(20px) translateX(-50%);
}
</style>
