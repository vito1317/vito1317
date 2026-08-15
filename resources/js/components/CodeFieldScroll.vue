<template>
  <section ref="sectionRef" class="cf relative" :style="{ height: fallback ? 'auto' : '420vh' }">
    <!-- reduced-motion / WebGL 失敗：同一份資料改用數字陳述 -->
    <div v-if="fallback" class="cf-flat container mx-auto px-4 py-16">
      <p class="cf-kicker">IMMERSIVE CODE EXPERIENCE</p>
      <h2 class="text-3xl sm:text-4xl font-black mb-3">{{ totalLabel }} 次提交，逐日攤開</h2>
      <p class="text-gray-400 mb-8">過去一年的 GitHub 貢獻日曆，每一天都在這裡。</p>
      <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div v-for="s in stats" :key="s.label" class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4">
          <dt class="text-xs text-gray-500 font-mono tracking-widest">{{ s.label }}</dt>
          <dd class="mt-1 text-2xl font-mono font-bold" :style="{ color: s.css }">{{ s.value.toLocaleString('en-US') }}<em class="text-sm not-italic opacity-70">{{ s.unit }}</em></dd>
        </div>
      </dl>
    </div>

    <div v-else class="sticky top-0 h-screen w-full overflow-hidden cf-stage">
      <canvas ref="canvasRef" class="absolute inset-0 w-full h-full"></canvas>

      <!-- 開場標題：進場後隨捲動淡出讓位給資料 -->
      <header class="cf-head" :style="headStyle">
        <p class="cf-kicker">IMMERSIVE CODE EXPERIENCE</p>
        <h2 class="text-3xl sm:text-5xl md:text-6xl font-black leading-tight">
          {{ totalLabel }} 次提交，<br class="sm:hidden">逐日攤開
        </h2>
        <p class="mt-3 text-sm sm:text-base text-gray-400 max-w-md">
          過去一年的 GitHub 貢獻日曆——每根柱子是真實的一天，高度是那天的提交量。
        </p>
        <span class="cf-source">SOURCE · GITHUB CONTRIBUTION CALENDAR · LIVE</span>
      </header>

      <!-- 計數器：依飛行進度逐一點亮 -->
      <div class="cf-readout" :class="{ aside: verdictOn }">
        <div v-for="s in stats" :key="s.label" :class="{ on: p >= s.at }">
          <span class="cf-readout-label">{{ s.label }}</span>
          <strong :style="{ color: s.css }">{{ countTo(s).toLocaleString('en-US') }}<em>{{ s.unit }}</em></strong>
        </div>
      </div>

      <!-- 目前月份（鏡頭所在位置） -->
      <div class="cf-band">
        <span class="cf-band-tag">{{ topDown ? 'FULL YEAR' : 'NOW PASSING' }}</span>
        <strong>{{ topDown ? '365 天全景' : currentMonth }}</strong>
        <em>{{ topDown ? `${totalLabel} contributions` : currentMonthCount }}</em>
      </div>

      <div class="cf-legend" aria-hidden="true">
        <span v-for="item in LEGEND" :key="item.label"><i :style="{ background: item.css }"></i>{{ item.label }}</span>
      </div>

      <!-- 高峰日 callout -->
      <div class="cf-peak" :class="{ on: peakOn }" role="status">
        <span class="cf-peak-count">{{ busiest.c }}</span>
        <span>
          <strong>單日提交高峰</strong>
          <em>{{ busiest.d }} · 一天寫掉 {{ busiestShare }}% 的年度總量</em>
        </span>
      </div>

      <!-- 最終判定 -->
      <div class="cf-verdict" :class="{ on: verdictOn }">
        <strong>{{ totalLabel }}<em>commits</em></strong>
        <span>365 天 · {{ activeDays }} 個活躍日 · 最長連續 {{ longestStreak }} 天</span>
      </div>

      <!-- hover 探測：游標下那一天的真實紀錄 -->
      <div v-if="probe" class="cf-probe" :style="{ left: `${probe.x}px`, top: `${probe.y}px` }" aria-hidden="true">
        <strong>{{ probe.date }}</strong>
        <span>{{ probe.count }} commits</span>
      </div>

      <nav class="cf-nav" aria-label="巡覽段落">
        <button
          v-for="(act, i) in ACTS"
          :key="act.label"
          type="button"
          :class="{ on: currentAct === i }"
          @click="jumpTo(act.at)"
        >{{ act.label }}</button>
      </nav>
    </div>
  </section>
