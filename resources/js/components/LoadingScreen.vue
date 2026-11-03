<template>
  <div class="boot" :class="`boot--${stage}`" role="status" aria-live="polite" aria-label="網站載入中">
    <div class="boot__bg"></div>

    <div class="boot__screen">
      <canvas ref="canvasEl" class="boot__canvas" aria-hidden="true"></canvas>
      <div class="boot__grid" aria-hidden="true"></div>
      <div class="boot__scanlines" aria-hidden="true"></div>
      <div class="boot__vignette" aria-hidden="true"></div>

      <div class="boot__hud" aria-hidden="true">
        <span class="boot__corner boot__corner--tl"></span>
        <span class="boot__corner boot__corner--tr"></span>
        <span class="boot__corner boot__corner--bl"></span>
        <span class="boot__corner boot__corner--br"></span>

        <div class="boot__tl">
          <p class="boot__brand">VITO1317 <i>//</i> NEURAL BOOT SEQUENCE</p>
          <p class="boot__dim">SID 0x{{ sessionId }} · BUILD 4.0.{{ buildNo }}</p>
        </div>

        <div class="boot__tr">
          <p class="boot__clock">{{ clock }}</p>
          <p class="boot__dim">25.0657°N 121.6580°E · XIZHI/TW</p>
        </div>

        <div class="boot__log">
          <p v-for="line in visibleLog" :key="line.key" :class="{ ok: line.ok, pending: !line.ok }">
            <b>{{ line.ok ? '[ OK ]' : '[ .. ]' }}</b> {{ line.text }}
          </p>
        </div>

        <div class="boot__telemetry">
          <div v-for="t in telemetry" :key="t.k" class="boot__meter">
            <span>{{ t.k }}</span>
            <i><em :style="{ width: `${t.v}%` }"></em></i>
            <b>{{ String(Math.round(t.v)).padStart(2, '0') }}</b>
          </div>
        </div>
      </div>

      <div class="boot__readout" :style="{ top: `${readoutTop}px` }">
        <p v-if="stage === 'granted' || stage === 'warp' || stage === 'out'" class="boot__granted" data-text="ACCESS GRANTED">ACCESS GRANTED</p>
        <p v-else class="boot__phase">{{ phaseText }}</p>

        <div class="boot__bar" aria-hidden="true">
          <span v-for="n in SEGMENTS" :key="n" :class="{ on: n <= litSegments, head: n === litSegments }"></span>
        </div>
        <div class="boot__nums">
          <span>{{ stage === 'granted' || stage === 'warp' ? 'LINK ESTABLISHED' : 'SYNCING DATA STREAM' }}</span>
          <b>{{ percent }}<small>%</small></b>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { cappedPixelRatio } from '../utils/renderScale';

const props = defineProps({
  /** 資料是否已載入完成；為 true 後動畫會收尾並觸發 done */
  ready: { type: Boolean, default: false },
});
const emit = defineEmits(['done']);

/* ---------- 時間軸參數 ---------- */
const MIN_MS = 1900;      // 最短展示時間：避免快取命中時動畫一閃而過
const BOOT_MS = 520;      // CRT 開機 → 粒子開始成形
const GRANTED_MS = 560;   // ACCESS GRANTED 停留
const WARP_MS = 820;      // 粒子躍遷爆散
const OUT_MS = 360;       // 最後淡出
const SEGMENTS = 36;
const COLORS = ['#2ae6ff', '#8b7bff', '#ff3ac8'];
const GLYPHS = '!<>-_\\/[]{}=+*^?#01ABCDEF';

const stage = ref('boot'); // boot → forming → granted → warp → out
const progress = ref(0);
const phaseText = ref('');
const clock = ref('00:00:00.000');
const readoutTop = ref(0);
const canvasEl = ref(null);

const sessionId = Math.floor(Math.random() * 0xffffffff).toString(16).toUpperCase().padStart(8, '0');
const buildNo = Math.floor(1000 + Math.random() * 8999);

const percent = computed(() => progress.value.toFixed(1).padStart(5, '0'));
const litSegments = computed(() => Math.round((progress.value / 100) * SEGMENTS));

