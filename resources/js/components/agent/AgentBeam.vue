<script>
/**
 * 這個計數器一定要放在普通 <script> 裡，不能放 <script setup>。
 * <script setup> 的整段程式碼會被編譯進 setup()，變成「每個 instance 各一份」，
 * 計數器每次都會從 0 開始 —— 結果三個 instance 全部拿到同一個 id，共用同一份
 * CSS 和同一組 custom property，尺寸和效果會互相蓋掉。這是實測踩到的。
 */
let beamSeq = 0;
export function nextBeamId() {
  return `agent-beam-${++beamSeq}`;
}

/**
 * 上游的 5 種型態。放這裡是被 defineProps 逼的 —— defineProps 會被提升到
 * setup() 外面，裡面的 validator 不能引用 <script setup> 的區域變數。
 * 當白名單用：型態打錯字會讓 sizeThemePresets[undefined] 直接爆掉。
 */
export const UPSTREAM_TYPES = ['sm', 'md', 'line', 'pulse-inner', 'pulse-outside'];
</script>

<script setup>
import { ref, computed, watch, watchEffect, onMounted, onBeforeUnmount } from 'vue';
import {
  sizePresets,
  sizeThemePresets,
  generateBeamCSS,
  getPulseDriverConfig,
} from '@agentfx/border-beam/styles';
import { registerPulseInstance } from '@agentfx/border-beam/pulseDriver';

/**
 * 助理的邊框光束。
 *
 * 這不是「像」border-beam 的手刻版本 —— 畫面完全由上游的 CSS 產生器 generateBeamCSS()
 * 吐出，pulse 的呼吸也是上游那顆共用 rAF 振盪器 registerPulseInstance() 在寫 CSS 變數。
 * 這個檔案只做三件事：把 props 換算成上游要的參數、把產出的 CSS 塞進 <style>、
 * 以及把 React 版 BorderBeam.tsx 的生命週期（observer / 主題偵測 / 尺寸量測 / 淡入淡出）
 * 用 Vue 的寫法重做一次。所有數值都來自 styles.ts 的預設表，這裡不調任何一個。
 *
 * 對照來源：upstream/Libraries/packages/border-beam/src/BorderBeam.tsx（320 行）
 */

const props = defineProps({
  // —— 整合用的兩個 prop（四個效果元件共用同一組介面）——
  size: { type: String, default: 'md', validator: (v) => v === 'sm' || v === 'md' },
  busy: { type: Boolean, default: false }, // 助理正在生成 → 效果更活躍

  // —— 以下是上游 props 的直通口，全部沿用 BorderBeam.tsx 的預設值 ——
  // 留著是因為上游的 5 種型態 / 4 種配色都還在，整合時想換一種不用改這個檔。
  variant: {                                      // 直接指定上游型態，覆寫 size+busy 的推導
    type: String,
    default: null,
    validator: (v) => v == null || UPSTREAM_TYPES.includes(v),
  },
  colorVariant: {
    type: String,
    default: 'colorful',
    validator: (v) => ['colorful', 'mono', 'ocean', 'sunset'].includes(v),
  },
  theme: {
    type: String,
    default: 'dark',
    validator: (v) => ['dark', 'light', 'auto'].includes(v),
  },
  active: { type: Boolean, default: true },
  strength: { type: Number, default: 1 },
  duration: { type: Number, default: null },
  borderRadius: { type: Number, default: null },
  brightness: { type: Number, default: null },
  saturation: { type: Number, default: null },
  hueRange: { type: Number, default: 30 },
  staticColors: { type: Boolean, default: false },
});

const emit = defineEmits(['activate', 'deactivate']);

/**
 * 每個 instance 要有自己的 id，因為上游把 id 編進了 CSS 選擇器（[data-beam="…"]）
 * 和所有 custom property 名稱（--beam-angle-…、--bop-tl-… 等等）。React 用 useId()，
 * 這裡用上面那個模組層計數器 —— 面板是純前端渲染，不必考慮 SSR 兩端要對得起來。
 */
const id = nextBeamId();

// 'sm' 在助理面板裡是按鈕上的 28px 圓形；圓形的圓角就是邊長的一半。
// 這不是「調數值」，是幾何：上游 sm preset 的 borderRadius 32 是為它自己
// 70×36 的示範按鈕算的，套到 28px 會超出方框。
const SM_BOX = 28;

// md 在面板頂部大約 320×112；高度給 min-height，塞了內容還是能長高。
const MD_MIN_HEIGHT = 112;

