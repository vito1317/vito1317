<template>
  <canvas
    ref="canvasRef"
    class="scene3d absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none"
    aria-hidden="true"
  ></canvas>
</template>

<script setup>
/**
 * ScrollScene3D — PinnedStory 的 3D 捲動場景（three.js 動態載入）。
 * 捲動進度（progress 0-1）直接刷動畫時間軸：幾何體旋轉、軌道環、
 * 粒子殼層與鏡頭推近全部由捲動驅動；act 切換時幾何體交叉變形。
 * 效能：由父層的 active prop 閘控 rAF（區塊不可見即停）、DPR 上限、
 * 手機減粒子、reduced-motion 只渲染靜態單幀。
 */
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { cappedPixelRatio } from '../utils/renderScale';

const props = defineProps({
  progress: { type: Number, default: 0 },
  act: { type: Number, default: 0 },
  active: { type: Boolean, default: false },
});

const ACCENTS = [0x22e0ff, 0xff2bd6, 0xa78bfa];

const canvasRef = ref(null);

let THREE = null;
let renderer = null;
let scene = null;
let camera = null;
let rafId = null;
let disposed = false;

let coreGroups = [];   // 每幕一個幾何體群組（交叉變形）
let orbitRings = [];
let particles = null;
let particleMat = null;
let ringMats = [];
let colorCurrent = null;
let mouseX = 0;
let mouseY = 0;
let mouseTX = 0;
let mouseTY = 0;
let smoothProgress = 0;
let hasMouse = false;

const reducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const buildScene = () => {
  const mobile = window.innerWidth < 768;
  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(55, 1, 0.1, 200);
  camera.position.z = 30;

  renderer = new THREE.WebGLRenderer({
    canvas: canvasRef.value,
    alpha: true,
    antialias: !mobile,
    powerPreference: 'low-power',
  });
  renderer.setClearColor(0x000000, 0);

  // 三幕幾何體：資安=二十面體(盾)、AI=環面結(神經)、影響力=八面體(晶體)
  const geometries = [
    new THREE.IcosahedronGeometry(6.4, 1),
    new THREE.TorusKnotGeometry(4.4, 1.35, 110, 14),
    new THREE.OctahedronGeometry(6.6, 0),
  ];

  coreGroups = geometries.map((geo, i) => {
    const group = new THREE.Group();
    const color = ACCENTS[i];

    const edges = new THREE.EdgesGeometry(geo, 12);
    const lineMat = new THREE.LineBasicMaterial({ color, transparent: true, opacity: 0 });
    group.add(new THREE.LineSegments(edges, lineMat));

    const faceMat = new THREE.MeshBasicMaterial({
      color,
      transparent: true,
      opacity: 0,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      side: THREE.DoubleSide,
    });
    group.add(new THREE.Mesh(geo, faceMat));

    group.userData = { lineMat, faceMat };
    group.scale.setScalar(0.001);
    scene.add(group);
    return group;
  });

  // 三條傾斜軌道環
  orbitRings = [10.5, 12.5, 14.5].map((radius, i) => {
    const geo = new THREE.TorusGeometry(radius, 0.035, 6, 96);
    const mat = new THREE.MeshBasicMaterial({
      color: ACCENTS[0],
      transparent: true,
      opacity: 0.4 - i * 0.08,
    });
    const ring = new THREE.Mesh(geo, mat);
    ring.rotation.x = Math.PI / 2.2 + i * 0.35;
    ring.rotation.y = i * 0.9;
    ringMats.push(mat);
    scene.add(ring);
    return ring;
  });

  // 粒子殼層
  const count = mobile ? 220 : 480;
  const positions = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) {
    const r = 15 + Math.random() * 9;
    const theta = Math.random() * Math.PI * 2;
    const phi = Math.acos(2 * Math.random() - 1);
    positions[i * 3] = r * Math.sin(phi) * Math.cos(theta);
    positions[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta) * 0.7;
    positions[i * 3 + 2] = r * Math.cos(phi);
  }
  const pGeo = new THREE.BufferGeometry();
  pGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  particleMat = new THREE.PointsMaterial({
    color: ACCENTS[0],
    size: 0.14,
    transparent: true,
    opacity: 0.75,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
  });
  particles = new THREE.Points(pGeo, particleMat);
  scene.add(particles);

  colorCurrent = new THREE.Color(ACCENTS[0]);
  resize();
};

const resize = () => {
  if (!renderer) return;
  const el = canvasRef.value;
  const size = el.clientWidth || 1;
  renderer.setPixelRatio(cappedPixelRatio(size, size));
  renderer.setSize(size, size, false);
  camera.aspect = 1;
  camera.updateProjectionMatrix();
};