</template>

<script setup>
/**
 * CodeFieldScroll — 固定式捲動的 3D 資料場域：
 * 過去一年的 GitHub 貢獻日曆（371 天）以 InstancedMesh 柱陣呈現，
 * 捲動刷關鍵影格鏡頭飛行（進場 → 巡航 → 高峰 → 俯瞰全年馬賽克），
 * 柱陣以波前方式長出、計數器逐一點亮、高峰日光柱 + 掃描牆收尾。
 * 資料來自 /api/github/contributions（GraphQL 貢獻日曆，6h 快取）。
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { cappedPixelRatio } from '../utils/renderScale';

const ACTS = [
  { label: '進場', at: 0.02 },
  { label: '巡航', at: 0.4 },
  { label: '高峰', at: 0.66 },
  { label: '全景', at: 0.97 },
];
const LEGEND = [
  { label: '低活動', css: '#0e7490' },
  { label: '高活動', css: '#67e8f9' },
  { label: '高峰日', css: '#ff2bd6' },
];

const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
const BAR = 0.86;
const MAX_H = 7;
const PEAK_H = 15;

const sectionRef = ref(null);
const canvasRef = ref(null);

const fallback = ref(false);
const progress = ref(0);
const smooth = ref(0);
const probe = ref(null);

const days = ref([]);
const total = ref(0);
const busiest = ref({ d: '—', c: 0 });
const activeDays = ref(0);
const longestStreak = ref(0);

let THREE = null;
let renderer = null;
let composer = null;
let scene = null;
let camera = null;
let bars = null;
let grid = null;
let scanWall = null;
let beams = [];
let raycaster = null;
let rafId = null;
let io = null;
let disposed = false;
let visible = false;
let built = false;
let lastFront = -999;
let frameCount = 0;
let scrollRaf = null;
const pointer = { x: 0, y: 0, px: 0, py: 0, active: false, moved: false };

const WEEKS = computed(() => Math.ceil(days.value.length / 7));
const p = computed(() => smooth.value);
const totalLabel = computed(() => total.value.toLocaleString('en-US'));
const busiestShare = computed(() => (total.value ? Math.round((busiest.value.c / total.value) * 100) : 0));

const stats = computed(() => [
  { label: '全年貢獻', value: total.value, unit: '', css: '#e2f6ff', at: 0.06 },
  { label: '活躍天數', value: activeDays.value, unit: ' 天', css: '#67e8f9', at: 0.16 },
  { label: '最長連續', value: longestStreak.value, unit: ' 天', css: '#a78bfa', at: 0.26 },
  { label: '單日最高', value: busiest.value.c, unit: '', css: '#ff2bd6', at: 0.6 },
]);

const headStyle = computed(() => {
  const out = sliceP(p.value, 0.14, 0.28);
  return { opacity: (1 - out).toFixed(3), transform: `translateY(${(-out * 40).toFixed(1)}px)` };
});

const peakOn = computed(() => p.value >= 0.62 && p.value < 0.88);
const verdictOn = computed(() => p.value >= 0.9);
const topDown = computed(() => p.value >= 0.86);

const currentWeek = computed(() => Math.round(clamp(p.value / 0.74) * (WEEKS.value - 1)));
const currentMonth = computed(() => {
  const day = days.value[Math.min(days.value.length - 1, currentWeek.value * 7)];
  if (!day) return '—';
  const dt = new Date(day.d);
  return `${dt.getFullYear()} ${MONTHS[dt.getMonth()]}`;
});
const currentMonthCount = computed(() => {
  const day = days.value[Math.min(days.value.length - 1, currentWeek.value * 7)];
  if (!day) return '';
  const m = day.d.slice(0, 7);
  const count = days.value.filter((x) => x.d.startsWith(m)).reduce((s, x) => s + x.c, 0);
  return `${count.toLocaleString('en-US')} commits`;
});
const currentAct = computed(() => {
  let index = 0;
  ACTS.forEach((act, i) => { if (p.value >= act.at - 0.02) index = i; });
  return index;
});

const clamp = (v) => Math.min(1, Math.max(0, v));
const sliceP = (v, from, to) => clamp((v - from) / (to - from));
const easeInOut = (t) => (t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2);
const countTo = (stat) => Math.round(sliceP(p.value, stat.at, stat.at + 0.12) * stat.value);

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* --------------------------------------------------------------------------
   three.js 場景
   ------------------------------------------------------------------------ */