/**
 * busy 的處理方式（上游沒有這個概念，是這個專案自己加的，說明如下）：
 *
 * - md：切到上游的 'pulse-inner'。它本來就是為 v5 Card 4（約 350×140）調的，
 *   跟面板頂部 320×112 幾乎同尺寸，blob 的 px 幾何直接吻合；呼吸感也比轉更快
 *   的光束更像「正在想」。用的是上游型態本身，連 duration 都用它自己的 2.3。
 * - sm：留在 rotate 家族只加速。pulse 的 blob 尺寸是寫死的 px（最大 216px），
 *   28px 的圓裡只會糊成一團，所以不能切 pulse。
 *
 * BUSY_SPEEDUP 是這整個檔案唯一一個我自己決定的數字 —— 上游沒有「快速」的
 * 預設值可抄，duration 又是它公開的 prop，取一半是最好解釋的選擇。
 */
const BUSY_SPEEDUP = 0.5;

const isPulseType = (t) => t === 'pulse-inner' || t === 'pulse-outside';

const upstreamType = computed(() => {
  if (props.variant && UPSTREAM_TYPES.includes(props.variant)) return props.variant;
  if (props.size === 'sm') return 'sm';
  return props.busy ? 'pulse-inner' : 'md';
});

/* ── 系統主題偵測（對應 BorderBeam.tsx 的 useSystemTheme）────────────────── */

// 初始值在 setup 當下就用 matchMedia 讀好（對應 useState 的 initializer）。
// 不能等到 onMounted 才讀：驅動迴圈的 watchEffect 是 post flush，可能在
// onMounted 之前跑，那一瞬間會用到錯的值 —— 例如替要求減少動態的人開了一幀動畫。
const mediaMatches = (query) =>
  typeof window !== 'undefined' && !!window.matchMedia && window.matchMedia(query).matches;

const systemTheme = ref(mediaMatches('(prefers-color-scheme: dark)') ? 'dark' : 'light');
let themeMq = null;
let onThemeChange = null;

/* ── prefers-reduced-motion ────────────────────────────────────────────────
 * 上游只在 useEffect 裡讀一次 matchMedia。這裡改成掛 listener 持續追蹤，
 * 因為戰情面板會開整天，使用者中途改系統設定時應該立刻生效。
 */
const reducedMotion = ref(mediaMatches('(prefers-reduced-motion: reduce)'));
let motionMq = null;
let onMotionChange = null;

onMounted(() => {
  if (typeof window === 'undefined' || !window.matchMedia) return;

  themeMq = window.matchMedia('(prefers-color-scheme: dark)');
  onThemeChange = (e) => { systemTheme.value = e.matches ? 'dark' : 'light'; };
  themeMq.addEventListener('change', onThemeChange);

  motionMq = window.matchMedia('(prefers-reduced-motion: reduce)');
  onMotionChange = (e) => { reducedMotion.value = e.matches; };
  motionMq.addEventListener('change', onMotionChange);
});

const resolvedTheme = computed(() =>
  props.theme === 'auto' ? systemTheme.value : props.theme
);

/* ── 狀態機（對應 React 的 isActive / isFading / isVisible）──────────────── */

const root = ref(null);
const isActive = ref(props.active);
const isFading = ref(false);
const isVisible = ref(true);
const detectedRadius = ref(null);
const pulseGlowScale = ref({ x: 1, y: 1 });

/**
 * active 切換 → 進 / 出淡出狀態。照抄上游那段三向判斷（含把 isActive / isFading
 * 一起當依賴，讓淡出結束後重新評估一次）。
 *
 * 偏離處：reduced-motion 開啟時直接落地，不走淡出。因為那個模式下我們把
 * animation 全關了，animationend 永遠不會來，照上游的寫法會卡在 fading 狀態。
 */
watch(
  () => [props.active, isActive.value, isFading.value, reducedMotion.value],
  () => {
    if (props.active && !isActive.value && !isFading.value) {
      isActive.value = true;
      if (reducedMotion.value) emit('activate');
    } else if (!props.active && isActive.value && !isFading.value) {
      if (reducedMotion.value) {
        isActive.value = false;
        emit('deactivate');
      } else {
        isFading.value = true;
      }
    }
  },
  { immediate: true }
);

function onAnimationEnd(e) {
  const name = e.animationName || '';
  if (name.includes('fade-out')) {
    isActive.value = false;
    isFading.value = false;
    emit('deactivate');
  } else if (name.includes('fade-in')) {
    emit('activate');
  }
}

