<script>
/**
 * AgentMetal —— 把 Jakub Antalik 的 metal-fx（原生 WebGL1「液態金屬環」）接成 Vue 3 元件。
 *
 * 為什麼是 vendor 而不是 `import { ... } from 'metal-fx'`：
 *   1. npm 上的 metal-fx@1.0.4 只出一個 bundle（dist/index.es.js），檔頭就是
 *      `import ... from "react"` 與 `"react/jsx-runtime"`。這個專案沒有裝 React，
 *      Vite 解析 bare specifier 會直接失敗——tree-shaking 是打包後期的事，救不了解析。
 *   2. 更關鍵的是 API 缺口：上游 `src/index.ts` 只 re-export 了 createInstance /
 *      destroyInstance / updateInstance / setSharedPreset / pauseShared / resumeShared /
 *      PRESETS / hexToRgb。但 `src/MetalFx.tsx`（我們的規格書）還用到
 *      setInstanceVisible、registerGlowInstance、unregisterGlowInstance、setGlowCallback、
 *      injectGlow、updateGlow —— 這些沒被 export。也就是說「只用 npm 公開 API」
 *      做不出 IntersectionObserver 暫停與 SVG glow 光暈，只能做半套。
 *   所以照任務指示，把 `src/engine/`（純 .ts、零 React）與 `src/styles.ts` 原樣複製到
 *   resources/js/soc/vendor/metal-fx/，一個字都沒改，shader 也是上游那 257 行。
 *
 * 共用架構完全保留：整頁只有一個 WebGL context、shader 只編譯一次、一個 rAF loop
 * 驅動所有實例。這些都在 vendor 的 renderer/core.ts 與 renderer/loop.ts 裡，
 * 本元件只是「建立 / 更新 / 銷毀一個 instance」的殼，不自己開 context、不自己跑 rAF。
 */
import { ensureStylesInjected } from '@agentfx/metal-fx/styles';
import { injectGlow, updateGlow } from '@agentfx/metal-fx/engine/glow/glow';
import {
  createInstance,
  destroyInstance,
  registerGlowInstance,
  setGlowCallback,
  setInstanceVisible,
  setSharedPreset,
  unregisterGlowInstance,
  updateInstance,
} from '@agentfx/metal-fx/engine/renderer/loop';
import { ease, tween, tweenStart, tweenTick } from '@agentfx/metal-fx/engine/tween';
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

/**
 * 以下三行是「整個模組只做一次」的事，所以放在普通 <script> 而不是 <script setup>。
 * <script setup> 的頂層敘述其實是 setup() 的函式體，每 mount 一個元件就會再跑一次。
 */

// 上游在 MetalFx.tsx 也是 module scope 就注入，讓樣式先於第一次 render 存在。
ensureStylesInjected();

/**
 * instance → glow handles 的橋接表。照抄上游 MetalFx.tsx 的 glowHandlesMap 設計：
 * loop.ts 刻意不 import glow（避免循環相依），改用一個 callback，
 * 每一格只挑 queue 裡的一個 instance 更新，讓 glow 成本不隨實例數線性成長。
 */
const glowBridge = new Map();

// setGlowCallback 是全域單一插槽，只能設定一次；若在元件 unmount 時清掉會害其他實例失明。
setGlowCallback((inst, nowMs) => {
  const entry = glowBridge.get(inst);
  if (!entry) return;
  // 第四個參數傳 inst.opacityMul，等同上游把 strength 同時餵給 ring 與 halo。
  updateGlow(entry.handles, inst, nowMs, inst.opacityMul, entry.theme.value);
});

/**
 * 調色只有 chromatic / silver / gold 三種，而 setSharedPreset 是「整頁共用」的——
 * 它改的是 SHARED.preset，不是 per-instance。所以全站固定一個值，不隨 busy 切換，
 * 否則兩個 AgentMetal 狀態不同時會互相蓋掉對方的 uniform。
 * 選 chromatic：它的 dark 色階（#aae8ff / #c5fe9e / #007cff）本來就偏青藍，
 * 和面板的 --cyan (#2fe6d6) 同一個色溫；silver 太灰、gold 會和 --amb 告警色打架。
 */
const PRESET = 'chromatic';

// busy 用上游 `strength` prop 的語意表達：只縮放合成到畫面上的 alpha，
// shader 本身照常全速跑。待命時壓暗成一圈冷金屬，生成時整條環＋光暈亮起來。
const STRENGTH_IDLE = 0.55;
const STRENGTH_BUSY = 1;
const STRENGTH_MS = 480;

