<template>
  <div>
  <div class="container mx-auto px-4 py-12 sm:py-24 pt-20">
    <h1 v-decrypt class="text-3xl sm:text-4xl md:text-5xl font-bold mb-10 sm:mb-16 text-center" data-aos="fade-down">新聞報導</h1>

    <NewsSignalScroll :items="newsItems" />

    <div class="relative">
      <div id="news-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8 scroll-mt-24">
        <div
          v-for="(item, index) in newsItems"
          :key="item.name"
          class="flex flex-col justify-between p-6 rounded-2xl transition-all duration-300 bg-gray-800/50 backdrop-blur-sm border border-gray-700/50 shadow-lg hover:shadow-cyan-500/10 hover:-translate-y-2"
          data-aos="fade-up"
          :data-aos-delay="100 * (index % 3)"
        >
          <a :href="item.url" target="_blank" rel="noopener noreferrer" class="block flex-grow">
            <h2 class="text-xl font-semibold text-gray-200 hover:text-teal-400 transition-colors">{{ item.name }}</h2>
          </a>
          
          <button 
            @click.prevent.stop="shareNews(item)" 
            class="mt-4 self-end p-2 rounded-full text-gray-400 hover:bg-gray-700/80 hover:text-white transition-all"
            title="分享此新聞"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12s-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.368a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
            </svg>
          </button>
        </div>
      </div>
      
      <Transition name="popup-fade">
        <div v-if="showCopiedMessage" class="absolute top-[-4rem] left-1/2 -translate-x-1/2 bg-teal-500 text-white px-4 py-2 rounded-lg shadow-lg">
          連結已複製！
        </div>
      </Transition>

    </div>
  </div>

  <footer class="text-center py-12 mt-12 bg-black/20 backdrop-blur-sm">
    <p class="text-gray-600 text-sm">
      返回 <router-link to="/" class="text-teal-400 hover:underline">首頁</router-link>
    </p>
  </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import NewsSignalScroll from '../components/NewsSignalScroll.vue';

const showCopiedMessage = ref(false);

