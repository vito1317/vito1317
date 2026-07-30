<template>
  <section ref="sectionRef" class="fixed-3d-scroll" :style="{ height: `${slides.length * 115}vh` }">
    <div class="fixed-3d-scroll__sticky">
      <div class="fixed-3d-scroll__stars"></div>
      <div class="fixed-3d-scroll__horizon"></div>

      <div class="fixed-3d-scroll__scene" :style="sceneStyle" aria-hidden="true">
        <div class="fixed-3d-scroll__floor"></div>
        <div class="fixed-3d-scroll__beam fixed-3d-scroll__beam--left"></div>
        <div class="fixed-3d-scroll__beam fixed-3d-scroll__beam--right"></div>
        <div
          v-for="(slide, index) in slides"
          :key="slide.code"
          class="fixed-3d-scroll__panel"
          :class="{ 'is-active': activeIndex === index }"
          :style="panelStyle(index)"
        >
          <div class="fixed-3d-scroll__panel-glow"></div>
          <span class="fixed-3d-scroll__panel-number">{{ String(index + 1).padStart(2, '0') }}</span>
          <span class="fixed-3d-scroll__panel-code">{{ slide.code }}</span>
          <div v-if="index === 0" class="algorithm-visual algorithm-visual--tact">
            <span v-for="node in 9" :key="node" class="tact-node" :class="{ 'tact-node--winner': node > 5 }"></span>
            <span class="tact-core">γ</span>
            <span class="tact-caption">RANKED CONSENSUS</span>
          </div>
          <div v-else-if="index === 1" class="algorithm-visual algorithm-visual--rlev">
            <span v-for="chain in 5" :key="chain" class="rlev-chain" :class="{ 'rlev-chain--filtered': chain > 3 }">
              <i></i><i></i><i></i><i></i>
            </span>
            <span class="rlev-gate">≠</span>
            <span class="rlev-caption">DE-DUPLICATE</span>
          </div>
          <div v-else class="algorithm-visual algorithm-visual--anomaly">
            <span v-for="point in 30" :key="point" class="anomaly-point" :class="{ 'anomaly-point--alert': point === 6 || point === 17 || point === 28 }"></span>
            <span class="anomaly-scan"></span>
            <span class="anomaly-caption">16D BASELINE // OUTLIERS</span>
          </div>
          <span class="fixed-3d-scroll__panel-line"></span>
        </div>
      </div>

      <div class="fixed-3d-scroll__copy container mx-auto px-6 sm:px-10">
        <p class="fixed-3d-scroll__eyebrow">ORIGINAL ALGORITHMS // 3D SCROLL</p>
        <Transition name="fixed-3d-scroll__text" mode="out-in">
          <div :key="activeSlide.code" class="max-w-xl">
            <p class="fixed-3d-scroll__chapter">{{ activeSlide.chapter }}</p>
            <h2 v-html="activeSlide.title"></h2>
            <p class="fixed-3d-scroll__description">{{ activeSlide.description }}</p>
          </div>
        </Transition>
      </div>

      <div class="fixed-3d-scroll__progress" aria-label="捲動進度">
        <span v-for="(slide, index) in slides" :key="slide.code" :class="{ active: activeIndex === index }"></span>
      </div>
      <p class="fixed-3d-scroll__hint">SCROLL TO EXPLORE <i></i></p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const slides = [
  { code: '01 // TACT', chapter: '01 — TACT CONSENSUS', title: '信心失準時，<br><em>共識仍能可靠</em>。', description: 'TACT 以秩統計取代原始信心值，零標籤辨識信心通道的正負號；沒有足夠證據時，會精確退回 Self-Consistency。', color: '#22e0ff' },
  { code: '02 // RLEV-VOI', chapter: '02 — REDUNDANCY CONTROL', title: '不是更多投票，<br><em>而是更有效的票</em>。', description: 'RLEV-VoI 對重複推理鏈折扣，避免錯誤模板以回音式投票壟斷答案，並以資訊價值判斷何時停止取樣。', color: '#ff2bd6' },
  { code: '03 // ML ANOMALY', chapter: '03 — ADAPTIVE DEFENSE', title: '讓每個站點，<br><em>學會辨認異常</em>。', description: '自適應 ML 行為異常偵測引擎從 16 維請求特徵建立基線，依標記量自動升級 Statistical、Logistic 與 MLP 模型。', color: '#a78bfa' },
];

const sectionRef = ref(null);
const progress = ref(0);
const activeIndex = ref(0);
let rafId = null;

