<template>
  <section ref="sectionRef" class="af relative" :style="{ height: fallback ? 'auto' : '420vh' }">
    <!-- reduced-motion / WebGL 失敗：同一組數據改用卡片陳述 -->
    <div v-if="fallback" class="af-flat py-14">
      <p class="af-kicker">IMMERSIVE ALGORITHM EXPERIENCE</p>
      <h2 class="text-3xl sm:text-4xl font-black mb-3">1,424 條推理軌跡，逐條攤開</h2>
      <p class="text-gray-400 mb-8">TACT 確認實驗（MATH-500 Level 5）——89 題評估集 × 16 條 Haiku 軌跡。</p>
      <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div v-for="s in STATS" :key="s.label" class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4">
          <dt class="text-xs text-gray-500 font-mono tracking-widest">{{ s.label }}</dt>
          <dd class="mt-1 text-2xl font-mono font-bold" :style="{ color: s.css }">{{ s.display }}</dd>
        </div>
      </dl>
    </div>

    <div v-else class="sticky top-0 h-screen w-full overflow-hidden af-stage">
      <canvas ref="canvasRef" class="absolute inset-0 w-full h-full"></canvas>

      <header class="af-head" :style="headStyle">
        <p class="af-kicker">IMMERSIVE ALGORITHM EXPERIENCE</p>
        <h2 class="text-3xl sm:text-5xl md:text-6xl font-black leading-tight">
          1,424 條推理軌跡，<br class="sm:hidden">逐條攤開
        </h2>
        <p class="mt-3 text-sm sm:text-base text-gray-400 max-w-md">
          TACT 確認實驗——89 題 MATH Level 5，每題 16 條推理軌跡。
          高度是模型的自報信心，顏色是那條軌跡答對或答錯。
        </p>
        <span class="af-source">SOURCE · REPORT-TACT-HARD · 依公開統計重建</span>
      </header>

      <div class="af-readout" :class="{ aside: verdictOn }">
        <div v-for="s in STATS" :key="s.label" :class="{ on: p >= s.at }">
          <span class="af-readout-label">{{ s.label }}</span>
          <strong :style="{ color: s.css }">{{ s.display }}</strong>
        </div>
      </div>

      <!-- 鏡頭所在題目的分層 -->
      <div class="af-band">
        <span class="af-band-tag">{{ topDown ? 'EVAL SET' : 'NOW PASSING' }}</span>
        <strong>{{ topDown ? '89 題全景' : `Q ${String(currentRow + 1).padStart(3, '0')} / 089` }}</strong>
        <em :style="{ color: currentStratum.css }">{{ topDown ? '1,424 trajectories' : currentStratum.label }}</em>
      </div>

      <div class="af-legend" aria-hidden="true">
        <span v-for="item in LEGEND" :key="item.label"><i :style="{ background: item.css }"></i>{{ item.label }}</span>
      </div>

      <!-- 通道證實 callout -->
      <div class="af-peak" :class="{ on: peakOn }" role="status">
        <span class="af-peak-count">z=+2.54</span>
        <span>
          <strong>信心通道第一次在真實資料上被證實</strong>
          <em>pooled D̂ = +0.250 · 高信心軌跡確實更可能是對的</em>
        </span>
      </div>

      <!-- 最終判定：零調校常數閉式 -->
      <div class="af-verdict" :class="{ on: verdictOn }">
        <strong>γ = z·√(2+z²)</strong>
        <span>零調校常數 · κ=−0.6 準確率 0.807 → 1.000 · 證偽 4/4 存活 · 98 tests</span>
      </div>

      <!-- hover 探測：游標下那條軌跡 -->
      <div v-if="probe" class="af-probe" :style="{ left: `${probe.x}px`, top: `${probe.y}px` }" aria-hidden="true">
        <strong>Q{{ probe.q }} · 軌跡 #{{ probe.k }}</strong>
        <span>信心 {{ probe.conf }}</span>
        <em :class="probe.ok ? 'ok' : 'bad'">{{ probe.ok ? 'CORRECT' : 'WRONG' }}</em>
      </div>

      <nav class="af-nav" aria-label="巡覽段落">
        <button v-for="(act, i) in ACTS" :key="act.label" type="button" :class="{ on: currentAct === i }" @click="jumpTo(act.at)">
          {{ act.label }}
        </button>
      </nav>
    </div>
  </section>
</template>

