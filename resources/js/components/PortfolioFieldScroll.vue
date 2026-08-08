<template>
  <section ref="sectionRef" class="pf relative" :style="{ height: fallback ? 'auto' : '460vh' }">
    <!-- reduced-motion / WebGL 失敗：同一組資料改用清單陳述 -->
    <div v-if="fallback" class="pf-flat container mx-auto px-4 py-14">
      <p class="pf-kicker">IMMERSIVE PORTFOLIO EXPERIENCE</p>
      <h2 class="text-3xl sm:text-4xl font-black mb-3">{{ projects.length }} 件作品，一條技術脈絡</h2>
      <p class="text-gray-400 mb-8">共 {{ techCount }} 種技術棧、{{ edgeCount }} 條共用技術連結。</p>
      <ul class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <li v-for="proj in projects" :key="proj.id" class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4">
          <h3 class="font-bold text-gray-100">{{ proj.title }}</h3>
          <p class="mt-1 text-xs text-teal-400/90 font-mono">{{ proj.technologies }}</p>
        </li>
      </ul>
    </div>

    <div v-else class="sticky top-0 h-screen w-full overflow-hidden pf-stage">
      <canvas ref="canvasRef" class="absolute inset-0 w-full h-full" :class="{ 'cursor-pointer': !!probe }"></canvas>

      <header class="pf-head" :style="headStyle">
        <p class="pf-kicker">IMMERSIVE PORTFOLIO EXPERIENCE</p>
        <h2 class="text-3xl sm:text-5xl md:text-6xl font-black leading-tight">
          {{ projects.length }} 件作品，<br class="sm:hidden">一條技術脈絡
        </h2>
        <p class="mt-3 text-sm sm:text-base text-gray-400 max-w-md">
          每一張卡是一個真實上線的專案，卡與卡之間的光線是它們共用的技術棧。
        </p>
        <span class="pf-source">SOURCE · 作品集資料庫 · 即時載入</span>
      </header>

      <!-- 目前作品：鏡頭經過哪張卡就顯示哪一個 -->
      <Transition name="pf-swap">
        <div v-if="activeProject && p >= 0.12 && p < 0.58" :key="activeProject.id" class="pf-project">
          <span class="pf-project-index">WORK {{ String(activeIndex + 1).padStart(2, '0') }} / {{ String(projects.length).padStart(2, '0') }}</span>
          <strong>{{ activeProject.title }}</strong>
          <p>{{ activeProject.description }}</p>
          <div class="pf-chips">
            <span v-for="tech in activeTech" :key="tech">{{ tech }}</span>
          </div>
          <em v-if="activeProject.live_url || activeProject.github_url">點擊卡片前往 →</em>
        </div>
      </Transition>

      <div class="pf-readout" :class="{ aside: verdictOn }">
        <div v-for="s in stats" :key="s.label" :class="{ on: p >= s.at }">
          <span class="pf-readout-label">{{ s.label }}</span>
          <strong :class="{ small: s.small }" :style="{ color: s.css }">{{ s.display }}</strong>
        </div>
      </div>

      <div class="pf-legend" aria-hidden="true">
        <span v-for="item in LEGEND" :key="item.label"><i :style="{ background: item.css }"></i>{{ item.label }}</span>
      </div>

      <!-- 技術網絡 callout -->
      <div class="pf-peak" :class="{ on: peakOn }" role="status">
        <span class="pf-peak-count">{{ edgeCount }}</span>
        <span>
          <strong>條共用技術連結被點亮</strong>
          <em>{{ topTechs.map((t) => `${t.name} ×${t.count}`).join(' · ') }} · {{ techCount }} 種技術棧交織成一張網</em>
        </span>
      </div>

      <div class="pf-verdict" :class="{ on: verdictOn }">
        <strong>{{ projects.length }}<em>works</em></strong>
        <span>{{ techCount }} 種技術棧 · {{ edgeCount }} 條技術連結 · 從 WAF 到 LLM 的完整交付</span>
      </div>

      <div v-if="probe" class="pf-probe" :style="{ left: `${probe.x}px`, top: `${probe.y}px` }" aria-hidden="true">
        <strong>{{ probe.title }}</strong>
        <span>{{ probe.tech }}</span>
        <em v-if="probe.href">前往專案 ↗</em>
      </div>

      <nav class="pf-nav" aria-label="巡覽段落">
        <button v-for="(act, i) in ACTS" :key="act.label" type="button" :class="{ on: currentAct === i }" @click="jumpTo(act.at)">
          {{ act.label }}
        </button>
      </nav>
    </div>
  </section>
