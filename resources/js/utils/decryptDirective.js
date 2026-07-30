/**
 * v-decrypt — 標題解碼動畫：文字從亂碼字元逐字「解密」成原文。
 * 用法：<h1 v-decrypt>vito1317</h1>，可帶參數 v-decrypt="{ duration: 900 }"。
 * 字元池依原字元分流（CJK 用全形池、其餘用 ASCII 池）以減少寬度抖動；
 * prefers-reduced-motion 時直接顯示原文。
 */

const ASCII_POOL = '<>/\\|{}[]=+*#_10';
const CJK_POOL = '演算研究資安防禦系統核心加密解碼網路智慧';

const scrambleChar = (ch) => {
  if (ch === ' ' || ch === ' ') return ch;
  const pool = /[　-鿿豈-﫿]/.test(ch) ? CJK_POOL : ASCII_POOL;
  return pool[(Math.random() * pool.length) | 0];
};

export const decryptDirective = {
  mounted(el, binding) {
    const original = el.textContent;
    if (!original || !original.trim()) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const duration = binding.value?.duration ?? 900;
    el.setAttribute('aria-label', original.trim());

    const start = performance.now();
    const tick = (now) => {
      if (!el.isConnected) return;
      const progress = Math.min((now - start) / duration, 1);
      const solved = Math.floor(original.length * progress);
      el.textContent =
        original.slice(0, solved) +
        original
          .slice(solved)
          .split('')
          .map(scrambleChar)
          .join('');
      if (progress < 1) {
        requestAnimationFrame(tick);
      } else {
        el.textContent = original;
      }
    };
    requestAnimationFrame(tick);
  },
};
