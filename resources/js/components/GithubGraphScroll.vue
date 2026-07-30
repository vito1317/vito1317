<template>
  <section ref="sectionRef" class="github-graph-scroll" :style="{ height: `${entries.length * 112}vh` }">
    <div class="github-graph-scroll__sticky" :style="{ '--git': active.color }">
      <div class="github-graph-scroll__terminal-lines"></div>
      <div class="github-graph-scroll__graph" :style="graphStyle" aria-hidden="true">
        <span class="github-graph-scroll__branch github-graph-scroll__branch--main"></span>
        <span class="github-graph-scroll__branch github-graph-scroll__branch--fork"></span>
        <span class="github-graph-scroll__branch github-graph-scroll__branch--merge"></span>
        <i v-for="node in 11" :key="node" class="github-graph-scroll__commit" :class="{ current: node === activeIndex * 3 + 2 }">{{ node.toString(16).toUpperCase() }}</i>
        <div class="github-graph-scroll__repo-cube">
          <span v-for="face in 6" :key="face"></span>
          <b>&lt;/&gt;</b>
        </div>
      </div>

      <div class="github-graph-scroll__copy container mx-auto px-6 sm:px-10">
        <p class="github-graph-scroll__eyebrow">GITHUB / LIVE REPOSITORY GRAPH</p>
        <Transition name="git-copy" mode="out-in">
          <article :key="active.id">
            <p class="github-graph-scroll__step">{{ active.step }}</p>
            <h2>{{ active.name }}</h2>
            <p class="github-graph-scroll__description">{{ active.description }}</p>
            <div class="github-graph-scroll__meta"><span>{{ active.language }}</span><span>{{ active.commits }} COMMITS</span><span>HEAD → {{ active.hash }}</span></div>
          </article>
        </Transition>
      </div>

      <div class="github-graph-scroll__refs">
        <span>refs/heads/main</span><b>{{ (progress * 100).toFixed(0).padStart(2, '0') }}%</b>
      </div>
      <div class="github-graph-scroll__pager"><i v-for="(_, index) in entries" :key="index" :class="{ active: activeIndex === index }"></i></div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({ repos: { type: Array, default: () => [] } });
const fallback = [
  { id: 'build', name: 'Build the system.', language: 'ARCHITECTURE', commits: '01', description: '從一個空白 repository 開始，把可驗證的結構、工具與測試建立成可持續演進的程式基底。', color: '#34d399' },
  { id: 'iterate', name: 'Commit the idea.', language: 'ITERATION', commits: '02', description: '每個 commit 都保留思考的軌跡：實驗、修正與可重現的演算法設計逐步匯聚。', color: '#2dd4bf' },
  { id: 'ship', name: 'Ship the impact.', language: 'DELIVERY', commits: '03', description: '讓原創程式碼離開本機，進入真實使用情境；從研究成果推進成可部署的產品。', color: '#60a5fa' },
];
const sectionRef = ref(null); const progress = ref(0); const activeIndex = ref(0); let frame = null;
const entries = computed(() => props.repos.length ? props.repos.slice(0, 3).map((repo, index) => ({ id: repo.full_name || repo.name, name: repo.name, language: repo.language || 'CODE', commits: String(repo.recent_commits || 0).padStart(2, '0'), description: repo.description || '持續演進中的原創專案，從程式碼、實驗到部署保留完整的開發脈絡。', color: ['#34d399', '#2dd4bf', '#60a5fa'][index], hash: (repo.full_name || repo.name).slice(0, 8).toUpperCase(), step: `COMMIT / 0${index + 1}` })) : fallback.map((entry, index) => ({ ...entry, hash: ['A71C4E', 'C03E9D', 'F19B82'][index], step: `COMMIT / 0${index + 1}` })));
const active = computed(() => entries.value[activeIndex.value] || entries.value[0]);
const graphStyle = computed(() => ({ '--graph-turn': `${-18 + progress.value * 38}deg`, '--graph-depth': `${progress.value * 140}px` }));
const update = () => { frame = null; const rect = sectionRef.value?.getBoundingClientRect(); if (!rect) return; const range = rect.height - window.innerHeight; progress.value = range > 0 ? Math.min(1, Math.max(0, -rect.top / range)) : 0; activeIndex.value = Math.min(entries.value.length - 1, Math.floor(progress.value * entries.value.length)); };
const onScroll = () => { if (frame === null) frame = requestAnimationFrame(update); };
onMounted(() => { update(); window.addEventListener('scroll', onScroll, { passive: true }); window.addEventListener('resize', onScroll); });
onUnmounted(() => { window.removeEventListener('scroll', onScroll); window.removeEventListener('resize', onScroll); if (frame) cancelAnimationFrame(frame); });
</script>