const PHASES = [
  [0, 'POWERING NEURAL CORE'],
  [18, 'DECRYPTING SIGNAL'],
  [40, 'ASSEMBLING IDENTITY MATRIX'],
  [64, 'CALIBRATING VISUAL FIELD'],
  [86, 'HANDSHAKING AI CORE'],
];

const LOG = [
  [0, 'BIOS HANDSHAKE · VITO-OS 4.0'],
  [7, 'MOUNTING /dev/portfolio'],
  [20, 'ENCRYPTION LAYER · AES-256-GCM'],
  [34, 'SECURITY ONE WAF · SHIELD ACTIVE'],
  [50, 'FETCHING PROJECT MANIFEST', 'ready'],
  [66, 'THREE.JS RENDER PIPELINE WARM'],
  [82, 'AI CORE · V.AI NEURAL LINK ONLINE'],
  [99.9, 'ALL SYSTEMS NOMINAL'],
];

const visibleLog = computed(() => LOG
  .filter(([at]) => progress.value >= at)
  .slice(-6)
  .map(([at, text, waitFor]) => ({ key: at, text, ok: waitFor ? props.ready : true })));

const telemetry = ref([
  { k: 'CPU', v: 34 }, { k: 'GPU', v: 58 }, { k: 'MEM', v: 41 }, { k: 'NET', v: 72 },
]);

/* ---------- 文字解碼效果 ---------- */
let scrambleTimer = 0;
let currentPhase = -1;
function scrambleTo(text) {
  window.clearInterval(scrambleTimer);
  let frame = 0;
  const total = 14;
  scrambleTimer = window.setInterval(() => {
    frame += 1;
    const revealed = (text.length * frame) / total;
    phaseText.value = text
      .split('')
      .map((ch, i) => (i < revealed || ch === ' ' ? ch : GLYPHS[(Math.random() * GLYPHS.length) | 0]))
      .join('');
    if (frame >= total) {
      window.clearInterval(scrambleTimer);
      phaseText.value = text;
    }
  }, 30);
}

/* ---------- 粒子系統 ---------- */
const reduceMotion = typeof window !== 'undefined'
  && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

let ctx = null;
let W = 0;
let H = 0;
let cx = 0;
let cy = 0;
let R = 0;           // HUD 環半徑
let logo = { x: 0, y: 0, w: 0, h: 0 };
let N = 0;
let P = null;        // 粒子屬性（typed arrays）
let raf = 0;
let start = 0;
let last = 0;
let stageAt = 0;
let telemetryAt = 0;
let doneTimer = 0;
let safetyTimer = 0;

function allocParticles(count) {
  N = count;
  P = {
    a: new Float32Array(N),      // 漩渦角度
    r: new Float32Array(N),      // 漩渦半徑（相對 R）
    h: new Float32Array(N),      // 漩渦高度偏移
    s: new Float32Array(N),      // 角速度
    tx: new Float32Array(N),     // 目標（文字）座標
    ty: new Float32Array(N),
    has: new Uint8Array(N),      // 是否有文字目標
    thr: new Float32Array(N),    // 成形門檻（錯開時間）
    c: new Uint8Array(N),        // 色桶
    ph: new Float32Array(N),     // 閃爍相位
    x: new Float32Array(N),      // 目前位置（躍遷用）
    y: new Float32Array(N),
    v: new Float32Array(N),      // 躍遷速度
  };
  for (let i = 0; i < N; i += 1) {
    P.a[i] = Math.random() * Math.PI * 2;
    P.r[i] = 0.25 + Math.pow(Math.random(), 0.7) * 1.45;
    P.h[i] = (Math.random() - 0.5) * 0.22;
    P.s[i] = (0.35 + Math.random() * 0.9) * (Math.random() < 0.12 ? -1 : 1);
    P.thr[i] = Math.random() * 0.78;
    P.ph[i] = Math.random() * Math.PI * 2;
    P.c[i] = (Math.random() * 3) | 0;
  }
}

