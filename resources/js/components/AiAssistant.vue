<template>
  <div class="vai" :class="{ 'vai--open': open }">
    <!-- 啟動鈕 -->
    <button
      v-show="!open"
      type="button"
      class="vai-launcher"
      aria-label="開啟 AI 助理"
      @click="toggle(true)"
    >
      <span class="vai-launcher__ring"></span>
      <span class="vai-launcher__ring vai-launcher__ring--b"></span>
      <AgentOrbs v-if="streaming" size="sm" :state="phase" busy />
      <svg v-else viewBox="0 0 24 24" class="vai-launcher__icon" aria-hidden="true">
        <path d="M12 2l2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z" fill="currentColor" />
        <circle cx="19" cy="19" r="2" fill="currentColor" />
      </svg>
      <span class="vai-launcher__label">ASK V.AI</span>
    </button>

    <!-- 對話面板 -->
    <Transition name="vai-panel">
      <section
        v-if="open"
        class="vai-panel"
        role="dialog"
        aria-label="V.AI 助理"
        @keydown.esc="toggle(false)"
      >
        <!-- 邊框光束（同 gac-soc agent）：閒置時微亮，生成時沿邊框跑 -->
        <AgentBeam
          class="vai-beam" size="md" :busy="streaming" active
          color-variant="ocean" :strength="streaming ? 1 : 0.4" aria-hidden="true"
        />
        <header class="vai-head">
          <div class="vai-head__id">
            <span class="vai-dot" :class="{ busy: streaming || voice.aiSpeaking.value }"></span>
            <div>
              <p class="vai-head__title">V.AI <small>// NEURAL LINK</small></p>
              <p class="vai-head__sub">{{ voiceMode ? 'FULL-DUPLEX VOICE · ON-PREM GPU' : 'NEURAL CORE · ON-PREM GPU' }}</p>
            </div>
          </div>
          <div class="vai-head__actions">
            <button
              type="button"
              class="vai-call"
              :class="{ 'vai-call--active': voiceMode }"
              :title="voiceMode ? '掛斷，回到文字對話' : '語音通話'"
              :aria-label="voiceMode ? '掛斷通話' : '開始語音通話'"
              @click="voiceMode ? endVoice() : startVoice()"
              :disabled="streaming"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true" :class="{ hangup: voiceMode }"><path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.6a1 1 0 01-.25 1z" fill="currentColor" /></svg>
              <span>{{ voiceMode ? '掛斷' : '通話' }}</span>
            </button>
            <button type="button" title="清除對話" aria-label="清除對話" @click="reset" :disabled="streaming">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m-8 0l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.6" /></svg>
            </button>
            <button type="button" title="關閉" aria-label="關閉" @click="toggle(false)">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="1.6" /></svg>
            </button>
          </div>
        </header>

        <div v-if="voiceMode" class="vai-voice">
          <div class="vai-voice__stage">
            <VoiceOrb
              :analysers="voice.analysers.value"
              :live="voice.state.value === 'live'"
              :speaking="voice.aiSpeaking.value"
              :muted="voice.muted.value"
            />
            <div v-if="cam.active.value" class="vai-voice__cam">
              <video ref="voiceVideoEl" autoplay playsinline muted :class="{ mirror: cam.facing.value === 'user' }"></video>
              <span class="vai-cam__badge"><i></i>{{ visionBusy ? 'SCANNING' : 'VISION' }}</span>
            </div>
          </div>
          <p v-if="cam.active.value && sceneText" class="vai-voice__scene" :title="sceneText">👁 {{ sceneText }}</p>
          <p class="vai-voice__status" :class="`is-${voiceStatus.tone}`">{{ voiceStatus.text }}</p>
          <div class="vai-voice__captions" aria-live="polite">
            <p v-for="(c, i) in voice.captions.value" :key="i" :class="`cap--${c.role}`">
              <b>{{ c.role === 'user' ? 'YOU' : 'V.AI' }}</b>{{ c.text }}
            </p>
            <p v-if="!voice.captions.value.length && voice.state.value === 'live'" class="cap--hint">
              直接開口說話即可，AI 說話時也可以隨時插話打斷。<br>開啟鏡頭後可以問「你看到什麼？」
            </p>
            <p v-if="cam.error.value" class="cap--err">{{ cam.error.value }}</p>
          </div>
          <form v-if="leadOpen" class="vai-vlead" @submit.prevent="submitVoiceLead">
            <template v-if="leadState !== 'saved'">
              <p class="vai-vlead__title">留下聯絡方式，V.AI 會把剛才的需求整理好轉交給 Vito</p>
              <div class="vai-vlead__row">
                <input v-model.trim="leadName" type="text" maxlength="100" placeholder="稱呼（選填）" aria-label="稱呼" />
                <input ref="leadEmailEl" v-model.trim="leadEmail" type="email" maxlength="255" required placeholder="Email" aria-label="Email" />
              </div>
              <p v-if="leadError" class="vai-vlead__err">{{ leadError }}</p>
              <div class="vai-vlead__actions">
                <button type="button" class="ghost" @click="leadOpen = false">取消</button>
                <button type="submit" :disabled="leadState === 'sending' || !leadEmail">
                  {{ leadState === 'sending' ? '整理需求中…' : '轉交給 Vito' }}
                </button>
              </div>
            </template>
            <div v-else class="vai-lead">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4.5 4.5L19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
              <div><b>需求已轉交給 Vito</b><span>{{ leadSummary || '他會親自透過 Email 與你聯繫' }}</span></div>
            </div>
          </form>

          <div class="vai-voice__controls">
            <button
              v-if="voice.state.value === 'live' && leadState !== 'saved'"
              type="button"
              class="vai-voice__btn"
              :class="{ on: leadOpen }"
              aria-label="留下聯絡方式給 Vito"
              title="留下聯絡方式給 Vito"
              @click="openVoiceLead"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.6" /><path d="M3.5 6.5L12 13l8.5-6.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" /></svg>
            </button>
            <button
              v-if="voice.state.value === 'live'"
              type="button"
              class="vai-voice__btn"
              :class="{ on: cam.active.value }"
              :aria-label="cam.active.value ? '關閉鏡頭' : '開啟鏡頭讓 AI 看畫面'"
              :title="cam.active.value ? '關閉鏡頭' : '開啟鏡頭讓 AI 看畫面'"
              @click="toggleCamera"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h3l2-2h8l2 2h3v12H3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" /><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="1.6" /><path v-if="!cam.active.value" d="M4 4l16 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
            </button>
            <button
              v-if="voice.state.value === 'live'"
              type="button"
              class="vai-voice__btn"
              :class="{ off: voice.muted.value }"
              :aria-label="voice.muted.value ? '取消靜音' : '靜音'"
              @click="voice.toggleMute"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a3 3 0 00-3 3v6a3 3 0 006 0V6a3 3 0 00-3-3zM5 11a7 7 0 0014 0M12 18v3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path v-if="voice.muted.value" d="M4 4l16 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
            </button>
            <button
              v-if="voice.state.value === 'live' || voice.state.value === 'connecting'"
              type="button"
              class="vai-voice__btn vai-voice__btn--end"
              aria-label="結束語音"
              @click="endVoice"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 14c5-5 13-5 18 0l-2.5 2.5-3-1.5v-2.5a10 10 0 00-5 0V15l-3 1.5z" fill="currentColor" /></svg>
            </button>
            <button v-else type="button" class="vai-voice__btn vai-voice__btn--call" aria-label="重新撥打" title="重新撥打" @click="startVoice">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.6a1 1 0 01-.25 1z" fill="currentColor" /></svg>
            </button>
          </div>
        </div>

        <div v-show="!voiceMode" ref="scroller" class="vai-body" @click="onBodyClick">
          <div v-if="!messages.length" class="vai-intro">
            <p class="vai-intro__hello">&gt; 連線建立。我是 <b>V.AI</b>，Vito 的 AI 分身。</p>
            <p class="vai-intro__hint">想了解 Vito 的經歷、專案、資安研究或合作方式？直接問我。</p>
            <button type="button" class="vai-dial" @click="startVoice">
              <span class="vai-dial__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.6a1 1 0 01-.25 1z" fill="currentColor" /></svg></span>
              <span class="vai-dial__text"><b>語音通話 V.AI</b><small>即時雙向對話，可隨時插話，也能開鏡頭</small></span>
            </button>
            <div class="vai-chips">
              <button v-for="q in suggestions" :key="q" type="button" @click="send(q)">{{ q }}</button>
            </div>
          </div>

          <div
            v-for="(m, i) in messages"
            :key="i"
            class="vai-msg"
            :class="[`vai-msg--${m.role}`, { 'vai-msg--error': m.error }]"
          >
            <span v-if="m.role === 'user' || m.content || !(streaming && i === messages.length - 1)" class="vai-msg__tag">{{ m.role === 'user' ? 'YOU' : 'V.AI' }}</span>
            <div v-if="m.role === 'user'" class="vai-msg__text">
              <img v-if="m.image" :src="m.image" alt="附上的鏡頭畫面" class="vai-msg__img" />
              {{ m.content }}
            </div>
            <div
              v-else-if="m.content || !(streaming && i === messages.length - 1)"
              class="vai-msg__text vai-md"
              :class="{ 'is-streaming': streaming && i === messages.length - 1 }"
              v-html="render(scrubModelNames(m.content))"
            ></div>
            <div v-if="m.lead" class="vai-lead">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4.5 4.5L19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
              <div><b>需求已轉交給 Vito</b><span>他會親自透過 Email 與你聯繫</span></div>
            </div>
          </div>

          <!-- 生成中的思考指示器（同 gac-soc agent）：還沒出字是 searching，逐字生成時是 composing -->
          <div v-if="streaming" class="vai-thinking">
            <AgentOrbs size="md" :state="phase" busy />
            <span>{{ PHASE_LABEL[phase] }}</span>
          </div>
        </div>

        <div v-if="!voiceMode && (cam.active.value || cam.error.value)" class="vai-cam">
          <template v-if="cam.active.value">
            <video ref="textVideoEl" autoplay playsinline muted :class="{ mirror: cam.facing.value === 'user' }"></video>
            <div class="vai-cam__info">
              <span class="vai-cam__badge"><i></i>LIVE</span>
              <p>送出訊息時會附上當下畫面給 AI 看</p>
              <div class="vai-cam__actions">
                <button type="button" @click="cam.flip()">切換鏡頭</button>
                <button type="button" @click="cam.stop()">關閉</button>
              </div>
            </div>
          </template>
          <p v-else class="vai-cam__err">{{ cam.error.value }}</p>
        </div>

        <form v-show="!voiceMode" class="vai-input" @submit.prevent="send()">
          <button
            type="button"
            class="vai-input__cam"
            :class="{ on: cam.active.value }"
            :aria-label="cam.active.value ? '關閉鏡頭' : '開啟鏡頭讓 AI 看畫面'"
            :title="cam.active.value ? '關閉鏡頭' : '開啟鏡頭讓 AI 看畫面'"
            :disabled="cam.starting.value"
            @click="toggleCamera"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h3l2-2h8l2 2h3v12H3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" /><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="1.6" /></svg>
          </button>
          <textarea
            ref="inputEl"
            v-model="draft"
            rows="1"
            maxlength="1000"
            placeholder="輸入訊息，Enter 送出…"
            aria-label="輸入訊息"
            @keydown.enter.exact.prevent="send()"
            @input="autosize"
          ></textarea>
          <!-- 液態金屬環包住送出鈕（同 gac-soc agent） -->
          <AgentMetal class="vai-sendmetal" size="sm" :busy="streaming">
            <button v-if="streaming" type="button" class="stop" title="停止" aria-label="停止產生" @click="stop">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5" fill="currentColor" /></svg>
            </button>
            <button v-else type="submit" :disabled="!draft.trim()" title="送出（Enter）" aria-label="送出">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4v7a4 4 0 01-4 4H5m0 0l4-4m-4 4l4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
          </AgentMetal>
        </form>
        <p class="vai-foot">{{ voiceMode || cam.active.value ? '語音與畫面由自架伺服器即時處理，不會保存。' : '' }}AI 回覆可能有誤，重要資訊請以網站內容或直接聯繫為準。</p>
      </section>
    </Transition>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { scrubModelNames, useVoiceLink } from '../utils/useVoiceLink';