</template>

<script setup>
/**
 * PortfolioFieldScroll — 固定式捲動的 3D 作品星系。
 * 每個作品是一張貼上真實截圖的發光卡片，沿廊道右半側螺旋排列；
 * 卡與卡之間依「共用技術棧」連線成網絡（真資料推導，非裝飾）。
 * 捲動刷鏡頭穿飛 → 網絡點亮 → 拉遠俯瞰整張技術網。
 * 效能與降級策略同 CodeFieldScroll：懶建場景、可視性閘控、
 * WebGL 失敗或 reduced-motion 退化為清單。
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useProjectStore } from '../stores/projectStore';

const ACTS = [
  { label: '進場', at: 0.02 },
  { label: '巡覽', at: 0.28 },
  { label: '網絡', at: 0.68 },
  { label: '全景', at: 0.97 },
];
const LEGEND = [
  { label: '作品卡', css: '#67e8f9' },
  { label: '共用技術連結', css: '#a78bfa' },
  { label: '技術節點', css: '#ff2bd6' },
];

const CARD_W = 9;
const CARD_H = 5.6;
const GAP = 15;      // 卡與卡的縱深間距
const FIRST_Z = -10; // 第一張卡的 z
const RADIUS = 8.2;

const sectionRef = ref(null);
const canvasRef = ref(null);
const fallback = ref(false);
const progress = ref(0);
const smooth = ref(0);
const probe = ref(null);
const activeIndex = ref(0);

const store = useProjectStore();
const projects = computed(() => store.projects ?? []);

const techsOf = (proj) =>
  (proj.technologies || '').split(',').map((t) => t.trim()).filter(Boolean);

const techCount = computed(() => new Set(projects.value.flatMap(techsOf)).size);

const edges = computed(() => {
  const list = [];
  const sets = projects.value.map((proj) => new Set(techsOf(proj)));
  for (let i = 0; i < sets.length; i++) {
    for (let j = i + 1; j < sets.length; j++) {
      const shared = [...sets[i]].filter((t) => sets[j].has(t));
      if (shared.length) list.push({ i, j, weight: shared.length });
    }
  }
  return list;
});
const edgeCount = computed(() => edges.value.length);

// 最常用技術：取使用專案數最高的兩項
const topTechs = computed(() => {
  const tally = {};
  projects.value.forEach((proj) => techsOf(proj).forEach((t) => { tally[t] = (tally[t] || 0) + 1; }));
  return Object.entries(tally)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 2)
    .map(([name, count]) => ({ name, count }));
});

const stats = computed(() => [
  { label: '上線作品', display: String(projects.value.length), css: '#e2f6ff', at: 0.06 },
  { label: '技術棧', display: String(techCount.value), css: '#67e8f9', at: 0.16 },
  { label: '技術連結', display: String(edgeCount.value), css: '#a78bfa', at: 0.6 },
  { label: '最常用', display: topTechs.value.map((t) => t.name).join('、'), css: '#ff2bd6', at: 0.68, small: true },
]);

const activeProject = computed(() => projects.value[activeIndex.value] ?? null);
const activeTech = computed(() => (activeProject.value ? techsOf(activeProject.value).slice(0, 6) : []));

const clamp = (v) => Math.min(1, Math.max(0, v));
const sliceP = (v, from, to) => clamp((v - from) / (to - from));
const easeInOut = (t) => (t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2);

const p = computed(() => smooth.value);
const headStyle = computed(() => {
  const out = sliceP(p.value, 0.04, 0.12);
  return { opacity: (1 - out).toFixed(3), transform: `translateY(${(-out * 40).toFixed(1)}px)` };
});
const peakOn = computed(() => p.value >= 0.66 && p.value < 0.92);
const verdictOn = computed(() => p.value >= 0.92);
const currentAct = computed(() => {
  let index = 0;
  ACTS.forEach((act, i) => { if (p.value >= act.at - 0.02) index = i; });
  return index;
});

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* --------------------------------------------------------------------------
   three.js 場景
   ------------------------------------------------------------------------ */