function layout() {
  const canvas = canvasEl.value;
  if (!canvas) return;
  W = window.innerWidth;
  H = window.innerHeight;
  const mobile = W < 768;
  const dpr = cappedPixelRatio(W, H);
  canvas.width = Math.round(W * dpr);
  canvas.height = Math.round(H * dpr);
  canvas.style.width = `${W}px`;
  canvas.style.height = `${H}px`;
  ctx = canvas.getContext('2d');
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

  cx = W / 2;
  cy = H * (mobile ? 0.42 : 0.44);
  const fs = Math.min(W * 0.14, H * 0.2, 150);

  if (!P) allocParticles(mobile ? 950 : 1900);

  // 把 "VITO1317" 畫到離屏 canvas，取樣出文字像素當粒子目標
  const off = document.createElement('canvas');
  off.width = Math.ceil(W);
  off.height = Math.ceil(H);
  const octx = off.getContext('2d', { willReadFrequently: true });
  octx.fillStyle = '#fff';
  octx.textAlign = 'center';
  octx.textBaseline = 'middle';
  octx.font = `800 ${fs}px ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace`;
  const text = 'VITO1317';
  const tw = octx.measureText(text).width;
  octx.fillText(text, cx, cy);
  logo = { x: cx - tw / 2, y: cy - fs * 0.42, w: tw, h: fs * 0.84 };
  R = Math.max(110, Math.min(tw * 0.58, H * 0.34));
  readoutTop.value = Math.min(H - 150, cy + Math.min(R, fs * 1.6) + (mobile ? 24 : 36));

  const step = fs > 90 ? 3 : 2;
  const pts = [];
  const x0 = Math.max(0, Math.floor(logo.x - 4));
  const y0 = Math.max(0, Math.floor(cy - fs * 0.6));
  const sw = Math.min(off.width - x0, Math.ceil(tw + 8));
  const sh = Math.min(off.height - y0, Math.ceil(fs * 1.2));
  const data = octx.getImageData(x0, y0, sw, sh).data;
  for (let y = 0; y < sh; y += step) {
    for (let x = 0; x < sw; x += step) {
      if (data[(y * sw + x) * 4 + 3] > 140) pts.push(x0 + x, y0 + y);
    }
  }

  // 洗牌後分配；約 82% 粒子組成文字，其餘留在外圍當星塵
  const count = pts.length / 2;
  const order = Array.from({ length: count }, (_, i) => i);
  for (let i = count - 1; i > 0; i -= 1) {
    const j = (Math.random() * (i + 1)) | 0;
    [order[i], order[j]] = [order[j], order[i]];
  }
  const useN = Math.min(count, Math.floor(N * 0.82));
  for (let i = 0; i < N; i += 1) {
    if (i < useN) {
      const k = order[i];
      P.tx[i] = pts[k * 2];
      P.ty[i] = pts[k * 2 + 1];
      P.has[i] = 1;
      const rel = (P.tx[i] - logo.x) / tw;
      P.c[i] = rel < 0.36 ? 0 : rel < 0.68 ? 1 : 2;
    } else {
      P.has[i] = 0;
    }
  }
}

const easeOut = (t) => 1 - Math.pow(1 - t, 3);
const clamp01 = (t) => (t < 0 ? 0 : t > 1 ? 1 : t);

function setStage(next, now) {
  stage.value = next;
  stageAt = now;
  if (next === 'warp') {
    for (let i = 0; i < N; i += 1) P.v[i] = 40 + Math.random() * 160;
  }
  if (next === 'out') {
    doneTimer = window.setTimeout(() => emit('done'), OUT_MS);
  }
}

