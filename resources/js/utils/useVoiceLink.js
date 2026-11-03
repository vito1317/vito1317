// 全雙工語音連線：瀏覽器 ⇄ voice_server（MiniCPM-o 4.5，Socket.IO）
//
// 協定（與自架的語音伺服器對齊）：
//   → prompt_text        系統人格（字串）
//   → recording-started  開始一輪新的收音（伺服器端 VAD 會自動切句）
//   → audio              JSON {audio: number[] (int16 LE bytes), sample_rate: 16000}
//   ← audio              ArrayBuffer：int16 PCM，24 kHz mono
//   ← ai_text / user_transcript   字幕
//   ← stop_tts           使用者插話（barge-in）→ 立即停止播放
//   ← too_many_users / out_time   滿線 / 閒置逾時
//
// 麥克風在 AI 說話時也持續上傳，伺服器偵測到插話會中斷生成，這就是「全雙工」。
import { io } from 'socket.io-client';
import { ref, shallowRef } from 'vue';

const IN_SR = 16000;
const OUT_SR = 24000;
const SEND_SAMPLES = 1024; // 64 ms 一包

const WORKLET_SRC = `
class VaiCapture extends AudioWorkletProcessor {
  process(inputs) {
    const ch = inputs[0] && inputs[0][0];
    if (ch) this.port.postMessage(ch.slice(0));
    return true;
  }
}
registerProcessor('vai-capture', VaiCapture);
`;

// 語音 AI 的字幕也過濾底層模型 / 廠商名稱（與後端 SiteAi::scrub 對齊）。
// 規則以 base64 存放，避免模型名稱以明文出現在打包後的 JS 裡。
const decode = (b) => new TextDecoder().decode(Uint8Array.from(atob(b), (c) => c.charCodeAt(0)));
let scrubRules = null;
export function scrubModelNames(text) {
  scrubRules ||= [
    [new RegExp(decode('Z2VtbWFbXHNcLV9dKjQoPzpbXHNcLV9dKlxkK2IpPyg/Oltcc1wtX10qYVxkK2IpPyg/Oltcc1wtX10qaXQpP3xcYjI2Yltcc1wtX10qYTRiXGJ8bWluaWNwbVtcc1wtX10qbz9bXHNcLV9dKltcZC5fXSp86Z2i5aOB5bCPW+mLvOmSol3ngq58bGxhbWFcLj9jcHA='), 'giu'), 'V.AI'],
    [new RegExp(decode('Z29vZ2xlXHMqZGVlcG1pbmR8ZGVlcG1pbmR8b3BlbmJtYnzpnaLlo4Hmmbrog7186Z2i5aOB'), 'giu'), 'vito1317（柯瑋宸）'],
  ];
  return scrubRules.reduce((t, [re, to]) => t.replace(re, to), String(text || ''));
}

