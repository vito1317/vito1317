<script setup>
/**
 * AgentOrbs —— 助理狀態的「思考球」。
 *
 * 這裡沒有任何自己刻的動畫。畫面上每一顆點的座標、半徑、墨色，都來自上游
 * thinking-orbs@0.3.1 的 engine 入口（`thinking-orbs/engine`），也就是作者
 * 自己為「非 React 渲染器」開的那道門：
 *
 *   resolvePreset(state, size) → { mode, speed, opts }
 *   MODE_FRAMES[mode](size, t, opts) → { dots, lines }   // 純幾何，已 z-sort
 *   paintFrame(ctx, frame, dark)                          // 唯一的繪製
 *
 * 這支檔案的職責只有「把 React 的生命週期翻成 Vue 的生命週期」，幾何一行都沒改。
 * 對照組是上游 src/ThinkingOrb.tsx（117 行），底下每一段都標了它對應到哪裡。
 *
 * 為什麼用 MODE_FRAMES + paintFrame 而不是 MODE_DRAWS：
 * 上游的 MODE_DRAWS[mode] 就是 `(ctx,size,t,dark,opts) => paintFrame(ctx, frame(size,t,opts), dark)`
 * ——同一件事拆成兩步而已，輸出逐位元相同。拆開的好處是 frame 這層拿得到，
 * 驗收腳本才能把 dots 跟上游 spec/orbs-golden.json 逐點比對（72 個 case 全過）。
 */

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { MODE_FRAMES, paintFrame, resolvePreset } from '@agentfx/thinking-orbs/engine';
// gac-soc 用自己的 i18n；這裡只需要繁中標籤
const ORB_LABELS = {
  breathing: '思考中…', composing: '撰寫中…', connecting: '連線中…', listening: '聆聽中…',
  searching: '搜尋中…', shaping: '塑形中…', solving: '解析中…', weaving: '彙整中…', working: '處理中…',
};
const t = (key) => ORB_LABELS[key.replace('orbs.', '')] || '';

const props = defineProps({
  // 'sm' = 助理按鈕裡的 28px 圓形 | 'md' = 助理面板頂部（約 320×112）
  size: { type: String, default: 'md' },
  // 助理正在生成 → 換成更活躍的狀態
  busy: { type: Boolean, default: false },

  // 以下是上游 ThinkingOrbProps 的直通選項，整合時用不到可以不理
  state: { type: String, default: null },   // 指定九種狀態之一，蓋掉 busy 的自動選擇
  theme: { type: String, default: 'dark' }, // 'auto' | 'dark' | 'light'
  speed: { type: Number, default: 1 },      // 疊在 preset 內建速度之上的倍率
  paused: { type: Boolean, default: false },
});

/**
 * 上游只出兩個尺寸 preset：64（頭像級）與 20（行內文字級）。
 * README 講得很白 —— 這是兩套各自調過 dot 數 / dot 半徑 / 速度的「設計」，
 * 不是同一套的縮放。所以 canvas 一律用 preset 的原始 CSS 尺寸畫，
 * 外層再用 flex 置中塞進 28px 的按鈕或面板頂部，絕不用 CSS 把它拉大拉小。
 */
const ORB_PX = { sm: 20, md: 64 };

/**
 * busy 對應到哪個狀態，是我為這個面板做的判斷（上游沒有 busy 這個概念）。
 * 依據是實際跑過九種狀態 × 兩種尺寸的 dot 數與動態量：
 *
 * - busy=false → 'breathing'（ring 模式）。上游自己給它的 aria-label 就是
 *   「Thinking…」，face-on 的環緩慢起伏、沒有背景鬼影球，待機時不搶視線。
 * - busy=true 且 md(64px) → 'weaving'（braid，三股辮繞球）。64px 下有 153 顆點，
 *   球體輪廓完整、深度分明，編織感正好對上「正在把資料組織成回答」。
 * - busy=true 且 sm(20px) → 'solving'（rubik，色帶錯動後歸位）。這裡刻意不跟 md
 *   一致：20px 下 weaving 只剩 35 顆點，辮子的交錯糊成一團；working(orbits) 在
 *   20px 是一條斜向的散點，動得很明顯但沒有球的輪廓——塞進 28px 的圓形按鈕裡
 *   會像幾顆跑掉的雜點。rubik 在 20px 是 30 顆點但維持完整的圓形剪影，
 *   色帶每四分之一圈跳一次，縮到 20px 仍然讀得出「在動」，
 *   語意上「解析中」也比「聆聽中」貼近助理正在生成答案。
 */
const IDLE_STATE = 'breathing';
const BUSY_STATE = { sm: 'solving', md: 'weaving' };