function frame(now) {
  raf = requestAnimationFrame(frame);
  if (!start) { start = now; last = now; stageAt = now; }
  const dt = Math.min(0.05, (now - last) / 1000);
  last = now;
  const elapsed = now - start;

  /* ---- 進度：未就緒時緩慢逼近 92%，就緒後衝到 100%，並受最短展示時間限制 ---- */
  const target = props.ready ? 100 : 92;
  const cap = Math.min(100, (elapsed / MIN_MS) * 100);
  let p = progress.value;
  p += (target - p) * (1 - Math.exp(-(props.ready ? 6 : 0.9) * dt));
  p = Math.min(p, cap, target);
  if (props.ready && cap >= 100 && p > 99.4) p = 100;
  progress.value = p;

  const phaseIdx = PHASES.reduce((acc, [at], i) => (p >= at ? i : acc), 0);
  if (phaseIdx !== currentPhase) { currentPhase = phaseIdx; scrambleTo(PHASES[phaseIdx][1]); }

  /* ---- 時鐘 / 遙測 ---- */
  const d = new Date();
  clock.value = `${d.toTimeString().slice(0, 8)}.${String(d.getMilliseconds()).padStart(3, '0')}`;
  if (now - telemetryAt > 140) {
    telemetryAt = now;
    telemetry.value = telemetry.value.map((t) => ({
      k: t.k,
      v: Math.max(6, Math.min(98, t.v + (Math.random() - 0.48) * 18)),
    }));
  }

  /* ---- 階段推進 ---- */
  const inStage = now - stageAt;
  if (stage.value === 'boot' && elapsed > BOOT_MS) setStage('forming', now);
  else if (stage.value === 'forming' && p >= 100) setStage('granted', now);
  else if (stage.value === 'granted' && inStage > GRANTED_MS) setStage('warp', now);
  else if (stage.value === 'warp' && inStage > WARP_MS) setStage('out', now);
  else if (stage.value === 'out' && inStage > OUT_MS + 200) cancelAnimationFrame(raf);

  if (!ctx || reduceMotion) return;
  draw(now, elapsed, p, dt);
}

function draw(now, elapsed, p, dt) {
  const st = stage.value;
  const t = now / 1000;

  // 殘影：用 destination-out 淡掉上一幀，保持 canvas 透明（躍遷時才看得到底下的頁面）
  ctx.globalCompositeOperation = 'destination-out';
  ctx.globalAlpha = 1;
  ctx.fillStyle = st === 'warp' ? 'rgba(0,0,0,0.28)' : 'rgba(0,0,0,0.5)';
  ctx.fillRect(0, 0, W, H);
  ctx.globalCompositeOperation = 'lighter';

  const spread = easeOut(clamp01(elapsed / 1100));       // 開機時粒子從中心炸開
  const formP = st === 'boot' ? 0 : Math.min(1, (p / 100) * 1.06);
  const granted = st === 'granted';
  const flash = granted ? Math.max(0, 1 - (now - stageAt) / 420) : 0;
  const tilt = 0.3;

  if (st === 'warp' || st === 'out') {
    drawWarp(now, dt);
  } else {
    for (let c = 0; c < 3; c += 1) {
      ctx.fillStyle = COLORS[c];
      for (let i = 0; i < N; i += 1) {
        if (P.c[i] !== c) continue;
        let form = 0;
        if (P.has[i]) form = easeOut(clamp01((formP - P.thr[i]) / 0.22));

        P.a[i] += P.s[i] * dt * (1 - form * 0.95) * (P.has[i] ? 1 : 0.6);
        const rr = P.r[i] * R * spread;
        const ca = Math.cos(P.a[i]);
        const sa = Math.sin(P.a[i]);
        const vx = cx + ca * rr;
        const vy = cy + sa * rr * tilt + P.h[i] * R;
        const depth = (sa + 1) / 2; // 0 後 → 1 前

        let x = vx;
        let y = vy;
        if (form > 0) {
          const jx = Math.sin(t * 2.1 + P.ph[i]) * 0.7;
          const jy = Math.cos(t * 1.7 + P.ph[i]) * 0.7;
          x = vx + (P.tx[i] + jx - vx) * form;
          y = vy + (P.ty[i] + jy - vy) * form;
        }
        P.x[i] = x;
        P.y[i] = y;

        const size = form > 0.9 ? 1.6 + flash * 1.4 : 0.7 + depth * 1.5;
        let alpha = form > 0.9 ? 0.85 : 0.25 + depth * 0.6;
        if (!P.has[i]) alpha *= 0.55;
        alpha *= 0.75 + Math.sin(t * 5 + P.ph[i]) * 0.25;
        ctx.globalAlpha = Math.min(1, alpha + flash * 0.4);
        ctx.fillRect(x - size / 2, y - size / 2, size, size);
      }
    }

    drawRings(t, p, spread, flash, 1, 1);

    // 掃描光束：沿文字水平掃過
    if (st === 'forming' && formP > 0.15) {
      const sx = logo.x - 40 + ((t * 0.55) % 1) * (logo.w + 80);
      const g = ctx.createLinearGradient(sx - 40, 0, sx + 40, 0);
      g.addColorStop(0, 'rgba(42,230,255,0)');
      g.addColorStop(0.5, 'rgba(160,250,255,0.28)');
      g.addColorStop(1, 'rgba(42,230,255,0)');
      ctx.globalAlpha = 1;
      ctx.fillStyle = g;
      ctx.fillRect(sx - 40, logo.y - 18, 80, logo.h + 36);
    }

    if (flash > 0) {
      const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R * 1.4);
      g.addColorStop(0, `rgba(180,250,255,${0.35 * flash})`);
      g.addColorStop(1, 'rgba(42,230,255,0)');
      ctx.globalAlpha = 1;
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, W, H);
    }
  }
}