import { useCamera } from '../utils/useCamera';
import VoiceOrb from './VoiceOrb.vue';
// 與 gac-soc agent 相同的生成動畫（點陣球體 / 邊框光束 / 液態金屬送出鈕），按需載入
const AgentOrbs = defineAsyncComponent(() => import('./agent/AgentOrbs.vue'));
const AgentBeam = defineAsyncComponent(() => import('./agent/AgentBeam.vue'));
const AgentMetal = defineAsyncComponent(() => import('./agent/AgentMetal.vue'));

const STORAGE_KEY = 'vai:history';
const router = useRouter();

const open = ref(false);
const messages = ref([]);
const draft = ref('');
const streaming = ref(false);
const scroller = ref(null);
const inputEl = ref(null);
let controller = null;

// 跟 gac-soc 一樣：送出後還沒有第一個字 → searching，開始逐字生成 → composing
const PHASE_LABEL = { searching: '思考中…', composing: '撰寫中…', breathing: '待命' };
const phase = computed(() => {
  if (!streaming.value) return 'breathing';
  const last = messages.value[messages.value.length - 1];
  return last?.role === 'assistant' && last.content ? 'composing' : 'searching';
});

/* ---------- 全雙工語音 ---------- */
const VOICE_PROMPT = [
  '你是「V.AI」，柯瑋宸（Vito）個人網站 vito1317.com 的語音助理，正在跟訪客即時語音對話。',
  '全程用台灣正體中文口語回答，每次一到三句、自然簡短，不要念出符號、網址或條列格式。',
  '關於 Vito：20 歲、新北汐止，自學出身的資安與全端工程師；現任智能悅信 IntelliTrust 資安暨研發經理，領導 Security One WAF 與資安維運中心產品；',
  '也是聚騰科技研發工程師、中華科技大學資訊顧問，曾通報中華科大 CVSS 9.8 的 RCE 與 SQL Injection 漏洞而被延攬；',
  '微調大型語言模型做資安助理 Project Sentinel，發表 LLM 自我一致性投票論文 TACT；擔任 GDG on Campus 臺師大 Tech 課程講師兼課程長；曾婉拒蝦皮總部 offer 選擇讀大學。',
  '專案有 Security One、Aura News 新聞平台、奈奈 Discord 陪伴機器人、蝦皮自動化工具、台北旅遊預訂系統等。',
  '想聯絡他可以寄信到 service@vito1317.com 或用網站的聯繫我頁面。',
  '【合作需求】當訪客想跟 Vito 合作、委託專案、邀請演講、詢問報價、提供工作機會或想讓 Vito 聯絡他時：先熱情回應，說你可以幫忙整理需求轉交給 Vito；',
  '接著一次只問一項，依序詢問稱呼、想做什麼（需求內容）、期望時程、預算範圍，訪客不想說的就跳過；每次聽完都要用一句話覆述你聽到的內容，例如「好的，你是大文科技的陳先生，想在三個月內導入 WAF」；',
  '不要用講的詢問或覆述 Email，問完需求後要說：「請在畫面上輸入你的 Email，再按轉交給 Vito」。不要對報價、時程或是否接案做任何承諾，這些由 Vito 本人決定。',
  '你是由 vito1317（柯瑋宸）開發的 V.AI。被問到誰開發的、哪家公司、用什麼模型或版本時，一律只說「我是 V.AI，由 vito1317 柯瑋宸開發」，絕不提及任何其他公司、團隊或模型名稱。',
  '不知道的事就說不清楚，請對方透過聯繫頁面詢問，絕對不要編造。不協助任何未授權的入侵或攻擊。',
].join('');