const colX = (i) => (i % 7) * 1.15 - 3.45; // 星期（週日~週六）橫向
const rowZ = (i) => Math.floor(i / 7);      // 週序沿 Z 縱深
const barHeight = (i) => {
  const c = days.value[i].c;
  if (c === 0) return 0.06;
  if (c === busiest.value.c) return PEAK_H;
  return 0.3 + (Math.log1p(c) / Math.log1p(busiest.value.c)) * MAX_H;
};

// 鏡頭關鍵影格：低空進場 → 側面巡航 → 高峰俯近 → 拉高俯瞰全年
const CAMERA_KEYS = () => {
  const L = WEEKS.value; // ~53
  return [
    { at: 0, pos: [-5.5, 1.6, -9], look: [0, 2, 5] },
    { at: 0.34, pos: [-8, 6.5, L * 0.28], look: [3, 1.2, L * 0.52] },
    { at: 0.7, pos: [7, 6, L * 0.62], look: [-3, 4, L * 0.88] },
    // 陡但不垂直：正下看會讓 lookAt 失去 roll 定義（forward ∥ up）
    { at: 1, pos: [0, 62, L * 0.18], look: [0, 0, L * 0.55] },
  ];
};

const buildBars = () => {
  const geometry = new THREE.BoxGeometry(BAR, 1, BAR);
  geometry.translate(0, 0.5, 0); // 底部貼地，y-scale 即向上長高

  bars = new THREE.InstancedMesh(geometry, new THREE.MeshLambertMaterial(), days.value.length);
  bars.instanceMatrix.setUsage(THREE.DynamicDrawUsage);

  const dark = new THREE.Color('#0e7490');
  const light = new THREE.Color('#67e8f9');
  const zero = new THREE.Color('#12293a');
  const peak = new THREE.Color('#ff2bd6');
  const colour = new THREE.Color();
  for (let i = 0; i < days.value.length; i++) {
    const c = days.value[i].c;
    if (c === 0) colour.copy(zero);
    else if (c === busiest.value.c) colour.copy(peak);
    else colour.copy(dark).lerp(light, Math.log1p(c) / Math.log1p(busiest.value.c));
    bars.setColorAt(i, colour);
  }
  bars.instanceColor.needsUpdate = true;
  scene.add(bars);
  writeHeights(999);
};

// 波前式長高：只在波前移動時重寫矩陣
const writeHeights = (front) => {
  if (Math.abs(front - lastFront) < 0.3) return;
  lastFront = front;
  const matrix = new THREE.Matrix4();
  const position = new THREE.Vector3();
  const quaternion = new THREE.Quaternion();
  const scale = new THREE.Vector3();
  for (let i = 0; i < days.value.length; i++) {
    const row = rowZ(i);
    const grown = clamp((front - row) / 6);
    position.set(colX(i), 0, row);
    scale.set(1, Math.max(0.0001, barHeight(i) * grown), 1);
    matrix.compose(position, quaternion, scale);
    bars.setMatrixAt(i, matrix);
  }
  bars.instanceMatrix.needsUpdate = true;
  bars.computeBoundingSphere();
};

