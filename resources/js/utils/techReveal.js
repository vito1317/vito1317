/**
 * Tech Reveal — 以 IntersectionObserver 驅動既有 [data-aos] 標記的科技感進場動畫。
 * 搭配 resources/css/tech-motion.css 使用；MutationObserver 讓 v-if / v-for
 * 之後才掛載的節點（作品集、GitHub repo、演算法卡片等）自動被納入。
 *
 * 設計原則：
 * - 隱藏初始態由 JS 加上（.tr-pre），JS 失效時內容保持可見
 * - 動畫結束後移除所有 class 與 inline style，把元素還原給 Tailwind 的
 *   hover transition（卡片的 hover:-translate-y-2 不受影響）
 * - prefers-reduced-motion 時完全跳過
 */

const REVEAL_TIMEOUT_MS = 1600;

let io = null;
let mo = null;

const reducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const finish = (el) => {
  el.classList.remove('tr-pre', 'tr-in');
  el.style.transitionDelay = '';
};

const onIntersect = (entries) => {
  for (const entry of entries) {
    if (!entry.isIntersecting) continue;
    const el = entry.target;
    io.unobserve(el);

    // 下一影格再切換，確保 .tr-pre 的初始態已完成繪製
    requestAnimationFrame(() => {
      el.classList.add('tr-in');
      let done = false;
      const cleanup = () => {
        if (done) return;
        done = true;
        el.removeEventListener('transitionend', cleanup);
        finish(el);
      };
      el.addEventListener('transitionend', cleanup);
      setTimeout(cleanup, REVEAL_TIMEOUT_MS);
    });
  }
};

const bind = (el) => {
  if (el.__trBound) return;
  el.__trBound = true;
  if (reducedMotion()) return;

  el.classList.add('tr-pre');
  const delay = parseInt(el.getAttribute('data-aos-delay') || '0', 10);
  if (delay > 0) el.style.transitionDelay = `${Math.min(delay, 600)}ms`;
  io.observe(el);
};

const scan = (node) => {
  if (node.nodeType !== Node.ELEMENT_NODE) return;
  if (node.hasAttribute('data-aos')) bind(node);
  node.querySelectorAll('[data-aos]').forEach(bind);
};

export function initTechReveal(root = document.body) {
  if (io || typeof IntersectionObserver === 'undefined') return;

  // 元素一進入視口就觸發，避免內容滑到一半才出現；
  // 手機視口矮、單卡佔比高，提早量加大（25%），桌面 6%
  const earlyMargin = window.innerWidth < 768 ? '25%' : '6%';
  io = new IntersectionObserver(onIntersect, {
    threshold: 0.01,
    rootMargin: `0px 0px ${earlyMargin} 0px`,
  });

  scan(root);

  mo = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach(scan);
    }
  });
  mo.observe(root, { childList: true, subtree: true });
}