let THREE = null;
let renderer = null;
let composer = null;
let scene = null;
let camera = null;
let cards = [];        // { mesh, frame, glow, index, href }
let edgeLines = null;
let edgeMat = null;
let nodes = null;
let nodeMat = null;
let raycaster = null;
let rafId = null;
let io = null;
let disposed = false;
let visible = false;
let built = false;
let frameCount = 0;
let scrollRaf = null;
let totalDepth = 0;
let billboard = null;
const pointer = { x: 0, y: 0, px: 0, py: 0, active: false, moved: false };
const textures = [];

// 卡片沿廊道「右半側」螺旋排列，左半留給前景文案
const cardPos = (i) => {
  const theta = (-0.55 + (i / Math.max(1, projects.value.length - 1)) * 1.1) * Math.PI * 0.62;
  return {
    x: Math.cos(theta) * RADIUS + 1.5,
    y: Math.sin(theta) * 4.6,
    z: FIRST_Z - i * GAP,
  };
};

const buildCards = async () => {
  const loader = new THREE.TextureLoader();
  const loadTexture = (url) =>
    new Promise((resolve) => {
      loader.load(
        url,
        (tex) => { tex.colorSpace = THREE.SRGBColorSpace; resolve(tex); },
        undefined,
        () => resolve(null),
      );
    });

  const maps = await Promise.all(projects.value.map((proj) => (proj.image ? loadTexture(proj.image) : Promise.resolve(null))));
  if (disposed) return;

  projects.value.forEach((proj, i) => {
    const group = new THREE.Group();
    const pos = cardPos(i);
    group.position.set(pos.x, pos.y, pos.z);
    group.lookAt(0, 0, pos.z); // 面向廊道軸線（也就是鏡頭的行進路徑）

    const map = maps[i];
    if (map) textures.push(map);

    // 卡片高度隨截圖原始比例調整，避免畫面被壓扁
    const aspect = map?.image?.width ? map.image.width / map.image.height : CARD_W / CARD_H;
    const height = Math.min(9, Math.max(3.4, CARD_W / aspect));
    const geometry = new THREE.PlaneGeometry(CARD_W, height);

    const material = new THREE.MeshBasicMaterial({
      // 略為壓暗，避免白底截圖衝破 bloom 閾值糊成一片白
      map: map || null,
      color: map ? 0xd2e4ef : 0x123049,
      transparent: true,
      opacity: 0,
      side: THREE.DoubleSide,
      toneMapped: false,
    });
    const mesh = new THREE.Mesh(geometry, material);
    group.add(mesh);

    const frame = new THREE.LineSegments(
      new THREE.EdgesGeometry(geometry),
      new THREE.LineBasicMaterial({ color: 0x67e8f9, transparent: true, opacity: 0 }),
    );
    frame.scale.setScalar(1.02);
    group.add(frame);

    // 背面暈光板：讓卡片在暗場裡浮起來
    const glow = new THREE.Mesh(
      new THREE.PlaneGeometry(CARD_W * 1.35, height * 1.5),
      new THREE.MeshBasicMaterial({
        color: 0x22d3ee,
        transparent: true,
        opacity: 0,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        side: THREE.DoubleSide,
      }),
    );
    glow.position.z = -0.35;
    group.add(glow);

    scene.add(group);
    cards.push({
      group,
      mesh,
      material,
      frameMat: frame.material,
      glowMat: glow.material,
      baseQuat: group.quaternion.clone(), // 全景時由此 slerp 到正面朝向鏡頭
      index: i,
      href: proj.live_url || proj.github_url || null,
      spin: (i % 2 === 0 ? 1 : -1) * (0.1 + (i % 3) * 0.05),
    });
  });
};