const buildScenery = () => {
  grid = new THREE.GridHelper(160, 80, 0x1d4a68, 0x122c40);
  grid.position.set(0, -0.02, WEEKS.value / 2);
  grid.material.transparent = true;
  scene.add(grid);

  // 收尾掃描牆
  scanWall = new THREE.Mesh(
    new THREE.PlaneGeometry(14, 18),
    new THREE.MeshBasicMaterial({
      color: 0x22d3ee,
      transparent: true,
      opacity: 0,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      side: THREE.DoubleSide,
    }),
  );
  scanWall.position.set(0, 8, 0);
  scene.add(scanWall);

  // 高峰日光柱（前三名）
  const ranked = days.value
    .map((d, i) => ({ i, c: d.c }))
    .sort((a, b) => b.c - a.c)
    .slice(0, 3);
  const beamGeometry = new THREE.CylinderGeometry(0.12, 0.12, 46, 6, 1, true);
  beams = ranked.map(({ i }) => {
    const beam = new THREE.Mesh(beamGeometry, new THREE.MeshBasicMaterial({
      color: 0xff2bd6,
      transparent: true,
      opacity: 0,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
    }));
    beam.position.set(colX(i), 23, rowZ(i));
    scene.add(beam);
    return beam;
  });
};

const init = async () => {
  if (built || disposed || !days.value.length) return;
  built = true;
  try {
    const [three, { EffectComposer }, { RenderPass }, { UnrealBloomPass }] = await Promise.all([
      import('three'),
      import('three/examples/jsm/postprocessing/EffectComposer.js'),
      import('three/examples/jsm/postprocessing/RenderPass.js'),
      import('three/examples/jsm/postprocessing/UnrealBloomPass.js'),
    ]);
    THREE = three;
    if (disposed) return;

    const mobile = window.innerWidth < 768;
    renderer = new THREE.WebGLRenderer({ canvas: canvasRef.value, antialias: !mobile, alpha: true });

    scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0x050c17, 30, 150);
    camera = new THREE.PerspectiveCamera(52, 1, 0.1, 400);

    // 低環境光 + 斜射主光：柱體讀起來是實心方塊，亮頂吃 bloom
    scene.add(new THREE.AmbientLight(0xbfeeff, 0.6));
    const key = new THREE.DirectionalLight(0xffffff, 1.9);
    key.position.set(-40, 55, -25);
    scene.add(key);
    const fill = new THREE.DirectionalLight(0x38bdf8, 0.5);
    fill.position.set(30, 20, 60);
    scene.add(fill);

    buildBars();
    buildScenery();

    raycaster = new THREE.Raycaster();
    const rect = canvasRef.value.getBoundingClientRect();
    const width = Math.max(1, rect.width);
    const height = Math.max(1, rect.height);
    renderer.setPixelRatio(cappedPixelRatio(width, height));
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    // 閾值抓高：只有高峰洋紅與最亮的青柱頂會 bloom
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(width, height), 0.72, 0.85, 0.8));

    window.addEventListener('resize', resize, { passive: true });
    if (window.matchMedia('(hover: hover)').matches) {
      window.addEventListener('pointermove', onPointerMove, { passive: true });
      window.addEventListener('pointerleave', onPointerLeave, { passive: true });
    }
    start();
  } catch (err) {
    console.warn('[CodeFieldScroll] WebGL 初始化失敗，退化為統計版', err);
    fallback.value = true;
  }
};

const resize = () => {
  if (!renderer) return;
  const rect = canvasRef.value.getBoundingClientRect();
  const width = Math.max(1, rect.width);
  const height = Math.max(1, rect.height);
  renderer.setPixelRatio(cappedPixelRatio(width, height));
  renderer.setSize(width, height, false);
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
  composer?.setSize(width, height);
};

const lookTarget = { v: null };
const placeCamera = (value) => {
  const keys = CAMERA_KEYS();
  let index = 0;
  for (let i = 0; i < keys.length - 1; i++) {
    if (value >= keys[i].at) index = i;
  }
  const from = keys[index];
  const to = keys[Math.min(keys.length - 1, index + 1)];
  const span = Math.max(0.0001, to.at - from.at);
  const t = easeInOut(clamp((value - from.at) / span));

  if (!lookTarget.v) lookTarget.v = new THREE.Vector3();
  const pos = new THREE.Vector3().fromArray(from.pos).lerp(new THREE.Vector3().fromArray(to.pos), t);
  const look = new THREE.Vector3().fromArray(from.look).lerp(new THREE.Vector3().fromArray(to.look), t);

  // 滑鼠視差，俯瞰段淡出以免馬賽克晃動
  const sway = 1 - clamp((value - 0.82) / 0.18);
  camera.position.set(pos.x + pointer.px * 4 * sway, pos.y - pointer.py * 2.2 * sway, pos.z);
  lookTarget.v.copy(look);
  camera.lookAt(lookTarget.v);
};