// 防呆：size 傳了奇怪的值就當 md，不要讓 ORB_PX 查出 undefined 把 canvas 弄壞
const sizeKey = computed(() => (props.size === 'sm' ? 'sm' : 'md'));
const orbPx = computed(() => ORB_PX[sizeKey.value]);
const orbState = computed(
  () => props.state || (props.busy ? BUSY_STATE[sizeKey.value] : IDLE_STATE),
);

// 上游 LABELS 的在地化版本。九種都給，因為 state prop 開放覆寫
const label = computed(() => t(`orbs.${orbState.value}`));

const el = ref(null);
const dark = ref(true);     // 上游 useResolvedDark 的 pre-mount fallback 也是 dark
const reduced = ref(false); // 上游 useReducedMotion
const dpr = ref(1);

// ---------------------------------------------------------------- 主題偵測
// 照抄上游 src/theme.ts 的三層解析：明確 prop → 祖先 data-theme/.dark/.light
// → prefers-color-scheme，後兩層都要能即時反應變動。

function ancestorTheme(node) {
  while (node) {
    const attr = node.getAttribute?.('data-theme');
    if (attr === 'dark') return true;
    if (attr === 'light') return false;
    if (node.classList?.contains('dark')) return true;
    if (node.classList?.contains('light')) return false;
    node = node.parentElement;
  }

  return null;
}

function systemDark() {
  return typeof matchMedia === 'undefined'
    || matchMedia('(prefers-color-scheme: dark)').matches;
}

let mqDark = null;
let mo = null;

function resolveDark() {
  const fromTree = ancestorTheme(el.value);
  dark.value = fromTree ?? systemDark();
}

function teardownTheme() {
  mqDark?.removeEventListener('change', resolveDark);
  mqDark = null;
  mo?.disconnect();
  mo = null;
}

function setupTheme() {
  teardownTheme();

  // 釘死的兩種模式不需要任何監聽（上游也是在這裡直接 return）
  if (props.theme === 'dark') { dark.value = true; return; }
  if (props.theme === 'light') { dark.value = false; return; }

  resolveDark();

  if (typeof matchMedia !== 'undefined') {
    mqDark = matchMedia('(prefers-color-scheme: dark)');
    mqDark.addEventListener('change', resolveDark);
  }

  if (typeof MutationObserver !== 'undefined') {
    mo = new MutationObserver(resolveDark);
    mo.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class', 'data-theme'],
      subtree: true,
    });
  }
}

// ------------------------------------------------------- 減動 / 裝置像素比

let mqMotion = null;
const onMotion = (e) => { reduced.value = e.matches; };

let mqDpr = null;
const onDpr = () => syncDpr();

/**
 * 上游只在 effect 裡讀一次 devicePixelRatio。這裡多掛一個 resolution 查詢
 * 監看它變動 —— 這是刻意偏離上游的地方，原因是這面板會整天開著，
 * 使用者把視窗從筆電螢幕拖到外接螢幕是日常，DPR 變了卻不重配 backing store
 * 就會一直糊著。上游是元件庫，假設宿主會重新掛載；我們沒有那個機會。
 */
function syncDpr() {
  if (mqDpr) { mqDpr.removeEventListener('change', onDpr); mqDpr = null; }

  const raw = (typeof devicePixelRatio !== 'undefined' && devicePixelRatio) || 1;

  dpr.value = Math.min(2, raw); // 上限 2，跟上游一致

  // 查詢字串要用未截斷的 raw，否則 DPR 在 2 以上變動時不會觸發
  if (typeof matchMedia === 'undefined') return;
  mqDpr = matchMedia(`(resolution: ${raw}dppx)`);
  mqDpr.addEventListener('change', onDpr);
}

// ------------------------------------------------------------------ 繪製

let raf = 0;
let running = false;
let io = null;
let onVisibility = null;

function stopRender() {
  running = false;
  if (raf) { cancelAnimationFrame(raf); raf = 0; }
  io?.disconnect();
  io = null;
  if (onVisibility) {
    document.removeEventListener('visibilitychange', onVisibility);
    onVisibility = null;
  }
}

