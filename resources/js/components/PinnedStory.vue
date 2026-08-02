<template>
  <section ref="sectionRef" class="pinned-story relative" :style="{ height: totalHeight }">
    <div class="sticky top-0 h-screen w-full flex items-center justify-center overflow-hidden">

      <canvas ref="rainCanvas" class="absolute inset-0 w-full h-full opacity-[0.22]"></canvas>

      <div class="absolute inset-0 pointer-events-none">
        <div
          class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full blur-3xl transition-all duration-700"
          :style="glowStyle"
        ></div>
        <div class="absolute inset-0 grid-bg opacity-40"></div>
        <div class="scan-sweep"></div>
      </div>

      <ScrollScene3D :progress="progress" :act="activeIndex" :active="sectionVisible" />

      <div class="relative container mx-auto px-4 sm:px-6 z-10 max-w-full">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mb-3 sm:mb-4" data-aos="fade-right">
          <span class="hud-bar" :style="{ background: `linear-gradient(to right, transparent, ${currentAccent})`, boxShadow: `0 0 8px ${currentAccent}` }"></span>
          <span class="text-[10px] sm:text-xs tracking-[0.25em] sm:tracking-[0.3em] font-mono uppercase" :style="{ color: currentAccent }">
            CH {{ String(activeIndex + 1).padStart(2, '0') }} / {{ String(acts.length).padStart(2, '0') }}
          </span>
          <span class="text-[10px] sm:text-xs tracking-[0.2em] font-mono uppercase text-gray-500">
            · {{ (progress * 100).toFixed(0) }}%
          </span>
        </div>

        <div class="relative min-h-[420px] sm:min-h-[380px] md:min-h-[400px]">
          <Transition
            v-for="(act, i) in acts"
            :key="act.title"
            name="act"
          >
            <div
              v-show="activeIndex === i"
              class="absolute inset-0"
            >
              <p class="text-xs sm:text-sm md:text-base font-mono tracking-widest mb-3 sm:mb-4" :style="{ color: act.accent }">
                <span class="terminal-prompt">┌─</span>
                <span class="ml-2">{{ act.tag }}</span>
              </p>
              <h2
                class="glitch text-3xl sm:text-4xl md:text-6xl lg:text-7xl font-black mb-4 sm:mb-6 leading-tight break-keep"
                :data-text="stripTags(act.title)"
                :style="{ '--accent': act.accent }"
              >
                <span v-html="act.title"></span>
              </h2>

              <p class="text-sm sm:text-base md:text-xl text-gray-300 leading-relaxed max-w-3xl mb-4 sm:mb-6 font-mono pr-6 sm:pr-0">
                <span class="terminal-prompt mr-2" :style="{ color: act.accent }">&gt;</span>
                <span>{{ typed[i] }}</span>
                <span v-if="activeIndex === i" class="caret" :style="{ background: act.accent }"></span>
              </p>

              <div class="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3 max-w-3xl mb-4 sm:mb-6">
                <div
                  v-for="(stat, si) in act.stats"
                  :key="stat.label"
                  class="stat-card"
                  :style="{ '--accent': act.accent, animationDelay: (0.1 + si * 0.08) + 's' }"
                >
                  <div class="stat-value" :style="{ color: act.accent }">
                    <span v-if="activeIndex === i">{{ animateStats[si] }}</span>
                    <span v-else>{{ stat.value }}</span>
                    <span class="stat-suffix">{{ stat.suffix || '' }}</span>
                  </div>
                  <div class="stat-label">{{ stat.label }}</div>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 sm:gap-3">
                <span
                  v-for="kw in act.keywords"
                  :key="kw"
                  class="keyword-chip"
                  :style="{ borderColor: act.accent, color: act.accent, boxShadow: `0 0 12px ${act.accent}33 inset` }"
                >
                  &gt; {{ kw }}
                </span>
              </div>
            </div>
          </Transition>
        </div>
      </div>

      <div class="dot-nav absolute right-3 sm:right-6 md:right-10 top-1/2 -translate-y-1/2 flex flex-col gap-4 sm:gap-6 z-20">
        <div
          v-for="(act, i) in acts"
          :key="act.title + '_dot'"
          class="dot"
          :class="{ active: activeIndex === i }"
          :style="{ '--dot-color': act.accent }"
        >
          <span class="dot-label">{{ act.shortLabel }}</span>
        </div>
      </div>

      <div class="absolute bottom-4 sm:bottom-8 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center gap-2">
        <span class="text-[9px] sm:text-[10px] tracking-[0.25em] sm:tracking-[0.3em] text-cyan-300/70 font-mono animate-pulse">▼ SCROLL ▼</span>
        <div class="progress-rail">
          <div class="progress-fill" :style="{ height: (progress * 100) + '%' }"></div>
        </div>
      </div>

      <div class="corner-bracket top-left"></div>
      <div class="corner-bracket top-right"></div>
      <div class="corner-bracket bottom-left"></div>
      <div class="corner-bracket bottom-right"></div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, reactive } from 'vue';