<script setup>
/**
 * AlgorithmFieldScroll — 固定式捲動的 3D 演算法資料場域。
 * 依 REPORT-TACT-HARD 公開統計「精確重建」TACT 確認實驗的評估集：
 * 89 題 × 16 條軌跡 = 1,424 柱；全域約束逐一滿足——
 * SC 多數決正確 79/89（0.888）、每樣本正確率 0.819（1,166/1,424）、
 * 能力牆 6 題（池中無正解）、可救援 4 題（金樣本在池內但多數決錯）、
 * 信心通道 D̂ > 0（正確軌跡的信心分佈整體較高）。
 * 佈局 / 鏡頭 / 節拍 / 探測與 CodeFieldScroll 同級。
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';

const Q = 89;
const K = 16;
const N = Q * K;

const ACTS = [
  { label: '進場', at: 0.02 },
  { label: '巡航', at: 0.4 },
  { label: '通道', at: 0.66 },
  { label: '全景', at: 0.97 },
];
const LEGEND = [
  { label: '答對軌跡', css: '#67e8f9' },
  { label: '答錯軌跡', css: '#3f5570' },
  { label: '可救援金樣本', css: '#ff2bd6' },
];
const STATS = [
  { label: '評估題目', display: '89', css: '#e2f6ff', at: 0.06 },
  { label: '推理軌跡', display: '1,424', css: '#67e8f9', at: 0.16 },
  { label: '通道效應', display: 'D̂ +0.250', css: '#a78bfa', at: 0.56 },
  { label: 'SC 準確率', display: '0.888', css: '#ff2bd6', at: 0.8 },
];
const STRATA = {
  saturated: { label: '飽和層 · 多數決已正確', css: '#67e8f9' },
  wall: { label: '能力牆 · 池中無正解', css: '#8194ad' },
  window: { label: '可救援 · 金樣本在池內', css: '#ff2bd6' },
};

const BAR = 0.8;
const MAX_H = 6.5;

/* --------------------------------------------------------------------------
   依公開統計的確定性重建（seeded PRNG，每次載入同一個場）
   ------------------------------------------------------------------------ */