/* ── observer 一：子元素圓角自動偵測 ────────────────────────────────────────
 * 上游用 MutationObserver 觀察 childList，處理「CSS 晚一步載入 / 內容換掉」
 * 的情況。sm 的圓角是幾何算出來的，不需要偵測，跟上游「有明確值就跳過」同一條路。
 */
let radiusObserver = null;

function detectRadius() {
  const el = root.value;
  if (!el) return;
  const child = el.firstElementChild;
  if (!child) return;
  const raw = parseFloat(getComputedStyle(child).borderTopLeftRadius);
  if (!isNaN(raw) && raw > 0) detectedRadius.value = raw;
}

/* ── observer 二：捲出畫面就暫停 ───────────────────────────────────────────
 * 這些層每一幀都在重繪，捲出去還在畫是純浪費。rootMargin 256px 跟上游一致，
 * 讓它在進畫面前一點就開始動，不會被看到「突然亮起來」。
 */
let intersectionObserver = null;

/* ── observer 三：pulse-outside 的光暈尺寸量測 ─────────────────────────────
 * 上游的 halo 幾何是照 350×140 的參考元件寫死的 px，所以要量實際尺寸再按軸縮放。
 * 我們預設不會走到 pulse-outside，但 variant prop 開放了它，所以這段照樣要在。
 */
let resizeObserver = null;

const PULSE_REF_WIDTH = 350;
const PULSE_REF_HEIGHT = 140;
const PULSE_MIN_SCALE = 0.35;
const PULSE_MAX_SCALE = 4;

function measurePulseGlow() {
  const el = root.value;
  if (!el) return;
  const child = el.firstElementChild;
  if (!child) return;
  const rect = child.getBoundingClientRect();
  if (!rect.width || !rect.height) return;

  const clamp = (v) => Math.max(PULSE_MIN_SCALE, Math.min(PULSE_MAX_SCALE, v));
  const x = +clamp(rect.width / PULSE_REF_WIDTH).toFixed(3);
  const y = +clamp(rect.height / PULSE_REF_HEIGHT).toFixed(3);
  if (pulseGlowScale.value.x !== x || pulseGlowScale.value.y !== y) {
    pulseGlowScale.value = { x, y };
  }
}

/**
 * 掛 / 拆 pulse-outside 的量測。抽成函式是因為兩個地方都要用：掛載當下、
 * 以及型態換掉的時候（對應上游那個 useEffect 的 [size, children] 依賴）。
 */
function syncPulseObserver() {
  resizeObserver?.disconnect();
  resizeObserver = null;

  if (upstreamType.value !== 'pulse-outside') {
    pulseGlowScale.value = { x: 1, y: 1 };
    return;
  }
  measurePulseGlow();

  const child = root.value?.firstElementChild;
  if (!child || typeof ResizeObserver === 'undefined') return;
  resizeObserver = new ResizeObserver(measurePulseGlow);
  resizeObserver.observe(child);
}

onMounted(() => {
  const el = root.value;
  if (!el) return;

  syncPulseObserver();

  if (props.borderRadius == null && props.size !== 'sm') {
    detectRadius();
    if (typeof MutationObserver !== 'undefined') {
      radiusObserver = new MutationObserver(detectRadius);
      radiusObserver.observe(el, { childList: true, subtree: false });
    }
  }

  if (typeof IntersectionObserver !== 'undefined') {
    intersectionObserver = new IntersectionObserver(
      (entries) => { for (const entry of entries) isVisible.value = entry.isIntersecting; },
      { rootMargin: '256px' }
    );
    intersectionObserver.observe(el);
  }
});

// 型態換到 / 換離 pulse-outside 時重掛量測。這裡刻意不用 immediate —— Vue 的
// immediate 是在 setup 當下同步跑的，那時 template ref 還是 null，量不到東西；
// 首次掛載交給 onMounted。
watch(upstreamType, syncPulseObserver, { flush: 'post' });

/* ── 換算成上游 generateBeamCSS() 的參數 ───────────────────────────────────
 * 這一段是 BorderBeam.tsx 那幾行 final* 的逐行對照，數值一律取自 styles.ts 的表。
 */

const themeConfig = computed(() => sizeThemePresets[upstreamType.value][resolvedTheme.value]);
const sizeConfig = computed(() => sizePresets[upstreamType.value]);

