<?php

namespace Database\Seeders;

use App\Models\Algorithm;
use Illuminate\Database\Seeder;

class AlgorithmSeeder extends Seeder
{
    public function run(): void
    {
        Algorithm::truncate();

        $algorithms = [
            [
                'title' => 'TACT — 信心穩健加權共識',
                'tagline' => '零標籤也能辨識信心正負號的推理期 LLM 共識演算法',
                'category' => 'LLM 推理期演算法',
                'status_label' => '正面結果・證偽條件 4/4 存活',
                'status_type' => 'positive',
                'problem' => 'Self-Consistency 把每條推理鏈當成等值的一票；已發表的信心加權方法（CISC、DeepConf、各種加權 SC）全都只會「正向」加權——一旦模型的信心校準崩壞、信心與正確性負相關，這些方法要嘛貼死 SC 地板，要嘛直接被毒化。沒有任何已發表方法能在零標籤下辨識信心通道的正負號。',
                'approach' => '以題內信心「中位秩」的 van der Waerden 分數取代原始信心值，對一切嚴格單調失真免疫；權重強度 γ 不靠網格搜尋，而是由混合 van Elteren Somers\' D（帶精確 tie 校正與逐題 jackknife SE）經「正部 James–Stein 收縮 → Bayes 判別連結」解析推導——無證據時 γ=0，位元級退回 SC。無標籤變體（TACT-LF）用去重加權多數決偽標籤 + CCN 衰減理論 + split-half 去衰減，在完全沒有標籤的情況下辨識出信心的正負號與強度；自信回音等理論不可解情境由警報機制誠實退回 SC。',
                'formula' => "w_i = exp(γ·φ_i)\nφ_i = 題內信心中位秩的 van der Waerden 分數（單調失真免疫）\nγ  ← 混合 van Elteren Somers' D → 正部 James–Stein 收縮 → Bayes 判別連結\n     （無證據 ⇒ γ = 0 ⇒ 位元級等同 Self-Consistency）",
                'highlights' => [
                    '負相關信心（κ=−0.6）情境準確率 0.807 → 1.000，所有已發表方法在整個負半軸貼死 SC 地板',
                    '零標籤變體 TACT-LF 與使用 200 個標籤的 TACT-dev 幾乎逐點重合，負號辨識 z = −17.6',
                    '單調壓扁情境拿下 1.000，超越原始值權重家族的理論上限 oracle（0.965）',
                    '自信回音下由 E1 警報正確拒絕並退回 SC——條件保證按設計運作，不假裝解決理論上不可解的符號歧義（Parisi / Hui–Walter）',
                    '四項預先登記的證偽條件全數存活，收縮成本與適用邊界皆誠實量化',
                ],
                'metrics' => [
                    ['label' => 'κ=−0.6 準確率', 'value' => '0.807 → 1.000'],
                    ['label' => '零標籤符號辨識', 'value' => 'z = −17.6'],
                    ['label' => '證偽條件', 'value' => '4/4 存活'],
                    ['label' => '單元測試', 'value' => '69 tests'],
                ],
                'links' => [
                    ['label' => 'GitHub', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus'],
                    ['label' => '實驗報告', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/REPORT-TACT.md'],
                    ['label' => '演算法規格', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/SPEC-TACT.md'],
                ],
                'tags' => 'LLM Reasoning, Self-Consistency, Rank Statistics, James–Stein Shrinkage, Label-free',
            ],
            [
                'title' => 'RLEV-VoI — 冗餘折扣共識 + 資訊價值停止',
                'tagline' => '修復回音式重複投票的共識演算法——以及一份誠實的負面結果報告',
                'category' => 'LLM 推理期演算法',
                'status_label' => '負面結果・誠實發表',
                'status_type' => 'negative',
                'problem' => 'Self-Consistency 把取樣出來的推理鏈當成獨立投票，但鏈之間是相關的——一個熱門但錯誤的推理模板可以靠複製自己贏得多數決，在逐字回音情境下準確率崩潰到接近隨機（0.320），而且越取樣越差。固定取樣數 K 同時也在浪費算力：簡單題抽 40 條是浪費，難題抽 5 條又不夠。',
                'approach' => 'DDWC（冗餘折扣加權共識）以相似度質量的倒數 w_i = 1/Σ_j S_ij 作為有效權重，讓 m 條近乎相同的鏈總權重收斂到 1 票而非 m 票（Rao–Scott 設計效應修正的精神）；VoI-Stop 對有效票數維護 Dirichlet 後驗，逐鏈評估「領先者是真眾數」的機率與每 token 邊際資訊價值，不划算就停。紅隊審查抓出早期版本以 Kish 離散度比當有效樣本數的方向性錯誤（完全相同的 K 份複本會被回報成 K 而非 1），修正後固化為單元測試。',
                'formula' => "w_i = 1 / Σ_j S_ij          （有效權重，m 條回音鏈 → 總計 1 票）\nN_a^eff = Σ_{i:a_i=a} w_i    （答案 a 的有效票數）\nα_a = α₀ + N_a^eff           （Dirichlet 後驗 → P_stable 與 VoI 停止）",
                'highlights' => [
                    '診斷為真且方法有效：回音情境準確率 0.320 → 0.880（p ≈ 7e-68）',
                    '但簡單得多的 n-gram 去重基線（RASC-lite）在每個情境全面勝出——同等準確率只需 1/4 ~ 1/5 成本',
                    '消融實驗把有效機制精確定位在「逐字重複偵測」（與增益相關 +0.935），而非反相似度加權或 VoI',
                    '預先登記的五項證偽條件觸發 4.5 項，README 直接以負面結果開頭：受回音之苦先做最簡單的去重',
                    '這份「屍檢」直接催生了第二階段的 TACT——CISC 類方法在信心校準崩壞時毀滅的觀察，成為新演算法的起點',
                ],
                'metrics' => [
                    ['label' => '回音情境修復', 'value' => '0.320 → 0.880'],
                    ['label' => '基線成本優勢', 'value' => '4–5×'],
                    ['label' => '消融歸因', 'value' => 'r = +0.935'],
                ],
                'links' => [
                    ['label' => 'GitHub', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus'],
                    ['label' => '實驗報告', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/REPORT.md'],
                    ['label' => '數學推導', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/ALGORITHM.md'],
                ],
                'tags' => 'Self-Consistency, Effective Sample Size, Rao–Scott, Value of Information, Negative Result',
            ],
            [
                'title' => '自適應 ML 行為異常偵測引擎',
                'tagline' => '為每個站點自動建立行為基線的三階模型階梯，攔截 WAF 規則無法覆蓋的未知威脅',
                'category' => '資安 × 機器學習（Security One SOC）',
                'status_label' => '生產環境運行中',
                'status_type' => 'production',
                'problem' => '傳統 WAF 規則引擎只能攔截已知攻擊模式——零日漏洞、變形 payload 與低速慢掃這類「未知威脅」永遠跑在規則更新前面。需要一道獨立於規則之外、能自我學習每個站點正常行為的最後防線。',
                'approach' => '對每個請求提取 16 維特徵向量（路徑結構、查詢字串熵、請求本體熵、標頭特徵、24 小時週期化時間、IP 速率與端點稀有度），為每個站點/端點建立獨立行為基線 Profile。依累積標記量自動升級三階評分模型：Statistical（加權 |z-score|，無監督冷啟動）→ Logistic（監督式線性）→ MLP（兩層前饋網路）。閾值依 f1 / fp_min / recall 目標函數每日自動校準，Profile 支援跨站點繼承以加速冷啟動，管理者的 TP/FP 標記直接迴流訓練集形成閉環。',
                'formula' => "x ∈ R¹⁶  （路徑、查詢、本體、標頭、時間、行為 六大類特徵）\nStatistical: score = Σ wₖ·|zₖ| / Σ wₖ      （無監督冷啟動）\nLogistic → MLP（ReLU + Sigmoid）             （隨標記量自動升級）",
                'highlights' => [
                    '16 維特徵涵蓋路徑深度、查詢/本體 Shannon 熵、特殊字元比例、UA 特徵、時間週期化與端點稀有度',
                    'statistical → logistic → MLP 三階模型階梯，依 TP/FP 標記量自動升級，冷啟動門檻 500 樣本',
                    '每小時排程訓練、每日 04:00 自動校準閾值，靈敏度三檔 × 目標函數三種可調',
                    'Profile 跨站點繼承（inherited_from_profile_id）讓新站點跳過冷啟動期',
                    '獨立於規則引擎運作，作為未知威脅的最後一道防線，誤判可一鍵標記 FP 迴流再訓練',
                ],
                'metrics' => [
                    ['label' => '特徵維度', 'value' => '16'],
                    ['label' => '評分模型', 'value' => '3 階'],
                    ['label' => '冷啟動門檻', 'value' => '500 樣本'],
                ],
                'links' => [
                    ['label' => '產品官網', 'url' => 'https://cybersecureone.com'],
                ],
                'tags' => 'Anomaly Detection, Unsupervised Learning, Logistic Regression, MLP, WAF',
            ],
        ];

        foreach ($algorithms as $index => $data) {
            $data['display_order'] = $index;
            Algorithm::create($data);
        }
    }
}