import ScrollScene3D from './ScrollScene3D.vue';

const acts = [
  {
    tag: 'ACT_01 · SECURITY',
    shortLabel: 'SEC',
    title: '在防線崩壞之前，<br/><span class="accent-text">先讓 AI 看見它</span>。',
    body: '領導 IntelliTrust 研發下一代 AI-SOC 平台，整合 WAF、IDS/IPS 與 LLM 偵測引擎，讓未知威脅在發生當下就被識別、攔截、反制。',
    keywords: ['WAF', 'SOC', 'CVE Research', 'Zero-Day Defense'],
    stats: [
      { label: 'CVSS Max', value: 10, suffix: '.0' },
      { label: 'Threat Layers', value: 7 },
      { label: 'Detect Speed', value: 50, suffix: 'ms' },
      { label: 'Uptime SLA', value: 99, suffix: '.9%' },
    ],
    accent: '#22e0ff',
    glow: '#22e0ff',
  },
  {
    tag: 'ACT_02 · AI',
    shortLabel: 'AI',
    title: '把大型語言模型，<br/><span class="accent-text">煉成資安武器</span>。',
    body: '針對資安領域 Fine-tune Gemma-3-12B，結合 RAG 架構打造地端可部署的 AI 助手 — 漏洞自動識別、修補建議、事件回應，全部在你的網段內完成。',
    keywords: ['Gemma-3', 'Fine-tuning', 'RAG', 'On-prem'],
    stats: [
      { label: 'Model Params', value: 12, suffix: 'B' },
      { label: 'Context Win.', value: 128, suffix: 'K' },
      { label: 'RAG Docs', value: 50, suffix: 'K+' },
      { label: 'Latency', value: 120, suffix: 'ms' },
    ],
    accent: '#ff2bd6',
    glow: '#ff2bd6',
  },
  {
    tag: 'ACT_03 · IMPACT',
    shortLabel: 'YOU',
    title: '20 歲，<br/><span class="accent-text">從自學者走進產業核心</span>。',
    body: '通報校園 CVSS 9.8 RCE 漏洞、受邀擔任大學技術顧問、在 GDG 與 HackIt 演講、領導企業級資安產品 — 用實力證明：學歷不是終點，實踐才是起點。',
    keywords: ['CVSS 9.8', 'Speaker', 'Consultant', 'Manager'],
    stats: [
      { label: 'Years Old', value: 20 },
      { label: 'Media Coverage', value: 20, suffix: '+' },
      { label: 'Talks Given', value: 2, suffix: '+' },
      { label: 'Roles', value: 3 },
    ],
    accent: '#a78bfa',
    glow: '#a78bfa',
  },
];

const stripTags = (html) => html.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]*>/g, '');

const sectionRef = ref(null);
const rainCanvas = ref(null);
const progress = ref(0);
const activeIndex = ref(0);
const sectionVisible = ref(false);

const typed = reactive(acts.map(() => ''));
const animateStats = reactive(acts[0].stats.map(() => 0));

const totalHeight = computed(() => `${acts.length * 110}vh`);
const currentAccent = computed(() => acts[activeIndex.value].accent);

const glowStyle = computed(() => ({
  width: '70vw',
  height: '70vw',
  maxWidth: '900px',
  maxHeight: '900px',
  opacity: 0.28,
  background: `radial-gradient(circle, ${acts[activeIndex.value].glow} 0%, transparent 65%)`,
}));