const mulberry32 = (seed) => () => {
  seed |= 0; seed = (seed + 0x6d2b79f5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

const buildDataset = () => {
  const rand = mulberry32(20260731);
  // 題目分層：79 飽和 + 6 能力牆 + 4 可救援（位置洗牌）
  const types = [
    ...Array(79).fill('saturated'),
    ...Array(6).fill('wall'),
    ...Array(4).fill('window'),
  ];
  for (let i = types.length - 1; i > 0; i--) {
    const j = Math.floor(rand() * (i + 1));
    [types[i], types[j]] = [types[j], types[i]];
  }

  // 每題正確樣本數：飽和層 14 或 15（湊 1,154）、能力牆 0、可救援 3
  // 全域正確樣本 = 1,154 + 12 = 1,166 → 每樣本正確率 0.8188 ≈ 0.819 ✓
  const saturatedIdx = types.map((t, i) => (t === 'saturated' ? i : -1)).filter((i) => i >= 0);
  const bonus = new Set(saturatedIdx.slice(0, 48));
  const correctCount = types.map((t, i) =>
    t === 'saturated' ? (bonus.has(i) ? 15 : 14) : t === 'wall' ? 0 : 3,
  );

  const samples = [];
  for (let q = 0; q < Q; q++) {
    const flags = Array(K).fill(false);
    let placed = 0;
    while (placed < correctCount[q]) {
      const at = Math.floor(rand() * K);
      if (!flags[at]) { flags[at] = true; placed++; }
    }
    for (let k = 0; k < K; k++) {
      const ok = flags[k];
      // 通道 D̂ > 0：正確軌跡信心整體較高，但兩分佈刻意重疊
      const conf = ok
        ? 0.45 + rand() * 0.55
        : 0.2 + rand() * 0.6;
      samples.push({ q, k, ok, conf, type: types[q], gold: types[q] === 'window' && ok });
    }
  }
  return { samples, types };
};

const DATA = buildDataset();

/* -------------------------------------------------------------------------- */
const sectionRef = ref(null);
const canvasRef = ref(null);
const fallback = ref(false);
const progress = ref(0);
const smooth = ref(0);
const probe = ref(null);

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

const p = computed(() => smooth.value);
const clamp = (v) => Math.min(1, Math.max(0, v));
const sliceP = (v, from, to) => clamp((v - from) / (to - from));
const easeInOut = (t) => (t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2);

const headStyle = computed(() => {
  const out = sliceP(p.value, 0.14, 0.28);
  return { opacity: (1 - out).toFixed(3), transform: `translateY(${(-out * 40).toFixed(1)}px)` };
});
const peakOn = computed(() => p.value >= 0.6 && p.value < 0.88);
const verdictOn = computed(() => p.value >= 0.9);
const topDown = computed(() => p.value >= 0.86);
const currentRow = computed(() => Math.round(clamp(p.value / 0.74) * (Q - 1)));
const currentStratum = computed(() => STRATA[DATA.types[currentRow.value]] ?? STRATA.saturated);
const currentAct = computed(() => {
  let index = 0;
  ACTS.forEach((act, i) => { if (p.value >= act.at - 0.02) index = i; });
  return index;
});

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* --------------------------------------------------------------------------
   three.js 場景（16 軌跡橫向 × 89 題縱深）
   ------------------------------------------------------------------------ */
const colX = (i) => (i % K) * 0.95 - (K - 1) * 0.475;
const rowZ = (i) => Math.floor(i / K);
const barHeight = (i) => 0.35 + DATA.samples[i].conf * MAX_H;

const CAMERA_KEYS = () => [
  { at: 0, pos: [-7, 1.6, -9], look: [0, 2, 5] },
  { at: 0.34, pos: [-13, 9, Q * 0.26], look: [4, 1, Q * 0.52] },
  { at: 0.7, pos: [13, 9.5, Q * 0.6], look: [-5, 3, Q * 0.86] },
  // 陡但不垂直，避免 lookAt 在 forward ∥ up 時失去 roll 定義
  { at: 1, pos: [0, 66, Q * 0.18], look: [0, 0, Q * 0.55] },
];

const buildBars = () => {
  const geometry = new THREE.BoxGeometry(BAR, 1, BAR);
  geometry.translate(0, 0.5, 0);

  bars = new THREE.InstancedMesh(geometry, new THREE.MeshLambertMaterial(), N);
  bars.instanceMatrix.setUsage(THREE.DynamicDrawUsage);

  const okDark = new THREE.Color('#0e7490');
  const okLight = new THREE.Color('#67e8f9');
  const bad = new THREE.Color('#2b3a4d');
  const badDim = new THREE.Color('#3f5570');
  const gold = new THREE.Color('#ff2bd6');
  const colour = new THREE.Color();
  for (let i = 0; i < N; i++) {
    const s = DATA.samples[i];
    if (s.gold) colour.copy(gold);
    else if (s.ok) colour.copy(okDark).lerp(okLight, s.conf);
    else colour.copy(bad).lerp(badDim, s.conf);
    bars.setColorAt(i, colour);
  }
  bars.instanceColor.needsUpdate = true;
  scene.add(bars);
  writeHeights(999);
};

const writeHeights = (front) => {
  if (Math.abs(front - lastFront) < 0.3) return;
  lastFront = front;
  const matrix = new THREE.Matrix4();
  const position = new THREE.Vector3();
  const quaternion = new THREE.Quaternion();
  const scale = new THREE.Vector3();
  for (let i = 0; i < N; i++) {
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
  grid = new THREE.GridHelper(200, 100, 0x1d4a68, 0x122c40);
  grid.position.set(0, -0.02, Q / 2);
  grid.material.transparent = true;
  scene.add(grid);

  scanWall = new THREE.Mesh(
    new THREE.PlaneGeometry(20, 18),
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

  // 可救援金樣本的光柱（12 根 = 4 題 × 3 條）
  const beamGeometry = new THREE.CylinderGeometry(0.1, 0.1, 46, 6, 1, true);
  beams = DATA.samples
    .map((s, i) => (s.gold ? i : -1))
    .filter((i) => i >= 0)
    .map((i) => {
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
  if (built || disposed) return;
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
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, mobile ? 1.25 : 1.75));

    scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0x050c17, 30, 160);
    camera = new THREE.PerspectiveCamera(52, 1, 0.1, 400);

    scene.add(new THREE.AmbientLight(0xbfeeff, 0.6));
    const key = new THREE.DirectionalLight(0xffffff, 1.9);
    key.position.set(-40, 55, -25);
    scene.add(key);
    const fill = new THREE.DirectionalLight(0x38bdf8, 0.5);
    fill.position.set(30, 20, 70);
    scene.add(fill);

    buildBars();
    buildScenery();

    raycaster = new THREE.Raycaster();
    const rect = canvasRef.value.getBoundingClientRect();
    const width = Math.max(1, rect.width);
    const height = Math.max(1, rect.height);
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(width, height), 0.72, 0.85, 0.8));

    window.addEventListener('resize', resize, { passive: true });
    if (window.matchMedia('(hover: hover)').matches) {
      window.addEventListener('pointermove', onPointerMove, { passive: true });
      window.addEventListener('pointerleave', onPointerLeave, { passive: true });
    }
    start();
  } catch (err) {
    console.warn('[AlgorithmFieldScroll] WebGL 初始化失敗，退化為統計版', err);
    fallback.value = true;
  }
};