const updateProbe = () => {
  if (!pointer.active || !bars) {
    if (probe.value) probe.value = null;
    return;
  }
  raycaster.setFromCamera({ x: pointer.px, y: -pointer.py }, camera);
  const hit = raycaster.intersectObject(bars, false)[0];
  if (!hit || hit.instanceId === undefined) {
    if (probe.value) probe.value = null;
    return;
  }
  const day = days.value[hit.instanceId];
  probe.value = { date: day.d, count: day.c, x: pointer.x, y: pointer.y };
};

const render = () => {
  rafId = requestAnimationFrame(render);
  if (!renderer || !visible) return;

  smooth.value += (progress.value - smooth.value) * 0.09;
  const value = smooth.value;

  writeHeights(sliceP(value, 0, 0.32) * (WEEKS.value + 8) - 4);
  placeCamera(value);

  const sweep = sliceP(value, 0.72, 1);
  scanWall.position.z = sweep * (WEEKS.value + 6) - 3;
  scanWall.material.opacity = Math.sin(sweep * Math.PI) * 0.18;

  const beamIn = sliceP(value, 0.58, 0.7);
  beams.forEach((beam) => { beam.material.opacity = beamIn * 0.5; });

  // 俯瞰時淡出地面格線，避免柱間縫隙產生摩爾紋
  grid.material.opacity = 1 - sliceP(value, 0.78, 0.95) * 0.9;

  frameCount++;
  if (pointer.moved && frameCount % 3 === 0) {
    pointer.moved = false;
    updateProbe();
  }

  composer.render();
};

const start = () => {
  if (rafId || !renderer || disposed) return;
  rafId = requestAnimationFrame(render);
};
const stop = () => {
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
};

/* --------------------------------------------------------------------------
   捲動 / 指標 / 生命週期
   ------------------------------------------------------------------------ */
const updateProgress = () => {
  scrollRaf = null;
  const rect = sectionRef.value?.getBoundingClientRect();
  if (!rect) return;
  const available = rect.height - window.innerHeight;
  progress.value = available > 0 ? clamp(-rect.top / available) : 0;
};
const onScroll = () => {
  if (scrollRaf === null) scrollRaf = requestAnimationFrame(updateProgress);
};

const jumpTo = (at) => {
  const section = sectionRef.value;
  if (!section) return;
  const available = section.offsetHeight - window.innerHeight;
  window.scrollTo({ top: section.offsetTop + available * at, behavior: 'smooth' });
};

const onPointerMove = (event) => {
  const rect = canvasRef.value?.getBoundingClientRect();
  if (!rect) return;
  pointer.x = event.clientX;
  pointer.y = event.clientY;
  pointer.px = ((event.clientX - rect.left) / rect.width) * 2 - 1;
  pointer.py = ((event.clientY - rect.top) / rect.height) * 2 - 1;
  pointer.active = true;
  pointer.moved = true;
};
const onPointerLeave = () => {
  pointer.active = false;
  probe.value = null;
};
const onVisibility = () => {
  document.hidden ? stop() : (visible && start());
};

onMounted(async () => {
  if (reducedMotion()) fallback.value = true;

  try {
    const res = await axios.get('/api/github/contributions');
    days.value = res.data.days ?? [];
    total.value = res.data.total ?? 0;
    busiest.value = res.data.busiest ?? { d: '—', c: 0 };
    activeDays.value = res.data.active_days ?? 0;
    longestStreak.value = res.data.longest_streak ?? 0;
  } catch {
    fallback.value = true;
    return;
  }
  if (!days.value.length) { fallback.value = true; return; }
  if (fallback.value) return;

  window.addEventListener('scroll', onScroll, { passive: true });
  document.addEventListener('visibilitychange', onVisibility);
  updateProgress();

  // 首次可見才建場景：沒捲到這裡的訪客不付 WebGL 成本
  io = new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible) { init().then(start); } else { stop(); }
  }, { rootMargin: '25% 0px' });
  io.observe(sectionRef.value);
});

