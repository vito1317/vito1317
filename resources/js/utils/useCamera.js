// 鏡頭：開關、前後鏡頭切換、擷取畫面成壓縮過的 JPEG data URL（送給 AI 看）
import { ref, shallowRef } from 'vue';

export function useCamera() {
  const active = ref(false);
  const starting = ref(false);
  const error = ref('');
  const facing = ref('user'); // user = 前鏡頭、environment = 後鏡頭
  const stream = shallowRef(null);

  function policyBlocked() {
    // 網站的 Permissions-Policy 若是 camera=()，瀏覽器會直接拒絕，給出比較清楚的提示
    const policy = document.permissionsPolicy || document.featurePolicy;
    try { return policy?.allowsFeature ? !policy.allowsFeature('camera') : false; } catch { return false; }
  }

  async function start() {
    if (active.value || starting.value) return true;
    error.value = '';
    if (!navigator.mediaDevices?.getUserMedia) {
      error.value = '此瀏覽器不支援鏡頭存取（需 HTTPS）。';
      return false;
    }
    if (policyBlocked()) {
      error.value = '鏡頭目前被網站安全政策停用。';
      return false;
    }
    starting.value = true;
    try {
      stream.value = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: facing.value, width: { ideal: 960 }, height: { ideal: 720 } },
        audio: false,
      });
      active.value = true;
      return true;
    } catch (err) {
      error.value = err?.name === 'NotAllowedError'
        ? '需要鏡頭權限才能讓 AI 看畫面。'
        : err?.name === 'NotFoundError' ? '找不到可用的鏡頭。' : '無法開啟鏡頭。';
      return false;
    } finally {
      starting.value = false;
    }
  }

  function stop() {
    stream.value?.getTracks().forEach((t) => t.stop());
    stream.value = null;
    active.value = false;
  }

  async function flip() {
    facing.value = facing.value === 'user' ? 'environment' : 'user';
    if (active.value) { stop(); await start(); }
  }

  /** 從 <video> 擷取一張畫面；最長邊縮到 maxSide，JPEG 壓縮。影片尚未就緒時回傳 null */
  function capture(video, maxSide = 768, quality = 0.72) {
    if (!video || !video.videoWidth || !video.videoHeight) return null;
    const scale = Math.min(1, maxSide / Math.max(video.videoWidth, video.videoHeight));
    const w = Math.round(video.videoWidth * scale);
    const h = Math.round(video.videoHeight * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    // 前鏡頭預覽是鏡像的，但送給 AI 的畫面要是正的（文字才讀得出來）
    ctx.drawImage(video, 0, 0, w, h);
    return canvas.toDataURL('image/jpeg', quality);
  }

  return { active, starting, error, facing, stream, start, stop, flip, capture };
}