export function useVoiceLink({ url, path, prompt }) {
  const state = ref('idle'); // idle | connecting | live | busy | error | ended
  const error = ref('');
  const muted = ref(false);
  const aiSpeaking = ref(false);
  const captions = ref([]); // { role: 'user' | 'ai', text }，畫面只顯示最近幾則
  const transcript = ref([]); // 整通對話的完整字幕（轉交需求給 Vito 時使用）
  const analysers = shallowRef({ mic: null, ai: null });

  let socket = null;
  let ctx = null;
  let stream = null;
  let worklet = null;
  let micSource = null;
  let aiOut = null;
  let nextTime = 0;
  const sources = new Set();
  let pending = new Float32Array(0);
  let resamplePos = 0;
  let sendBuffer = [];
  let currentPrompt = prompt;

  function pushCaption(role, rawText) {
    const text = role === 'ai' ? scrubModelNames(rawText) : rawText;
    if (!text) return;
    const list = captions.value;
    const last = list[list.length - 1];
    // 有些事件是逐步累積的完整句子：同角色且為前綴延伸時直接取代
    const full = transcript.value;
    const lastFull = full[full.length - 1];
    if (last && last.role === role && text.startsWith(last.text)) {
      last.text = text;
      captions.value = [...list];
      if (lastFull && lastFull.role === role) lastFull.text = text;
      return;
    }
    captions.value = [...list.slice(-5), { role, text }];
    transcript.value = [...full.slice(-79), { role, text }];
  }

  function stopPlayback() {
    sources.forEach((s) => { try { s.stop(); } catch { /* already stopped */ } });
    sources.clear();
    nextTime = 0;
    aiSpeaking.value = false;
  }

  function playChunk(data) {
    if (!ctx || !aiOut) return;
    const pcm = new Int16Array(data instanceof ArrayBuffer ? data : data.buffer ?? data);
    if (!pcm.length) return;
    const buf = ctx.createBuffer(1, pcm.length, OUT_SR);
    const ch = buf.getChannelData(0);
    for (let i = 0; i < pcm.length; i += 1) ch[i] = pcm[i] / 32768;

    const src = ctx.createBufferSource();
    src.buffer = buf;
    src.connect(aiOut);
    // 無縫排程：接在上一段後面，不會有斷音
    const startAt = Math.max(ctx.currentTime + 0.04, nextTime);
    src.start(startAt);
    nextTime = startAt + buf.duration;
    sources.add(src);
    aiSpeaking.value = true;
    src.onended = () => {
      sources.delete(src);
      if (!sources.size) aiSpeaking.value = false;
    };
  }

  // 原生取樣率（44.1k/48k）→ 16k：線性內插降採樣，輸出 int16 封包
  function onMicFrame(frame) {
    if (!socket?.connected || muted.value) return;
    const ratio = ctx.sampleRate / IN_SR;
    const merged = new Float32Array(pending.length + frame.length);
    merged.set(pending);
    merged.set(frame, pending.length);

    const out = [];
    let pos = resamplePos;
    while (pos + 1 < merged.length) {
      const i = Math.floor(pos);
      const f = pos - i;
      out.push(merged[i] * (1 - f) + merged[i + 1] * f);
      pos += ratio;
    }
    const consumed = Math.floor(pos);
    pending = merged.slice(consumed);
    resamplePos = pos - consumed;

    sendBuffer = sendBuffer.concat(out);
    while (sendBuffer.length >= SEND_SAMPLES) {
      const chunk = sendBuffer.splice(0, SEND_SAMPLES);
      const pcm16 = new Int16Array(SEND_SAMPLES);
      for (let k = 0; k < SEND_SAMPLES; k += 1) {
        const s = Math.max(-1, Math.min(1, chunk[k]));
        pcm16[k] = s < 0 ? s * 0x8000 : s * 0x7fff;
      }
      socket.emit('audio', JSON.stringify({ audio: Array.from(new Uint8Array(pcm16.buffer)), sample_rate: IN_SR }));
    }
  }

  async function start() {
    if (state.value === 'connecting' || state.value === 'live') return;
    error.value = '';
    captions.value = [];
    transcript.value = [];
    state.value = 'connecting';

    try {
      if (!navigator.mediaDevices?.getUserMedia) throw new Error('此瀏覽器不支援麥克風存取（需 HTTPS）。');
      stream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
      });

      ctx = new (window.AudioContext || window.webkitAudioContext)();
      if (ctx.state === 'suspended') await ctx.resume();

      const blobUrl = URL.createObjectURL(new Blob([WORKLET_SRC], { type: 'application/javascript' }));
      await ctx.audioWorklet.addModule(blobUrl);
      URL.revokeObjectURL(blobUrl);

      micSource = ctx.createMediaStreamSource(stream);
      const micAnalyser = ctx.createAnalyser();
      micAnalyser.fftSize = 256;
      micSource.connect(micAnalyser);

      worklet = new AudioWorkletNode(ctx, 'vai-capture');
      worklet.port.onmessage = (e) => onMicFrame(e.data);
      micSource.connect(worklet);
      // worklet 需接到 destination 才會被驅動；用 0 增益避免把麥克風放出來
      const sink = ctx.createGain();
      sink.gain.value = 0;
      worklet.connect(sink).connect(ctx.destination);

      aiOut = ctx.createGain();
      const aiAnalyser = ctx.createAnalyser();
      aiAnalyser.fftSize = 256;
      aiOut.connect(aiAnalyser);
      aiAnalyser.connect(ctx.destination);
      analysers.value = { mic: micAnalyser, ai: aiAnalyser };
    } catch (err) {
      cleanup();
      state.value = 'error';
      error.value = err?.name === 'NotAllowedError' ? '需要麥克風權限才能語音對話。' : (err?.message || '無法啟動麥克風。');
      return;
    }

    socket = io(url, {
      path,
      transports: ['websocket', 'polling'],
      reconnection: false,
      timeout: 8000,
    });

    socket.on('connect', () => {
      socket.emit('prompt_text', currentPrompt);
      socket.emit('recording-started');
      state.value = 'live';
    });
    socket.on('connect_error', () => {
      state.value = 'error';
      error.value = '語音核心離線中，請稍後再試或改用文字對話。';
      cleanup();
    });
    socket.on('disconnect', () => {
      if (state.value === 'live') state.value = 'ended';
      cleanup();
    });
    socket.on('audio', playChunk);
    socket.on('ai_text', (t) => pushCaption('ai', String(t || '').trim()));
    socket.on('user_transcript', (t) => pushCaption('user', String(t || '').trim()));
    socket.on('stop_tts', stopPlayback);
    socket.on('too_many_users', () => {
      state.value = 'busy';
      error.value = '語音線路已滿，請稍後再試或改用文字對話。';
      cleanup();
    });
    socket.on('out_time', () => {
      state.value = 'ended';
      error.value = '閒置過久，語音連線已結束。';
      cleanup();
    });
  }

  function cleanup() {
    stopPlayback();
    try { socket?.emit('recording-stopped'); } catch { /* ignore */ }
    socket?.removeAllListeners();
    socket?.disconnect();
    socket = null;
    worklet?.port && (worklet.port.onmessage = null);
    try { worklet?.disconnect(); micSource?.disconnect(); } catch { /* ignore */ }
    stream?.getTracks().forEach((t) => t.stop());
    ctx?.close().catch(() => {});
    ctx = null;
    stream = null;
    worklet = null;
    micSource = null;
    aiOut = null;
    pending = new Float32Array(0);
    resamplePos = 0;
    sendBuffer = [];
    analysers.value = { mic: null, ai: null };
  }

  function stop() {
    cleanup();
    if (state.value !== 'error' && state.value !== 'busy') state.value = 'idle';
  }

  /** 連線途中更新人設（例如塞入最新的鏡頭畫面描述）；伺服器每輪都會讀最新的人設 */
  function setPrompt(next) {
    currentPrompt = next;
    if (socket?.connected) socket.emit('prompt_text', next);
  }

  function toggleMute() {
    muted.value = !muted.value;
    stream?.getAudioTracks().forEach((t) => { t.enabled = !muted.value; });
  }

  return { state, error, muted, aiSpeaking, captions, transcript, analysers, start, stop, toggleMute, setPrompt };
}
