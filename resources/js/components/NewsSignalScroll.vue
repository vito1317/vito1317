<template>
  <section ref="section" class="news-signal" :style="{ height: `${stories.length * 108}vh` }">
    <div class="news-signal__sticky" :style="{ '--news': active.color }">
      <div class="news-signal__scan"></div>
      <div class="news-signal__stack" :style="{ '--tilt': `${-15 + progress * 30}deg` }" aria-hidden="true">
        <div v-for="(story, index) in stories" :key="story.code" class="news-signal__paper" :class="{ active: index === activeIndex }" :style="paperStyle(index)">
          <span>{{ story.code }}</span><b>{{ story.titleText }}</b><small v-for="source in story.sources.slice(0, 4)" :key="source.name">{{ source.name }}</small><i></i><i></i><i></i><em>●</em>
        </div>
        <div class="news-signal__beacon"><span></span><span></span><span></span><b>LIVE</b></div>
      </div>
      <div class="news-signal__copy container mx-auto px-6 sm:px-10">
        <p>PRESS ARCHIVE / VERIFIED COVERAGE</p>
        <Transition name="news-copy" mode="out-in"><article :key="active.code"><small>{{ active.code }} / {{ active.tag }}</small><h2 v-html="active.title"></h2><div class="news-signal__line"></div><p class="news-signal__body">{{ active.body }}</p><div class="news-signal__outlets"><a v-for="source in active.sources" :key="source.name" :href="source.url" target="_blank" rel="noopener noreferrer">{{ source.name }} <span>↗</span></a></div></article></Transition>
      </div>
      <div class="news-signal__ticker"><span>MEDIA MONITORING</span><b>{{ String(activeIndex + 1).padStart(2, '0') }} / {{ String(stories.length).padStart(2, '0') }}</b></div>
      <button class="news-signal__jump" type="button" @click="scrollToList">VIEW ALL REPORTS <span>↓</span></button>
    </div>
  </section>