function drawRings(t, p, spread, flash, scale, alphaMul) {
  const r0 = R * spread * scale;
  if (r0 < 4) return;
  ctx.lineWidth = 1;

  // 外圈刻度（慢速旋轉）
  ctx.save();
  ctx.translate(cx, cy);
  ctx.scale(1, 0.92);
  ctx.rotate(t * 0.12);
  ctx.strokeStyle = COLORS[0];
  ctx.globalAlpha = 0.28 * alphaMul;
  ctx.beginPath();
  for (let k = 0; k < 120; k += 1) {
    const ang = (k / 120) * Math.PI * 2;
    const len = k % 10 === 0 ? 10 : 4;
    ctx.moveTo(Math.cos(ang) * r0, Math.sin(ang) * r0);
    ctx.lineTo(Math.cos(ang) * (r0 - len), Math.sin(ang) * (r0 - len));
  }
  ctx.stroke();
  ctx.restore();

  ctx.save();
  ctx.translate(cx, cy);
  ctx.scale(1, 0.92);

  // 進度弧
  ctx.lineWidth = 2;
  ctx.strokeStyle = COLORS[0];
  ctx.globalAlpha = (0.7 + flash * 0.3) * alphaMul;
  ctx.beginPath();
  ctx.arc(0, 0, r0 * 0.94, -Math.PI / 2, -Math.PI / 2 + (p / 100) * Math.PI * 2);
  ctx.stroke();

  // 反向旋轉的洋紅弧段
  ctx.lineWidth = 1.5;
  ctx.strokeStyle = COLORS[2];
  ctx.globalAlpha = 0.45 * alphaMul;
  for (let k = 0; k < 3; k += 1) {
    const base = -t * 0.6 + (k * Math.PI * 2) / 3;
    ctx.beginPath();
    ctx.arc(0, 0, r0 * 1.08, base, base + 0.7);
    ctx.stroke();
  }

  // 紫色虛線內圈
  ctx.setLineDash([2, 7]);
  ctx.strokeStyle = COLORS[1];
  ctx.globalAlpha = 0.4 * alphaMul;
  ctx.rotate(t * 0.35);
  ctx.beginPath();
  ctx.arc(0, 0, r0 * 0.82, 0, Math.PI * 2);
  ctx.stroke();
  ctx.setLineDash([]);
  ctx.restore();
}