// 第一格畫出來之前 wrapper 是隱形的（照上游避免 unsized canvas 閃一下）。
// 但若元件掛載時剛好在可視範圍外，IntersectionObserver 會讓它永遠畫不出第一格，
// 包在裡面的按鈕就會一直點不到——上游有同樣的洞，這裡加一道保險。
const READY_FALLBACK_MS = 600;
</script>

<script setup>
const props = defineProps({
  // 'sm' = 28px 圓形（助理面板按鈕裡）| 'md' = 面板頂部（約 320×112）
  size: { type: String, default: 'md', validator: (v) => v === 'sm' || v === 'md' },
  // 助理正在生成 → 效果要更活躍
  busy: { type: Boolean, default: false },
});

const rootEl = ref(null);
const canvasEl = ref(null);
const glowHostEl = ref(null);
const contentEl = ref(null);

const ready = ref(false);
const reduced = ref(false);
const surfaceDark = ref(true);

// 上游 variant 的兩個值：'circle' 走 2px 環 / shaderScale 1.3，'button' 走 1px 環 / 1.6。
// 28px 的助理按鈕對應 circle，320×112 的面板頂部對應 button。
const variant = computed(() => (props.size === 'sm' ? 'circle' : 'button'));
const shape = computed(() => (props.size === 'sm' ? 'circle' : 'pill'));

/**
 * 上游 normalizeHostStyles 預設 true，會對被包住的子元素下
 * `background/border/outline/box-shadow` 的 !important 歸零——因為它的使用情境是
 * 「包一顆按鈕，金屬環取代按鈕原本的外框」。sm 正是這個情境，所以開。
 * md 包的是面板頂部的內容（標題、狀態列），把它們的底色框線全砍掉是破壞性的，所以關。
 */
const normalize = computed(() => (props.size === 'sm' ? 'true' : 'false'));

/**
 * 主題解析。上游 useResolvedTheme 是 matchMedia('(prefers-color-scheme: dark)')
 * ＋ change 監聽，這裡機制照做（見 onMounted 的 bindMedia），但「判斷依據」換了：
 *
 * 這個面板沒有淺色主題，背景永遠是 --bg (#070a12)。若照 OS 偏好走，
 * 使用者的系統設成淺色時就會套上 light preset（整組白到 #ffffff 的色階）
 * 加上 mix-blend-mode: multiply，在深底上會糊成一團看不見。
 * 所以真正決定 dark/light 的是「元件實際貼在什麼顏色的表面上」——
 * 直接量 wrapper 自己的 computed background 亮度。matchMedia 仍然掛著，
 * 因為 CSS 變數未來若跟著 prefers-color-scheme 變，需要它來觸發重新量測。
 */
const resolvedTheme = computed(() => (surfaceDark.value ? 'dark' : 'light'));

let inst = null;
let ro = null;
let io = null;
let resizeRaf = 0;
let readyTimer = 0;
let initialWrapperRadius = 0;
let strengthTw = null;
const mediaBindings = [];

// ─── 幾何量測 ──────────────────────────────────────────────────────────────

/**
 * 照抄上游 resolveRadius。
 * circle 一律取 min(w,h)/2：variant='circle' 是使用者「這東西就是圓的」的明示，
 * 比讀 computed border-radius 可靠（CSS zoom 下 getComputedStyle 回原始 px、
 * getBoundingClientRect 回縮放後 px，兩者不同座標系）。
 * pill 則讀被包裹子元素的 border-radius，讀不到才退回 wrapper 自己 mount 當下的值。
 */
function resolveRadius(w, h) {
  if (shape.value === 'circle') return Math.min(w, h) / 2;
  let raw = initialWrapperRadius;
  const childEl = contentEl.value?.firstElementChild;
  if (childEl) {
    const parsed = parseFloat(getComputedStyle(childEl).borderTopLeftRadius);
    if (Number.isFinite(parsed) && parsed > 0) raw = parsed;
  }
  return Math.min(raw, Math.min(w, h) / 2);
}

function measure() {
  const rect = rootEl.value.getBoundingClientRect();
  const cssWidth = Math.max(1, Math.round(rect.width));
  const cssHeight = Math.max(1, Math.round(rect.height));
  return { cssWidth, cssHeight, cornerRadius: resolveRadius(cssWidth, cssHeight) };
}

// wrapper 的圓角要跟著 shader 打洞的半徑走，否則 ::after 的外框和金屬環會對不齊。
function applyRadius(root, r) {
  root.style.setProperty('--mfx-radius', `${r}px`);
  root.style.borderRadius = `${r}px`;
}

