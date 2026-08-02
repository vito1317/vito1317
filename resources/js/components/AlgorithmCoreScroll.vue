<template>
  <section ref="sectionRef" class="algorithm-core-scroll" :style="{ height: `${chapters.length * 120}vh` }">
    <div class="algorithm-core-scroll__sticky" :style="themeStyle">
      <div class="algorithm-core-scroll__noise"></div>
      <div class="algorithm-core-scroll__axis algorithm-core-scroll__axis--x"></div>
      <div class="algorithm-core-scroll__axis algorithm-core-scroll__axis--y"></div>

      <div class="algorithm-core-scroll__engine" aria-hidden="true" :style="engineStyle">
        <div class="algorithm-core-scroll__ring algorithm-core-scroll__ring--a"></div>
        <div class="algorithm-core-scroll__ring algorithm-core-scroll__ring--b"></div>
        <div class="algorithm-core-scroll__ring algorithm-core-scroll__ring--c"></div>
        <div class="algorithm-core-scroll__cube">
          <span v-for="face in 6" :key="face" class="algorithm-core-scroll__face"></span>
          <span class="algorithm-core-scroll__kernel">{{ activeChapter.symbol }}</span>
        </div>
        <div class="algorithm-core-scroll__nodes">
          <i v-for="node in 12" :key="node" :class="{ hot: node <= activeIndex + 3 }"></i>
        </div>
      </div>

      <div class="algorithm-core-scroll__content container mx-auto px-6 sm:px-10">
        <div class="algorithm-core-scroll__index">RESEARCH / {{ String(activeIndex + 1).padStart(2, '0') }}</div>
        <Transition name="core-copy" mode="out-in">
          <article :key="activeChapter.id">
            <p class="algorithm-core-scroll__type">{{ activeChapter.type }}</p>
            <h2 v-html="activeChapter.title"></h2>
            <p class="algorithm-core-scroll__summary">{{ activeChapter.summary }}</p>
            <div class="algorithm-core-scroll__formula">{{ activeChapter.formula }}</div>
          </article>
        </Transition>
      </div>

      <div class="algorithm-core-scroll__chapters" aria-label="演算法章節進度">
        <button v-for="(chapter, index) in chapters" :key="chapter.id" type="button" :class="{ active: index === activeIndex }" @click="scrollToChapter(index)">
          <span>{{ String(index + 1).padStart(2, '0') }}</span>{{ chapter.short }}
        </button>
      </div>
      <div class="algorithm-core-scroll__telemetry"><span>CORE_STABILITY</span><b>{{ stability }}%</b></div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const chapters = [
  { id: 'tact', short: 'TACT', symbol: 'γ', type: 'LLM REASONING / CONSENSUS · IEEE PAPER', title: 'TACT — 信心穩健<br>加權共識', summary: '整條方法收成一個零調校常數的式子：γ 完全由資料導出，訊號不足時恰為 0、位元等同 Self-Consistency——信心通道第一次在真實資料上被證實（z = +2.54）。', formula: 'γ = z·√(2 + z²),  z = Φ⁻¹(AUC)', color: '#31e6ff' },
  { id: 'window', short: 'WINDOW', symbol: '%', type: 'STRUCTURAL BOUNDARY / 5 SUBSTRATES', title: '薄窗 — 聚合的<br>結構性邊界', summary: '跨兩個領域、五個基質的直接量測：無標籤聚合能作用的分層只佔題目 2–7.5%，且難度上升不會讓它變寬——一條邊界解釋了六個死亡設計的死因。', formula: '窗口 = oracle − 基線 = 2–7.5%', color: '#5effa7' },
  { id: 'rlev', short: 'RLEV', symbol: 'Σ', type: 'REDUNDANCY / VALUE OF INFORMATION', title: 'RLEV-VoI — 冗餘折扣<br>共識引擎', summary: '相似推理鏈不再重複計票；系統持續估計每一個新樣本的資訊價值，在答案穩定時及早停止。誠實發表的負面結果，屍檢催生了整個後續研究計畫。', formula: 'wᵢ = 1 / Σⱼ Sᵢⱼ', color: '#ff3bc8' },
  { id: 'ml', short: 'ML', symbol: 'Δ', type: 'SECURITY / ANOMALY DETECTION', title: '自適應 ML<br>行為異常偵測', summary: '從請求的 16 維特徵建立每個站點的行為基線，並隨標記資料演進到 Logistic 與 MLP 模型。', formula: 'score = Σ wₖ · |zₖ|', color: '#a98cff' },
];

const sectionRef = ref(null);
const progress = ref(0);
const activeIndex = ref(0);
const compactViewport = ref(false);
let frameId = null;
const activeChapter = computed(() => chapters[activeIndex.value]);
const stability = computed(() => Math.round(72 + progress.value * 27));
const themeStyle = computed(() => ({ '--core': activeChapter.value.color }));
const engineStyle = computed(() => ({
  '--spin-x': compactViewport.value ? '-12deg' : `${-19 + progress.value * 46}deg`,
  '--spin-y': `${20 + progress.value * 290}deg`,
  '--drift': `${progress.value * (compactViewport.value ? -28 : -42)}px`,
}));