function drawWarp(now, dt) {
  const k = clamp01((now - stageAt) / WARP_MS);
  const accel = 2600;
  ctx.lineWidth = 1.4;
  for (let c = 0; c < 3; c += 1) {
    ctx.strokeStyle = COLORS[c];
    ctx.globalAlpha = 0.9 * (1 - k * 0.6);
    ctx.beginPath();
    for (let i = 0; i < N; i += 1) {
      if (P.c[i] !== c) continue;
      let dx = P.x[i] - cx;
      let dy = P.y[i] - cy;
      const len = Math.hypot(dx, dy) || 1;
      dx /= len;
      dy /= len;
      P.v[i] += accel * dt * (0.6 + (len / R) * 0.5);
      const step = P.v[i] * dt;
      const tail = Math.min(220, P.v[i] * 0.07);
      P.x[i] += dx * step;
      P.y[i] += dy * step;
      ctx.moveTo(P.x[i] - dx * tail, P.y[i] - dy * tail);
      ctx.lineTo(P.x[i], P.y[i]);
    }
    ctx.stroke();
  }
  drawRings(now / 1000, 100, 1, 0, 1 + easeOut(k) * 2.2, 1 - k);
}

function onResize() {
  if (stage.value === 'warp' || stage.value === 'out') return;
  layout();
}

watch(() => props.ready, (ready) => {
  if (!ready) return;
  // 分頁在背景時 rAF 不會跑；保險起見，就緒後最多 MIN_MS + 4s 一定結束
  window.clearTimeout(safetyTimer);
  safetyTimer = window.setTimeout(() => emit('done'), MIN_MS + 4000);
}, { immediate: true });

onMounted(() => {
  layout();
  window.addEventListener('resize', onResize);
  raf = requestAnimationFrame(frame);
});

onUnmounted(() => {
  cancelAnimationFrame(raf);
  window.removeEventListener('resize', onResize);
  window.clearInterval(scrambleTimer);
  window.clearTimeout(doneTimer);
  window.clearTimeout(safetyTimer);
});
</script>

<style scoped>
.boot {
  --c: #2ae6ff; --m: #ff3ac8; --v: #8b7bff;
  position: fixed; inset: 0; z-index: 9999; overflow: hidden; color: #eafaff; isolation: isolate;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  transition: opacity .36s ease;
}
.boot--out { opacity: 0; pointer-events: none; }