const finalBorderRadius = computed(() => {
  if (props.borderRadius != null) return props.borderRadius;
  // size='sm' 的外框永遠是 28px 方框（見 extraCss），所以圓角跟著箍在半徑上，
  // 就算 variant 被覆寫成別的型態也一樣 —— 否則 preset 的 32px 會超出方框。
  if (props.size === 'sm') return SM_BOX / 2;
  return detectedRadius.value ?? sizeConfig.value.borderRadius;
});

const finalDuration = computed(() => {
  if (props.duration != null) return props.duration;
  const type = upstreamType.value;
  const base = type === 'line' ? 3.1 : isPulseType(type) ? 2.3 : 1.96;
  // pulse 是靠「切過去」表達忙碌，不再額外加速，維持上游調好的呼吸節奏。
  return props.busy && !isPulseType(type) ? base * BUSY_SPEEDUP : base;
});

const finalSaturation = computed(() => props.saturation ?? themeConfig.value.saturation);
const finalBrightness = computed(() => props.brightness ?? themeConfig.value.brightness ?? 1.3);
const finalHueRange = computed(() =>
  upstreamType.value === 'line' ? Math.min(props.hueRange, 13) : props.hueRange
);
const finalStaticColors = computed(() =>
  props.colorVariant === 'mono' ? true : props.staticColors
);

const beamCss = computed(() =>
  generateBeamCSS({
    id,
    borderRadius: finalBorderRadius.value,
    borderWidth: sizeConfig.value.borderWidth,
    duration: finalDuration.value,
    strokeOpacity: themeConfig.value.strokeOpacity,
    innerOpacity: themeConfig.value.innerOpacity,
    bloomOpacity: themeConfig.value.bloomOpacity,
    innerShadow: themeConfig.value.innerShadow,
    size: upstreamType.value,
    colorVariant: props.colorVariant,
    staticColors: finalStaticColors.value,
    brightness: finalBrightness.value,
    saturation: finalSaturation.value,
    hueRange: finalHueRange.value,
    theme: resolvedTheme.value,
    hairlineOpacity: themeConfig.value.hairlineOpacity,
  })
);

/**
 * 這個專案自己補的兩塊 CSS，接在引擎產出後面（同權重，靠順序覆蓋）：
 *
 * 1. 外框尺寸。上游的 wrapper 不管尺寸，整個交給被包住的元件去撐；這裡的需求是
 *    元件自己要站得住 28px 圓形 / 面板頂部那一條，所以由 size prop 決定外框。
 *    只設 display / 尺寸，不碰 overflow —— pulse-outside 需要 overflow: visible
 *    才能讓光暈溢出去，蓋掉就沒效果了。
 *
 * 2. reduced-motion。上游只有 pulse 家族自帶這個 media query，rotate 家族的
 *    README 明說「交給使用端實作」。但單純把 animation 關掉會出事：--beam-opacity
 *    的初始值是 0，唯一把它推到 1 的就是 fade-in 那條 keyframe，關掉之後整個
 *    效果會直接消失。所以這裡補一行把它釘在 1 —— 動畫停下來，但邊框還在，
 *    不會讓「助理在忙」這個訊息在無障礙設定下整個不見。
 */
const extraCss = computed(() => {
  const box = props.size === 'sm'
    ? `display: grid; place-items: center; flex: 0 0 auto; width: ${SM_BOX}px; height: ${SM_BOX}px;`
    : `display: block; width: 100%; min-height: ${MD_MIN_HEIGHT}px;`;

  return `
[data-beam="${id}"] { ${box} }

@media (prefers-reduced-motion: reduce) {
  [data-beam="${id}"],
  [data-beam="${id}"]::after,
  [data-beam="${id}"]::before,
  [data-beam="${id}"] [data-beam-bloom] {
    animation: none !important;
  }
  [data-beam="${id}"][data-active] { --beam-opacity-${id}: 1; }
}
`;
});

/* ── 把 CSS 掛進 document.head ─────────────────────────────────────────────
 * React 版是 render 出一個 <style> 節點。Vue 的 template 不適合放 <style>
 * （SFC 編譯器會把根層 style 當成元件樣式區塊），所以改成自己建節點、
 * 自己在 onBeforeUnmount 拆掉，行為等價。
 */
let styleEl = null;

watchEffect(() => {
  const css = beamCss.value + extraCss.value;
  if (typeof document === 'undefined') return;
  if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.setAttribute('data-beam-style', id);
    document.head.appendChild(styleEl);
  }
  styleEl.textContent = css;
});

/* ── pulse 呼吸驅動（上游的共用 rAF 迴圈）─────────────────────────────────── */