/** 量 wrapper 實際的底色亮度，決定要餵 dark 還是 light preset。 */
function readSurface() {
  const root = rootEl.value;
  if (!root) return;
  const parts = getComputedStyle(root).backgroundColor.match(/-?\d+(?:\.\d+)?/g);
  if (!parts || parts.length < 3) return;
  const [r, g, b] = parts.map(Number);
  const a = parts.length > 3 ? Number(parts[3]) : 1;
  // 半透明底色要先跟頁面底（深色）合成再判斷，否則 alpha 0.7 的深藍會被算成中灰。
  const lum = ((0.2126 * r + 0.7152 * g + 0.0722 * b) / 255) * a;
  surfaceDark.value = lum < 0.5;
}

// ─── busy → 強度 ───────────────────────────────────────────────────────────

/**
 * 用上游的 tween 模組做強度過渡，並且掛在 inst.onAfterFrame 上跑——
 * 那是共用 rAF loop 每合成完一格就會呼叫的鉤子（上游拿它排 reflection）。
 * 借它就不必自己再開一條 rAF，也自動吃到 loop 的 15fps 節流、
 * 分頁隱藏暫停、以及 IntersectionObserver 的可見性判斷。過渡完成就把鉤子拆掉。
 */
function tickStrength() {
  if (!inst || !strengthTw) return;
  updateInstance(inst, { opacityMul: tweenTick(strengthTw, performance.now()) });
  if (strengthTw.done) {
    strengthTw = null;
    inst.onAfterFrame = undefined;
  }
}

/**
 * 凍結中的實例（reduced-motion）不會再被 loop 複製，改 opacityMul 不會反映到畫面。
 * 借 onFirstCopy 這個「複製一格後自動清空」的 one-shot 回呼：解凍 → 畫一格 → 再凍回去。
 */
function repaintOnce() {
  const target = inst;
  if (!target || !target.paused || !target.everCopied) return;
  target.onFirstCopy = () => updateInstance(target, { paused: true });
  updateInstance(target, { paused: false });
}

function applyStrength(animate) {
  if (!inst) return;
  const to = props.busy ? STRENGTH_BUSY : STRENGTH_IDLE;
  if (!animate) {
    strengthTw = null;
    inst.onAfterFrame = undefined;
    updateInstance(inst, { opacityMul: to });
    repaintOnce();
    return;
  }
  strengthTw = tween(inst.opacityMul, to, STRENGTH_MS, ease.smoothstep);
  tweenStart(strengthTw, performance.now());
  inst.onAfterFrame = tickStrength;
}

// ─── 生命週期 ──────────────────────────────────────────────────────────────

function mountGlow(geom) {
  const host = glowHostEl.value;
  if (!host) return;
  // 尺寸變了必須整個重建：SVG 的 filter region、mask、blob 路徑長度都是
  // injectGlow 當下依 width/height/radius 算死的字串，改不了只能重畫。
  host.innerHTML = '';
  const handles = injectGlow(host, {
    width: geom.cssWidth,
    height: geom.cssHeight,
    cornerRadius: geom.cornerRadius,
    kind: shape.value,
    scale: 1,
  });
  if (inst) glowBridge.set(inst, { handles, theme: resolvedTheme });
}