/** 對應上游 ThinkingOrb.tsx 的整個 useEffect body。 */
function startRender() {
  const canvas = el.value;

  if (!canvas) return;

  const px = orbPx.value;
  const d = dpr.value;

  canvas.width = Math.round(px * d);
  canvas.height = Math.round(px * d);

  const ctx = canvas.getContext('2d');

  if (!ctx) return;

  const { mode, speed: baseSpeed, opts } = resolvePreset(orbState.value, px);
  const geometry = MODE_FRAMES[mode];
  const effSpeed = baseSpeed * props.speed;
  const isDark = dark.value;

  const frame = (tSec) => {
    ctx.setTransform(d, 0, 0, d, 0, 0);
    ctx.clearRect(0, 0, px, px);
    paintFrame(ctx, geometry(px, tSec, opts), isDark);
  };

  /**
   * 減動：畫一張固定時間戳的靜態幀就收工，不開 rAF。
   * t=0.6 是上游挑的代表性瞬間，這裡沿用同一個值，靜態畫面才跟上游一樣。
   * app.css 的全域規則只擋得住 CSS animation/transition，擋不住 rAF，
   * 所以這個判斷非做不可。
   */
  if (reduced.value) {
    frame(0.6);

    return;
  }

  // 時鐘直接讀 performance.now()，不是各自從掛載時刻起算 ——
  // 上游靠這點讓同時存在的多顆球維持同相位，離開畫面再回來也接得回去
  const loop = () => {
    frame((performance.now() / 1000) * effSpeed);
    if (running) raf = requestAnimationFrame(loop);
  };

  const start = () => {
    if (running || props.paused) return;
    running = true;
    raf = requestAnimationFrame(loop);
  };

  const stop = () => {
    running = false;
    if (raf) { cancelAnimationFrame(raf); raf = 0; }
  };

  // 就算 paused 或在畫面外，至少也要有一幀，不然是一塊空白
  frame((performance.now() / 1000) * effSpeed);

  // 效能關鍵：捲出畫面或分頁隱藏就完全不跑 rAF。
  // 助理面板收合時這顆球通常還掛在 DOM 上，沒有這段就是白燒一整天的 CPU。
  let visible = true;

  io = typeof IntersectionObserver !== 'undefined'
    ? new IntersectionObserver(([entry]) => {
      visible = entry.isIntersecting;
      if (visible && document.visibilityState !== 'hidden') start();
      else stop();
    })
    : null;

  io?.observe(canvas);

  onVisibility = () => {
    if (document.visibilityState === 'hidden') stop();
    else if (visible) start();
  };
  document.addEventListener('visibilitychange', onVisibility);

  // 有 IO 的話不用手動 start：observe() 本來就會立刻回呼一次
  if (!io) start();
}

function restart() {
  stopRender();
  startRender();
}

// 對應上游 useEffect 的相依陣列 [state, size, dark, speed, paused, reduced]，
// 外加我們自己多出來的 dpr
watch(
  [sizeKey, orbState, dark, reduced, dpr, () => props.speed, () => props.paused],
  restart,
);

watch(() => props.theme, setupTheme);

onMounted(() => {
  if (typeof matchMedia !== 'undefined') {
    mqMotion = matchMedia('(prefers-reduced-motion: reduce)');
    reduced.value = mqMotion.matches;
    mqMotion.addEventListener('change', onMotion);
  }

  syncDpr();
  setupTheme(); // 要在掛載後才做，祖先鏈這時候才走得上去
  restart();
});

/**
 * 這面板一開就是一整天，任何一條沒收的線都是慢性洩漏：
 * rAF、IntersectionObserver、visibilitychange、三個 matchMedia、MutationObserver。
 * canvas 是 template 裡的節點，Vue 自己會拔掉；沒有注入任何 DOM/SVG，也沒有
 * WebGL context 要釋放（上游是純 Canvas 2D，這正是選它的理由之一）。
 */
onBeforeUnmount(() => {
  stopRender();
  teardownTheme();
  mqMotion?.removeEventListener('change', onMotion);
  mqMotion = null;
  mqDpr?.removeEventListener('change', onDpr);
  mqDpr = null;
});
</script>

<template>
  <span class="orb" :class="sizeKey">
    <canvas
      ref="el"
      role="img"
      :aria-label="label"
      :style="{ width: `${orbPx}px`, height: `${orbPx}px` }"
    ></canvas>
  </span>
</template>

<style scoped>
/* line-height:0 是為了吃掉 inline 元素底下那條 baseline 空隙，
   不然 28px 的插槽裡會多出幾 px 把球擠偏 */
.orb {
  display: inline-flex; align-items: center; justify-content: center;
  flex: none; line-height: 0; pointer-events: none;
}

/* sm：佔滿按鈕給的 28px 圓形插槽，20px 的球置中。
   刻意不畫底色或外框 —— 那是按鈕自己的事，這裡只負責那顆球 */
.orb.sm { width: 28px; height: 28px; border-radius: 50%; }

/* md：撐滿面板頂部那塊（約 320×112）並置中。
   min-* 是保險：父層高度 auto 時 height:100% 會塌成 0，球會溢出去 */
.orb.md { width: 100%; height: 100%; min-width: 64px; min-height: 64px; }

.orb canvas { display: block; }
</style>