const driverConfig = computed(() =>
  isPulseType(upstreamType.value)
    ? getPulseDriverConfig(
        upstreamType.value,
        resolvedTheme.value,
        finalDuration.value,
        finalHueRange.value,
        finalStaticColors.value,
        id
      )
    : null
);

/**
 * @property 缺席時的退路。
 *
 * rotate 家族的旋轉全靠 @keyframes 內插 --beam-angle，而 custom property 只有
 * 註冊過（@property）才能被內插；沒有支援的瀏覽器不只是「不會轉」，是
 * conic-gradient(from var(--beam-angle-…)) 因為值無效而整層不畫。
 *
 * 這裡不自己另寫引擎 —— 上游 pulseDriver 的 hue 通道 continuous 模式本來就是
 * 「period 秒內線性跑完 range 度，寫成 deg」，那就是勻速旋轉。直接借它來推
 * --beam-angle，退化路徑用的還是上游的程式碼，只是幀率變 30fps。
 */
const supportsAtProperty =
  typeof CSS !== 'undefined' && typeof CSS.registerProperty === 'function';

const angleFallbackConfig = computed(() => {
  if (supportsAtProperty || isPulseType(upstreamType.value)) return null;
  return {
    oscillators: [],
    hue: { prop: `--beam-angle-${id}`, range: 360, period: finalDuration.value, continuous: true },
  };
});

// 依賴跟上游那個 useEffect 一樣：設定、是否在動、是否在畫面內、是否要求減少動態。
// flush: 'post' 是為了拿到掛載後的 root。
watchEffect(
  (onCleanup) => {
    const config = driverConfig.value ?? angleFallbackConfig.value;
    if (!config) return;
    if (!(isActive.value || isFading.value) || !isVisible.value) return;
    if (reducedMotion.value) return;

    const el = root.value;
    if (!el) return;

    const stop = registerPulseInstance(el, config);
    onCleanup(stop);
  },
  { flush: 'post' }
);

/* ── 收拾 ───────────────────────────────────────────────────────────────────
 * 面板會開一整天，這裡漏一個就是慢性洩漏。watchEffect 的 onCleanup（含
 * registerPulseInstance 的解除註冊，最後一個 instance 走了 rAF 迴圈才會停）
 * 由 Vue 在卸載時自動跑，剩下的手動掛的東西在這裡拆。
 */
onBeforeUnmount(() => {
  radiusObserver?.disconnect();
  intersectionObserver?.disconnect();
  resizeObserver?.disconnect();
  radiusObserver = intersectionObserver = resizeObserver = null;

  if (themeMq && onThemeChange) themeMq.removeEventListener('change', onThemeChange);
  if (motionMq && onMotionChange) motionMq.removeEventListener('change', onMotionChange);
  themeMq = motionMq = onThemeChange = onMotionChange = null;

  styleEl?.remove();
  styleEl = null;
});

/* ── wrapper 的 inline 變數 ─────────────────────────────────────────────── */

const wrapperStyle = computed(() => {
  const style = { '--beam-strength': String(Math.max(0, Math.min(1, props.strength))) };

  // 沒有 @property 的話 --beam-opacity 也內插不了，淡入那條 keyframe 等於沒作用，
  // 所有層會停在初始值 0 —— 光束轉得再好也是全黑。這裡直接把它釘成 1：
  // 沒有淡入淡出，但效果本身看得到。有支援時不寫，交還給 keyframe。
  if (!supportsAtProperty) style[`--beam-opacity-${id}`] = '1';

  if (upstreamType.value === 'pulse-outside') {
    style['--pulse-glow-sx'] = String(pulseGlowScale.value.x);
    style['--pulse-glow-sy'] = String(pulseGlowScale.value.y);
  }
  return style;
});
</script>

<template>
  <!--
    結構跟 React 版一模一樣：一層 wrapper 帶 data-beam / data-active / data-fading /
    data-paused，內容之後再放一個 [data-beam-bloom]。所有效果都畫在 ::before /
    ::after / bloom 上，全部 pointer-events: none，不會擋到裡面的互動。
  -->
  <div
    ref="root"
    :data-beam="id"
    :data-active="isActive && !isFading ? '' : undefined"
    :data-fading="isFading ? '' : undefined"
    :data-paused="isActive && !isFading && !isVisible ? '' : undefined"
    :style="wrapperStyle"
    @animationend="onAnimationEnd"
  >
    <slot />
    <div data-beam-bloom aria-hidden="true"></div>
  </div>
</template>