onUnmounted(() => {
  disposed = true;
  stop();
  io?.disconnect();
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('resize', resize);
  window.removeEventListener('pointermove', onPointerMove);
  window.removeEventListener('pointerleave', onPointerLeave);
  document.removeEventListener('visibilitychange', onVisibility);
  if (scrollRaf) cancelAnimationFrame(scrollRaf);
  if (scene) {
    scene.traverse((obj) => {
      obj.geometry?.dispose?.();
      if (obj.material) (Array.isArray(obj.material) ? obj.material : [obj.material]).forEach((m) => m.dispose());
    });
  }
  composer?.dispose();
  renderer?.dispose();
});
</script>

<style scoped>
.cf-stage {
  background: radial-gradient(120% 90% at 50% 120%, #0d2338, #050c17 62%);
}
/* 上下漸層壓住畫面邊緣，讓文案有底 */
.cf-stage::before,
.cf-stage::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  z-index: 2;
  pointer-events: none;
}
.cf-stage::before { top: 0; height: 26%; background: linear-gradient(180deg, rgba(5, 6, 15, 0.9), rgba(5, 6, 15, 0.4) 55%, transparent); }
.cf-stage::after { bottom: 0; height: 24%; background: linear-gradient(0deg, rgba(5, 6, 15, 0.92), rgba(5, 6, 15, 0.45) 55%, transparent); }

.cf-kicker {
  display: inline-block;
  margin-bottom: 0.6rem;
  color: #22d3ee;
  font: 500 11px/1 ui-monospace, monospace;
  letter-spacing: 0.3em;
}

.cf-head {
  position: absolute;
  z-index: 4;
  top: max(96px, 12vh);
  left: clamp(1.25rem, 6vw, 6rem);
  max-width: min(46ch, 82vw);
  color: #fff;
}
.cf-source {
  display: inline-block;
  margin-top: 0.8rem;
  padding: 0.3rem 0.6rem;
  color: #9ca8bd;
  font: 500 10px/1.4 ui-monospace, monospace;
  letter-spacing: 0.08em;
  border: 1px solid rgba(148, 163, 184, 0.25);
  border-radius: 6px;
  background: rgba(5, 6, 15, 0.6);
}

