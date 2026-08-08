<template>
  <div class="tech-hud pointer-events-none fixed inset-0 z-[60]">
    <div class="scanlines"></div>
    <div class="vignette"></div>

    <svg class="hud-bracket top-left" viewBox="0 0 60 60" fill="none">
      <path d="M2 20 L2 2 L20 2" stroke="#22e0ff" stroke-width="1.5" stroke-linecap="round" />
      <circle cx="6" cy="6" r="2" fill="#22e0ff" />
    </svg>
    <svg class="hud-bracket top-right" viewBox="0 0 60 60" fill="none">
      <path d="M40 2 L58 2 L58 20" stroke="#ff2bd6" stroke-width="1.5" stroke-linecap="round" />
      <circle cx="54" cy="6" r="2" fill="#ff2bd6" />
    </svg>
    <svg class="hud-bracket bottom-left" viewBox="0 0 60 60" fill="none">
      <path d="M2 40 L2 58 L20 58" stroke="#ff2bd6" stroke-width="1.5" stroke-linecap="round" />
      <circle cx="6" cy="54" r="2" fill="#ff2bd6" />
    </svg>
    <svg class="hud-bracket bottom-right" viewBox="0 0 60 60" fill="none">
      <path d="M40 58 L58 58 L58 40" stroke="#22e0ff" stroke-width="1.5" stroke-linecap="round" />
      <circle cx="54" cy="54" r="2" fill="#22e0ff" />
    </svg>

    <div class="hud-status hud-top">
      <span class="hud-dot"></span>
      <span class="hud-label">SYS · ONLINE</span>
      <span class="hud-sep">//</span>
      <span class="hud-label hud-cyan">SEC-ONE</span>
      <span class="hud-sep">//</span>
      <span class="hud-label">{{ coords }}</span>
    </div>

    <div class="hud-status hud-bottom">
      <span class="hud-label">vito1317.com</span>
      <span class="hud-sep">//</span>
      <span class="hud-label hud-magenta">TW · TPE</span>
      <span class="hud-sep">//</span>
      <span class="hud-label">{{ clock }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const clock = ref('--:--:--');
const coords = ref('25.06°N · 121.64°E');
let timer;

const tick = () => {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  clock.value = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())} UTC+8`;
};

onMounted(() => {
  tick();
  timer = setInterval(tick, 1000);
});

onUnmounted(() => {
  clearInterval(timer);
});
</script>

<style scoped>
.tech-hud {
  font-family: 'Fira Code', ui-monospace, SFMono-Regular, Menlo, monospace;
  overflow: hidden;
}

/* 掃描線用 transform 位移（合成器處理），
   而非 background-position（每幀重繪整個視口） */
.scanlines {
  position: absolute;
  left: 0;
  right: 0;
  top: -60px;
  height: calc(100% + 60px);
  background: repeating-linear-gradient(
    to bottom,
    rgba(34, 224, 255, 0.03) 0px,
    rgba(34, 224, 255, 0.03) 1px,
    transparent 1px,
    transparent 3px
  );
  mix-blend-mode: screen;
  opacity: 0.55;
  animation: scanlineShift 8s linear infinite;
  will-change: transform;
}

@keyframes scanlineShift {
  0% { transform: translateY(0); }
  100% { transform: translateY(60px); }
}

.vignette {
  position: absolute;
  inset: 0;
  background: radial-gradient(
    ellipse at center,
    transparent 40%,
    rgba(5, 6, 15, 0.55) 100%
  );
}

.hud-bracket {
  position: absolute;
  width: 60px;
  height: 60px;
  filter: drop-shadow(0 0 4px currentColor);
  animation: hudPulse 3.2s ease-in-out infinite;
}
.top-left    { top: 12px; left: 12px; }
.top-right   { top: 12px; right: 12px; }
.bottom-left { bottom: 12px; left: 12px; }
.bottom-right{ bottom: 12px; right: 12px; }

@keyframes hudPulse {
  0%, 100% { opacity: 0.75; }
  50%      { opacity: 1; }
}

.hud-status {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #8ad9ff;
  text-shadow: 0 0 6px rgba(34, 224, 255, 0.4);
  background: rgba(5, 6, 15, 0.35);
  padding: 6px 16px;
  border: 1px solid rgba(34, 224, 255, 0.25);
  border-radius: 999px;
  backdrop-filter: blur(4px);
}
.hud-top    { top: 16px; }
.hud-bottom { bottom: 16px; }

.hud-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #5effa7;
  box-shadow: 0 0 8px #5effa7;
  animation: hudBlink 1.4s ease-in-out infinite;
}
@keyframes hudBlink {
  0%, 100% { opacity: 1; }
  50%      { opacity: 0.3; }
}

.hud-sep { color: rgba(138, 217, 255, 0.4); }
.hud-cyan    { color: #22e0ff; }
.hud-magenta { color: #ff2bd6; text-shadow: 0 0 6px rgba(255, 43, 214, 0.4); }

@media (max-width: 768px) {
  .hud-bracket { width: 36px; height: 36px; }
  /* 手機版空間有限：狀態列與時鐘膠囊都會蓋到內容（如 CodeField 的
     計數器列），整組隱藏 */
  .hud-top { display: none; }
  .hud-bottom { display: none; }
}
@media (max-width: 480px) {
  .hud-bracket.top-left, .hud-bracket.top-right { display: none; }
}
</style>