const voice = useVoiceLink({
  url: import.meta.env.VITE_VOICE_URL || window.location.origin,
  path: import.meta.env.VITE_VOICE_PATH || '/voice-rt/socket.io/',
  prompt: VOICE_PROMPT,
});
const voiceMode = ref(false);

/* ---------- 語音模式的合作需求轉交 ---------- */
const leadOpen = ref(false);
const leadState = ref('idle'); // idle | sending | saved
const leadName = ref('');
const leadEmail = ref('');
const leadError = ref('');
const leadSummary = ref('');
const leadEmailEl = ref(null);
// AI 說出「請在畫面上輸入 Email」之類的話時，自動跳出聯絡卡片
const LEAD_CUE = /(畫面|螢幕|下方|這裡).{0,10}(輸入|填|留下).{0,8}(e-?mail|信箱|電子郵件|聯絡方式)/i;

function openVoiceLead() {
  if (leadState.value === 'saved') return;
  leadOpen.value = true;
  leadError.value = '';
  nextTick(() => leadEmailEl.value?.focus());
}

watch(() => voice.captions.value, (list) => {
  const last = list[list.length - 1];
  if (last?.role === 'ai' && LEAD_CUE.test(last.text) && !leadOpen.value) openVoiceLead();
});

async function submitVoiceLead() {
  if (leadState.value === 'sending' || !leadEmail.value) return;
  leadState.value = 'sending';
  leadError.value = '';
  try {
    const res = await fetch('/api/ai/voice-lead', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        email: leadEmail.value,
        name: leadName.value || null,
        transcript: voice.transcript.value.map(({ role, text }) => ({ role, text: text.slice(0, 1500) })),
      }),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || '轉交失敗，請稍後再試。');
    leadState.value = 'saved';
    leadSummary.value = data.summary?.need ? `「${data.summary.need}」— 他會親自透過 Email 與你聯繫` : '';
    // 讓語音 AI 知道已經轉交，訪客接著問時能自然回應
    voice.setPrompt(`${VOICE_PROMPT}【狀態】訪客已在畫面上留下 Email，需求已轉交給 Vito；Vito 會親自用 Email 聯繫訪客，不需要再蒐集聯絡資料。`);
  } catch (err) {
    leadState.value = 'idle';
    leadError.value = err.message;
  }
}

/* ---------- 鏡頭 ---------- */
const cam = useCamera();
const textVideoEl = ref(null);
const voiceVideoEl = ref(null);
const sceneText = ref('');
const visionBusy = ref(false);
const VISION_INTERVAL = 6000;
let visionTimer = 0;

// 同一條鏡頭串流同時接到文字模式 / 語音模式的 <video>
watch([() => cam.stream.value, textVideoEl, voiceVideoEl], ([stream, a, b]) => {
  [a, b].forEach((el) => { if (el && el.srcObject !== stream) el.srcObject = stream; });
});

async function toggleCamera() {
  if (cam.active.value) {
    cam.stop();
    return;
  }
  await cam.start();
}

function currentVideo() {
  return voiceMode.value ? voiceVideoEl.value : textVideoEl.value;
}

// 語音模式：定期把畫面交給視覺模型描述，再更新到語音 AI 的人設裡，它就「看得到」了
async function visionTick() {
  if (!voiceMode.value || !cam.active.value || voice.state.value !== 'live' || visionBusy.value) return;
  const image = cam.capture(voiceVideoEl.value, 640, 0.7);
  if (!image) return;
  visionBusy.value = true;
  try {
    const res = await fetch('/api/ai/vision', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ image }),
    });
    if (!res.ok) return;
    const { description } = await res.json();
    if (!description || !cam.active.value) return;
    sceneText.value = description;
    voice.setPrompt(`${VOICE_PROMPT}【即時鏡頭畫面】你現在可以透過訪客的鏡頭看到畫面，最新畫面是：${description}。訪客問到畫面、手上的東西或「你看到什麼」時，根據這段描述自然地回答，就像你親眼看到；沒被問到時不要主動描述畫面。`);
  } catch { /* 下一輪再試 */ } finally {
    visionBusy.value = false;
  }
}