</template>
<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
const props = defineProps({ items: { type: Array, default: () => [] } });
const sharedDetail = { title: '19歲高職肄業生自學程式<br><em>開發蝦皮物流自動化工具</em>', excerpt: '柯瑋宸以自學累積的程式能力，開發蝦皮店到店流程自動化工具；報導聚焦他如何從第一線工作觀察問題，並用程式改善重複流程。' };
const realDetails = {
  '台灣新聞聯播網': { title: '19歲高職肄業生自學程式<br><em>開發蝦皮物流自動化工具引起關注</em>', excerpt: '報導指出，柯瑋宸從 Facebook 技術社團分享 SQL Injection 概念圖開始，後來進入科技公司，並在蝦皮店到店工作期間開發 Chrome 自動化工具。' },
  '台灣新聞聯播網-後續': { title: '打工仔逆襲！19歲自學工程師柯瑋宸<br><em>從門市人員踏入技術核心</em>', excerpt: '後續報導聚焦蝦皮 IT 團隊完成工具安全審核，以及柯瑋宸因專案表現獲得進入總公司的機會。' },
  '桃園電子報': { title: '從肄業生到科技新貴<br><em>19歲奇才獲蝦皮延攬入主總部</em>', excerpt: '報導記錄 SQL Injection 概念圖、前端工程經驗與門市流程觀察，並描述自動化工具從個人專案走向官方審核的過程。' },
  'PChome新聞': { title: '從肄業生到科技新貴<br><em>19歲奇才獲蝦皮延攬入主總部</em>', excerpt: 'PChome 轉載桃園電子報內容，完整描述從技術圖解、門市流程觀察到蝦皮工具安全審核的職涯轉折。' },
};
const stories = computed(() => {
  if (!props.items.length) return [{ code: 'NEWS_01', tag: 'MEDIA SIGNAL', title: '正在建立<br>新聞訊號。', titleText: '正在建立新聞訊號', body: '報導資料載入後，每一則新聞都會成為獨立的捲動章節。', color: '#22e0ff', sources: [{ name: '媒體報導載入中', url: '#' }] }];
  const colors = ['#22e0ff', '#ffba3b', '#ff4db8', '#a78bfa'];
  const grouped = new Map();
  props.items.forEach((item) => {
    const detail = realDetails[item.name] || sharedDetail;
    const key = detail.title.replace(/<[^>]+>/g, '').trim();
    if (!grouped.has(key)) grouped.set(key, { detail, sources: [] });
    grouped.get(key).sources.push(item);
  });
  return [...grouped.values()].map(({ detail, sources }, index) => ({
    code: `NEWS_${String(index + 1).padStart(2, '0')}`,
    tag: `${sources.length} MEDIA SOURCES / SAME STORY`,
    title: detail.title,
    titleText: detail.title.replace(/<[^>]+>/g, ''),
    body: `${detail.excerpt} 目前共收錄 ${sources.length} 個相同報導來源。`,
    color: colors[index % colors.length], sources,
  }));
});
const section = ref(null), progress = ref(0), activeIndex = ref(0); let raf = null;
const active = computed(() => stories.value[activeIndex.value] || stories.value[0]);
const paperStyle = (index) => { const d = index - progress.value * (stories.value.length - 1); return { '--paper': stories.value[index].color, opacity: Math.max(0, 1 - Math.abs(d) * .5), transform: `translate3d(${d * 8}vw, ${d * -4}vh, ${-Math.abs(d) * 460}px) rotateZ(${d * 7}deg) rotateY(${d * -24}deg)` }; };
const update = () => { raf = null; const r = section.value?.getBoundingClientRect(); if (!r) return; const range = r.height - innerHeight; progress.value = range > 0 ? Math.min(1, Math.max(0, -r.top / range)) : 0; activeIndex.value = Math.min(stories.value.length - 1, Math.floor(progress.value * stories.value.length)); };
const scrollToList = () => { document.getElementById('news-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
const onScroll = () => { if (raf === null) raf = requestAnimationFrame(update); }; onMounted(() => { update(); addEventListener('scroll', onScroll, { passive: true }); addEventListener('resize', onScroll); }); onUnmounted(() => { removeEventListener('scroll', onScroll); removeEventListener('resize', onScroll); if (raf) cancelAnimationFrame(raf); });
</script>
<style scoped>
.news-signal { position: relative; margin: -1rem -1rem 4rem; }.news-signal__sticky { position: sticky; top: 0; height: 100vh; overflow: hidden; display: flex; align-items: center; isolation: isolate; background: radial-gradient(circle at 75% 47%, color-mix(in srgb, var(--news) 18%, transparent), transparent 31%), #080a13; }.news-signal__scan { position: absolute; inset: 0; background: repeating-linear-gradient(0deg, transparent 0 5px, rgba(255,255,255,.025) 6px 7px); }.news-signal__stack { position: absolute; right: 8%; top: 50%; width: min(55vw, 710px); aspect-ratio: 1; transform: translateY(-50%) rotateX(57deg) rotateZ(var(--tilt)); transform-style: preserve-3d; perspective: 1200px; }.news-signal__paper { position: absolute; width: 57%; height: 72%; left: 21%; top: 12%; padding: 18px; border: 1px solid var(--paper); background: linear-gradient(135deg, color-mix(in srgb, var(--paper) 16%, rgba(9,10,20,.75)), rgba(4,5,12,.72)); box-shadow: inset 0 0 45px color-mix(in srgb, var(--paper) 15%, transparent), 0 0 30px color-mix(in srgb, var(--paper) 18%, transparent); transition: opacity .16s linear; overflow: hidden; }.news-signal__paper span,.news-signal__paper b { display:block; color:var(--paper); font: 10px/1.4 ui-monospace,monospace; letter-spacing:.14em; }.news-signal__paper b { margin-top: 14px; color: #dce7ec; }.news-signal__paper i { display:block; height:1px; margin-top:15px; background:color-mix(in srgb,var(--paper) 50%,transparent); }.news-signal__paper i:nth-of-type(2) { width:76%; }.news-signal__paper i:nth-of-type(3) { width:55%; }.news-signal__paper em { position:absolute; right:16px; bottom:14px; color:var(--paper); font-style:normal; text-shadow:0 0 12px var(--paper); }.news-signal__beacon { position:absolute; left:46%; top:41%; z-index:4; width:74px; aspect-ratio:1; display:grid; place-items:center; border:1px solid var(--news); border-radius:50%; background:#080a13; box-shadow:0 0 30px var(--news); }.news-signal__beacon span { position:absolute; inset:-35%; border:1px solid var(--news); border-radius:inherit; opacity:.4; animation: beacon 2.4s ease-out infinite; }.news-signal__beacon span:nth-child(2){animation-delay:.8s}.news-signal__beacon span:nth-child(3){animation-delay:1.6s}.news-signal__beacon b { color:var(--news); font:700 11px/1 ui-monospace,monospace; }.news-signal__copy { position:relative; z-index:4; }.news-signal__copy > p,.news-signal__copy small { color:var(--news); font: 11px/1.5 ui-monospace,monospace; letter-spacing:.18em; }.news-signal__copy small { display:block; margin:25px 0 12px; }.news-signal h2 { max-width:570px; font-size:clamp(2.7rem,6vw,6.3rem); line-height:.98; font-weight:900; letter-spacing:-.06em; }.news-signal__line { width:70px; height:2px; margin:24px 0; background:var(--news); box-shadow:0 0 12px var(--news); }.news-signal__body { max-width:460px; color:#c3cad4; line-height:1.85; }.news-signal__ticker { position:absolute; z-index:4; right:26px; bottom:25px; color:#a8b0be; font:10px/1.4 ui-monospace,monospace; letter-spacing:.12em; text-align:right; }.news-signal__ticker b { display:block; margin-top:5px; color:var(--news); font-size:17px; }.news-copy-enter-active,.news-copy-leave-active{transition:opacity .3s,transform .35s}.news-copy-enter-from{opacity:0;transform:translateY(16px)}.news-copy-leave-to{opacity:0;transform:translateY(-12px)}@keyframes beacon{to{transform:scale(1.4);opacity:0}}
.news-signal__paper small { display:block; margin-top:5px; color:#adb6c2; font:7px/1.4 ui-monospace,monospace; letter-spacing:.1em; }.news-signal h2 :deep(em) { color:var(--news); font-style:normal; }.news-signal__outlets { display:flex; flex-wrap:wrap; gap:7px; max-width:480px; margin-top:17px; }.news-signal__outlets a { padding:6px 8px; border:1px solid color-mix(in srgb,var(--news) 34%,transparent); color:#dbe7eb; background:rgba(3,6,14,.45); font-size:11px; transition:.2s ease; }.news-signal__outlets a:hover { border-color:var(--news); color:var(--news); }.news-signal__outlets span { color:var(--news); }
.news-signal__jump { position:absolute; z-index:5; top:86px; right:26px; padding:9px 13px; border:1px solid color-mix(in srgb,var(--news) 48%,transparent); color:var(--news); background:rgba(3,6,14,.84); font:9px/1 ui-monospace,monospace; letter-spacing:.14em; cursor:pointer; transition:.2s ease; }.news-signal__jump:hover { background:var(--news); color:#07101b; box-shadow:0 0 18px var(--news); }.news-signal__jump span { margin-left:5px; font-size:13px; }
@media(max-width:700px){.news-signal__stack{width:90vw;right:-30vw;top:65%;opacity:.67}.news-signal__copy{align-self:flex-start;padding-top:17vh;max-height:calc(100vh - 155px);overflow:hidden}.news-signal h2{font-size:clamp(2.6rem,13vw,4.4rem);max-width:18rem}.news-signal__body{max-width:19rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.news-signal__outlets{height:94px!important;max-height:94px!important;overflow-y:scroll!important;align-content:flex-start;padding:3px 4px 8px 0;scrollbar-width:thin}.news-signal__outlets a{font-size:10px;padding:5px 7px}.news-signal__jump{top:74px;right:16px;padding:8px 10px;font-size:8px}.news-signal__ticker{right:15px;bottom:18px;font-size:8px}}@media(prefers-reduced-motion:reduce){.news-signal__beacon span{animation:none}.news-copy-enter-active,.news-copy-leave-active{transition:none}}
</style>