const activeSlide = computed(() => slides[activeIndex.value]);
const sceneStyle = computed(() => ({
  '--accent': activeSlide.value.color,
  '--turn': `${-16 + progress.value * 32}deg`,
  '--zoom': `${1 + progress.value * 0.08}`,
}));

const panelStyle = (index) => {
  const depth = index - progress.value * (slides.length - 1);
  const distance = Math.abs(depth);
  return {
    '--panel-color': slides[index].color,
    opacity: Math.max(0.18, 1 - distance * 0.38),
    transform: `translate3d(${depth * 14}vw, ${depth * -5}vh, ${-distance * 520}px) rotateY(${depth * -27}deg) rotateX(${depth * 8}deg) scale(${1 - Math.min(distance, 1.5) * 0.16})`,
    filter: `blur(${Math.max(0, distance - 0.2) * 2.2}px)`,
  };
};

const updateProgress = () => {
  rafId = null;
  const section = sectionRef.value;
  if (!section) return;
  const rect = section.getBoundingClientRect();
  const scrollable = rect.height - window.innerHeight;
  const nextProgress = scrollable > 0 ? Math.min(1, Math.max(0, -rect.top / scrollable)) : 0;
  progress.value = nextProgress;
  activeIndex.value = Math.min(slides.length - 1, Math.floor(nextProgress * slides.length));
};

const onScroll = () => {
  if (rafId === null) rafId = requestAnimationFrame(updateProgress);
};

onMounted(() => {
  updateProgress();
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
});

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('resize', onScroll);
  if (rafId) cancelAnimationFrame(rafId);
});
</script>