watch(
  () => voiceMode.value && cam.active.value && voice.state.value === 'live',
  (on) => {
    window.clearInterval(visionTimer);
    if (on) {
      window.setTimeout(visionTick, 800); // 等鏡頭畫面穩定再拍第一張
      visionTimer = window.setInterval(visionTick, VISION_INTERVAL);
    } else {
      sceneText.value = '';
      if (voice.state.value === 'live') voice.setPrompt(VOICE_PROMPT);
    }
  },
);

const voiceStatus = computed(() => {
  const s = voice.state.value;
  if (s === 'connecting') return { tone: 'wait', text: 'CALLING V.AI · 撥號中…' };
  if (s === 'live') {
    if (voice.muted.value) return { tone: 'muted', text: 'MIC MUTED' };
    return voice.aiSpeaking.value
      ? { tone: 'ai', text: 'V.AI SPEAKING · 可隨時插話' }
      : { tone: 'live', text: 'LISTENING · 請說話' };
  }
  if (s === 'ended') return { tone: 'muted', text: voice.error.value || '通話已結束' };
  if (s === 'error' || s === 'busy') return { tone: 'err', text: voice.error.value };
  return { tone: 'muted', text: 'STANDBY' };
});

async function startVoice() {
  voiceMode.value = true;
  leadOpen.value = false;
  if (leadState.value !== 'saved') leadError.value = '';
  await nextTick();
  voice.start();
}

function endVoice() {
  voice.stop();
  voiceMode.value = false;
}

onUnmounted(() => {
  voice.stop();
  cam.stop();
  cancelAnimationFrame(typeRaf);
  window.clearInterval(visionTimer);
});

const suggestions = [
  'Vito 是誰？用三句話介紹',
  'Security One 是什麼產品？',
  '他做過哪些 AI / LLM 研究？',
  '我想找 Vito 合作一個專案',
];

onMounted(() => {
  try {
    const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
    if (Array.isArray(saved)) messages.value = saved.filter((m) => m && m.content && !m.error);
  } catch { /* 無痕模式等情況，直接忽略 */ }
});

watch(messages, (val) => {
  if (streaming.value) return;
  // 圖片不存（太大，也避免把鏡頭畫面留在瀏覽器裡）
  const slim = val.slice(-30).map(({ image, ...rest }) => rest);
  try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(slim)); } catch { /* ignore */ }
}, { deep: true });

function toggle(state) {
  if (!state && voiceMode.value) endVoice();
  if (!state) cam.stop();
  open.value = state;
  if (state) nextTick(() => { inputEl.value?.focus(); scrollToBottom(); });
}

function reset() {
  messages.value = [];
  try { sessionStorage.removeItem(STORAGE_KEY); } catch { /* ignore */ }
}

function stop() {
  controller?.abort();
}

function autosize() {
  const el = inputEl.value;
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${Math.min(el.scrollHeight, 120)}px`;
}

function scrollToBottom() {
  const el = scroller.value;
  if (el) el.scrollTop = el.scrollHeight;
}

/* ---------- 逐字顯示：網路就算一次送來一大段，畫面也平滑地一個字一個字打出來 ---------- */
const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
let typeQueue = [];
let typeTarget = null;
let typeRaf = 0;

function enqueueText(target, text) {
  typeTarget = target;
  if (reduceMotion || document.hidden) {
    target.content += text;
    return;
  }
  typeQueue.push(...Array.from(text)); // Array.from 才不會把 emoji / 中文切壞
  if (!typeRaf) typeRaf = requestAnimationFrame(drainTyping);
}

function drainTyping() {
  typeRaf = 0;
  if (!typeQueue.length || !typeTarget) return;
  // 積得越多打得越快：一般約每秒 60–120 字，落後太多時自動加速追上
  const n = Math.max(1, Math.ceil(typeQueue.length / 18));
  typeTarget.content += typeQueue.splice(0, n).join('');
  const el = scroller.value;
  if (el && el.scrollHeight - el.scrollTop - el.clientHeight < 90) el.scrollTop = el.scrollHeight;
  if (typeQueue.length) typeRaf = requestAnimationFrame(drainTyping);
}

function finishTyping() {
  return new Promise((resolve) => {
    const check = () => {
      if (!typeQueue.length) return resolve();
      if (document.hidden) { // 背景分頁 rAF 不跑，直接補完
        typeTarget.content += typeQueue.join('');
        typeQueue = [];
        return resolve();
      }
      setTimeout(check, 30);
    };
    check();
  });
}

async function send(text) {
  const content = (text ?? draft.value).trim();
  if (!content || streaming.value) return;

  // 鏡頭開著就附上當下畫面
  const image = cam.active.value ? cam.capture(textVideoEl.value, 768, 0.72) : null;

  draft.value = '';
  nextTick(autosize);

  messages.value.push(image ? { role: 'user', content, image } : { role: 'user', content });
  const history = messages.value
    .filter((m) => !m.error)
    .map(({ role, content: c }) => ({ role, content: c.slice(0, 2000) }));

  messages.value.push({ role: 'assistant', content: '' });
  const reply = messages.value[messages.value.length - 1];
  streaming.value = true;
  controller = new AbortController();
  await nextTick();
  scrollToBottom();

  try {
    const res = await fetch('/api/ai/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
      body: JSON.stringify(image ? { messages: history, image } : { messages: history }),
      signal: controller.signal,
    });

    if (res.status === 429) throw new Error('請求太頻繁了，請稍候一分鐘再試。');
    if (!res.ok || !res.body) throw new Error('AI 核心暫時無法連線，請稍後再試。');

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });

      let idx;
      while ((idx = buffer.indexOf('\n\n')) !== -1) {
        const event = buffer.slice(0, idx).trim();
        buffer = buffer.slice(idx + 2);
        if (!event.startsWith('data:')) continue;
        const data = event.slice(5).trim();
        if (data === '[DONE]') continue;
        const parsed = JSON.parse(data);
        if (parsed.error) throw new Error(parsed.error);
        if (parsed.t) enqueueText(reply, parsed.t);
        if (parsed.lead === 'saved') reply.lead = true;
      }
    }
    await finishTyping();
    if (!reply.content) throw new Error('AI 沒有產生回覆，請換個問法再試一次。');
  } catch (err) {
    await finishTyping();
    if (err.name === 'AbortError') {
      if (!reply.content) messages.value.splice(messages.value.indexOf(reply), 1);
    } else {
      reply.error = true;
      reply.content = reply.content ? `${reply.content}\n\n⚠ ${err.message}` : `⚠ ${err.message}`;
    }
  } finally {
    streaming.value = false;
    controller = null;
    // 觸發 watch 寫入 sessionStorage
    messages.value = [...messages.value];
    nextTick(scrollToBottom);
  }
}

/* ---------- 精簡且安全的 Markdown 渲染（先跳脫 HTML 再套格式） ---------- */
function escapeHtml(s) {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function inline(s) {
  return s
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/g, (_, label, href) => link(href, label))
    .replace(/(^|[\s（(])(https?:\/\/[^\s<)）]+)/g, (_, pre, href) => pre + link(href, href))
    .replace(/(^|[\s（(：:、])(\/(?:news|github|algorithms|contact))(?=[\s）)，。、]|$)/g, (_, pre, href) => pre + link(href, href));
}

function link(href, label) {
  const internal = href.startsWith('/');
  return internal
    ? `<a href="${href}" data-internal="1">${label}</a>`
    : `<a href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`;
}

function render(src) {
  const lines = escapeHtml(src || '').split('\n');
  const out = [];
  let list = null;
  let code = null;

  const closeList = () => { if (list) { out.push(`</${list}>`); list = null; } };

  for (const raw of lines) {
    if (raw.trim().startsWith('```')) {
      if (code === null) { closeList(); code = []; } else { out.push(`<pre><code>${code.join('\n')}</code></pre>`); code = null; }
      continue;
    }
    if (code !== null) { code.push(raw); continue; }

    const line = raw.trimEnd();
    const ul = line.match(/^\s*[-*•]\s+(.*)$/);
    const ol = line.match(/^\s*\d+[.)]\s+(.*)$/);
    const h = line.match(/^#{1,4}\s+(.*)$/);

    if (ul || ol) {
      const tag = ul ? 'ul' : 'ol';
      if (list !== tag) { closeList(); out.push(`<${tag}>`); list = tag; }
      out.push(`<li>${inline((ul || ol)[1])}</li>`);
    } else if (h) {
      closeList();
      out.push(`<p class="vai-md__h">${inline(h[1])}</p>`);
    } else if (line.trim() === '') {
      closeList();
    } else {
      closeList();
      out.push(`<p>${inline(line)}</p>`);
    }
  }
  if (code !== null) out.push(`<pre><code>${code.join('\n')}</code></pre>`);
  closeList();
  return out.join('');
}