function build() {
  const root = rootEl.value;
  const canvas = canvasEl.value;
  if (!root || !canvas) return;

  readSurface();

  // 只在 mount 當下讀一次 wrapper 自己的圓角——之後 applyRadius 會覆寫 inline
  // border-radius，再讀就是讀到自己寫進去的值了。
  const parsed = parseFloat(getComputedStyle(root).borderTopLeftRadius);
  initialWrapperRadius = Number.isFinite(parsed) ? parsed : 0;

  const geom = measure();
  try {
    inst = createInstance({
      hostCanvas: canvas,
      cssWidth: geom.cssWidth,
      cssHeight: geom.cssHeight,
      cornerRadius: geom.cornerRadius,
      kind: shape.value,
      // reduced-motion 時直接以 paused 建立：上游 everCopied 的設計會保證
      // 仍然畫出第一格再凍住，所以看得到靜態金屬環，只是不動。
      paused: reduced.value,
      opacityMul: props.busy ? STRENGTH_BUSY : STRENGTH_IDLE,
      onFirstCopy: () => {
        ready.value = true;
        if (readyTimer !== 0) { clearTimeout(readyTimer); readyTimer = 0; }
      },
    });
  } catch (err) {
    // WebGL 拿不到（老機器、瀏覽器 context 數量上限、企業環境關掉 GPU）不能讓助理面板整個掛掉。
    console.warn('[AgentMetal] metal-fx 初始化失敗，退回無特效外框：', err);
    inst = null;
    ready.value = true;
    return;
  }

  // setSharedPreset 內部也會 ensureSharedRenderer()，所以必須等 createInstance
  // 成功（＝GL context 確定拿到了）之後才呼叫，否則沒有 WebGL 的機器會在
  // try/catch 外面丟一次，整個 onMounted 掛掉。preset 只在第一次 renderSharedFrame
  // 才上傳 uniform，而那是下一格 rAF 的事，所以擺在這裡仍然趕得上第一格。
  setSharedPreset(PRESET, resolvedTheme.value);

  applyRadius(root, geom.cornerRadius);
  mountGlow(geom);
  registerGlowInstance(inst);

  // rAF 去抖：同一格內的多次 resize 合併成一次，已排隊時直接丟掉後續事件。
  ro = new ResizeObserver(() => {
    if (resizeRaf !== 0) return;
    resizeRaf = requestAnimationFrame(() => {
      resizeRaf = 0;
      if (!inst || !rootEl.value) return;
      readSurface();
      const next = measure();
      updateInstance(inst, {
        cssWidth: next.cssWidth,
        cssHeight: next.cssHeight,
        cornerRadius: next.cornerRadius,
      });
      applyRadius(rootEl.value, next.cornerRadius);
      mountGlow(next);
      repaintOnce();
    });
  });
  ro.observe(root);

  // 捲出畫面的實例跳過每格的 drawImage；全部都不可見時連 GL render 都省掉。
  // rootMargin 64px 讓它在真的進場前一點就開始畫，避免進場瞬間才亮起來。
  if (typeof IntersectionObserver !== 'undefined') {
    io = new IntersectionObserver(
      (entries) => {
        if (!inst) return;
        for (const e of entries) setInstanceVisible(inst, e.isIntersecting);
      },
      { rootMargin: '64px' },
    );
    io.observe(root);
  }

  if (!ready.value) {
    readyTimer = window.setTimeout(() => { ready.value = true; }, READY_FALLBACK_MS);
  }
}

function teardown() {
  if (resizeRaf !== 0) { cancelAnimationFrame(resizeRaf); resizeRaf = 0; }
  if (readyTimer !== 0) { clearTimeout(readyTimer); readyTimer = 0; }
  ro?.disconnect();
  ro = null;
  io?.disconnect();
  io = null;
  if (inst) {
    inst.onAfterFrame = undefined;
    inst.onFirstCopy = undefined;
    glowBridge.delete(inst);
    unregisterGlowInstance(inst);
    // 最後一個實例消失時，上游 destroyInstance 會停 rAF、teardownSharedRenderer、
    // deleteBuffer/deleteProgram 並呼叫 WEBGL_lose_context —— GL 資源由它負責釋放。
    destroyInstance(inst);
    inst = null;
  }
  strengthTw = null;
  // injectGlow 是直接 appendChild 一顆 <svg> 進來的，Vue 不知道它存在，得自己清。
  if (glowHostEl.value) glowHostEl.value.innerHTML = '';
}

/** 綁一組 media query，回傳前先跑一次 update 以取得初值；handle 收在 mediaBindings 供卸載時解綁。 */
function bindMedia(query, onChange) {
  if (typeof window === 'undefined' || !window.matchMedia) return;
  const mql = window.matchMedia(query);
  const update = () => onChange(mql.matches);
  update();
  mql.addEventListener('change', update);
  mediaBindings.push(() => mql.removeEventListener('change', update));
}

onMounted(() => {
  bindMedia('(prefers-reduced-motion: reduce)', (m) => { reduced.value = m; });
  // OS 主題變動時重量一次表面亮度。目前面板沒有淺色主題，量到的永遠是深色，
  // 但機制照上游留著：CSS 變數哪天跟著 prefers-color-scheme 走時這裡才會跟上。
  bindMedia('(prefers-color-scheme: dark)', () => readSurface());
  build();
});

onBeforeUnmount(() => {
  for (const unbind of mediaBindings) unbind();
  mediaBindings.length = 0;
  teardown();
});

// shape 換了必須整個重建 instance：ringCssPx / shaderScale / glow 的 SVG 幾何
// 都是建立當下依 kind 決定的。上游把 useLayoutEffect 的 deps 設成 [shape] 也是同一個理由。
watch(shape, async () => {
  teardown();
  await nextTick();
  build();
});

watch(() => props.busy, () => applyStrength(!reduced.value));

watch(reduced, (isReduced) => {
  if (!inst) return;
  strengthTw = null;
  inst.onAfterFrame = undefined;
  // 凍結／解凍都走 updateInstance：解凍時它會自己把 shared loop 叫醒。
  updateInstance(inst, { paused: isReduced });
  applyStrength(!isReduced);
});