const resize = () => {
  if (!renderer) return;
  const rect = canvasRef.value.getBoundingClientRect();
  const width = Math.max(1, rect.width);
  const height = Math.max(1, rect.height);
  renderer.setSize(width, height, false);
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
  composer?.setSize(width, height);
};

let lookTargetV = null;
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

  if (!lookTargetV) lookTargetV = new THREE.Vector3();
  const pos = new THREE.Vector3().fromArray(from.pos).lerp(new THREE.Vector3().fromArray(to.pos), t);
  const look = new THREE.Vector3().fromArray(from.look).lerp(new THREE.Vector3().fromArray(to.look), t);

  const sway = 1 - clamp((value - 0.82) / 0.18);
  camera.position.set(pos.x + pointer.px * 4 * sway, pos.y - pointer.py * 2.2 * sway, pos.z);
  lookTargetV.copy(look);
  camera.lookAt(lookTargetV);
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
  const s = DATA.samples[hit.instanceId];
  probe.value = {
    q: String(s.q + 1).padStart(2, '0'),
    k: s.k + 1,
    conf: s.conf.toFixed(2),
    ok: s.ok,
    x: pointer.x,
    y: pointer.y,
  };
};

const render = () => {
  rafId = requestAnimationFrame(render);
  if (!renderer || !visible) return;

  smooth.value += (progress.value - smooth.value) * 0.09;
  const value = smooth.value;

  writeHeights(sliceP(value, 0, 0.32) * (Q + 8) - 4);
  placeCamera(value);

  const sweep = sliceP(value, 0.72, 1);
  scanWall.position.z = sweep * (Q + 6) - 3;
  scanWall.material.opacity = Math.sin(sweep * Math.PI) * 0.18;

  const beamIn = sliceP(value, 0.56, 0.68);
  beams.forEach((beam) => { beam.material.opacity = beamIn * 0.5; });

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

onMounted(() => {
  if (reducedMotion()) { fallback.value = true; return; }

  window.addEventListener('scroll', onScroll, { passive: true });
  document.addEventListener('visibilitychange', onVisibility);
  updateProgress();

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
.af { margin: 2rem -1rem 3rem; }
.af-stage { background: radial-gradient(120% 90% at 50% 120%, #0d2338, #050c17 62%); }
.af-stage::before,
.af-stage::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  z-index: 2;
  pointer-events: none;
}
.af-stage::before { top: 0; height: 26%; background: linear-gradient(180deg, rgba(5, 6, 15, 0.9), rgba(5, 6, 15, 0.4) 55%, transparent); }
.af-stage::after { bottom: 0; height: 24%; background: linear-gradient(0deg, rgba(5, 6, 15, 0.92), rgba(5, 6, 15, 0.45) 55%, transparent); }

.af-kicker {
  display: inline-block;
  margin-bottom: 0.6rem;
  color: #22d3ee;
  font: 500 11px/1 ui-monospace, monospace;
  letter-spacing: 0.3em;
}
.af-head {
  position: absolute;
  z-index: 4;
  top: max(96px, 12vh);
  left: clamp(1.25rem, 6vw, 6rem);
  max-width: min(46ch, 84vw);
  color: #fff;
}
.af-source {
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

.af-readout {
  position: absolute;
  z-index: 4;
  left: clamp(1.25rem, 6vw, 6rem);
  bottom: clamp(4rem, 11vh, 6.5rem);
  display: grid;
  grid-auto-flow: column;
  gap: clamp(0.9rem, 2.5vw, 2.25rem);
}
.af-readout > div { opacity: 0.25; transition: opacity 320ms ease-out; }
.af-readout > div.on { opacity: 1; }
/* 判定出現時計數器讓位，避免文字疊在一起 */
.af-readout.aside > div { opacity: 0.1; }
.af-readout-label { display: block; color: #8194ad; font: 500 10px/1 ui-monospace, monospace; letter-spacing: 0.1em; }
.af-readout strong { display: block; margin-top: 0.25rem; font: 600 clamp(1rem, 1.9vw, 1.55rem)/1 ui-monospace, monospace; }

.af-band {
  position: absolute;
  z-index: 4;
  right: clamp(1.25rem, 4vw, 3.5rem);
  top: max(96px, 12vh);
  width: min(250px, 44vw);
  padding: 0.7rem 0.9rem;
  text-align: right;
  border: 1px solid rgba(148, 163, 184, 0.2);
  border-right: 2px solid #22d3ee;
  border-radius: 10px;
  background: rgba(10, 16, 30, 0.66);
  backdrop-filter: blur(10px);
}
.af-band-tag { display: block; color: #8194ad; font: 500 9px/1 ui-monospace, monospace; letter-spacing: 0.12em; }
.af-band strong { display: block; margin-top: 0.3rem; color: #f1f6ff; font-size: 0.95rem; font-family: ui-monospace, monospace; }
.af-band em { display: block; margin-top: 0.18rem; font: 500 10px/1.4 ui-monospace, monospace; font-style: normal; }

.af-legend {
  position: absolute;
  z-index: 4;
  right: clamp(1.25rem, 4vw, 3.5rem);
  bottom: clamp(4rem, 11vh, 6.5rem);
  display: grid;
  gap: 0.35rem;
  justify-items: end;
}
.af-legend span { display: flex; align-items: center; gap: 0.5rem; color: #8194ad; font: 500 10px/1 ui-monospace, monospace; }
.af-legend i { width: 9px; height: 9px; border-radius: 2px; }

.af-peak {
  position: absolute;
  z-index: 5;
  left: 50%;
  top: 44%;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  max-width: min(92vw, 560px);
  padding: 0.7rem 1rem;
  border: 1px solid rgba(167, 139, 250, 0.6);
  border-radius: 14px;
  background: rgba(16, 8, 30, 0.84);
  backdrop-filter: blur(8px);
  box-shadow: 0 0 40px rgba(167, 139, 250, 0.28);
  opacity: 0;
  transform: translate(-50%, 8px) scale(0.94);
  transition: opacity 300ms ease-out, transform 360ms ease-out;
}
.af-peak.on { opacity: 1; transform: translate(-50%, 0) scale(1); }
.af-peak-count { color: #c4b5fd; font: 700 1.6rem/1 ui-monospace, monospace; white-space: nowrap; }
.af-peak strong { display: block; color: #fff; font-size: 0.82rem; }
.af-peak em { display: block; margin-top: 0.15rem; color: #a99ec9; font: 500 10px/1.4 ui-monospace, monospace; font-style: normal; }

.af-verdict {
  position: absolute;
  z-index: 5;
  left: 50%;
  bottom: clamp(4rem, 12vh, 7rem);
  text-align: center;
  width: max-content;
  max-width: 92vw;
  opacity: 0;
  transform: translate(-50%, 14px);
  transition: opacity 380ms ease-out, transform 420ms ease-out;
}
.af-verdict.on { opacity: 1; transform: translate(-50%, 0); }
.af-verdict strong { display: block; color: #fff; font: 600 clamp(1.9rem, 5vw, 3.8rem)/1 ui-monospace, monospace; letter-spacing: -0.02em; text-shadow: 0 0 30px rgba(103, 232, 249, 0.35); }
.af-verdict span { display: block; margin-top: 0.45rem; color: #aebdd4; font-size: 0.78rem; }

.af-probe {
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
.af-probe strong { display: block; color: #fff; font: 600 11px/1 ui-monospace, monospace; }
.af-probe span { display: block; margin-top: 0.2rem; color: #8ea3bd; font: 500 10px/1 ui-monospace, monospace; }
.af-probe em { display: block; margin-top: 0.25rem; font: 600 10px/1 ui-monospace, monospace; font-style: normal; }
.af-probe em.ok { color: #67e8f9; }
.af-probe em.bad { color: #ff5b7a; }

.af-nav {
  position: absolute;
  z-index: 6;
  right: clamp(1.25rem, 4vw, 3.5rem);
  top: 50%;
  transform: translateY(-50%);
  display: grid;
  gap: 0.35rem;
}
.af-nav button {
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
.af-nav button.on { color: #fff; border-color: rgba(34, 211, 238, 0.45); background: rgba(34, 211, 238, 0.12); }
.af-nav button:focus-visible { outline: 2px solid #22d3ee; outline-offset: 2px; }

@media (max-width: 767px) {
  .af-band { top: auto; bottom: clamp(9rem, 20vh, 12rem); }
  .af-legend { display: none; }
  .af-nav { display: none; }
  .af-readout { gap: 0.8rem; }
  /* 判定往上抬，避免與底部計數器重疊 */
  .af-verdict { bottom: clamp(11rem, 26vh, 14rem); }
}
</style>
