<template>
  <div class="fixed inset-0 pointer-events-none tech-bg" aria-hidden="true">
    <!-- 靜態層：深空漸層 + 微點陣格線 + 緩慢漂移的環境光暈（純 CSS，零 JS 成本） -->
    <div class="tech-bg__glow tech-bg__glow--cyan"></div>
    <div class="tech-bg__glow tech-bg__glow--violet"></div>
    <div class="tech-bg__grid"></div>

    <!-- 動態層：漂移粒子 + 資料流光 + warp 曲速（Canvas 2D） -->
    <canvas ref="canvasRef" class="absolute inset-0 w-full h-full"></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useUiStore } from '../stores/uiStore';

const canvasRef = ref(null);
const uiStore = useUiStore();
const { isWarping } = storeToRefs(uiStore);

const PALETTE = [
  [34, 224, 255], // cyan
  [123, 91, 255], // violet
  [94, 255, 167], // mint
  [255, 255, 255], // white
];

let ctx = null;
let rafId = null;
let running = false;
let width = 0;
let height = 0;
let dpr = 1;
let particles = [];
let streaks = [];
let nextStreakAt = 0;
let warpEnergy = 0; // 0 → 平常漂移，1 → 全速曲速
let scrollY = 0;
let mouseX = 0;
let mouseTargetX = 0;
let lastTime = 0;

const reducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const makeParticles = () => {
  const count = width < 768 ? 60 : 130;
  particles = Array.from({ length: count }, () => {
    const depth = 0.25 + Math.random() * 0.75; // 越大越近、視差越強
    const [r, g, b] = PALETTE[(Math.random() * PALETTE.length) | 0];
    return {
      x: Math.random() * width,
      y: Math.random() * height,
      depth,
      size: (0.5 + Math.random() * 1.1) * depth,
      color: `${r}, ${g}, ${b}`,
      baseAlpha: 0.25 + Math.random() * 0.5,
      twinkle: Math.random() * Math.PI * 2,
      vx: (Math.random() - 0.5) * 4,
      vy: -(2 + Math.random() * 6),
    };
  });
};

const spawnStreak = () => {
  const horizontal = Math.random() < 0.7;
  const [r, g, b] = PALETTE[(Math.random() * 2) | 0]; // 只用 cyan / violet
  streaks.push({
    x: horizontal ? -160 : Math.random() * width,
    y: horizontal ? Math.random() * height * 0.9 : -160,
    angle: horizontal ? 0 : Math.PI / 2.6,
    speed: 900 + Math.random() * 600,
    len: 110 + Math.random() * 90,
    color: `${r}, ${g}, ${b}`,
    life: 0,
  });
};