<style scoped>
.github-graph-scroll { position: relative; margin: 1rem -1rem 4rem; }.github-graph-scroll__sticky { position: sticky; top: 0; height: 100vh; display: flex; align-items: center; overflow: hidden; isolation: isolate; background: radial-gradient(circle at 72% 47%, color-mix(in srgb, var(--git) 18%, transparent), transparent 30%), #07100e; }.github-graph-scroll__terminal-lines { position: absolute; inset: 0; opacity: .45; background: repeating-linear-gradient(0deg, transparent, transparent 37px, rgba(255,255,255,.035) 38px), linear-gradient(90deg, color-mix(in srgb, var(--git) 19%, transparent) 1px, transparent 1px); background-size: auto, 70px 100%; mask-image: radial-gradient(ellipse at center, black, transparent 82%); }.github-graph-scroll__graph { position: absolute; right: 8%; top: 50%; width: min(62vw, 780px); aspect-ratio: 1; transform: translateY(-50%) rotateY(var(--graph-turn)) translateZ(var(--graph-depth)); transform-style: preserve-3d; perspective: 1100px; }.github-graph-scroll__branch { position: absolute; z-index: 1; height: 2px; background: var(--git); box-shadow: 0 0 12px var(--git); transform-origin: left; }.github-graph-scroll__branch--main { width: 76%; left: 10%; top: 51%; transform: rotate(-19deg) translateZ(20px); }.github-graph-scroll__branch--fork { width: 45%; left: 18%; top: 50%; transform: rotate(45deg) translateZ(30px); }.github-graph-scroll__branch--merge { width: 43%; left: 48%; top: 72%; transform: rotate(-52deg) translateZ(25px); }.github-graph-scroll__commit { position: absolute; z-index: 3; display: grid; place-items: center; width: 31px; height: 31px; border: 1px solid var(--git); border-radius: 50%; color: var(--git); background: #07100e; box-shadow: 0 0 13px color-mix(in srgb, var(--git) 50%, transparent); font: 9px/1 ui-monospace, monospace; }.github-graph-scroll__commit.current { color: #06110d; background: var(--git); box-shadow: 0 0 30px var(--git); transform: scale(1.36) translateZ(55px); }.github-graph-scroll__commit:nth-of-type(1) { left: 9%; top: 65%; }.github-graph-scroll__commit:nth-of-type(2) { left: 24%; top: 59%; }.github-graph-scroll__commit:nth-of-type(3) { left: 38%; top: 54%; }.github-graph-scroll__commit:nth-of-type(4) { left: 52%; top: 48%; }.github-graph-scroll__commit:nth-of-type(5) { left: 67%; top: 43%; }.github-graph-scroll__commit:nth-of-type(6) { left: 28%; top: 72%; }.github-graph-scroll__commit:nth-of-type(7) { left: 39%; top: 81%; }.github-graph-scroll__commit:nth-of-type(8) { left: 51%; top: 71%; }.github-graph-scroll__commit:nth-of-type(9) { left: 62%; top: 61%; }.github-graph-scroll__commit:nth-of-type(10) { left: 76%; top: 51%; }.github-graph-scroll__commit:nth-of-type(11) { left: 84%; top: 38%; }.github-graph-scroll__repo-cube { position: absolute; left: 53%; top: 22%; width: 110px; aspect-ratio: 1; transform-style: preserve-3d; animation: git-cube 5s linear infinite; }.github-graph-scroll__repo-cube span { position: absolute; inset: 0; border: 1px solid var(--git); background: color-mix(in srgb, var(--git) 12%, rgba(5,15,13,.56)); }.github-graph-scroll__repo-cube span:nth-child(1) { transform: translateZ(55px); }.github-graph-scroll__repo-cube span:nth-child(2) { transform: rotateY(180deg) translateZ(55px); }.github-graph-scroll__repo-cube span:nth-child(3) { transform: rotateY(90deg) translateZ(55px); }.github-graph-scroll__repo-cube span:nth-child(4) { transform: rotateY(-90deg) translateZ(55px); }.github-graph-scroll__repo-cube span:nth-child(5) { transform: rotateX(90deg) translateZ(55px); }.github-graph-scroll__repo-cube span:nth-child(6) { transform: rotateX(-90deg) translateZ(55px); }.github-graph-scroll__repo-cube b { position: absolute; inset: 0; z-index: 2; display: grid; place-items: center; transform: translateZ(57px); color: var(--git); font: 700 31px/1 ui-monospace, monospace; text-shadow: 0 0 16px var(--git); }.github-graph-scroll__copy { position: relative; z-index: 4; }.github-graph-scroll__eyebrow, .github-graph-scroll__step, .github-graph-scroll__meta { font: 11px/1.4 ui-monospace, monospace; letter-spacing: .15em; }.github-graph-scroll__eyebrow { color: #9fafab; }.github-graph-scroll__step { margin: 25px 0 12px; color: var(--git); }.github-graph-scroll h2 { max-width: 570px; font-size: clamp(2.8rem, 6vw, 6.5rem); line-height: .96; font-weight: 900; letter-spacing: -.06em; }.github-graph-scroll__description { max-width: 465px; margin-top: 21px; color: #c6d1cd; line-height: 1.8; }.github-graph-scroll__meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 23px; color: var(--git); }.github-graph-scroll__meta span { padding: 7px 9px; border: 1px solid color-mix(in srgb, var(--git) 45%, transparent); background: rgba(1,10,7,.45); }.github-graph-scroll__refs { position: absolute; z-index: 4; bottom: 25px; left: 26px; color: #9aa9a3; font: 10px/1.4 ui-monospace, monospace; letter-spacing: .12em; }.github-graph-scroll__refs b { display: block; margin-top: 4px; color: var(--git); font-size: 16px; }.github-graph-scroll__pager { position: absolute; z-index: 4; right: 25px; top: 50%; display: grid; gap: 9px; }.github-graph-scroll__pager i { width: 7px; height: 7px; border: 1px solid var(--git); transform: rotate(45deg); }.github-graph-scroll__pager i.active { background: var(--git); box-shadow: 0 0 12px var(--git); }.git-copy-enter-active, .git-copy-leave-active { transition: opacity .26s ease, transform .34s cubic-bezier(.16,1,.3,1); }.git-copy-enter-from { opacity: 0; transform: translateY(15px); }.git-copy-leave-to { opacity: 0; transform: translateY(-12px); } @keyframes git-cube { to { transform: rotateX(360deg) rotateY(360deg); } }
@media (max-width: 700px) { .github-graph-scroll__graph { width: 95vw; right: -36vw; top: 64%; opacity: .63; }.github-graph-scroll__repo-cube { width: 76px; }.github-graph-scroll__copy { align-self: flex-start; padding-top: 17vh; }.github-graph-scroll h2 { max-width: 18rem; font-size: clamp(2.7rem, 13vw, 4.5rem); }.github-graph-scroll__description { max-width: 19rem; }.github-graph-scroll__meta { max-width: 19rem; font-size: 8px; }.github-graph-scroll__refs { bottom: 18px; left: 18px; font-size: 8px; }.github-graph-scroll__pager { right: 12px; } }
@media (prefers-reduced-motion: reduce) { .github-graph-scroll__repo-cube { animation: none; }.git-copy-enter-active, .git-copy-leave-active { transition: none; } }
</style>
