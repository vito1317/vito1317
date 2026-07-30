<template>
  <div class="boot-screen" role="status" aria-live="polite" aria-label="系統載入中">
    <div class="boot-screen__grid"></div>
    <div class="boot-screen__scan"></div>
    <div class="boot-screen__corner boot-screen__corner--tl"></div>
    <div class="boot-screen__corner boot-screen__corner--tr"></div>
    <div class="boot-screen__corner boot-screen__corner--bl"></div>
    <div class="boot-screen__corner boot-screen__corner--br"></div>

    <div class="boot-screen__content">
      <p class="boot-screen__eyebrow">VITO1317 // SYSTEM BOOT</p>
      <div class="boot-screen__core" aria-hidden="true">
        <span class="boot-screen__orbit boot-screen__orbit--a"></span>
        <span class="boot-screen__orbit boot-screen__orbit--b"></span>
        <span class="boot-screen__orbit boot-screen__orbit--c"></span>
        <span class="boot-screen__pulse"></span>
        <span class="boot-screen__kernel">V</span>
      </div>
      <p class="boot-screen__phase">{{ phase }}</p>

      <div class="boot-screen__progress" aria-hidden="true">
        <span :style="{ width: `${progress}%` }"></span>
      </div>
      <div class="boot-screen__metrics">
        <span>INITIALIZING NEURAL INTERFACE</span>
        <b>{{ String(Math.round(progress)).padStart(2, '0') }}%</b>
      </div>
    </div>

    <div class="boot-screen__log" aria-hidden="true">
      <span v-for="(line, index) in logLines" :key="line" :class="{ active: index === activeLog }">{{ line }}</span>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const progress = ref(8);
const activeLog = ref(0);
const logLines = ['[ OK ] CORE_LINK_ESTABLISHED', '[ OK ] ENCRYPTION_LAYER_READY', '[ .. ] LOADING_PORTFOLIO_DATA', '[ .. ] CALIBRATING_VISUAL_FIELD'];
const phases = ['SYNCHRONIZING SIGNAL', 'MAPPING INTERFACE', 'VERIFYING DATA STREAM', 'PREPARING EXPERIENCE'];
const phase = ref(phases[0]);
let progressTimer;
let logTimer;

onMounted(() => {
  progressTimer = window.setInterval(() => {
    if (progress.value < 91) progress.value += Math.max(0.35, (92 - progress.value) * 0.055);
  }, 70);
  logTimer = window.setInterval(() => {
    activeLog.value = (activeLog.value + 1) % logLines.length;
    phase.value = phases[activeLog.value];
  }, 680);
});

onUnmounted(() => {
  window.clearInterval(progressTimer);
  window.clearInterval(logTimer);
});
</script>