const renderFrame = (time) => {
  const t = time * 0.001;
  smoothProgress += (props.progress - smoothProgress) * 0.08;
  mouseX += (mouseTX - mouseX) * 0.05;
  mouseY += (mouseTY - mouseY) * 0.05;
  const p = smoothProgress;

  // 幾何體交叉變形 + 捲動刷旋轉
  coreGroups.forEach((group, i) => {
    const isActive = i === props.act;
    const targetScale = isActive ? 1 : 0.001;
    const s = group.scale.x + (targetScale - group.scale.x) * 0.09;
    group.scale.setScalar(s);
    const ud = group.userData;
    ud.lineMat.opacity += ((isActive ? 0.9 : 0) - ud.lineMat.opacity) * 0.09;
    ud.faceMat.opacity += ((isActive ? 0.05 : 0) - ud.faceMat.opacity) * 0.09;

    group.rotation.y = p * Math.PI * 5 + t * 0.06 + i * 0.8;
    group.rotation.x = Math.sin(p * Math.PI * 2) * 0.4 + mouseY * 0.2;
    group.rotation.z = p * Math.PI * 0.6;
  });

  // 軌道環：捲動驅動 + 微量閒置漂移
  orbitRings.forEach((ring, i) => {
    const dir = i % 2 === 0 ? 1 : -1;
    ring.rotation.z = p * Math.PI * (1.6 + i * 0.7) * dir + t * 0.03 * dir;
  });

  // 粒子殼層
  particles.rotation.y = p * Math.PI * 2 + t * 0.025;
  particles.rotation.x = mouseY * 0.1;

  // 主題色朝當前幕的 accent 過渡
  const target = new THREE.Color(ACCENTS[props.act]);
  colorCurrent.lerp(target, 0.06);
  ringMats.forEach((m) => m.color.copy(colorCurrent));
  particleMat.color.copy(colorCurrent);

  // 鏡頭：隨進度推近 + 滑鼠視差
  camera.position.z = 30 - p * 8;
  camera.position.x = mouseX * 2.2;
  camera.position.y = mouseY * 1.4;
  camera.lookAt(0, 0, 0);

  renderer.render(scene, camera);
};

const loop = (time) => {
  rafId = requestAnimationFrame(loop);
  renderFrame(time);
};

const start = () => {
  if (rafId || !renderer || disposed || reducedMotion()) return;
  rafId = requestAnimationFrame(loop);
};

const stop = () => {
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
};

const onMouseMove = (e) => {
  mouseTX = (e.clientX / window.innerWidth) * 2 - 1;
  mouseTY = -((e.clientY / window.innerHeight) * 2 - 1);
};

watch(
  () => props.active,
  (v) => { v ? start() : stop(); },
);

// reduced-motion：act 變更時仍更新靜態畫面
watch(
  () => props.act,
  () => {
    if (renderer && reducedMotion()) {
      smoothProgress = props.progress;
      for (let i = 0; i < 40; i++) renderFrame(performance.now());
    }
  },
);

onMounted(async () => {
  try {
    THREE = await import('three');
    if (disposed) return;
    buildScene();
    window.addEventListener('resize', resize);
    hasMouse = window.matchMedia('(hover: hover)').matches;
    if (hasMouse) window.addEventListener('mousemove', onMouseMove, { passive: true });

    if (reducedMotion()) {
      // 靜態單幀（收斂交叉變形後再渲染）
      smoothProgress = props.progress;
      for (let i = 0; i < 40; i++) renderFrame(performance.now());
    } else if (props.active) {
      start();
    }
  } catch (err) {
    console.warn('[ScrollScene3D] WebGL 初始化失敗，略過 3D 場景', err);
  }
});

onUnmounted(() => {
  disposed = true;
  stop();
  window.removeEventListener('resize', resize);
  if (hasMouse) window.removeEventListener('mousemove', onMouseMove);
  if (scene) {
    scene.traverse((obj) => {
      obj.geometry?.dispose?.();
      if (obj.material) (Array.isArray(obj.material) ? obj.material : [obj.material]).forEach((m) => m.dispose());
    });
  }
  renderer?.dispose();
});
</script>

<style scoped>
.scene3d {
  width: min(100vmin, 720px);
  height: min(100vmin, 720px);
}
@media (max-width: 768px) {
  .scene3d { width: min(110vmin, 520px); height: min(110vmin, 520px); opacity: 0.6; }
}
</style>