const buildNetwork = () => {
  // 共用技術棧連線
  const positions = [];
  edges.value.forEach(({ i, j }) => {
    const a = cardPos(i);
    const b = cardPos(j);
    positions.push(a.x, a.y, a.z, b.x, b.y, b.z);
  });
  const geo = new THREE.BufferGeometry();
  geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
  edgeMat = new THREE.LineBasicMaterial({
    color: 0xa78bfa,
    transparent: true,
    opacity: 0,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
  });
  edgeLines = new THREE.LineSegments(geo, edgeMat);
  scene.add(edgeLines);

  // 技術節點：沿連線散佈的粒子，代表 34 種技術
  const pts = [];
  edges.value.forEach(({ i, j }) => {
    const a = cardPos(i);
    const b = cardPos(j);
    for (let s = 1; s <= 3; s++) {
      const t = s / 4;
      pts.push(
        a.x + (b.x - a.x) * t + (Math.random() - 0.5) * 0.6,
        a.y + (b.y - a.y) * t + (Math.random() - 0.5) * 0.6,
        a.z + (b.z - a.z) * t + (Math.random() - 0.5) * 0.6,
      );
    }
  });
  const nodeGeo = new THREE.BufferGeometry();
  nodeGeo.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
  nodeMat = new THREE.PointsMaterial({
    color: 0xff2bd6,
    size: 0.3,
    transparent: true,
    opacity: 0,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
  });
  nodes = new THREE.Points(nodeGeo, nodeMat);
  scene.add(nodes);

  // 環境星塵
  const dust = [];
  const count = window.innerWidth < 768 ? 220 : 460;
  for (let i = 0; i < count; i++) {
    dust.push(
      (Math.random() - 0.5) * 70,
      (Math.random() - 0.5) * 40,
      20 - Math.random() * (totalDepth + 60),
    );
  }
  const dustGeo = new THREE.BufferGeometry();
  dustGeo.setAttribute('position', new THREE.Float32BufferAttribute(dust, 3));
  scene.add(new THREE.Points(dustGeo, new THREE.PointsMaterial({
    color: 0x9be8ff,
    size: 0.14,
    transparent: true,
    opacity: 0.5,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
  })));
};