<style scoped>
.boot-screen { position: fixed; inset: 0; z-index: 9999; display: grid; place-items: center; overflow: hidden; background: radial-gradient(circle at 50% 49%, #111e38 0%, #070a16 38%, #03040b 100%); color: #eafaff; isolation: isolate; }
.boot-screen__grid { position: absolute; inset: -30%; background-image: linear-gradient(rgba(51, 225, 255, .09) 1px, transparent 1px), linear-gradient(90deg, rgba(51, 225, 255, .09) 1px, transparent 1px); background-size: 42px 42px; transform: perspective(500px) rotateX(67deg) translateY(28%); transform-origin: center; mask-image: linear-gradient(to bottom, transparent 8%, black 45%, transparent 88%); }
.boot-screen__scan { position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 46%, rgba(50, 230, 255, .15) 50%, transparent 54%); animation: boot-scan 2.8s linear infinite; mix-blend-mode: screen; }
.boot-screen__content { position: relative; z-index: 2; width: min(88vw, 410px); text-align: center; }.boot-screen__eyebrow,.boot-screen__phase,.boot-screen__metrics,.boot-screen__log { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .16em; }.boot-screen__eyebrow { margin-bottom: 28px; color: #78eaff; font-size: 10px; }.boot-screen__core { position: relative; width: 170px; height: 170px; margin: 0 auto 24px; perspective: 700px; transform-style: preserve-3d; }.boot-screen__orbit { position: absolute; inset: 10px; border: 1px solid #34e6ff; border-radius: 50%; box-shadow: 0 0 18px rgba(52,230,255,.45), inset 0 0 15px rgba(52,230,255,.18); }.boot-screen__orbit--a { transform: rotateX(68deg); animation: orbit-a 2.8s linear infinite; }.boot-screen__orbit--b { inset: 24px; border-color: #ff3ac8; transform: rotateY(70deg); animation: orbit-b 2.3s linear infinite reverse; }.boot-screen__orbit--c { inset: 38px; border-style: dashed; border-color: #b598ff; transform: rotateX(34deg) rotateY(44deg); animation: orbit-c 3.6s linear infinite; }.boot-screen__pulse { position: absolute; inset: 56px; border-radius: 50%; background: #2ae6ff; filter: blur(17px); opacity: .5; animation: kernel-pulse 1.5s ease-in-out infinite; }.boot-screen__kernel { position: absolute; inset: 58px; display: grid; place-items: center; border: 1px solid #dfffff; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #dcffff, #25c8e5 47%, #2435a5); box-shadow: 0 0 30px #2fe8ff, inset 0 0 13px #fff; color: #04101b; font: 900 31px/1 ui-monospace, monospace; animation: kernel-turn 1.5s ease-in-out infinite; }.boot-screen__phase { height: 20px; color: #d7e1f0; font-size: 11px; }.boot-screen__progress { height: 3px; margin-top: 20px; overflow: hidden; background: rgba(111,178,202,.2); }.boot-screen__progress span { display: block; height: 100%; background: linear-gradient(90deg, #2ae6ff, #8b7bff, #ff3ac8); box-shadow: 0 0 15px #2ae6ff; transition: width .18s ease-out; }.boot-screen__metrics { display: flex; justify-content: space-between; margin-top: 9px; color: #788aa4; font-size: 8px; text-align: left; }.boot-screen__metrics b { color: #7aebff; font-size: 11px; }.boot-screen__log { position: absolute; z-index: 2; left: clamp(18px, 5vw, 70px); bottom: 32px; display: grid; gap: 7px; color: #516175; font-size: 8px; text-align: left; }.boot-screen__log span { transition: color .25s ease; }.boot-screen__log span.active { color: #77edff; text-shadow: 0 0 9px #2ae6ff; }.boot-screen__corner { position: absolute; z-index: 2; width: 34px; height: 34px; border: 1px solid #2ae6ff; filter: drop-shadow(0 0 4px #2ae6ff); }.boot-screen__corner--tl { top: 22px; left: 22px; border-right: 0; border-bottom: 0; }.boot-screen__corner--tr { top: 22px; right: 22px; border-left: 0; border-bottom: 0; }.boot-screen__corner--bl { bottom: 22px; left: 22px; border-right: 0; border-top: 0; }.boot-screen__corner--br { right: 22px; bottom: 22px; border-left: 0; border-top: 0; }
@keyframes boot-scan { from { transform: translateY(-100%); } to { transform: translateY(100%); } } @keyframes orbit-a { to { transform: rotateX(68deg) rotateZ(360deg); } } @keyframes orbit-b { to { transform: rotateY(70deg) rotateZ(360deg); } } @keyframes orbit-c { to { transform: rotateX(34deg) rotateY(44deg) rotateZ(360deg); } } @keyframes kernel-pulse { 50% { transform: scale(1.35); opacity: .8; } } @keyframes kernel-turn { 50% { transform: scale(.86) rotate(180deg); } }
@media (max-width: 640px) { .boot-screen__core { transform: scale(.82); margin-bottom: 8px; }.boot-screen__eyebrow { margin-bottom: 16px; }.boot-screen__log { left: 20px; bottom: 18px; font-size: 7px; }.boot-screen__corner { width: 22px; height: 22px; }.boot-screen__corner--tl { top: 14px; left: 14px; }.boot-screen__corner--tr { top: 14px; right: 14px; }.boot-screen__corner--bl { bottom: 14px; left: 14px; }.boot-screen__corner--br { right: 14px; bottom: 14px; } }
@media (prefers-reduced-motion: reduce) { .boot-screen *, .boot-screen::before, .boot-screen::after { animation: none !important; } }
</style>