.boot__bg {
  position: absolute; inset: 0; z-index: -1;
  background: radial-gradient(ellipse at 50% 44%, #0e1a34 0%, #060915 42%, #020309 100%);
  transition: opacity .7s cubic-bezier(.6, 0, .4, 1);
}
.boot--warp .boot__bg, .boot--out .boot__bg { opacity: 0; }

.boot__screen { position: absolute; inset: 0; transform-origin: 50% 44%; animation: crt-on .62s cubic-bezier(.2, .8, .2, 1) both; }
.boot__canvas { position: absolute; inset: 0; display: block; }

.boot__grid {
  position: absolute; left: -30%; right: -30%; bottom: -10%; height: 55%;
  background-image: linear-gradient(rgba(42, 230, 255, .14) 1px, transparent 1px), linear-gradient(90deg, rgba(42, 230, 255, .14) 1px, transparent 1px);
  background-size: 48px 48px;
  transform: perspective(420px) rotateX(70deg); transform-origin: 50% 0;
  mask-image: linear-gradient(to bottom, transparent, #000 35%, transparent 95%);
  -webkit-mask-image: linear-gradient(to bottom, transparent, #000 35%, transparent 95%);
  animation: grid-run 1.6s linear infinite;
  opacity: .55; transition: opacity .4s;
}
.boot__scanlines { position: absolute; inset: 0; pointer-events: none; background: repeating-linear-gradient(to bottom, rgba(255, 255, 255, .025) 0 1px, transparent 1px 3px); }
.boot__vignette { position: absolute; inset: 0; pointer-events: none; background: radial-gradient(ellipse at center, transparent 55%, rgba(0, 0, 0, .65) 100%); transition: opacity .5s; }
.boot--warp .boot__grid, .boot--warp .boot__scanlines, .boot--warp .boot__vignette,
.boot--out .boot__grid, .boot--out .boot__scanlines, .boot--out .boot__vignette { opacity: 0; }

/* ---------- HUD ---------- */
.boot__hud { position: absolute; inset: 0; font-size: 9px; letter-spacing: .18em; transition: opacity .35s, transform .6s cubic-bezier(.6, 0, .4, 1), filter .5s; }
.boot--warp .boot__hud, .boot--out .boot__hud { opacity: 0; transform: scale(1.12); filter: blur(4px); }
.boot__corner { position: absolute; width: 30px; height: 30px; border: 1px solid var(--c); filter: drop-shadow(0 0 4px var(--c)); animation: corner-in .7s .35s cubic-bezier(.16, 1, .3, 1) both; }
.boot__corner--tl { top: 20px; left: 20px; border-right: 0; border-bottom: 0; }
.boot__corner--tr { top: 20px; right: 20px; border-left: 0; border-bottom: 0; }
.boot__corner--bl { bottom: 20px; left: 20px; border-right: 0; border-top: 0; }
.boot__corner--br { bottom: 20px; right: 20px; border-left: 0; border-top: 0; }

.boot__tl, .boot__tr { position: absolute; top: 34px; display: grid; gap: 6px; animation: fade-up .6s .45s both; }
.boot__tl { left: 40px; }
.boot__tr { right: 40px; text-align: right; }
.boot__brand { color: #9ff2ff; font-size: 10px; font-weight: 700; text-shadow: 0 0 10px rgba(42, 230, 255, .6); }
.boot__brand i { color: var(--m); font-style: normal; }
.boot__dim { color: #4f6380; }
.boot__clock { color: #cfe9ff; font-size: 12px; letter-spacing: .12em; font-variant-numeric: tabular-nums; }

.boot__log { position: absolute; left: 40px; bottom: 36px; display: grid; gap: 6px; min-width: 300px; }
.boot__log p { color: #6f86a3; animation: log-in .3s cubic-bezier(.16, 1, .3, 1) both; white-space: nowrap; }
.boot__log b { font-weight: 700; }
.boot__log p.ok b { color: #3dffa8; text-shadow: 0 0 8px rgba(61, 255, 168, .6); }
.boot__log p.pending b { color: #ffc24a; animation: blink .6s steps(2) infinite; }
.boot__log p:last-child { color: #d9f6ff; }

.boot__telemetry { position: absolute; right: 40px; bottom: 36px; display: grid; gap: 8px; width: 190px; animation: fade-up .6s .55s both; }
.boot__meter { display: grid; grid-template-columns: 34px 1fr 22px; align-items: center; gap: 8px; color: #5b7090; }
.boot__meter i { position: relative; height: 3px; background: rgba(120, 170, 210, .14); overflow: hidden; }
.boot__meter em { position: absolute; inset: 0 auto 0 0; background: linear-gradient(90deg, var(--c), var(--v)); box-shadow: 0 0 8px var(--c); transition: width .14s linear; }
.boot__meter b { color: #9ff2ff; text-align: right; font-variant-numeric: tabular-nums; }

/* ---------- readout ---------- */
.boot__readout {
  position: absolute; left: 50%; width: min(86vw, 440px); transform: translateX(-50%); text-align: center;
  animation: fade-up .6s .3s both; transition: opacity .35s, transform .6s cubic-bezier(.6, 0, .4, 1), filter .5s;
}
.boot--warp .boot__readout, .boot--out .boot__readout { opacity: 0; transform: translateX(-50%) scale(1.25); filter: blur(6px); transition-delay: .25s; }
.boot__phase, .boot__granted { height: 22px; font-size: 12px; letter-spacing: .32em; }
.boot__phase { color: #d6e6f8; }
.boot__granted { position: relative; color: #3dffa8; font-weight: 800; text-shadow: 0 0 14px rgba(61, 255, 168, .8); animation: granted-in .5s cubic-bezier(.16, 1, .3, 1) both; }
.boot__granted::before, .boot__granted::after { content: attr(data-text); position: absolute; inset: 0; opacity: .8; }
.boot__granted::before { color: var(--c); animation: glitch-a .5s steps(2) both; }
.boot__granted::after { color: var(--m); animation: glitch-b .5s steps(2) both; }

.boot__bar { display: grid; grid-template-columns: repeat(36, 1fr); gap: 3px; margin-top: 16px; }
.boot__bar span { height: 6px; background: rgba(110, 170, 210, .12); transform: skewX(-20deg); transition: background .2s, box-shadow .2s; }
.boot__bar span.on { background: linear-gradient(180deg, #9ff2ff, var(--c)); box-shadow: 0 0 6px rgba(42, 230, 255, .7); }
.boot__bar span.on:nth-child(n+13) { background: linear-gradient(180deg, #c3baff, var(--v)); box-shadow: 0 0 6px rgba(139, 123, 255, .7); }
.boot__bar span.on:nth-child(n+25) { background: linear-gradient(180deg, #ffb3ea, var(--m)); box-shadow: 0 0 6px rgba(255, 58, 200, .7); }
.boot__bar span.head { background: #fff !important; box-shadow: 0 0 12px #fff !important; }
.boot--granted .boot__bar span.on { animation: bar-flash .4s ease-out both; }
.boot__nums { display: flex; justify-content: space-between; align-items: baseline; margin-top: 10px; color: #5b7090; font-size: 9px; letter-spacing: .2em; }
.boot__nums b { color: #eafaff; font-size: 22px; font-weight: 700; letter-spacing: .04em; font-variant-numeric: tabular-nums; text-shadow: 0 0 14px rgba(42, 230, 255, .6); }
.boot__nums small { margin-left: 2px; color: var(--c); font-size: 11px; }

/* ---------- keyframes ---------- */
@keyframes crt-on {
  0% { transform: scale(0, .003); filter: brightness(5); }
  35% { transform: scale(1, .003); filter: brightness(4); }
  70% { transform: scale(1, 1); filter: brightness(1.6); }
  100% { transform: scale(1, 1); filter: none; }
}
@keyframes grid-run { to { background-position: 0 48px; } }
@keyframes corner-in { from { opacity: 0; transform: scale(1.8); } }
@keyframes fade-up { from { opacity: 0; transform: translateY(8px); } }
@keyframes log-in { from { opacity: 0; transform: translateX(-10px); clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }
@keyframes blink { 50% { opacity: .2; } }
@keyframes granted-in { from { opacity: 0; letter-spacing: .9em; } }
@keyframes glitch-a { 0% { transform: translate(-6px, -2px); clip-path: inset(0 0 55% 0); } 50% { transform: translate(4px, 1px); clip-path: inset(40% 0 20% 0); } 100% { transform: none; opacity: 0; } }
@keyframes glitch-b { 0% { transform: translate(6px, 2px); clip-path: inset(50% 0 0 0); } 50% { transform: translate(-3px, -1px); clip-path: inset(10% 0 60% 0); } 100% { transform: none; opacity: 0; } }
@keyframes bar-flash { 0% { filter: brightness(2.4); } 100% { filter: none; } }

@media (max-width: 767px) {
  .boot__screen { animation-name: crt-on-lite; }
  .boot__tl { left: 28px; top: 28px; }
  .boot__tr { right: 28px; top: 28px; }
  .boot__tr .boot__dim, .boot__telemetry { display: none; }
  .boot__log { left: 28px; bottom: 28px; min-width: 0; font-size: 8px; }
  .boot__corner { width: 20px; height: 20px; }
  .boot__corner--tl { top: 12px; left: 12px; }
  .boot__corner--tr { top: 12px; right: 12px; }
  .boot__corner--bl { bottom: 12px; left: 12px; }
  .boot__corner--br { bottom: 12px; right: 12px; }
  .boot__phase, .boot__granted { font-size: 10px; letter-spacing: .24em; }
}
/* 手機省去全螢幕 filter，避免開機瞬間掉幀 */
@keyframes crt-on-lite {
  0% { transform: scale(0, .003); }
  35% { transform: scale(1, .003); }
  70%, 100% { transform: scale(1, 1); }
}

@media (prefers-reduced-motion: reduce) {
  .boot *, .boot *::before, .boot *::after { animation: none !important; transition-duration: .2s !important; }
}
</style>