const init = async () => {
  if (built || disposed || !projects.value.length) return;
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

    totalDepth = (projects.value.length - 1) * GAP;
    const mobile = window.innerWidth < 768;

    renderer = new THREE.WebGLRenderer({ canvas: canvasRef.value, antialias: !mobile, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, mobile ? 1.25 : 1.75));

    scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0x050c17, 40, 190);
    camera = new THREE.PerspectiveCamera(58, 1, 0.1, 400);

    await buildCards();
    if (disposed) return;
    buildNetwork();

    raycaster = new THREE.Raycaster();
    const rect = canvasRef.value.getBoundingClientRect();
    const width = Math.max(1, rect.width);
    const height = Math.max(1, rect.height);
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    // 低強度、閾值落在霓虹邊框亮度上：邊框與連線會發光，截圖不過曝
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(width, height), 0.3, 0.7, 0.72));

    window.addEventListener('resize', resize, { passive: true });
    if (window.matchMedia('(hover: hover)').matches) {
      window.addEventListener('pointermove', onPointerMove, { passive: true });
      window.addEventListener('pointerleave', onPointerLeave, { passive: true });
    }
    canvasRef.value.addEventListener('click', onCanvasClick);
    start();
  } catch (err) {
    console.warn('[PortfolioFieldScroll] WebGL 初始化失敗，退化為清單', err);
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

let lookV = null;
let panPos = null;
let panLook = null;
let netPos = null;
let netLook = null;
let faceT = 0;     // 卡片轉正面向鏡頭的程度
let panoramaT = 0; // 1 = 側面星系全景
const placeCamera = (value, time) => {
  if (!lookV) {
    lookV = new THREE.Vector3();
    panPos = new THREE.Vector3();
    panLook = new THREE.Vector3();
    netPos = new THREE.Vector3();
    netLook = new THREE.Vector3();
  }
  // 直式螢幕的水平視野窄很多，取景策略與橫式不同
  const portrait = camera.aspect < 1;

  // 第一段：廊道飛行。等速前進，讓每張卡都拿到等量的捲動距離
  //（先前用 easeInOut，中段速度暴衝導致前幾張卡幾乎看不到）
  const travel = clamp((value - 0.06) / 0.52);
  const camZ = 40 - travel * (totalDepth + 64); // 起點拉遠，第一張卡才有完整的接近距離
  const sway = Math.sin(time * 0.00035) * 1.2;

  // 連續的卡片座標。前瞻 0.9 張：鏡頭看的是「即將抵達」那張卡，
  // 而不是正貼身掠過、已經糊掉的那張；面板也跟著同一張走。
  const last = projects.value.length - 1;
  const fIdx = (FIRST_Z - camZ) / GAP + 0.9;
  const i0 = Math.min(last, Math.max(0, Math.floor(fIdx)));
  const i1 = Math.min(last, i0 + 1);
  const raw = clamp(fIdx - i0);
  const blend = raw * raw * (3 - 2 * raw); // smoothstep：在卡前稍作停留再轉向下一張

  const idx = Math.min(last, Math.max(0, Math.round(fIdx)));
  if (idx !== activeIndex.value) activeIndex.value = idx;

  // 鏡頭看向「前方軸線」與「當前卡片」的混合，形成畫廊般的轉頭
  const a = cardPos(i0);
  const b = cardPos(i1);
  const tx = a.x + (b.x - a.x) * blend;
  const ty = a.y + (b.y - a.y) * blend;
  const tz = a.z + (b.z - a.z) * blend;
  const aheadZ = camZ - GAP * 0.9;
  lookV.set(tx * 0.55, ty * 0.5 - (portrait ? 4.6 : 0), (aheadZ + tz) / 2);

  const basePos = new THREE.Vector3(
    -3.2 + sway + pointer.px * 2.2,
    1.4 - pointer.py * 1.2,
    camZ,
  );

  const cx = 1.5 + RADIUS * 0.82;      // 卡片群的水平中心
  const cz = FIRST_Z - totalDepth / 2; // 縱深中心

  // 第二段：盪出廊道，斜側 3/4 視角看技術連線亮起
  const netT = easeInOut(sliceP(value, 0.58, 0.74));
  if (netT > 0) {
    if (portrait) netPos.set(cx + 20, 34, cz + 34);
    else netPos.set(cx + 46, 15, cz - 26);
    netLook.set(cx, 0, cz - 4);
    basePos.lerp(netPos, netT);
    lookV.lerp(netLook, netT);
  }

  // 第三段：拉到正側面，整條螺旋與技術網絡一次入鏡
  panoramaT = easeInOut(sliceP(value, 0.74, 1));
  if (panoramaT > 0) {
    if (portrait) {
      // 拉更遠以壓縮透視差（近端卡片才不會過大），視線壓低讓星系落在畫面上半
      panPos.set(cx + 24, 80, cz + 120);
      panLook.set(cx, -12, cz - 10);
    } else {
      panPos.set(cx + 95, 26, cz + 4);
      panLook.set(cx, -1, cz);
    }
    basePos.lerp(panPos, panoramaT);
    lookV.lerp(panLook, panoramaT);
  }

  // 離開廊道後把霧推開，否則遠端卡片會被吃掉
  faceT = Math.max(netT * 0.9, panoramaT);
  scene.fog.near = 40 + faceT * 260;
  scene.fog.far = 190 + faceT * 710;

  camera.position.copy(basePos);
  camera.lookAt(lookV);
};

const render = (time) => {
  rafId = requestAnimationFrame(render);
  if (!renderer || !visible) return;

  smooth.value += (progress.value - smooth.value) * 0.09;
  const value = smooth.value;

  placeCamera(value, time);

  // 卡片：依鏡頭距離淡入；全景時全部亮起並轉正面向鏡頭
  if (!billboard) billboard = new THREE.Object3D();
  cards.forEach((card) => {
    const dist = Math.abs(card.group.position.z - camera.position.z);
    const near = Math.max(0, 1 - dist / 46);
    const appear = Math.max(near, faceT);
    card.material.opacity = appear;
    card.frameMat.opacity = Math.min(1, appear * 1.15);
    card.glowMat.opacity = appear * 0.12;

    if (faceT > 0.001) {
      billboard.position.copy(card.group.position);
      billboard.lookAt(camera.position);
      card.group.quaternion.copy(card.baseQuat).slerp(billboard.quaternion, faceT);
    } else {
      card.group.quaternion.copy(card.baseQuat);
    }
    card.group.rotateZ(Math.sin(time * 0.0004 + card.index) * 0.02 * card.spin);
    card.group.scale.setScalar(1 + near * 0.04);
  });

  // 網絡：第三幕點亮，全景時最強
  const netIn = sliceP(value, 0.52, 0.66);
  edgeMat.opacity = netIn * 0.28 + panoramaT * 0.55;
  nodeMat.opacity = netIn * 0.5 + panoramaT * 0.45;
  nodes.rotation.z = time * 0.00002;

  frameCount++;
  if (pointer.moved && frameCount % 3 === 0) {
    pointer.moved = false;
    updateProbe();
  }

  composer.render();
};

const updateProbe = () => {
  if (!pointer.active || !cards.length) {
    if (probe.value) probe.value = null;
    return;
  }
  raycaster.setFromCamera({ x: pointer.px, y: -pointer.py }, camera);
  const hit = raycaster.intersectObjects(cards.map((c) => c.mesh), false)[0];
  if (!hit) {
    if (probe.value) probe.value = null;
    return;
  }
  const card = cards.find((c) => c.mesh === hit.object);
  const proj = projects.value[card.index];
  if (card.material.opacity < 0.25) {
    if (probe.value) probe.value = null;
    return;
  }
  probe.value = {
    title: proj.title,
    tech: techsOf(proj).slice(0, 3).join(' · '),
    href: card.href,
    x: pointer.x,
    y: pointer.y,
  };
};

const onCanvasClick = () => {
  if (probe.value?.href) window.open(probe.value.href, '_blank', 'noopener');
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

onMounted(async () => {
  if (reducedMotion()) fallback.value = true;

  if (!projects.value.length) {
    try { await store.fetchProjects(); } catch { /* 由 fallback 處理 */ }
  }
  if (!projects.value.length) { fallback.value = true; return; }
  if (fallback.value) return;

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
  canvasRef.value?.removeEventListener('click', onCanvasClick);
  if (scrollRaf) cancelAnimationFrame(scrollRaf);
  textures.forEach((tex) => tex.dispose());
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
.pf-stage { background: radial-gradient(120% 90% at 50% 120%, #0d2338, #050c17 62%); }
.pf-stage::before,
.pf-stage::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  z-index: 2;
  pointer-events: none;
}
.pf-stage::before { top: 0; height: 24%; background: linear-gradient(180deg, rgba(5, 6, 15, 0.88), rgba(5, 6, 15, 0.35) 55%, transparent); }
.pf-stage::after { bottom: 0; height: 24%; background: linear-gradient(0deg, rgba(5, 6, 15, 0.92), rgba(5, 6, 15, 0.45) 55%, transparent); }

.pf-kicker {
  display: inline-block;
  margin-bottom: 0.6rem;
  color: #22d3ee;
  font: 500 11px/1 ui-monospace, monospace;
  letter-spacing: 0.3em;
}
.pf-head {
  position: absolute;
  z-index: 4;
  top: max(96px, 12vh);
  left: clamp(1.25rem, 6vw, 6rem);
  max-width: min(44ch, 84vw);
  color: #fff;
}
.pf-source {
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

/* 目前作品資訊：固定在左側，鏡頭經過哪張卡就換哪一個 */
.pf-project {
  position: absolute;
  z-index: 4;
  left: clamp(1.25rem, 6vw, 6rem);
  top: 50%;
  transform: translateY(-50%);
  width: min(30rem, 46vw);
  padding: 1.1rem 1.25rem;
  border-left: 2px solid #22d3ee;
  border-radius: 4px 12px 12px 4px;
  background: linear-gradient(90deg, rgba(8, 15, 28, 0.9), rgba(8, 15, 28, 0.35));
  backdrop-filter: blur(8px);
}
.pf-project-index { display: block; color: #22d3ee; font: 500 10px/1 ui-monospace, monospace; letter-spacing: 0.24em; }
.pf-project strong { display: block; margin-top: 0.55rem; color: #fff; font-size: clamp(1.15rem, 2.2vw, 1.7rem); line-height: 1.25; }
.pf-project p {
  margin-top: 0.5rem;
  color: #b7c4d8;
  font-size: 0.8rem;
  line-height: 1.7;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.pf-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.7rem; }
.pf-chips span {
  padding: 0.2rem 0.55rem;
  border: 1px solid rgba(103, 232, 249, 0.35);
  border-radius: 999px;
  color: #8fe6f7;
  font: 500 10px/1.5 ui-monospace, monospace;
}
.pf-project em { display: block; margin-top: 0.7rem; color: #6b7a92; font: 500 10px/1 ui-monospace, monospace; font-style: normal; }

.pf-swap-enter-active, .pf-swap-leave-active { transition: opacity 0.42s ease, transform 0.42s cubic-bezier(0.16, 1, 0.3, 1); }
.pf-swap-enter-from { opacity: 0; transform: translate(0, calc(-50% + 12px)); }
.pf-swap-leave-to { opacity: 0; transform: translate(0, calc(-50% - 10px)); }

.pf-readout {
  position: absolute;
  z-index: 4;
  left: clamp(1.25rem, 6vw, 6rem);
  bottom: clamp(4rem, 11vh, 6.5rem);
  display: grid;
  grid-auto-flow: column;
  gap: clamp(0.9rem, 2.5vw, 2.25rem);
}
.pf-readout > div { opacity: 0.25; transition: opacity 320ms ease-out; }
.pf-readout > div.on { opacity: 1; }
.pf-readout.aside > div { opacity: 0.1; }
.pf-readout-label { display: block; color: #8194ad; font: 500 10px/1 ui-monospace, monospace; letter-spacing: 0.1em; }
.pf-readout strong { display: block; margin-top: 0.25rem; font: 600 clamp(1rem, 1.9vw, 1.55rem)/1 ui-monospace, monospace; }
/* 技術名稱較長，縮一級字避免擠爆最後一欄 */
.pf-readout strong.small { font-size: clamp(0.78rem, 1.3vw, 1.05rem); }

.pf-legend {
  position: absolute;
  z-index: 4;
  right: clamp(1.25rem, 4vw, 3.5rem);
  bottom: clamp(4rem, 11vh, 6.5rem);
  display: grid;
  gap: 0.35rem;
  justify-items: end;
}
.pf-legend span { display: flex; align-items: center; gap: 0.5rem; color: #8194ad; font: 500 10px/1 ui-monospace, monospace; }
.pf-legend i { width: 9px; height: 9px; border-radius: 2px; }

.pf-peak {
  position: absolute;
  z-index: 5;
  left: 50%;
  top: 16%;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  max-width: min(92vw, 540px);
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
.pf-peak.on { opacity: 1; transform: translate(-50%, 0) scale(1); }
.pf-peak-count { color: #c4b5fd; font: 700 2rem/1 ui-monospace, monospace; }
.pf-peak strong { display: block; color: #fff; font-size: 0.82rem; }
.pf-peak em { display: block; margin-top: 0.15rem; color: #a99ec9; font: 500 10px/1.4 ui-monospace, monospace; font-style: normal; }

.pf-verdict {
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
.pf-verdict.on { opacity: 1; transform: translate(-50%, 0); }
.pf-verdict strong { display: block; color: #fff; font: 600 clamp(2.2rem, 6vw, 4.4rem)/1 ui-monospace, monospace; letter-spacing: -0.02em; }
.pf-verdict strong em { margin-left: 0.5rem; font-size: 0.32em; font-style: normal; color: #67e8f9; }
.pf-verdict span { display: block; margin-top: 0.45rem; color: #aebdd4; font-size: 0.78rem; }

.pf-probe {
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
.pf-probe strong { display: block; color: #fff; font: 600 11px/1.3 ui-monospace, monospace; }
.pf-probe span { display: block; margin-top: 0.2rem; color: #8ea3bd; font: 500 10px/1 ui-monospace, monospace; }
.pf-probe em { display: block; margin-top: 0.25rem; color: #67e8f9; font: 600 10px/1 ui-monospace, monospace; font-style: normal; }

.pf-nav {
  position: absolute;
  z-index: 6;
  right: clamp(1.25rem, 4vw, 3.5rem);
  top: 50%;
  transform: translateY(-50%);
  display: grid;
  gap: 0.35rem;
}
.pf-nav button {
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
.pf-nav button.on { color: #fff; border-color: rgba(34, 211, 238, 0.45); background: rgba(34, 211, 238, 0.12); }
.pf-nav button:focus-visible { outline: 2px solid #22d3ee; outline-offset: 2px; }

@media (max-width: 767px) {
  .pf-project { top: auto; bottom: clamp(8.5rem, 22vh, 12rem); transform: none; width: calc(100vw - 2.5rem); }
  .pf-swap-enter-from, .pf-swap-leave-to { transform: none; }
  .pf-project p { -webkit-line-clamp: 2; }
  .pf-legend { display: none; }
  .pf-nav { display: none; }
  .pf-readout { gap: 0.8rem; }
  .pf-peak { top: 12%; }
  .pf-verdict { bottom: clamp(3rem, 9vh, 4.5rem); width: 92vw; }
  /* 手機底部空間小：判定出現時計數器完全讓位 */
  .pf-readout.aside > div { opacity: 0; }
}
</style>