.cf-readout {
  position: absolute;
  z-index: 4;
  left: clamp(1.25rem, 6vw, 6rem);
  bottom: clamp(4rem, 11vh, 6.5rem);
  display: grid;
  grid-auto-flow: column;
  gap: clamp(0.9rem, 2.5vw, 2.25rem);
}
.cf-readout > div { opacity: 0.25; transition: opacity 320ms ease-out; }
.cf-readout > div.on { opacity: 1; }
/* 判定出現時計數器讓位，避免文字疊在一起 */
.cf-readout.aside > div { opacity: 0.1; }
.cf-readout-label { display: block; color: #8194ad; font: 500 10px/1 ui-monospace, monospace; letter-spacing: 0.1em; }
.cf-readout strong { display: block; margin-top: 0.25rem; font: 600 clamp(1.05rem, 2.1vw, 1.7rem)/1 ui-monospace, monospace; }
.cf-readout strong em { font-size: 0.55em; font-style: normal; opacity: 0.75; }

.cf-band {
  position: absolute;
  z-index: 4;
  right: clamp(1.25rem, 4vw, 3.5rem);
  top: max(96px, 12vh);
  width: min(240px, 40vw);
  padding: 0.7rem 0.9rem;
  text-align: right;
  border: 1px solid rgba(148, 163, 184, 0.2);
  border-right: 2px solid #22d3ee;
  border-radius: 10px;
  background: rgba(10, 16, 30, 0.66);
  backdrop-filter: blur(10px);
}
.cf-band-tag { display: block; color: #8194ad; font: 500 9px/1 ui-monospace, monospace; letter-spacing: 0.12em; }
.cf-band strong { display: block; margin-top: 0.3rem; color: #f1f6ff; font-size: 0.95rem; }
.cf-band em { display: block; margin-top: 0.15rem; color: #22d3ee; font: 500 10px/1 ui-monospace, monospace; font-style: normal; }

.cf-legend {
  position: absolute;
  z-index: 4;
  right: clamp(1.25rem, 4vw, 3.5rem);
  bottom: clamp(4rem, 11vh, 6.5rem);
  display: grid;
  gap: 0.35rem;
  justify-items: end;
}
.cf-legend span { display: flex; align-items: center; gap: 0.5rem; color: #8194ad; font: 500 10px/1 ui-monospace, monospace; }
.cf-legend i { width: 9px; height: 9px; border-radius: 2px; }

.cf-peak {
  position: absolute;
  z-index: 5;
  left: 50%;
  top: 44%;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.7rem 1rem;
  border: 1px solid rgba(255, 43, 214, 0.6);
  border-radius: 14px;
  background: rgba(26, 6, 22, 0.82);
  backdrop-filter: blur(8px);
  box-shadow: 0 0 40px rgba(255, 43, 214, 0.28);
  opacity: 0;
  transform: translate(-50%, 8px) scale(0.94);
  transition: opacity 300ms ease-out, transform 360ms ease-out;
}
.cf-peak.on { opacity: 1; transform: translate(-50%, 0) scale(1); }
.cf-peak-count { color: #ff6ad5; font: 700 2.1rem/1 ui-monospace, monospace; }
.cf-peak strong { display: block; color: #fff; font-size: 0.82rem; }
.cf-peak em { display: block; margin-top: 0.15rem; color: #b491c8; font: 500 10px/1.4 ui-monospace, monospace; font-style: normal; }

.cf-verdict {
  position: absolute;
  z-index: 5;
  left: 50%;
  bottom: clamp(4rem, 12vh, 7rem);
  text-align: center;
  opacity: 0;
  transform: translate(-50%, 14px);
  transition: opacity 380ms ease-out, transform 420ms ease-out;
}
.cf-verdict.on { opacity: 1; transform: translate(-50%, 0); }
.cf-verdict strong { display: block; color: #fff; font: 600 clamp(2.2rem, 6vw, 4.4rem)/1 ui-monospace, monospace; letter-spacing: -0.02em; }
.cf-verdict strong em { margin-left: 0.4rem; font-size: 0.34em; font-style: normal; color: #67e8f9; }
.cf-verdict span { display: block; margin-top: 0.4rem; color: #aebdd4; font-size: 0.8rem; }

.cf-probe {
  position: fixed;
  z-index: 6;
  transform: translate(14px, -50%);
  pointer-events: none;
  padding: 0.45rem 0.6rem;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 8px;
  background: rgba(5, 6, 15, 0.9);
  backdrop-filter: blur(6px);
}
.cf-probe strong { display: block; color: #fff; font: 600 11px/1 ui-monospace, monospace; }
.cf-probe span { display: block; margin-top: 0.2rem; color: #67e8f9; font: 500 10px/1 ui-monospace, monospace; }

.cf-nav {
  position: absolute;
  z-index: 6;
  right: clamp(1.25rem, 4vw, 3.5rem);
  top: 50%;
  transform: translateY(-50%);
  display: grid;
  gap: 0.35rem;
}
.cf-nav button {
  min-width: 84px;
  min-height: 38px;
  padding: 0.45rem 0.7rem;
  color: #8194ad;
  font-size: 0.72rem;
  border: 1px solid rgba(148, 163, 184, 0.2);
  border-radius: 8px;
  background: rgba(5, 6, 15, 0.62);
  backdrop-filter: blur(6px);
  cursor: pointer;
  transition: color 200ms ease, border-color 200ms ease, background-color 200ms ease;
}
.cf-nav button.on { color: #fff; border-color: rgba(34, 211, 238, 0.45); background: rgba(34, 211, 238, 0.12); }
.cf-nav button:focus-visible { outline: 2px solid #22d3ee; outline-offset: 2px; }

@media (max-width: 767px) {
  .cf-band { top: auto; bottom: clamp(9rem, 20vh, 12rem); }
  .cf-legend { display: none; }
  .cf-nav { display: none; }
  .cf-readout { gap: 0.8rem; }
  /* 判定往上抬，避免與底部計數器重疊 */
  .cf-verdict { bottom: clamp(12rem, 28vh, 15rem); width: 92vw; }
}
</style>