let rafId = null;
let lastScrollY = -1;
let typingTimer = null;
let statTimer = null;
let matrixRaf = null;
let matrixState = null;
let sectionIo = null;

const startTypewriter = (index) => {
  clearInterval(typingTimer);
  typed[index] = '';
  const text = acts[index].body;
  let i = 0;
  typingTimer = setInterval(() => {
    typed[index] = text.slice(0, ++i);
    if (i >= text.length) clearInterval(typingTimer);
  }, 24);
};

const startStatsCount = (index) => {
  clearInterval(statTimer);
  const targets = acts[index].stats.map((s) => s.value);
  targets.forEach((_, i) => (animateStats[i] = 0));
  const steps = 28;
  let step = 0;
  statTimer = setInterval(() => {
    step++;
    const p = Math.min(step / steps, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    targets.forEach((t, i) => {
      const val = t * ease;
      animateStats[i] = Number.isInteger(t) ? Math.round(val) : val.toFixed(1);
    });
    if (step >= steps) clearInterval(statTimer);
  }, 30);
};

watch(activeIndex, (i) => {
  startTypewriter(i);
  startStatsCount(i);
});

const updateProgress = () => {
  rafId = null;
  if (!sectionRef.value) return;
  const rect = sectionRef.value.getBoundingClientRect();
  const viewH = window.innerHeight;
  const scrollable = rect.height - viewH;
  if (scrollable <= 0) return;
  const scrolled = Math.min(Math.max(-rect.top, 0), scrollable);
  const p = scrolled / scrollable;
  progress.value = p;

  const raw = p * acts.length;
  const idx = Math.min(acts.length - 1, Math.floor(raw));
  if (activeIndex.value !== idx) activeIndex.value = idx;
};

const onScroll = () => {
  if (window.scrollY === lastScrollY) return;
  lastScrollY = window.scrollY;
  if (rafId === null) rafId = requestAnimationFrame(updateProgress);
};

const initMatrix = () => {
  const canvas = rainCanvas.value;
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const resize = () => {
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
  };
  resize();

  const chars = '01ア0ミ1ズ0カゲ1ハ0ナ1ヒ0フ1ヘ0ホ1マ<>[]{}#@';
  const fontSize = 14;
  const cols = Math.floor(canvas.width / fontSize);
  const drops = Array.from({ length: cols }, () => Math.random() * -20);

  matrixState = { canvas, ctx, resize, chars, fontSize, cols, drops };

  window.addEventListener('resize', resize);
};

// Matrix rain：24fps 已足夠呈現效果，成本約為 60fps 的 40%
let lastRainTs = 0;
const drawRain = (ts) => {
  matrixRaf = requestAnimationFrame(drawRain);
  if (ts - lastRainTs < 41) return;
  lastRainTs = ts;
  const { ctx, canvas, chars, fontSize, drops } = matrixState;
  ctx.fillStyle = 'rgba(5, 6, 15, 0.18)';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.font = `${fontSize}px ui-monospace, monospace`;
  for (let i = 0; i < drops.length; i++) {
    const text = chars[Math.floor(Math.random() * chars.length)];
    const x = i * fontSize;
    const y = drops[i] * fontSize;
    ctx.fillStyle = currentAccent.value;
    ctx.fillText(text, x, y);
    if (y > canvas.height && Math.random() > 0.975) drops[i] = 0;
    drops[i] += 1.75; // 補償低幀率，維持原本視覺下落速度
  }
};

// 只在區塊可見且分頁前景時跑動畫迴圈（3D 場景由 sectionVisible prop 自行閘控）
const startLoops = () => {
  if (!sectionVisible.value || document.hidden) return;
  if (!matrixRaf && matrixState) {
    lastRainTs = 0;
    matrixRaf = requestAnimationFrame(drawRain);
  }
};

const stopLoops = () => {
  if (matrixRaf) { cancelAnimationFrame(matrixRaf); matrixRaf = null; }
};

const onVisibilityChange = () => {
  document.hidden ? stopLoops() : startLoops();
};

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  document.addEventListener('visibilitychange', onVisibilityChange);
  updateProgress();
  startTypewriter(0);
  startStatsCount(0);
  initMatrix();

  sectionIo = new IntersectionObserver(([entry]) => {
    sectionVisible.value = entry.isIntersecting;
    entry.isIntersecting ? startLoops() : stopLoops();
  }, { rootMargin: '10% 0px' });
  sectionIo.observe(sectionRef.value);
});

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('resize', onScroll);
  document.removeEventListener('visibilitychange', onVisibilityChange);
  if (rafId) cancelAnimationFrame(rafId);
  stopLoops();
  sectionIo?.disconnect();
  clearInterval(typingTimer);
  clearInterval(statTimer);
  if (matrixState) window.removeEventListener('resize', matrixState.resize);
});
</script>