function onBodyClick(e) {
  const a = e.target.closest('a[data-internal]');
  if (!a) return;
  e.preventDefault();
  router.push(a.getAttribute('href'));
  if (window.innerWidth < 640) toggle(false);
}
</script>

<style scoped>
.vai { --c: #2ae6ff; --m: #ff3ac8; --v: #8b7bff; font-family: ui-sans-serif, system-ui, sans-serif; }

/* ---------- launcher ---------- */
.vai-launcher {
  position: fixed; right: 22px; bottom: 22px; z-index: 70;
  display: flex; align-items: center; gap: 10px; height: 48px; padding: 0 18px 0 14px;
  border: 1px solid rgba(42, 230, 255, .55); border-radius: 999px;
  background: rgba(6, 12, 26, .78); backdrop-filter: blur(10px);
  color: #dffbff; cursor: pointer; isolation: isolate;
  box-shadow: 0 0 22px rgba(42, 230, 255, .25), inset 0 0 14px rgba(42, 230, 255, .12);
  transition: transform .25s cubic-bezier(.16, 1, .3, 1), box-shadow .25s;
}
.vai-launcher:hover { transform: translateY(-2px); box-shadow: 0 0 32px rgba(42, 230, 255, .45), inset 0 0 18px rgba(255, 58, 200, .18); }
.vai-launcher:focus-visible { outline: 2px solid var(--c); outline-offset: 3px; }
.vai-launcher__icon { width: 20px; height: 20px; color: var(--c); filter: drop-shadow(0 0 6px var(--c)); }
.vai-launcher__label { font: 600 11px/1 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .18em; }
.vai-launcher__ring { position: absolute; inset: -1px; z-index: -1; border-radius: inherit; border: 1px solid var(--c); opacity: 0; animation: vai-ping 2.8s cubic-bezier(.16, 1, .3, 1) infinite; }
.vai-launcher__ring--b { animation-delay: 1.4s; border-color: var(--m); }

/* ---------- panel ---------- */
.vai-panel {
  position: fixed; right: 22px; bottom: 22px; z-index: 70;
  display: flex; flex-direction: column;
  width: min(410px, calc(100vw - 32px)); height: min(620px, calc(100dvh - 110px));
  border: 1px solid rgba(42, 230, 255, .35); border-radius: 14px; overflow: hidden;
  background:
    linear-gradient(rgba(42, 230, 255, .035) 1px, transparent 1px) 0 0 / 100% 3px,
    linear-gradient(160deg, rgba(10, 18, 38, .96), rgba(4, 6, 16, .97));
  backdrop-filter: blur(14px);
  color: #e6f3ff;
  box-shadow: 0 20px 60px rgba(0, 0, 0, .55), 0 0 40px rgba(42, 230, 255, .14), inset 0 1px 0 rgba(255, 255, 255, .06);
}
.vai-beam { position: absolute; inset: 0; z-index: 0; pointer-events: none; border-radius: 14px; }
.vai-panel > :not(.vai-beam) { position: relative; z-index: 1; }
.vai-thinking { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 4px 0 2px; }
.vai-thinking span { color: #6f86a3; font: 600 9px/1 ui-monospace, monospace; letter-spacing: .16em; }
/* 金屬環在 shader 第一幀前是隱藏的；送出鈕是主要操作，內容層不受那個 gate 管 */
.vai-sendmetal { flex: none; opacity: 1 !important; } /* 金屬環著色前也要看得到送出鈕（無 WebGL 時更是如此） */
.vai-sendmetal :deep(.metal-fx-content) { visibility: visible !important; opacity: 1 !important; }
.vai-sendmetal :deep(.metal-fx-content button) { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; color: #eaf6ff; transition: color .2s, transform .2s; }
.vai-sendmetal :deep(.metal-fx-content button:hover:not(:disabled)) { color: #fff; transform: scale(1.06); }
.vai-sendmetal :deep(.metal-fx-content button:disabled) { color: #7f93ab; opacity: .6; }
.vai-sendmetal :deep(.metal-fx-content button svg) { width: 16px; height: 16px; }
.vai-sendmetal :deep(.metal-fx-content .stop) { color: #ffb3bd; }
.vai-panel::before {
  content: ''; position: absolute; inset: 0 0 auto; height: 2px;
  background: linear-gradient(90deg, transparent, var(--c), var(--v), var(--m), transparent);
  background-size: 200% 100%; animation: vai-flow 3s linear infinite;
}

.vai-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 14px 12px 16px; border-bottom: 1px solid rgba(42, 230, 255, .14); }
.vai-head__id { display: flex; align-items: center; gap: 11px; }
.vai-head__title { font: 700 14px/1.1 ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .12em; }
.vai-head__title small { color: #6f8aa6; font-weight: 500; font-size: 10px; letter-spacing: .16em; }
.vai-head__sub { margin-top: 4px; color: #5fdcf0; font: 500 9px/1 ui-monospace, monospace; letter-spacing: .2em; opacity: .8; }
.vai-head__actions { display: flex; gap: 4px; }
.vai-head__actions button { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 8px; color: #8fa6bf; transition: color .2s, background .2s; }
.vai-head__actions button:hover:not(:disabled) { color: #fff; background: rgba(42, 230, 255, .1); }
.vai-head__actions button:disabled { opacity: .35; }
.vai-head__actions svg { width: 17px; height: 17px; }
.vai-dot { width: 9px; height: 9px; border-radius: 50%; background: #3dffa8; box-shadow: 0 0 10px #3dffa8; }
.vai-dot.busy { background: var(--c); box-shadow: 0 0 12px var(--c); animation: vai-blink .7s ease-in-out infinite; }

.vai-body { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; scrollbar-width: thin; scrollbar-color: rgba(42, 230, 255, .3) transparent; overscroll-behavior: contain; }

.vai-intro__hello { font: 13px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; color: #bff6ff; }
.vai-intro__hello b { color: var(--c); }
.vai-intro__hint { margin-top: 6px; font-size: 13px; color: #8ea3bb; line-height: 1.6; }
.vai-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.vai-chips button { padding: 7px 11px; border: 1px solid rgba(139, 123, 255, .4); border-radius: 8px; background: rgba(139, 123, 255, .08); color: #d6d0ff; font-size: 12px; text-align: left; transition: border-color .2s, background .2s, transform .2s; }
.vai-chips button:hover { border-color: var(--c); background: rgba(42, 230, 255, .1); transform: translateY(-1px); }

.vai-msg { display: flex; flex-direction: column; gap: 5px; max-width: 92%; animation: vai-in .35s cubic-bezier(.16, 1, .3, 1); }
.vai-msg__tag { font: 600 9px/1 ui-monospace, monospace; letter-spacing: .2em; color: #5a7290; }
.vai-msg__text { padding: 10px 13px; border-radius: 10px; font-size: 13.5px; line-height: 1.65; word-break: break-word; }
.vai-msg--user { align-self: flex-end; align-items: flex-end; }
.vai-msg--user .vai-msg__text { white-space: pre-wrap; background: linear-gradient(135deg, rgba(42, 230, 255, .2), rgba(139, 123, 255, .2)); border: 1px solid rgba(42, 230, 255, .35); border-bottom-right-radius: 3px; }
.vai-msg--assistant .vai-msg__tag { color: var(--c); }
.vai-msg--assistant .vai-msg__text { background: rgba(255, 255, 255, .035); border: 1px solid rgba(255, 255, 255, .08); border-left: 2px solid var(--c); border-bottom-left-radius: 3px; }
.vai-msg--error .vai-msg__text { border-left-color: var(--m); color: #ffc6ec; }

.vai-md :deep(p) { margin: 0 0 .5em; }
.vai-md :deep(p:last-child) { margin-bottom: 0; }
.vai-md :deep(.vai-md__h) { font-weight: 700; color: #bff6ff; }
.vai-md :deep(ul), .vai-md :deep(ol) { margin: .2em 0 .6em; padding-left: 1.2em; }
.vai-md :deep(ul) { list-style: none; padding-left: .2em; }
.vai-md :deep(ul li) { position: relative; padding-left: 1em; }
.vai-md :deep(ul li)::before { content: '▸'; position: absolute; left: 0; color: var(--c); }
.vai-md :deep(ol) { list-style: decimal; }
.vai-md :deep(li) { margin: .2em 0; }
.vai-md :deep(strong) { color: #fff; font-weight: 700; }
.vai-md :deep(code) { padding: .1em .35em; border-radius: 4px; background: rgba(42, 230, 255, .12); color: #9ff2ff; font: .92em ui-monospace, monospace; }
.vai-md :deep(pre) { margin: .5em 0; padding: 10px; border-radius: 8px; background: rgba(0, 0, 0, .4); overflow-x: auto; }
.vai-md :deep(pre code) { padding: 0; background: none; }
.vai-md :deep(a) { color: var(--c); text-decoration: underline; text-underline-offset: 3px; text-decoration-color: rgba(42, 230, 255, .4); }
.vai-md :deep(a:hover) { color: #fff; }
/* 逐字生成時，游標接在最後一段文字後面（不另起一行） */
.vai-md.is-streaming :deep(> p:last-child)::after, .vai-md.is-streaming :deep(> ul:last-child > li:last-child)::after, .vai-md.is-streaming :deep(> ol:last-child > li:last-child)::after, .vai-md.is-streaming :deep(> pre:last-child code)::after { content: ''; display: inline-block; width: 7px; height: 14px; margin-left: 3px; vertical-align: -2px; background: var(--c); box-shadow: 0 0 8px var(--c); animation: vai-blink .8s steps(2) infinite; }
.vai-caret { display: inline-block; width: 7px; height: 14px; margin-left: 2px; vertical-align: -2px; background: var(--c); box-shadow: 0 0 8px var(--c); animation: vai-blink .8s steps(2) infinite; }

.vai-input { display: flex; align-items: flex-end; gap: 8px; margin: 0 12px; padding: 8px 8px 8px 12px; border: 1px solid rgba(42, 230, 255, .25); border-radius: 10px; background: rgba(0, 0, 0, .35); transition: border-color .2s, box-shadow .2s; }
.vai-input:focus-within { border-color: rgba(42, 230, 255, .7); box-shadow: 0 0 18px rgba(42, 230, 255, .18); }
.vai-input__prompt { padding-bottom: 7px; color: var(--c); font: 600 12px/1 ui-monospace, monospace; }
.vai-input textarea { flex: 1; resize: none; max-height: 120px; padding: 5px 0; border: 0; outline: 0; background: transparent; color: #fff; font-size: 14px; line-height: 1.5; }
.vai-input textarea::placeholder { color: #5b6f88; }
.vai-foot { padding: 8px 14px 11px; color: #4f6178; font-size: 10px; text-align: center; }

/* ---------- call button ---------- */
.vai-head__actions .vai-call { display: inline-flex !important; align-items: center; white-space: nowrap; width: auto !important; gap: 6px; padding: 0 12px 0 10px; margin-right: 4px; border-radius: 999px !important; background: linear-gradient(135deg, #22e08a, #10b981) !important; color: #03140c !important; font: 700 11px/1 ui-monospace, monospace; letter-spacing: .12em; box-shadow: 0 0 14px rgba(34, 224, 138, .35); transition: transform .2s, box-shadow .2s, background .2s; }
.vai-head__actions .vai-call:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 0 22px rgba(34, 224, 138, .6); }
.vai-head__actions .vai-call svg { width: 15px; height: 15px; }
.vai-head__actions .vai-call:not(.vai-call--active) svg { animation: vai-ring 2.4s ease-in-out infinite; }
.vai-head__actions .vai-call--active { background: linear-gradient(135deg, #ff3a78, #ff3ac8) !important; color: #fff !important; box-shadow: 0 0 16px rgba(255, 58, 140, .45); }
.vai-call svg.hangup { transform: rotate(135deg); }
.vai-dial { display: flex; align-items: center; gap: 12px; width: 100%; margin-top: 14px; padding: 11px 14px; border: 1px solid rgba(34, 224, 138, .45); border-radius: 12px; background: linear-gradient(135deg, rgba(34, 224, 138, .14), rgba(42, 230, 255, .06)); text-align: left; transition: transform .2s, box-shadow .2s, border-color .2s; }
.vai-dial:hover { transform: translateY(-1px); border-color: #22e08a; box-shadow: 0 0 22px rgba(34, 224, 138, .25); }
.vai-dial__icon { position: relative; display: grid; place-items: center; flex: none; width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #22e08a, #10b981); color: #03140c; box-shadow: 0 0 16px rgba(34, 224, 138, .5); }
.vai-dial__icon::after { content: ''; position: absolute; inset: -4px; border: 1px solid #22e08a; border-radius: 50%; animation: vai-ping 2s cubic-bezier(.16, 1, .3, 1) infinite; }
.vai-dial__icon svg { width: 19px; height: 19px; animation: vai-ring 2.4s ease-in-out infinite; }
.vai-dial__text b { display: block; color: #d9ffef; font-size: 14px; }
.vai-dial__text small { color: #8fb8a6; font-size: 11.5px; }
@keyframes vai-ring { 0%, 60%, 100% { transform: rotate(0); } 64%, 72%, 80% { transform: rotate(-14deg); } 68%, 76% { transform: rotate(14deg); } }

/* ---------- voice ---------- */
.vai-voice { flex: 1; min-height: 0; display: flex; flex-direction: column; align-items: center; padding: 10px 16px 14px; }
.vai-voice__status { margin-top: 2px; font: 600 10px/1.4 ui-monospace, monospace; letter-spacing: .2em; text-align: center; }
.vai-voice__status.is-live { color: #3dffa8; }
.vai-voice__status.is-ai { color: var(--c); }
.vai-voice__status.is-wait { color: #ffc24a; animation: vai-blink 1s ease-in-out infinite; }
.vai-voice__status.is-muted { color: #6f86a3; }
.vai-voice__status.is-err { color: #ff8fd8; letter-spacing: .06em; }
.vai-voice__captions { flex: 1; min-height: 0; width: 100%; margin-top: 12px; overflow-y: auto; display: flex; flex-direction: column; justify-content: flex-end; gap: 8px; font-size: 13px; line-height: 1.6; mask-image: linear-gradient(to bottom, transparent, #000 30%); -webkit-mask-image: linear-gradient(to bottom, transparent, #000 30%); }
.vai-voice__captions b { margin-right: 8px; font: 600 9px/1 ui-monospace, monospace; letter-spacing: .18em; }
.vai-voice__captions .cap--user { color: #c9d6e6; }
.vai-voice__captions .cap--user b { color: #8b7bff; }
.vai-voice__captions .cap--ai { color: #eafaff; }
.vai-voice__captions .cap--ai b { color: var(--c); }
.vai-voice__captions .cap--hint { color: #6f86a3; text-align: center; font-size: 12px; }
.vai-voice__controls { display: flex; gap: 18px; margin-top: 14px; }
.vai-voice__btn { display: grid; place-items: center; width: 52px; height: 52px; border-radius: 50%; border: 1px solid rgba(42, 230, 255, .4); background: rgba(42, 230, 255, .08); color: #dffbff; transition: transform .2s, background .2s, box-shadow .2s; }
.vai-voice__btn:hover { transform: scale(1.06); box-shadow: 0 0 20px rgba(42, 230, 255, .35); }
.vai-voice__btn.off { border-color: rgba(255, 194, 74, .5); color: #ffc24a; background: rgba(255, 194, 74, .08); }
.vai-voice__btn svg { width: 22px; height: 22px; }
.vai-voice__btn--end { border-color: rgba(255, 58, 120, .6); background: linear-gradient(135deg, #ff3a78, #ff3ac8); color: #fff; }
.vai-voice__btn--end:hover { box-shadow: 0 0 24px rgba(255, 58, 160, .5); }
.vai-voice__btn--call { border-color: rgba(34, 224, 138, .6); background: linear-gradient(135deg, #22e08a, #10b981); color: #03140c; }
.vai-voice__btn--call:hover { box-shadow: 0 0 24px rgba(34, 224, 138, .5); }

/* ---------- camera ---------- */
.vai-input__cam { display: grid; place-items: center; flex: none; width: 30px; height: 34px; margin-left: -4px; border-radius: 8px; color: #7d93ad; transition: color .2s, background .2s; }
.vai-input__cam:hover:not(:disabled) { color: var(--c); background: rgba(42, 230, 255, .08); }
.vai-input__cam.on { color: #ff5a8a; }
.vai-input__cam svg { width: 19px; height: 19px; }
.vai-input__prompt { display: none; }
.vai-cam { display: flex; gap: 12px; align-items: center; margin: 0 12px 8px; padding: 8px; border: 1px solid rgba(255, 58, 200, .3); border-radius: 10px; background: rgba(255, 58, 200, .05); animation: vai-in .3s cubic-bezier(.16, 1, .3, 1); }
.vai-cam video { width: 112px; aspect-ratio: 4 / 3; flex: none; object-fit: cover; border-radius: 7px; background: #000; }
.vai-cam__info { min-width: 0; font-size: 11.5px; color: #b9c6d6; }
.vai-cam__info p { margin: 5px 0 7px; line-height: 1.45; }
.vai-cam__actions { display: flex; gap: 6px; }
.vai-cam__actions button { padding: 4px 9px; border: 1px solid rgba(255, 255, 255, .14); border-radius: 6px; color: #d6e2ef; font-size: 11px; transition: border-color .2s; }
.vai-cam__actions button:hover { border-color: var(--c); }
.vai-cam__err { padding: 2px 4px; color: #ff9fd9; font-size: 12px; }
.vai-cam__badge { display: inline-flex; align-items: center; gap: 5px; color: #ff5a8a; font: 700 9px/1 ui-monospace, monospace; letter-spacing: .2em; }
.vai-cam__badge i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; box-shadow: 0 0 8px currentColor; animation: vai-blink 1s ease-in-out infinite; }
video.mirror { transform: scaleX(-1); }
.vai-msg__img { display: block; width: 100%; max-width: 220px; margin-bottom: 7px; border-radius: 7px; border: 1px solid rgba(255, 255, 255, .15); }

.vai-voice__stage { position: relative; }
.vai-voice__cam { position: absolute; right: -14px; bottom: 4px; width: 92px; padding: 3px; border: 1px solid rgba(255, 58, 200, .55); border-radius: 10px; background: rgba(4, 6, 16, .85); box-shadow: 0 0 18px rgba(255, 58, 200, .25); animation: vai-in .3s cubic-bezier(.16, 1, .3, 1); }
.vai-voice__cam video { display: block; width: 100%; aspect-ratio: 3 / 4; object-fit: cover; border-radius: 7px; background: #000; }
.vai-voice__cam .vai-cam__badge { position: absolute; left: 8px; top: 8px; text-shadow: 0 1px 3px #000; }
.vai-voice__scene { max-width: 100%; margin-top: 6px; overflow: hidden; color: #8fa6bf; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.vai-voice__captions .cap--err { color: #ff9fd9; text-align: center; font-size: 12px; }
.vai-voice__btn.on { border-color: rgba(255, 58, 200, .6); background: rgba(255, 58, 200, .14); color: #ffd3f0; }

/* ---------- voice lead form ---------- */
.vai-vlead { width: 100%; margin-top: 10px; padding: 12px; border: 1px solid rgba(42, 230, 255, .35); border-radius: 12px; background: rgba(6, 14, 30, .85); animation: vai-in .35s cubic-bezier(.16, 1, .3, 1); }
.vai-vlead__title { margin-bottom: 9px; color: #bff6ff; font-size: 12px; line-height: 1.5; }
.vai-vlead__row { display: grid; grid-template-columns: 1fr 1.4fr; gap: 8px; }
.vai-vlead input { min-width: 0; padding: 8px 10px; border: 1px solid rgba(42, 230, 255, .25); border-radius: 8px; background: rgba(0, 0, 0, .35); color: #fff; font-size: 13px; outline: 0; transition: border-color .2s, box-shadow .2s; }
.vai-vlead input:focus { border-color: var(--c); box-shadow: 0 0 12px rgba(42, 230, 255, .2); }
.vai-vlead__err { margin-top: 7px; color: #ff9fd9; font-size: 11.5px; }
.vai-vlead__actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px; }
.vai-vlead__actions button { padding: 7px 14px; border-radius: 8px; background: linear-gradient(135deg, var(--c), var(--v)); color: #041018; font-size: 12.5px; font-weight: 700; transition: opacity .2s; }
.vai-vlead__actions button:disabled { opacity: .45; }
.vai-vlead__actions button.ghost { background: transparent; border: 1px solid rgba(255, 255, 255, .15); color: #b9c6d6; font-weight: 500; }
.vai-vlead .vai-lead { margin: 0; }
@media (max-width: 640px) { .vai-vlead__row { grid-template-columns: 1fr; } .vai-vlead input { font-size: 16px; } }

/* ---------- lead ---------- */
.vai-lead { display: flex; align-items: center; gap: 10px; margin-top: 4px; padding: 10px 12px; border: 1px solid rgba(61, 255, 168, .45); border-radius: 10px; background: linear-gradient(135deg, rgba(61, 255, 168, .12), rgba(42, 230, 255, .06)); animation: vai-in .4s cubic-bezier(.16, 1, .3, 1); }
.vai-lead svg { flex: none; width: 22px; height: 22px; padding: 3px; border-radius: 50%; background: #3dffa8; color: #03140c; }
.vai-lead b { display: block; color: #b9ffdd; font-size: 13px; }
.vai-lead span { color: #8fb8a6; font-size: 11.5px; }

/* ---------- transitions ---------- */
.vai-panel-enter-active { transition: opacity .35s ease, transform .45s cubic-bezier(.16, 1, .3, 1), clip-path .45s cubic-bezier(.16, 1, .3, 1); }
.vai-panel-leave-active { transition: opacity .2s ease, transform .2s ease, clip-path .2s ease; }
.vai-panel-enter-from, .vai-panel-leave-to { opacity: 0; transform: translateY(16px) scale(.97); clip-path: inset(100% 0 0 0 round 14px); }
.vai-panel-enter-to, .vai-panel-leave-from { clip-path: inset(0 0 0 0 round 14px); }

@keyframes vai-ping { 0% { opacity: .7; transform: scale(1); } 100% { opacity: 0; transform: scale(1.35, 1.6); } }
@keyframes vai-flow { to { background-position: -200% 0; } }
@keyframes vai-blink { 50% { opacity: .25; } }
@keyframes vai-in { from { opacity: 0; transform: translateY(8px); } }

@media (max-width: 640px) {
  .vai-launcher { right: 14px; bottom: 14px; height: 44px; padding: 0 14px 0 12px; }
  .vai-panel { right: 8px; left: 8px; bottom: 8px; width: auto; height: min(78dvh, calc(100dvh - 80px)); }
  .vai-input textarea { font-size: 16px; } /* 避免 iOS 聚焦時自動放大 */
}

@media (prefers-reduced-motion: reduce) {
  .vai *, .vai *::before, .vai *::after { animation: none !important; transition-duration: .01ms !important; }
}
</style>