<style scoped>
.fixed-3d-scroll { position: relative; z-index: 1; }
.fixed-3d-scroll__sticky { position: sticky; top: 0; height: 100vh; overflow: hidden; display: flex; align-items: center; background: radial-gradient(circle at 70% 45%, color-mix(in srgb, var(--accent, #22e0ff) 16%, transparent), transparent 32%), #03040c; isolation: isolate; }
.fixed-3d-scroll__stars, .fixed-3d-scroll__horizon { position: absolute; inset: 0; pointer-events: none; }
.fixed-3d-scroll__stars { opacity: .6; background-image: radial-gradient(#fff 1px, transparent 1px), radial-gradient(var(--accent, #22e0ff) 1px, transparent 1px); background-size: 72px 72px, 113px 113px; background-position: 20px 16px, 8px 40px; mask-image: radial-gradient(ellipse at center, black, transparent 76%); }
.fixed-3d-scroll__horizon { background: linear-gradient(transparent 47%, color-mix(in srgb, var(--accent, #22e0ff) 38%, transparent) 49%, transparent 50%); opacity: .8; }
.fixed-3d-scroll__scene { position: absolute; width: min(78vw, 980px); height: min(78vw, 760px); right: -3vw; top: 50%; transform: translateY(-50%) scale(var(--zoom)); perspective: 1200px; transform-style: preserve-3d; transition: --accent .4s ease; }
.fixed-3d-scroll__floor { position: absolute; width: 150%; height: 95%; left: -25%; top: 53%; transform: rotateX(68deg) rotateZ(var(--turn)); transform-origin: center top; background-image: linear-gradient(color-mix(in srgb, var(--accent, #22e0ff) 26%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent, #22e0ff) 26%, transparent) 1px, transparent 1px); background-size: 52px 52px; mask-image: linear-gradient(to bottom, black, transparent 82%); }
.fixed-3d-scroll__beam { position: absolute; width: 1px; height: 140%; top: -20%; background: linear-gradient(transparent, var(--accent), transparent); box-shadow: 0 0 18px var(--accent); opacity: .5; transform: rotate(27deg); }.fixed-3d-scroll__beam--left { left: 25%; }.fixed-3d-scroll__beam--right { right: 14%; transform: rotate(-27deg); }
.fixed-3d-scroll__panel { position: absolute; width: min(44vw, 420px); aspect-ratio: .74; left: 25%; top: 14%; transform-style: preserve-3d; border: 1px solid color-mix(in srgb, var(--panel-color) 68%, transparent); background: linear-gradient(135deg, color-mix(in srgb, var(--panel-color) 14%, rgba(9,11,25,.72)), rgba(4,5,15,.56)); box-shadow: inset 0 0 50px color-mix(in srgb, var(--panel-color) 18%, transparent), 0 0 55px color-mix(in srgb, var(--panel-color) 28%, transparent); transition: opacity .18s linear, filter .18s linear; overflow: hidden; }
.fixed-3d-scroll__panel::before, .fixed-3d-scroll__panel::after { content: ''; position: absolute; inset: 12px; border: 1px solid color-mix(in srgb, var(--panel-color) 32%, transparent); }.fixed-3d-scroll__panel::after { inset: auto 12px 18%; height: 1px; background: var(--panel-color); border: 0; box-shadow: 0 0 14px var(--panel-color); }
.fixed-3d-scroll__panel-glow { position: absolute; width: 65%; aspect-ratio: 1; top: 22%; left: 18%; border-radius: 50%; background: radial-gradient(circle, color-mix(in srgb, var(--panel-color) 72%, white), transparent 66%); filter: blur(13px); opacity: .45; animation: pulse-orb 3s ease-in-out infinite; }
.algorithm-visual { position: absolute; z-index: 1; inset: 20% 13% 21%; transform: translateZ(48px); color: var(--panel-color); font-family: ui-monospace, monospace; }.algorithm-visual::before { content: ''; position: absolute; inset: 0; border: 1px solid color-mix(in srgb, var(--panel-color) 24%, transparent); background-image: linear-gradient(90deg, color-mix(in srgb, var(--panel-color) 12%, transparent) 1px, transparent 1px), linear-gradient(color-mix(in srgb, var(--panel-color) 12%, transparent) 1px, transparent 1px); background-size: 16px 16px; }
.algorithm-visual--tact { border-radius: 50%; }.algorithm-visual--tact::before { border-radius: 50%; }.tact-node { position: absolute; z-index: 2; width: 9px; height: 9px; border: 1px solid var(--panel-color); border-radius: 50%; background: #080a16; box-shadow: 0 0 8px var(--panel-color); }.tact-node::after { content: ''; position: absolute; width: 52px; height: 1px; top: 3px; left: 7px; transform-origin: left; background: linear-gradient(90deg, var(--panel-color), transparent); opacity: .5; }.tact-node:nth-child(1) { left: 13%; top: 17%; }.tact-node:nth-child(2) { left: 42%; top: 8%; }.tact-node:nth-child(3) { left: 76%; top: 20%; }.tact-node:nth-child(4) { left: 8%; top: 49%; }.tact-node:nth-child(5) { left: 80%; top: 52%; }.tact-node:nth-child(6) { left: 22%; top: 78%; }.tact-node:nth-child(7) { left: 50%; top: 88%; }.tact-node:nth-child(8) { left: 75%; top: 76%; }.tact-node:nth-child(9) { left: 48%; top: 48%; }.tact-node--winner { background: var(--panel-color); animation: tact-vote 1.8s ease-in-out infinite alternate; }.tact-node--winner::after { opacity: .95; }.tact-core { position: absolute; z-index: 3; top: 39%; left: 40%; width: 34px; height: 34px; display: grid; place-items: center; border: 1px solid var(--panel-color); border-radius: 50%; background: #090b1d; box-shadow: 0 0 26px var(--panel-color); font-size: 18px; }.tact-caption, .rlev-caption, .anomaly-caption { position: absolute; z-index: 3; bottom: -20px; left: 50%; transform: translateX(-50%); width: max-content; font-size: 7px; letter-spacing: .12em; opacity: .85; }
.algorithm-visual--rlev { padding: 8% 9%; }.rlev-chain { position: relative; z-index: 2; display: flex; gap: 7px; width: 72%; margin: 8px 0; }.rlev-chain i { display: block; width: 8px; height: 8px; border: 1px solid var(--panel-color); transform: rotate(45deg); box-shadow: 0 0 5px var(--panel-color); }.rlev-chain:not(:first-child) { opacity: .44; transform: translateX(12px); }.rlev-chain--filtered { opacity: .13 !important; }.rlev-chain--filtered i { text-decoration: line-through; }.rlev-gate { position: absolute; z-index: 4; right: 6%; top: 38%; display: grid; place-items: center; width: 32px; height: 32px; border: 1px solid var(--panel-color); background: #070916; box-shadow: 0 0 18px var(--panel-color); font-size: 23px; }.rlev-caption { bottom: 4px; }
.algorithm-visual--anomaly { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; padding: 12% 13%; overflow: hidden; }.anomaly-point { position: relative; z-index: 2; width: 5px; height: 5px; align-self: center; justify-self: center; border-radius: 50%; background: var(--panel-color); box-shadow: 0 0 7px var(--panel-color); opacity: .55; }.anomaly-point--alert { width: 12px; height: 12px; background: #ff4b71; box-shadow: 0 0 13px #ff4b71; animation: anomaly-alert 1s steps(2) infinite; }.anomaly-scan { position: absolute; z-index: 3; width: 100%; height: 1px; left: 0; top: 0; background: #fff; box-shadow: 0 0 14px var(--panel-color), 0 0 30px var(--panel-color); animation: anomaly-scan 2.7s linear infinite; }.anomaly-caption { bottom: 4px; }
.fixed-3d-scroll__panel-number, .fixed-3d-scroll__panel-code { position: absolute; z-index: 2; font: 700 11px/1 ui-monospace, monospace; letter-spacing: .18em; color: var(--panel-color); }.fixed-3d-scroll__panel-number { top: 27px; left: 28px; font-size: 26px; }.fixed-3d-scroll__panel-code { right: 25px; bottom: 25px; writing-mode: vertical-rl; }
.fixed-3d-scroll__copy { position: relative; z-index: 2; pointer-events: none; }.fixed-3d-scroll__eyebrow, .fixed-3d-scroll__chapter { font: 700 11px/1.5 ui-monospace, monospace; letter-spacing: .24em; color: var(--accent); }.fixed-3d-scroll__chapter { margin: 22px 0 12px; opacity: .9; }.fixed-3d-scroll__copy h2 { font-size: clamp(2.7rem, 6vw, 6.7rem); font-weight: 900; line-height: .98; letter-spacing: -.06em; }.fixed-3d-scroll__copy h2 :deep(em) { color: var(--accent); font-style: normal; text-shadow: 0 0 32px color-mix(in srgb, var(--accent) 58%, transparent); }.fixed-3d-scroll__description { margin-top: 20px; max-width: 29rem; font-size: clamp(.95rem, 1.6vw, 1.15rem); line-height: 1.8; color: #b6bfd2; }.fixed-3d-scroll__progress { position: absolute; z-index: 4; right: 28px; top: 50%; display: grid; gap: 10px; transform: translateY(-50%); }.fixed-3d-scroll__progress span { width: 3px; height: 34px; background: rgba(255,255,255,.2); transition: .3s ease; }.fixed-3d-scroll__progress .active { background: var(--accent); box-shadow: 0 0 14px var(--accent); height: 55px; }.fixed-3d-scroll__hint { position: absolute; z-index: 3; left: 50%; bottom: 30px; transform: translateX(-50%); font: 10px/1 ui-monospace, monospace; letter-spacing: .22em; color: #b8c1d8; white-space: nowrap; }.fixed-3d-scroll__hint i { display: inline-block; width: 34px; height: 1px; margin-left: 8px; vertical-align: middle; background: var(--accent); box-shadow: 0 0 8px var(--accent); }
.fixed-3d-scroll__text-enter-active, .fixed-3d-scroll__text-leave-active { transition: opacity .28s ease, transform .35s cubic-bezier(.16,1,.3,1); }.fixed-3d-scroll__text-enter-from { opacity: 0; transform: translateY(18px); }.fixed-3d-scroll__text-leave-to { opacity: 0; transform: translateY(-12px); }
@keyframes pulse-orb { 50% { transform: scale(1.18); opacity: .8; } } @keyframes tact-vote { to { transform: scale(1.7); box-shadow: 0 0 18px var(--panel-color); } } @keyframes anomaly-scan { to { transform: translateY(240px); } } @keyframes anomaly-alert { 50% { opacity: .2; } }
@media (max-width: 700px) { .fixed-3d-scroll__scene { width: 76vw; height: 76vw; right: -15vw; top: 64%; opacity: .58; }.fixed-3d-scroll__panel { width: 55vw; left: 12%; top: 8%; }.fixed-3d-scroll__panel:not(.is-active) { display: none; }.fixed-3d-scroll__panel-number { top: 15px; left: 16px; font-size: 18px; }.fixed-3d-scroll__panel-code { right: 13px; bottom: 13px; font-size: 8px; }.fixed-3d-scroll__copy { align-self: flex-start; padding-top: 18vh; }.fixed-3d-scroll__copy h2 { font-size: clamp(2.8rem, 14vw, 4.7rem); }.fixed-3d-scroll__description { max-width: 20rem; }.fixed-3d-scroll__progress { right: 12px; }.fixed-3d-scroll__hint { bottom: 18px; font-size: 8px; } }
@media (prefers-reduced-motion: reduce) { .fixed-3d-scroll__panel-glow, .tact-node--winner, .anomaly-scan, .anomaly-point--alert { animation: none; }.fixed-3d-scroll__text-enter-active, .fixed-3d-scroll__text-leave-active { transition: none; } }
</style>