<style scoped>
:deep(.accent-text) {
  color: var(--accent, #22e0ff);
  text-shadow: 0 0 18px var(--accent, #22e0ff), 0 0 2px #fff;
}

.grid-bg {
  background-image:
    linear-gradient(rgba(34, 224, 255, 0.08) 1px, transparent 1px),
    linear-gradient(90deg, rgba(34, 224, 255, 0.08) 1px, transparent 1px);
  background-size: 60px 60px;
  mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
}

.scan-sweep {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to bottom,
    transparent 0%,
    transparent 48%,
    rgba(34, 224, 255, 0.15) 50%,
    transparent 52%,
    transparent 100%
  );
  animation: sweep 4s linear infinite;
  mix-blend-mode: screen;
}
@keyframes sweep {
  0%   { transform: translateY(-100%); }
  100% { transform: translateY(100%); }
}

.hud-bar {
  display: inline-block;
  width: 42px;
  height: 2px;
}

.terminal-prompt {
  font-family: ui-monospace, 'Fira Code', monospace;
}

.caret {
  display: inline-block;
  width: 8px;
  height: 1.1em;
  vertical-align: text-bottom;
  margin-left: 4px;
  animation: blink 1s steps(2) infinite;
}
@keyframes blink { 50% { opacity: 0; } }

.glitch {
  position: relative;
  color: #fff;
  text-shadow: 0 0 18px rgba(255, 255, 255, 0.25);
}
.glitch::before,
.glitch::after {
  content: attr(data-text);
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.7;
  white-space: pre-line;
}
.glitch::before {
  color: var(--accent, #22e0ff);
  transform: translate(-2px, 0);
  clip-path: polygon(0 0, 100% 0, 100% 45%, 0 45%);
  animation: glitchA 3.6s infinite linear alternate-reverse;
}
.glitch::after {
  color: #ff2bd6;
  transform: translate(2px, 0);
  clip-path: polygon(0 55%, 100% 55%, 100% 100%, 0 100%);
  animation: glitchB 4.2s infinite linear alternate-reverse;
}
@media (max-width: 768px) {
  .glitch::before, .glitch::after { display: none; }
}
@keyframes glitchA {
  0%, 88%, 100% { transform: translate(-2px, 0); }
  90% { transform: translate(-6px, 1px); }
  92% { transform: translate(-2px, -2px); }
  94% { transform: translate(-6px, 1px); }
}
@keyframes glitchB {
  0%, 85%, 100% { transform: translate(2px, 0); }
  87% { transform: translate(6px, -1px); }
  89% { transform: translate(2px, 2px); }
  91% { transform: translate(6px, -1px); }
}

.stat-card {
  padding: 12px 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-left: 2px solid var(--accent);
  background: linear-gradient(135deg, rgba(5, 6, 15, 0.6), rgba(5, 6, 15, 0.2));
  backdrop-filter: blur(6px);
  animation: statIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  min-width: 0;
}
@media (max-width: 640px) {
  .stat-card { padding: 8px 10px; }
}
.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 0 24px var(--accent);
}
@keyframes statIn {
  from { opacity: 0; transform: translateX(-10px); }
  to   { opacity: 1; transform: translateX(0); }
}
.stat-value {
  font-family: ui-monospace, 'Fira Code', monospace;
  font-size: clamp(16px, 3.8vw, 22px);
  font-weight: 700;
  letter-spacing: 0.04em;
  line-height: 1;
  text-shadow: 0 0 12px currentColor;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.stat-suffix {
  font-size: 0.7em;
  opacity: 0.7;
  margin-left: 2px;
}
.stat-label {
  margin-top: 4px;
  font-size: clamp(9px, 2vw, 10px);
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.5);
  font-family: ui-monospace, monospace;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.keyword-chip {
  padding: 5px 10px;
  font-size: clamp(10px, 2.4vw, 12px);
  font-family: ui-monospace, 'Fira Code', monospace;
  letter-spacing: 0.08em;
  border: 1px solid;
  border-radius: 999px;
  background: rgba(5, 6, 15, 0.4);
  backdrop-filter: blur(4px);
  transition: transform 0.25s ease;
}
@media (min-width: 640px) {
  .keyword-chip { padding: 6px 14px; }
}
.keyword-chip:hover { transform: translateY(-2px); }

.dot {
  position: relative;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.18);
  transition: all 0.4s ease;
}
@media (min-width: 640px) {
  .dot { width: 10px; height: 10px; }
}
.dot::after {
  content: '';
  position: absolute;
  inset: -6px;
  border-radius: 50%;
  border: 1px solid transparent;
  transition: all 0.4s ease;
}
.dot.active {
  background: var(--dot-color);
  box-shadow: 0 0 14px var(--dot-color);
}
.dot.active::after {
  border-color: var(--dot-color);
  animation: pulseRing 2.2s ease-in-out infinite;
}
.dot-label {
  position: absolute;
  right: 18px;
  top: 50%;
  transform: translateY(-50%);
  white-space: nowrap;
  font-size: 9px;
  font-family: ui-monospace, 'Fira Code', monospace;
  letter-spacing: 0.2em;
  color: var(--dot-color);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}
@media (min-width: 640px) {
  .dot-label { right: 22px; font-size: 10px; }
}
@media (max-width: 480px) {
  .dot-label { display: none; }
}
.dot.active .dot-label { opacity: 0.9; }
@keyframes pulseRing {
  0%, 100% { transform: scale(1); opacity: 1; }
  50%      { transform: scale(1.4); opacity: 0.3; }
}

.progress-rail {
  width: 2px;
  height: 60px;
  background: rgba(34, 224, 255, 0.18);
  border-radius: 2px;
  overflow: hidden;
}
.progress-fill {
  width: 100%;
  background: linear-gradient(to bottom, #22e0ff, #ff2bd6, #a78bfa);
  transition: height 0.15s linear;
  box-shadow: 0 0 8px #22e0ff;
}

.corner-bracket {
  position: absolute;
  width: 20px;
  height: 20px;
  border: 1px solid rgba(34, 224, 255, 0.5);
  filter: drop-shadow(0 0 4px rgba(34, 224, 255, 0.5));
}
@media (min-width: 640px) {
  .corner-bracket { width: 32px; height: 32px; }
}
.corner-bracket.top-left     { top: 76px; left: 10px; border-right: none; border-bottom: none; }
.corner-bracket.top-right    { top: 76px; right: 10px; border-left: none; border-bottom: none; border-color: #ff2bd6; filter: drop-shadow(0 0 4px #ff2bd6); }
.corner-bracket.bottom-left  { bottom: 10px; left: 10px; border-right: none; border-top: none; border-color: #ff2bd6; filter: drop-shadow(0 0 4px #ff2bd6); }
.corner-bracket.bottom-right { bottom: 10px; right: 10px; border-left: none; border-top: none; }
@media (min-width: 640px) {
  .corner-bracket.top-left, .corner-bracket.top-right { top: 92px; }
  .corner-bracket.bottom-left, .corner-bracket.bottom-right { bottom: 16px; }
  .corner-bracket.top-left, .corner-bracket.bottom-left { left: 16px; }
  .corner-bracket.top-right, .corner-bracket.bottom-right { right: 16px; }
}

.act-enter-active,
.act-leave-active {
  transition: opacity 0.45s ease, transform 0.55s cubic-bezier(0.22, 1, 0.36, 1), filter 0.45s ease;
}
.act-enter-from {
  opacity: 0;
  transform: translateY(30px);
  filter: blur(6px);
}
.act-leave-to {
  opacity: 0;
  transform: translateY(-20px);
  filter: blur(4px);
}
</style>
