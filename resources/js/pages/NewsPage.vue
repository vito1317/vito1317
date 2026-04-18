<template>
  <div class="container mx-auto px-4 py-24 pt-20">
    <h1 class="text-4xl md:text-5xl font-bold mb-16 text-center" data-aos="fade-down">新聞報導</h1>
    
    <div class="relative">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
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
</template>

<script setup>
import { ref } from 'vue';

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
  { name: '台灣新聞聯播網', url: 'https://twnewshub.com/archives/89729' },
  { name: '台灣新聞聯播網-後續', url: 'https://twnewshub.com/archives/89871'},
  { name: '桃園電子報', url: 'https://tyenews.com/2025/06/881588/' },
  { name: 'LINE TODAY', url: 'https://today.line.me/tw/v2/article/DRP2ZEg' },
  { name: 'Yahoo新聞', url: 'https://reurl.cc/1O48DD' },
  { name: 'PChome新聞', url: 'https://news.pchome.com.tw/society/tyenews/20250625/index-75084080724970343002.html' },
  { name: '蕃新聞', url: 'https://n.yam.com/Article/20250630373033' },
  { name: '奧丁丁新聞', url: 'https://news.owlting.com/articles/1055098' },
  { name: 'LIFE新聞', url: 'https://life.tw/?app=view&no=2752436' },
  { name: '享新聞', url: 'https://i-news.com.tw/2025/06/213691/'},
  { name: '樂聯網', url: 'https://leho.com.tw/archives/160738' },  
  { name: '台灣線報', url: 'https://twline365.com/2025/06/992595/' },
  { name: '是新聞', url: 'https://reurl.cc/NY3Mne' },
  { name: '台北郵報', url: 'https://taipeipost.org/289970/' },
  { name: '民生電子報', url: 'https://lifenews.com.tw/359200/' },
  { name: '商傳媒', url: 'https://reurl.cc/mxeAMA' },
  { name: '火報', url: 'https://reurl.cc/VYylMn' },
  { name: 'ezPR', url: 'https://reurl.cc/ko58yb' },
  { name: '享民頭條', url: 'https://reurl.cc/GnM9qx' },
  { name: '獨家報導', url: 'https://reurl.cc/EVOkQ0'},
  { name: '記者爆料網', url: 'https://new-reporter.com/news/39382/' },
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