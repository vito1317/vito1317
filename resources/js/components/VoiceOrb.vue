<template>
  <div ref="host" class="orb" :class="{ 'orb--fallback': fallback }" aria-hidden="true">
    <div v-if="fallback" class="orb__fallback" :style="{ transform: `scale(${1 + level * 0.35})` }"></div>
  </div>
</template>

<script setup>
// 語音對話的全像粒子球（Three.js）
//  - 4000 顆粒子組成的球體，以 3D simplex noise 即時變形；AI 說話時振幅隨音量放大
//  - 菲涅耳發光核心、頻譜光柱環、三道傾斜軌道環
//  - 聆聽：青 / 紫；AI 說話：青 / 洋紅；靜音或離線：降低亮度
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
  analysers: { type: Object, default: () => ({ mic: null, ai: null }) },
  live: { type: Boolean, default: false },
  speaking: { type: Boolean, default: false },
  muted: { type: Boolean, default: false },
});

const host = ref(null);
const fallback = ref(false);
const level = ref(0);

let THREE = null;
let renderer = null;
let scene = null;
let camera = null;
let raf = 0;
let ro = null;
const disposables = [];

const NOISE_GLSL = /* glsl */ `
vec3 mod289(vec3 x){return x-floor(x*(1.0/289.0))*289.0;}
vec4 mod289(vec4 x){return x-floor(x*(1.0/289.0))*289.0;}
vec4 permute(vec4 x){return mod289(((x*34.0)+1.0)*x);}
vec4 taylorInvSqrt(vec4 r){return 1.79284291400159-0.85373472095314*r;}
float snoise(vec3 v){
  const vec2 C=vec2(1.0/6.0,1.0/3.0); const vec4 D=vec4(0.0,0.5,1.0,2.0);
  vec3 i=floor(v+dot(v,C.yyy)); vec3 x0=v-i+dot(i,C.xxx);
  vec3 g=step(x0.yzx,x0.xyz); vec3 l=1.0-g; vec3 i1=min(g.xyz,l.zxy); vec3 i2=max(g.xyz,l.zxy);
  vec3 x1=x0-i1+C.xxx; vec3 x2=x0-i2+C.yyy; vec3 x3=x0-D.yyy;
  i=mod289(i);
  vec4 p=permute(permute(permute(i.z+vec4(0.0,i1.z,i2.z,1.0))+i.y+vec4(0.0,i1.y,i2.y,1.0))+i.x+vec4(0.0,i1.x,i2.x,1.0));
  float n_=0.142857142857; vec3 ns=n_*D.wyz-D.xzx;
  vec4 j=p-49.0*floor(p*ns.z*ns.z); vec4 x_=floor(j*ns.z); vec4 y_=floor(j-7.0*x_);
  vec4 x=x_*ns.x+ns.yyyy; vec4 y=y_*ns.x+ns.yyyy; vec4 h=1.0-abs(x)-abs(y);
  vec4 b0=vec4(x.xy,y.xy); vec4 b1=vec4(x.zw,y.zw);
  vec4 s0=floor(b0)*2.0+1.0; vec4 s1=floor(b1)*2.0+1.0; vec4 sh=-step(h,vec4(0.0));
  vec4 a0=b0.xzyw+s0.xzyw*sh.xxyy; vec4 a1=b1.xzyw+s1.xzyw*sh.zzww;
  vec3 p0=vec3(a0.xy,h.x); vec3 p1=vec3(a0.zw,h.y); vec3 p2=vec3(a1.xy,h.z); vec3 p3=vec3(a1.zw,h.w);
  vec4 norm=taylorInvSqrt(vec4(dot(p0,p0),dot(p1,p1),dot(p2,p2),dot(p3,p3)));
  p0*=norm.x; p1*=norm.y; p2*=norm.z; p3*=norm.w;
  vec4 m=max(0.6-vec4(dot(x0,x0),dot(x1,x1),dot(x2,x2),dot(x3,x3)),0.0); m=m*m;
  return 42.0*dot(m*m,vec4(dot(p0,x0),dot(p1,x1),dot(p2,x2),dot(p3,x3)));
}`;