const update = () => {
  frameId = null;
  compactViewport.value = window.innerWidth <= 700;
  const rect = sectionRef.value?.getBoundingClientRect();
  if (!rect) return;
  const available = rect.height - window.innerHeight;
  progress.value = available > 0 ? Math.min(1, Math.max(0, -rect.top / available)) : 0;
  activeIndex.value = Math.min(chapters.length - 1, Math.floor(progress.value * chapters.length));
};
const onScroll = () => { if (frameId === null) frameId = requestAnimationFrame(update); };
const scrollToChapter = (index) => {
  const section = sectionRef.value;
  if (!section) return;
  const available = section.offsetHeight - window.innerHeight;
  window.scrollTo({ top: section.offsetTop + available * (index / chapters.length) + 2, behavior: 'smooth' });
};
onMounted(() => { update(); window.addEventListener('scroll', onScroll, { passive: true }); window.addEventListener('resize', onScroll); });
onUnmounted(() => { window.removeEventListener('scroll', onScroll); window.removeEventListener('resize', onScroll); if (frameId) cancelAnimationFrame(frameId); });
</script>

<style scoped>
.algorithm-core-scroll { position: relative; margin: 2rem -1rem 4rem; }.algorithm-core-scroll__sticky { position: sticky; top: 0; display: flex; align-items: center; height: 100vh; overflow: hidden; isolation: isolate; background: radial-gradient(circle at 72% 49%, color-mix(in srgb, var(--core) 17%, transparent), transparent 29%), linear-gradient(140deg, #060613, #0c0c1d 55%, #050510); }.algorithm-core-scroll__noise { position: absolute; inset: 0; opacity: .32; background-image: linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px), linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px); background-size: 26px 26px; mask-image: radial-gradient(ellipse, black, transparent 75%); }.algorithm-core-scroll__axis { position: absolute; background: color-mix(in srgb, var(--core) 30%, transparent); box-shadow: 0 0 14px var(--core); }.algorithm-core-scroll__axis--x { left: 0; right: 0; top: 51%; height: 1px; }.algorithm-core-scroll__axis--y { top: 0; bottom: 0; right: 28%; width: 1px; }
.algorithm-core-scroll__engine { position: absolute; z-index: 1; right: 9%; top: 50%; width: min(49vw, 620px); aspect-ratio: 1; transform: translateY(calc(-50% + var(--drift))) rotateX(var(--spin-x)) rotateY(var(--spin-y)); transform-style: preserve-3d; perspective: 1000px; transition: --core .35s ease; }.algorithm-core-scroll__ring { position: absolute; inset: 10%; border: 1px solid var(--core); border-radius: 50%; box-shadow: 0 0 20px color-mix(in srgb, var(--core) 45%, transparent), inset 0 0 20px color-mix(in srgb, var(--core) 15%, transparent); }.algorithm-core-scroll__ring--a { transform: rotateX(70deg) translateZ(45px); }.algorithm-core-scroll__ring--b { inset: 19%; transform: rotateY(72deg) translateZ(18px); }.algorithm-core-scroll__ring--c { inset: 30%; transform: rotateX(28deg) rotateY(45deg); border-style: dashed; }.algorithm-core-scroll__cube { position: absolute; inset: 35%; transform-style: preserve-3d; animation: kernel-pulse 2.4s ease-in-out infinite; }.algorithm-core-scroll__face { position: absolute; inset: 0; border: 1px solid var(--core); background: color-mix(in srgb, var(--core) 11%, transparent); box-shadow: inset 0 0 16px var(--core); }.algorithm-core-scroll__face:nth-child(1) { transform: translateZ(55px); }.algorithm-core-scroll__face:nth-child(2) { transform: rotateY(180deg) translateZ(55px); }.algorithm-core-scroll__face:nth-child(3) { transform: rotateY(90deg) translateZ(55px); }.algorithm-core-scroll__face:nth-child(4) { transform: rotateY(-90deg) translateZ(55px); }.algorithm-core-scroll__face:nth-child(5) { transform: rotateX(90deg) translateZ(55px); }.algorithm-core-scroll__face:nth-child(6) { transform: rotateX(-90deg) translateZ(55px); }.algorithm-core-scroll__kernel { position: absolute; inset: 28%; display: grid; place-items: center; transform: translateZ(70px); border: 1px solid #fff; border-radius: 50%; color: #fff; background: var(--core); box-shadow: 0 0 35px var(--core); font: 700 30px/1 ui-monospace, monospace; }.algorithm-core-scroll__nodes { position: absolute; inset: 6%; transform-style: preserve-3d; }.algorithm-core-scroll__nodes i { position: absolute; width: 6px; height: 6px; border: 1px solid var(--core); border-radius: 50%; box-shadow: 0 0 7px var(--core); opacity: .35; }.algorithm-core-scroll__nodes i.hot { background: var(--core); opacity: 1; animation: node-ping 1.7s infinite alternate; }.algorithm-core-scroll__nodes i:nth-child(1) { left: 5%; top: 22%; }.algorithm-core-scroll__nodes i:nth-child(2) { left: 24%; top: 4%; }.algorithm-core-scroll__nodes i:nth-child(3) { left: 52%; top: 12%; }.algorithm-core-scroll__nodes i:nth-child(4) { right: 8%; top: 29%; }.algorithm-core-scroll__nodes i:nth-child(5) { left: 7%; top: 65%; }.algorithm-core-scroll__nodes i:nth-child(6) { left: 28%; bottom: 8%; }.algorithm-core-scroll__nodes i:nth-child(7) { left: 59%; bottom: 3%; }.algorithm-core-scroll__nodes i:nth-child(8) { right: 9%; bottom: 24%; }.algorithm-core-scroll__nodes i:nth-child(n+9) { left: 48%; top: 48%; transform: translate3d(80px, -30px, 45px); }
.algorithm-core-scroll__content { position: relative; z-index: 3; }.algorithm-core-scroll__index, .algorithm-core-scroll__type, .algorithm-core-scroll__formula { font-family: ui-monospace, monospace; letter-spacing: .16em; }.algorithm-core-scroll__index { font-size: 10px; color: #8790a9; }.algorithm-core-scroll__type { margin: 26px 0 12px; color: var(--core); font-size: 11px; }.algorithm-core-scroll h2 { max-width: 610px; font-size: clamp(2.5rem, 5.5vw, 6rem); font-weight: 900; line-height: 1.03; letter-spacing: -.055em; white-space: pre-line; }.algorithm-core-scroll__summary { max-width: 490px; margin-top: 22px; color: #c2c7d7; line-height: 1.85; font-size: clamp(.95rem, 1.5vw, 1.08rem); }.algorithm-core-scroll__formula { display: inline-block; margin-top: 24px; padding: 12px 14px; border: 1px solid color-mix(in srgb, var(--core) 42%, transparent); color: var(--core); background: rgba(4,4,14,.68); font-size: 12px; box-shadow: 0 0 25px color-mix(in srgb, var(--core) 13%, transparent); }.algorithm-core-scroll__chapters { position: absolute; z-index: 4; bottom: 28px; left: max(24px, calc((100% - 1280px) / 2 + 40px)); display: flex; gap: 16px; }.algorithm-core-scroll__chapters button { color: #9ca4b8; background: none; border: 0; padding: 0; font: 10px/1 ui-monospace, monospace; letter-spacing: .12em; cursor: pointer; transition: color .25s ease; }.algorithm-core-scroll__chapters button span { display: block; width: 34px; height: 2px; margin-bottom: 8px; background: #4d5364; }.algorithm-core-scroll__chapters button.active { color: var(--core); }.algorithm-core-scroll__chapters button.active span { background: var(--core); box-shadow: 0 0 10px var(--core); }.algorithm-core-scroll__telemetry { position: absolute; z-index: 4; right: 26px; bottom: 26px; color: #858ca0; font: 10px/1.3 ui-monospace, monospace; letter-spacing: .12em; text-align: right; }.algorithm-core-scroll__telemetry b { display: block; margin-top: 5px; color: var(--core); font-size: 17px; }
.core-copy-enter-active, .core-copy-leave-active { transition: opacity .28s ease, transform .35s cubic-bezier(.16,1,.3,1); }.core-copy-enter-from { opacity: 0; transform: translateY(16px); }.core-copy-leave-to { opacity: 0; transform: translateY(-12px); } @keyframes kernel-pulse { 50% { transform: scale(1.18); } } @keyframes node-ping { to { box-shadow: 0 0 20px var(--core); transform: scale(1.7); } }
@media (max-width: 700px) { .algorithm-core-scroll { margin-left: -1rem; margin-right: -1rem; }.algorithm-core-scroll__engine { width: 72vw; right: -8vw; top: 68%; opacity: .82; }.algorithm-core-scroll__ring--a { transform: rotateX(63deg) translateZ(32px); }.algorithm-core-scroll__ring--b { transform: rotateY(66deg) translateZ(14px); }.algorithm-core-scroll__content { align-self: flex-start; padding-top: 16vh; }.algorithm-core-scroll h2 { font-size: clamp(2.35rem, 10vw, 3.5rem); max-width: 21rem; }.algorithm-core-scroll__summary { max-width: 19rem; }.algorithm-core-scroll__type { max-width: 15rem; line-height: 1.7; }.algorithm-core-scroll__chapters { bottom: 20px; gap: 10px; left: 22px; }.algorithm-core-scroll__chapters button { font-size: 8px; }.algorithm-core-scroll__telemetry { right: 18px; bottom: 18px; font-size: 8px; }.algorithm-core-scroll__axis--y { right: 12%; } }
@media (prefers-reduced-motion: reduce) { .algorithm-core-scroll__cube, .algorithm-core-scroll__nodes i.hot { animation: none; }.core-copy-enter-active, .core-copy-leave-active { transition: none; } }
</style>
