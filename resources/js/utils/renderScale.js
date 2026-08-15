// Cap the WebGL drawing-buffer resolution on high-DPI / 2K / 4K screens.
//
// A full-viewport canvas on a 4K display is ~3840×2160 CSS px; multiplying by
// devicePixelRatio pushes the drawing buffer past 25M pixels, which crushes
// fill-rate — especially with post-processing (UnrealBloom does several
// downsample/upsample passes at buffer resolution). We cap the longest
// drawing-buffer side to a fixed budget so per-frame cost is bounded
// regardless of screen size, then let the browser upscale the canvas via CSS.
// The tradeoff is a little softness on very large screens for a large GPU
// saving; on 1080p (the common case) the result is unchanged.
//
// Returns an effective devicePixelRatio to pass to renderer.setPixelRatio().
// Call it with the canvas's CSS width/height on both init AND resize so the
// ratio tracks the current size (e.g. when a window moves between monitors).
export function cappedPixelRatio(cssW, cssH) {
  const dpr = window.devicePixelRatio || 1;
  const mobile = window.innerWidth < 768;
  const raw = Math.min(dpr, mobile ? 1.25 : 1.75);
  const maxDim = mobile ? 1280 : 1920; // longest drawing-buffer side budget
  const longest = Math.max(cssW, cssH, 1);
  const cap = maxDim / longest;
  return Math.max(0.6, Math.min(raw, cap));
}