watch(resolvedTheme, (theme) => {
  // 同理：沒有 instance 就代表 renderer 沒建起來，不要在這裡把它逼出來。
  if (!inst) return;
  setSharedPreset(PRESET, theme);
  repaintOnce();
});

const rootStyle = computed(() => ({
  '--mfx-strength': String(props.busy ? STRENGTH_BUSY : STRENGTH_IDLE),
  opacity: ready.value ? 1 : 0,
  visibility: ready.value ? 'visible' : 'hidden',
  transition: ready.value ? 'opacity 0.15s ease-out' : 'none',
}));
</script>

<!--
  結構與 data-* 全部照上游 MetalFx.tsx：金屬環畫在 z=0 的 canvas、
  z=1 的 inner 是內縮間隔、z=3 是 glow 的 SVG 宿主、z=5 才是真正的內容。
  data-variant / data-shape / data-theme 是 vendor/styles.ts 的 CSS 選擇器依據，
  少一個圓形版的內圈髮絲線和光暈混合模式就會跑掉。

  註：說明寫在 <template> 外面是刻意的——放在 root 元素前面會讓這個元件變成
  多根 fragment，父層下的 class / style 就不會自動落到 wrapper 上。
-->
<template>
  <div
    ref="rootEl"
    class="metal-fx-root agent-metal"
    :data-size="size"
    :data-variant="variant"
    :data-shape="shape"
    :data-theme="resolvedTheme"
    :data-paused="reduced ? 'true' : undefined"
    :data-normalize="normalize"
    :style="rootStyle"
  >
    <canvas ref="canvasEl" class="metal-fx-canvas" aria-hidden="true"></canvas>
    <div class="metal-fx-inner" aria-hidden="true"></div>
    <div ref="glowHostEl" class="agent-metal-glow" aria-hidden="true"></div>
    <div ref="contentEl" class="metal-fx-content"><slot /></div>

    <!--
      金屬環純粹是裝飾，讀螢幕的人看不到「助理在忙」。sm 尺寸本身就包在一顆
      已經有 aria-label 的按鈕裡，再播一次是噪音，所以只有 md 版報狀態。
    -->
    <span v-if="size === 'md'" class="agent-metal-sr" aria-live="polite">
      {{ busy ? 'V.AI 正在生成回覆' : 'V.AI 待命中' }}
    </span>
  </div>
</template>

<style scoped>
/*
  以下幾條是刻意覆寫 vendor/styles.ts 注入的全域樣式。
  scoped 會多帶一個 [data-v-*] 屬性選擇器，權重自然高過上游的單一 class，不必動 vendor 檔。
*/

.agent-metal {
  /* 上游預設 #272727（它假設 wrapper 就是按鈕本體的表面）。這裡的面板是玻璃質感，
     shader 中心是被 destination-out 打穿的，wrapper 的底色會直接透出來，
     所以換成半透明深藍讓底下的面板紋理繼續看得到。 */
  background: var(--metal-surface, rgba(12, 17, 30, 0.82));
  color: var(--txt);
}

/* 28px 圓形：助理面板按鈕裡。上游 circle variant 的 2px 環在這個尺寸剛好是可辨識的下限。 */
.agent-metal[data-size='sm'] {
  display: inline-flex;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  --metal-surface: rgba(10, 15, 27, 0.9);
}

/* 面板頂部：寬度交給父層決定（面板約 320px），高度固定 112px。 */
.agent-metal[data-size='md'] {
  display: flex;
  width: 100%;
  height: 112px;
  border-radius: var(--r);
  --metal-surface: rgba(14, 20, 36, 0.72);
}

/* 上游是用 inline style 給 glow 宿主定位的；這裡改成 class，值一模一樣。 */
.agent-metal-glow {
  position: absolute;
  inset: 0;
  z-index: 3;
  border-radius: inherit;
  pointer-events: none;
}

.agent-metal-sr {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  white-space: nowrap;
  border: 0;
  clip-path: inset(50%);
}

/*
  rAF 迴圈在 script 裡就已經依 prefers-reduced-motion 把 instance 凍住了
  （靜態金屬環還在，只是不再更新）。這裡補的是上游 injectGlow 直接寫在
  SVG 元素 style 上的 100ms transition —— 那是 inline style，
  app.css 的全域 * 規則權重蓋不過，只能從這裡用 :deep 進去關掉。
*/
@media (prefers-reduced-motion: reduce) {
  .agent-metal :deep(.metal-fx-glow-svg) g {
    transition: none !important;
  }
  .agent-metal {
    transition: none !important;
  }
}
</style>