const shareNews = async (newsItem) => {
  const shareData = {
    title: `快來看看 柯瑋宸 的媒體報導：${newsItem.name}`,
    text: `柯瑋宸 的個人網站上，有來自「${newsItem.name}」的新聞報導！`,
    url: newsItem.url
  };

  if (navigator.share) {
    try {
      await navigator.share(shareData);
      console.log('新聞分享成功！');
    } catch (err) {
      console.error('分享失敗:', err);
    }
  } else {
    try {
      await navigator.clipboard.writeText(newsItem.url);
      console.log('連結已複製到剪貼簿');
      
      showCopiedMessage.value = true;
      setTimeout(() => {
        showCopiedMessage.value = false;
      }, 2000);

    } catch (err) {
      console.error('複製失敗:', err);
      alert('無法自動複製連結，請手動複製：' + newsItem.url);
    }
  }
};
const newsItems = [
  { name: '台灣新聞聯播網', title: '19歲自學少年，靠實力走進資安產業核心', excerpt: '從自學程式到投入企業資安研發，靠著持續實作累積出超越年齡的技術履歷。', url: 'https://twnewshub.com/archives/89729' },
  { name: '台灣新聞聯播網-後續', title: '從漏洞通報到技術顧問：把研究變成真正影響力', excerpt: '校園漏洞研究獲得重視，讓年輕研究者直接參與系統防禦與改善。', url: 'https://twnewshub.com/archives/89871'},
  { name: '桃園電子報', title: '年輕資安研究者的實戰成長路線', excerpt: '不等待完美條件，透過專案、漏洞研究與公開分享一步步建立專業能力。', url: 'https://tyenews.com/2025/06/881588/' },
  { name: 'LINE TODAY', title: '技術自學不設限，19歲踏入職場核心', excerpt: '以實作取代等待，用真實專案證明自學也能走出自己的工程道路。', url: 'https://today.line.me/tw/v2/article/DRP2ZEg' },
  { name: 'Yahoo新聞', title: '把好奇心寫成程式，讓資安能力被看見', excerpt: '從問題拆解、工具開發到漏洞驗證，將學習成果轉化成可被驗證的技術。', url: 'https://reurl.cc/1O48DD' },
  { name: 'PChome新聞', title: '少年工程師的資安實戰與職涯突破', excerpt: '面對真實系統與真實風險，從每一次分析與修補中建立工程判斷力。', url: 'https://news.pchome.com.tw/society/tyenews/20250625/index-75084080724970343002.html' },
  { name: '蕃新聞', title: '用作品累積信任，從自學者成為資安開發者', excerpt: '將技術研究公開、分享並持續迭代，讓作品本身成為最有力的介紹。', url: 'https://n.yam.com/Article/20250630373033' },
  { name: '奧丁丁新聞', title: '從漏洞研究現場，看見新世代資安人才', excerpt: '以攻擊者視角理解系統，再把發現轉化為防禦策略與可落地的修補方案。', url: 'https://news.owlting.com/articles/1055098' },
  { name: 'LIFE新聞', title: '不被學歷定義的技術成長故事', excerpt: '學習、實作、驗證與分享形成循環，讓每一個階段都能留下可追蹤的成果。', url: 'https://life.tw/?app=view&no=2752436' },
  { name: '享新聞', title: '把資安熱情變成可以運作的產品', excerpt: '從研究概念走到服務與產品，持續思考技術如何在真實環境創造價值。', url: 'https://i-news.com.tw/2025/06/213691/'},
  { name: '樂聯網', title: '實戰派青年工程師的成長軌跡', excerpt: '每一次專案交付與問題排查，都是把技術能力推向下一個層級的練習。', url: 'https://leho.com.tw/archives/160738' },  
  { name: '台灣線報', title: '讓技術分享成為連結產業的橋樑', excerpt: '透過公開演講與社群交流，把個人研究轉化為更多人能使用的知識。', url: 'https://twline365.com/2025/06/992595/' },
  { name: '是新聞', title: '從校園研究到企業資安現場', excerpt: '把在學期間累積的觀察力與實作力，帶進更高要求的產品研發環境。', url: 'https://reurl.cc/NY3Mne' },
  { name: '台北郵報', title: '年輕世代用程式碼回應真實問題', excerpt: '不只學習框架與語法，更關注系統安全、使用者需求與長期維護。', url: 'https://taipeipost.org/289970/' },
  { name: '民生電子報', title: '資安與全端開發並行的技術路線', excerpt: '在前端體驗、後端系統與安全防護之間建立完整的工程視角。', url: 'https://lifenews.com.tw/359200/' },
  { name: '商傳媒', title: '從個人專案到企業級研發思維', excerpt: '用可測試、可部署、可持續的方式，讓個人創意具備產品級的生命力。', url: 'https://reurl.cc/mxeAMA' },
  { name: '火報', title: '不怕從零開始，持續打造自己的技術護城河', excerpt: '以長期累積取代短期追逐，讓每一次學習都能沉澱成下一次突破。', url: 'https://reurl.cc/VYylMn' },
  { name: 'ezPR', title: '用開源與分享放大技術影響力', excerpt: '把解法、工具與研究紀錄留下來，讓更多開發者能站在成果上繼續前進。', url: 'https://reurl.cc/ko58yb' },
  { name: '享民頭條', title: '從興趣出發，走出資安專業道路', excerpt: '興趣只是起點，真正讓能力持續成長的是反覆實作與面對問題的耐心。', url: 'https://reurl.cc/GnM9qx' },
  { name: '獨家報導', title: '用技術實力寫下自己的職涯答案', excerpt: '不等待別人定義未來，透過作品、研究與現場經驗累積自己的選擇權。', url: 'https://reurl.cc/EVOkQ0'},
  { name: '記者爆料網', title: '19歲資安新秀的逆襲：用實力被產業看見', excerpt: '從學習者到研發團隊成員，持續把技術挑戰轉化為可以交付的成果。', url: 'https://new-reporter.com/news/39382/' },
];
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