const draw = (now) => {
  rafId = requestAnimationFrame(draw);
  const delta = Math.min((now - lastTime) / 1000, 0.05);
  lastTime = now;

  // warp 能量緩升緩降（route 切換時 uiStore.isWarping = true 800ms）
  const targetEnergy = isWarping.value ? 1 : 0;
  warpEnergy += (targetEnergy - warpEnergy) * (targetEnergy ? 0.12 : 0.06);

  mouseX += (mouseTargetX - mouseX) * 0.05;

  ctx.clearRect(0, 0, width, height);

  // 粒子
  for (const p of particles) {
    p.twinkle += delta * 2;
    p.x += p.vx * delta;
    p.y += p.vy * (1 + warpEnergy * 40) * delta;

    if (p.y < -20) { p.y = height + 10; p.x = Math.random() * width; }
    if (p.x < -20) p.x = width + 10;
    if (p.x > width + 20) p.x = -10;

    const px = p.x + mouseX * 14 * p.depth;
    const py = p.y - scrollY * 0.06 * p.depth;
    const wrappedY = ((py % (height + 40)) + height + 40) % (height + 40) - 20;
    const alpha = p.baseAlpha * (0.7 + Math.sin(p.twinkle) * 0.3);

    if (warpEnergy > 0.05) {
      // 曲速：粒子拉成垂直光痕
      const trail = 6 + warpEnergy * 90 * p.depth;
      const grad = ctx.createLinearGradient(px, wrappedY - trail, px, wrappedY);
      grad.addColorStop(0, `rgba(${p.color}, 0)`);
      grad.addColorStop(1, `rgba(${p.color}, ${alpha})`);
      ctx.strokeStyle = grad;
      ctx.lineWidth = p.size;
      ctx.beginPath();
      ctx.moveTo(px, wrappedY - trail);
      ctx.lineTo(px, wrappedY);
      ctx.stroke();
    } else {
      ctx.fillStyle = `rgba(${p.color}, ${alpha})`;
      ctx.beginPath();
      ctx.arc(px, wrappedY, p.size, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  // 資料流光
  if (now >= nextStreakAt && streaks.length < 2 && warpEnergy < 0.05) {
    spawnStreak();
    nextStreakAt = now + 2600 + Math.random() * 3200;
  }
  streaks = streaks.filter((s) => {
    s.life += delta;
    s.x += Math.cos(s.angle) * s.speed * delta;
    s.y += Math.sin(s.angle) * s.speed * delta;
    if (s.x > width + 200 || s.y > height + 200) return false;

    const tailX = s.x - Math.cos(s.angle) * s.len;
    const tailY = s.y - Math.sin(s.angle) * s.len;
    const grad = ctx.createLinearGradient(tailX, tailY, s.x, s.y);
    grad.addColorStop(0, `rgba(${s.color}, 0)`);
    grad.addColorStop(0.85, `rgba(${s.color}, 0.55)`);
    grad.addColorStop(1, 'rgba(255, 255, 255, 0.9)');
    ctx.strokeStyle = grad;
    ctx.lineWidth = 1.2;
    ctx.beginPath();
    ctx.moveTo(tailX, tailY);
    ctx.lineTo(s.x, s.y);
    ctx.stroke();
    return true;
  });
};

const drawStaticFrame = () => {
  // reduced-motion：只畫一張靜態星空
  ctx.clearRect(0, 0, width, height);
  for (const p of particles) {
    ctx.fillStyle = `rgba(${p.color}, ${p.baseAlpha})`;
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
    ctx.fill();
  }
};

const start = () => {
  if (running || reducedMotion()) return;
  running = true;
  lastTime = performance.now();
  rafId = requestAnimationFrame(draw);
};

const stop = () => {
  running = false;
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
};

const resize = () => {
  const canvas = canvasRef.value;
  width = window.innerWidth;
  height = window.innerHeight;
  dpr = Math.min(window.devicePixelRatio || 1, 1.5);
  canvas.width = width * dpr;
  canvas.height = height * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  makeParticles();
  if (reducedMotion()) drawStaticFrame();
};

const onScroll = () => { scrollY = window.scrollY; };
const onMouseMove = (e) => { mouseTargetX = (e.clientX / width) * 2 - 1; };
const onVisibility = () => { document.hidden ? stop() : start(); };

onMounted(() => {
  ctx = canvasRef.value.getContext('2d');
  resize();
  window.addEventListener('resize', resize);
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('mousemove', onMouseMove, { passive: true });
  document.addEventListener('visibilitychange', onVisibility);
  start();
});

onUnmounted(() => {
  stop();
  window.removeEventListener('resize', resize);
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('mousemove', onMouseMove);
  document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<style scoped>
.tech-bg {
  z-index: 0;
  background:
    radial-gradient(ellipse 120% 60% at 50% -10%, #101d38 0%, transparent 60%),
    radial-gradient(ellipse 100% 70% at 80% 110%, #140f2e 0%, transparent 55%),
    #05060f;
  overflow: hidden;
}

/* 微點陣：純 CSS，中央淡出避免干擾閱讀 */
.tech-bg__grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(94, 234, 255, 0.14) 1px, transparent 1px);
  background-size: 30px 30px;
  mask-image: radial-gradient(ellipse at center, transparent 25%, black 80%);
  -webkit-mask-image: radial-gradient(ellipse at center, transparent 25%, black 80%);
}

/* 環境光暈：緩慢漂移 */
.tech-bg__glow {
  position: absolute;
  width: 55vw;
  height: 55vw;
  max-width: 820px;
  max-height: 820px;
  border-radius: 9999px;
  filter: blur(90px);
  opacity: 0.16;
  will-change: transform;
}
.tech-bg__glow--cyan {
  top: -18%;
  left: -12%;
  background: radial-gradient(circle, #22e0ff 0%, transparent 70%);
  animation: glow-drift-a 26s ease-in-out infinite alternate;
}
.tech-bg__glow--violet {
  bottom: -22%;
  right: -14%;
  background: radial-gradient(circle, #7b5bff 0%, transparent 70%);
  animation: glow-drift-b 32s ease-in-out infinite alternate;
}
@keyframes glow-drift-a {
  from { transform: translate(0, 0) scale(1); }
  to   { transform: translate(8vw, 6vh) scale(1.15); }
}
@keyframes glow-drift-b {
  from { transform: translate(0, 0) scale(1.1); }
  to   { transform: translate(-7vw, -8vh) scale(0.95); }
}

@media (prefers-reduced-motion: reduce) {
  .tech-bg__glow { animation: none; }
}
</style>
