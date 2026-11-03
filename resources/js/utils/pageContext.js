// 擷取訪客「現在在網站上看到什麼」給 AI 參考：所在頁面、畫面上可見的文字、滑鼠（或最後點擊）指著的東西、選取的文字。
// 只讀本站自己的 DOM 內容，不擷取螢幕畫面，也不包含 AI 對話框本身。

let pointer = null;
if (typeof window !== 'undefined') {
  const remember = (e) => { pointer = { x: e.clientX, y: e.clientY }; };
  window.addEventListener('pointermove', remember, { passive: true });
  window.addEventListener('pointerdown', remember, { passive: true });
}

const PANEL = '.vai'; // AI 助理自己的 UI 不算
const TEXT_SELECTOR = 'h1, h2, h3, h4, p, li, figcaption, blockquote, dt, dd, td, th, [data-ai-context]';
const clean = (s, max) => String(s || '').replace(/\s+/g, ' ').trim().slice(0, max);

function describeElement(el) {
  if (!el || el.closest(PANEL)) return '';
  const img = el.closest('img, [role="img"]');
  if (img) return clean(`圖片：${img.getAttribute('alt') || img.getAttribute('aria-label') || ''}`, 200);
  const target = el.closest('a, button, h1, h2, h3, h4, p, li, figcaption, [data-ai-context], article, section') || el;
  const text = clean(target.getAttribute?.('aria-label') || target.innerText || target.textContent, 220);
  if (!text) return '';
  const kind = target.tagName === 'A' ? '連結' : target.tagName === 'BUTTON' ? '按鈕' : /^H\d$/.test(target.tagName) ? '標題' : '文字';
  const href = target.tagName === 'A' ? ` → ${target.getAttribute('href')}` : '';
  return `${kind}「${text}」${href}`;
}

function visibleText(limit = 1500) {
  const vh = window.innerHeight;
  const seen = new Set();
  const out = [];
  let total = 0;
  for (const el of document.querySelectorAll(TEXT_SELECTOR)) {
    if (el.closest(PANEL)) continue;
    const r = el.getBoundingClientRect();
    if (r.bottom < 0 || r.top > vh || r.width === 0 || r.height === 0) continue;
    const style = getComputedStyle(el);
    if (style.visibility === 'hidden' || Number(style.opacity) === 0) continue;
    const text = clean(el.innerText, 300);
    if (text.length < 2 || seen.has(text)) continue;
    // 父元素已收錄就略過子元素（避免 li > p 重複）
    if ([...seen].some((t) => t.includes(text))) continue;
    seen.add(text);
    out.push(text);
    total += text.length;
    if (total > limit) break;
  }
  return out.join(' / ').slice(0, limit);
}

/** 回傳目前頁面情境；後端會把它當作「訪客目前畫面」附在 AI 的提示裡 */
export function capturePageContext() {
  if (typeof window === 'undefined') return null;
  const atPointer = pointer ? document.elementFromPoint(pointer.x, pointer.y) : null;
  const scrollable = document.documentElement.scrollHeight - window.innerHeight;
  return {
    path: location.pathname,
    title: clean(document.title, 120),
    scroll: scrollable > 0 ? Math.round((window.scrollY / scrollable) * 100) : 0,
    pointer: describeElement(atPointer),
    selection: clean(window.getSelection?.().toString(), 500),
    visible: visibleText(),
  };
}

/** 給語音模式用：把情境整理成一段文字 */
export function pageContextText(ctx) {
  if (!ctx) return '';
  return [
    `訪客目前在「${ctx.title}」（${ctx.path}），大約捲到 ${ctx.scroll}% 的位置。`,
    ctx.pointer ? `滑鼠／手指指著：${ctx.pointer}。` : '',
    ctx.selection ? `訪客選取了文字：「${ctx.selection}」。` : '',
    ctx.visible ? `畫面上看得到的內容：${ctx.visible.slice(0, 900)}` : '',
  ].filter(Boolean).join('');
}