function rms(analyser, buf) {
  if (!analyser) return 0;
  analyser.getByteTimeDomainData(buf);
  let sum = 0;
  for (let i = 0; i < buf.length; i += 1) { const v = (buf[i] - 128) / 128; sum += v * v; }
  return Math.min(1, Math.sqrt(sum / buf.length) * 4);
}

function track(obj) { disposables.push(obj); return obj; }

async function init() {
  try {
    THREE = await import('three');
  } catch {
    fallback.value = true;
    return;
  }
  const el = host.value;
  if (!el) return;

  try {
    renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'high-performance' });
  } catch {
    fallback.value = true;
    return;
  }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setClearColor(0x000000, 0);
  el.appendChild(renderer.domElement);

  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
  camera.position.set(0, 0, 4.6);

  const uniforms = {
    uTime: { value: 0 },
    uAmp: { value: 0.08 },
    uSpeak: { value: 0 },
    uDim: { value: 1 },
    uPixel: { value: renderer.getPixelRatio() },
  };

  /* ---- 粒子球：Fibonacci 均勻分布 ---- */
  const N = window.innerWidth < 640 ? 2600 : 4000;
  const pos = new Float32Array(N * 3);
  const seed = new Float32Array(N);
  const golden = Math.PI * (3 - Math.sqrt(5));
  for (let i = 0; i < N; i += 1) {
    const y = 1 - (i / (N - 1)) * 2;
    const r = Math.sqrt(1 - y * y);
    const th = golden * i;
    pos[i * 3] = Math.cos(th) * r;
    pos[i * 3 + 1] = y;
    pos[i * 3 + 2] = Math.sin(th) * r;
    seed[i] = Math.random();
  }
  const pGeo = track(new THREE.BufferGeometry());
  pGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  pGeo.setAttribute('aSeed', new THREE.BufferAttribute(seed, 1));
  const pMat = track(new THREE.ShaderMaterial({
    uniforms,
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    vertexShader: /* glsl */ `
      uniform float uTime; uniform float uAmp; uniform float uSpeak; uniform float uPixel;
      attribute float aSeed;
      varying float vDisp; varying float vSeed; varying float vFacing;
      ${NOISE_GLSL}
      void main(){
        vec3 p = position;
        float n = snoise(p * 1.7 + vec3(0.0, uTime * 0.45, uTime * 0.2));
        float n2 = snoise(p * 4.2 - uTime * 0.9) * 0.35;
        float disp = (n + n2) * uAmp;
        vec3 displaced = p * (1.0 + disp);
        // 說話時表層粒子會被「吹」出去一點
        displaced += normalize(p) * uSpeak * 0.06 * sin(uTime * 6.0 + aSeed * 30.0);
        vec4 mv = modelViewMatrix * vec4(displaced, 1.0);
        gl_Position = projectionMatrix * mv;
        vDisp = disp; vSeed = aSeed;
        vFacing = normalize(normalMatrix * p).z;
        gl_PointSize = (1.6 + max(disp, 0.0) * 9.0 + aSeed * 1.2) * uPixel * (3.2 / -mv.z);
      }`,
    fragmentShader: /* glsl */ `
      uniform float uSpeak; uniform float uDim; uniform float uTime;
      varying float vDisp; varying float vSeed; varying float vFacing;
      void main(){
        vec2 c = gl_PointCoord - 0.5;
        float d = length(c);
        if (d > 0.5) discard;
        float soft = smoothstep(0.5, 0.0, d);
        vec3 cyan = vec3(0.16, 0.9, 1.0);
        vec3 violet = vec3(0.55, 0.48, 1.0);
        vec3 magenta = vec3(1.0, 0.23, 0.78);
        vec3 accent = mix(violet, magenta, uSpeak);
        vec3 col = mix(cyan, accent, smoothstep(-0.05, 0.12, vDisp) * 0.85 + vSeed * 0.15);
        col += vec3(1.0) * smoothstep(0.1, 0.25, vDisp) * 0.5; // 波峰發白
        float back = 0.35 + 0.65 * smoothstep(-0.6, 0.6, vFacing); // 背面較暗，做出立體感
        float twinkle = 0.75 + 0.25 * sin(uTime * 3.0 + vSeed * 50.0);
        gl_FragColor = vec4(col, soft * back * twinkle * 0.85 * uDim);
      }`,
  }));
  const points = new THREE.Points(pGeo, pMat);
  scene.add(points);

  /* ---- 菲涅耳發光核心 ---- */
  const coreGeo = track(new THREE.SphereGeometry(0.62, 48, 48));
  const coreMat = track(new THREE.ShaderMaterial({
    uniforms,
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    vertexShader: /* glsl */ `
      varying vec3 vN; varying vec3 vV;
      void main(){
        vec4 mv = modelViewMatrix * vec4(position, 1.0);
        vN = normalize(normalMatrix * normal); vV = normalize(-mv.xyz);
        gl_Position = projectionMatrix * mv;
      }`,
    fragmentShader: /* glsl */ `
      uniform float uSpeak; uniform float uAmp; uniform float uDim;
      varying vec3 vN; varying vec3 vV;
      void main(){
        float f = pow(1.0 - max(dot(vN, vV), 0.0), 2.2);
        vec3 col = mix(vec3(0.25, 0.85, 1.0), vec3(1.0, 0.35, 0.85), uSpeak);
        float core = 0.10 + uAmp * 0.9;
        gl_FragColor = vec4(col * (f * 1.3 + core), (f * 0.9 + core) * uDim);
      }`,
  }));
  const core = new THREE.Mesh(coreGeo, coreMat);
  scene.add(core);

  /* ---- 背光暈（canvas 貼圖 sprite） ---- */
  const gc = document.createElement('canvas');
  gc.width = gc.height = 128;
  const g = gc.getContext('2d');
  const grad = g.createRadialGradient(64, 64, 0, 64, 64, 64);
  grad.addColorStop(0, 'rgba(120,220,255,0.55)');
  grad.addColorStop(0.4, 'rgba(110,90,255,0.18)');
  grad.addColorStop(1, 'rgba(0,0,0,0)');
  g.fillStyle = grad;
  g.fillRect(0, 0, 128, 128);
  const glowTex = track(new THREE.CanvasTexture(gc));
  const glowMat = track(new THREE.SpriteMaterial({ map: glowTex, blending: THREE.AdditiveBlending, depthWrite: false, transparent: true }));
  const glow = new THREE.Sprite(glowMat);
  glow.scale.set(4.2, 4.2, 1);
  scene.add(glow);

  /* ---- 頻譜光柱環 ---- */
  const BARS = 96;
  const barPos = new Float32Array(BARS * 2 * 3);
  const barGeo = track(new THREE.BufferGeometry());
  barGeo.setAttribute('position', new THREE.BufferAttribute(barPos, 3));
  const barMat = track(new THREE.LineBasicMaterial({ color: 0x2ae6ff, transparent: true, opacity: 0.75, blending: THREE.AdditiveBlending, depthWrite: false }));
  const bars = new THREE.LineSegments(barGeo, barMat);
  scene.add(bars);

  /* ---- 軌道環 ---- */
  const rings = [];
  const ringDefs = [
    { r: 1.62, color: 0x2ae6ff, tilt: [1.15, 0.2, 0], speed: 0.35, dash: false },
    { r: 1.74, color: 0xff3ac8, tilt: [1.35, -0.5, 0.3], speed: -0.25, dash: false },
    { r: 1.9, color: 0x8b7bff, tilt: [0.9, 0.7, -0.2], speed: 0.18, dash: true },
  ];
  ringDefs.forEach((d) => {
    const pts = [];
    const seg = 160;
    for (let i = 0; i <= seg; i += 1) {
      const a = (i / seg) * Math.PI * 2;
      pts.push(new THREE.Vector3(Math.cos(a) * d.r, Math.sin(a) * d.r, 0));
    }
    const geo = track(new THREE.BufferGeometry().setFromPoints(pts));
    const mat = track(d.dash
      ? new THREE.LineDashedMaterial({ color: d.color, dashSize: 0.06, gapSize: 0.08, transparent: true, opacity: 0.55, blending: THREE.AdditiveBlending, depthWrite: false })
      : new THREE.LineBasicMaterial({ color: d.color, transparent: true, opacity: 0.45, blending: THREE.AdditiveBlending, depthWrite: false }));
    const line = new THREE.Line(geo, mat);
    if (d.dash) line.computeLineDistances();
    const pivot = new THREE.Group();
    pivot.rotation.set(...d.tilt);
    pivot.add(line);
    scene.add(pivot);
    rings.push({ line, speed: d.speed, mat });
  });

  const resize = () => {
    const w = el.clientWidth || 240;
    renderer.setSize(w, w, false);
    renderer.domElement.style.width = `${w}px`;
    renderer.domElement.style.height = `${w}px`;
    camera.aspect = 1;
    camera.updateProjectionMatrix();
  };
  resize();
  ro = new ResizeObserver(resize);
  ro.observe(el);

  const td = new Uint8Array(256);
  const fd = new Uint8Array(128);
  let mic = 0;
  let ai = 0;
  let speak = 0;
  let last = performance.now();
  const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  const frame = (now) => {
    raf = requestAnimationFrame(frame);
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;
    const t = now / 1000 * (reduce ? 0.35 : 1);
    const { mic: micA, ai: aiA } = props.analysers || {};

    mic += (rms(micA, td) * (props.muted ? 0 : 1) - mic) * 0.2;
    ai += (rms(aiA, td) - ai) * 0.22;
    speak += ((props.speaking || ai > 0.04 ? 1 : 0) - speak) * Math.min(1, dt * 4);
    level.value = Math.max(ai, mic * 0.6);

    uniforms.uTime.value = t;
    uniforms.uAmp.value = 0.07 + ai * 0.55 + mic * 0.28 + Math.sin(t * 1.3) * 0.01;
    uniforms.uSpeak.value = speak;
    uniforms.uDim.value += ((props.live ? (props.muted ? 0.55 : 1) : 0.4) - uniforms.uDim.value) * 0.08;

    points.rotation.y = t * 0.18;
    points.rotation.x = Math.sin(t * 0.3) * 0.25;
    core.scale.setScalar(1 + ai * 0.35 + mic * 0.12);
    glow.material.opacity = 0.55 + ai * 0.8;
    glow.scale.setScalar(4 + ai * 1.6);

    // 頻譜光柱：說話時用 AI 聲音，否則用麥克風
    const src = ai > 0.02 ? aiA : micA;
    if (src) src.getByteFrequencyData(fd); else fd.fill(0);
    const base = 1.32;
    for (let i = 0; i < BARS; i += 1) {
      const a = (i / BARS) * Math.PI * 2 + t * 0.15;
      const bin = 2 + ((i < BARS / 2 ? i : BARS - i) % 48); // 左右對稱
      const v = fd[bin] / 255;
      const len = 0.03 + v * 0.55 * uniforms.uDim.value;
      const c = Math.cos(a);
      const s = Math.sin(a);
      barPos.set([c * base, s * base, 0, c * (base + len), s * (base + len), 0], i * 6);
    }
    barGeo.attributes.position.needsUpdate = true;
    barMat.color.setHex(speak > 0.5 ? 0xff5ad6 : 0x2ae6ff);
    barMat.opacity = 0.35 + Math.min(0.6, ai + mic) ;

    rings.forEach((r, i) => {
      r.line.rotation.z = t * r.speed;
      r.mat.opacity = (0.3 + speak * 0.25 + Math.sin(t * 2 + i) * 0.08) * uniforms.uDim.value;
    });

    renderer.render(scene, camera);
  };
  raf = requestAnimationFrame(frame);
}

onMounted(init);

onUnmounted(() => {
  cancelAnimationFrame(raf);
  ro?.disconnect();
  disposables.forEach((d) => d.dispose?.());
  renderer?.dispose();
  renderer?.forceContextLoss?.();
  renderer?.domElement?.remove();
});
</script>

<style scoped>
.orb { position: relative; width: min(260px, 66vw); aspect-ratio: 1; }
.orb :deep(canvas) { display: block; }
.orb__fallback {
  position: absolute; inset: 22%; border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #e8ffff, #2ae6ff 40%, #8b7bff 70%, transparent 72%);
  box-shadow: 0 0 50px rgba(42, 230, 255, .5);
  transition: transform .08s linear;
}
</style>
