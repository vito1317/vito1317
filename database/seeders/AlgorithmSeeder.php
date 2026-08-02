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
                'tagline' => '零調校常數的閉式共識演算法——訊號不足時位元等同 Self-Consistency',
                'category' => 'LLM 推理期演算法',
                'status_label' => '正面結果・已成論文（IEEE 格式）',
                'status_type' => 'positive',
                'problem' => 'Self-Consistency 把每條推理鏈當成等值的一票；已發表的信心加權方法（CISC、DeepConf、各種加權 SC）全都只會「正向」加權——一旦模型的信心校準崩壞、信心與正確性負相關，這些方法要嘛貼死 SC 地板，要嘛直接被毒化。沒有任何已發表方法能在零標籤下辨識信心通道的正負號，也沒有人回答「訊號不足時該怎麼辦」。',
                'approach' => '整條方法收成一個沒有任何調校常數的式子：以題內信心中位秩的 van der Waerden 分數 φ 取代原始信心值（對一切嚴格單調失真免疫），指數強度 γ = z√(2+z²) 完全由資料導出（z = Φ⁻¹(AUC 估計)，經 James–Stein 收縮與 Bayes 判別連結）。訊號不足時 γ 恰為 0，投票位元等同 Self-Consistency——「不確定時不動手」是設計行為而非保守妥協。無標籤變體（TACT-LF）用去重偽標籤 + CCN 衰減理論在零標籤下辨識信心通道的正負號；不可解情境由警報機制（E2/E4）誠實棄權。',
                'formula' => "â_q = argmax_A Σ_{i: a_i = A} exp(γ·φ_i)\nγ = z·√(2 + z²)，z = Φ⁻¹(AUC 估計)   ← 全式零調校常數\nφ_i = 題內信心中位秩的 van der Waerden 分數（單調失真免疫）\n訊號不足 ⇒ γ = 0 ⇒ 位元等同 Self-Consistency（棄權即設計）",
                'highlights' => [
                    '負相關信心（κ=−0.6）情境準確率 0.807 → 1.000，所有已發表方法在整個負半軸貼死 SC 地板；四項預先登記證偽條件全數存活',
                    '信心通道第一次在真實資料上被證實：MATH-L5 × Haiku 混合答案池內 pooled D̂ = +0.250（z = +2.54）',
                    '真實資料上正確棄權：訊號密度不足時警報觸發、γ 收到 0 與 SC 位元相同，而亂動手的 best-conf 基線付出 −4.5pp 代價',
                    '完整寫成 8 頁 IEEE 格式論文（含中文版與參數逐項詳解），一行式與等價性皆有形式化證明與測試',
                    '棄權設計被薄窗量測證明不是保守而是唯一正確行為——可作用分層只佔題目 2–7.5%',
                ],
                'metrics' => [
                    ['label' => 'κ=−0.6 準確率', 'value' => '0.807 → 1.000'],
                    ['label' => '真實資料通道', 'value' => 'z = +2.54'],
                    ['label' => '證偽條件', 'value' => '4/4 存活'],
                    ['label' => '測試', 'value' => '98 tests'],
                ],
                'links' => [
                    ['label' => 'GitHub', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus'],
                    ['label' => '論文 PDF', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/paper/tact.pdf'],
                    ['label' => '真實資料報告', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/REPORT-TACT-HARD.md'],
                    ['label' => '演算法規格', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/SPEC-TACT.md'],
                ],
                'tags' => 'LLM Reasoning, Self-Consistency, Rank Statistics, James–Stein Shrinkage, Label-free, Closed-form',
            ],
            [
                'title' => '薄窗 — 無標籤聚合的結構性邊界',
                'tagline' => '跨兩個領域、五個基質的量測：聚合方法能作用的空間只有 2–7.5%，且難度上升不會讓它變寬',
                'category' => 'LLM 推理期演算法・結構性發現',
                'status_label' => '結構性發現・跨 5 基質直接量測',
                'status_type' => 'research',
                'problem' => '推理期共識研究隱含一個假設：只要方法夠聰明，就能從取樣池中救回更多答案。但沒有人量測過「可救援的空間」到底有多大——多數決已對的題目方法無事可做，正解不在池內的題目聚合無能為力，方法真正能改變結果的只有中間那一層。',
                'approach' => '把每個基質的題目分成三個形態：飽和（基線已正確）、能力牆（池中沒有正解）、窗口（可救援）——窗口 = oracle 上限 − 行為叢集基線。跨無標籤 QA（GSM8K/CSQA、MATH-L5、AIME/AMC）與可執行真值的程式碼生成（HumanEval+/MBPP+、LeetCode Med/Hard 沙箱直測）逐一量測，並以 oracle@N 外推檢驗候選預算的影響。',
                'formula' => "窗口 = oracle(池內存在正解) − 基線(最大行為叢集)\nLeetCode Med/Hard 直測：0.825 − 0.750 = 0.075（CI95: 0.026–0.199）\n難度上升 ⇒ 題目從「飽和」直接跳到「能力牆」，窗口不變寬",
                'highlights' => [
                    '無標籤 QA 窗口 2–5%、HumanEval+/MBPP+ 3.56%、LeetCode Med/Hard 沙箱直測 7.5%——兩個領域、五個基質同一形狀',
                    '文獻重建值高達 24–27% 的基質，直接量測只有 7.5%——差異來源是候選預算假設（n≈200 vs 實測 n=8），oracle@N 外推證實 N=32 也只到 7.4%',
                    '這條邊界一次解釋了倉庫裡六個死亡設計的死因（GRAVEYARD.md 完整記錄每個設計與屍檢）',
                    '證明 TACT 的棄權設計（γ=0）在此 régime 下不是保守，而是唯一正確的行為',
                    '方法論警告：批次收集軌跡的實驗必須把批次大小當實驗參數報告——同一難度層 SC 從 0.40 到 0.888 只因批次壓力不同',
                ],
                'metrics' => [
                    ['label' => 'QA 窗口', 'value' => '2–5%'],
                    ['label' => '程式碼窗口（直測)', 'value' => '7.5%'],
                    ['label' => '量測基質', 'value' => '5'],
                    ['label' => '死亡設計歸因', 'value' => '6/6'],
                ],
                'links' => [
                    ['label' => 'GitHub', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus'],
                    ['label' => '窗口實測報告', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/REPORT-G1.md'],
                    ['label' => '死亡設計目錄', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/GRAVEYARD.md'],
                ],
                'tags' => 'Inference-time Scaling, Oracle Gap, Structural Boundary, Code Generation, Negative Result',
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
                    '這份「屍檢」催生了整個研究計畫：TACT 演算法、薄窗結構性邊界，以及收錄六個死亡設計的 GRAVEYARD——每個死因都有完整歸因',
                ],
                'metrics' => [
                    ['label' => '回音情境修復', 'value' => '0.320 → 0.880'],
                    ['label' => '基線成本優勢', 'value' => '4–5×'],
                    ['label' => '消融歸因', 'value' => 'r = +0.935'],
                ],
                'links' => [
                    ['label' => 'GitHub', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus'],
                    ['label' => '實驗報告', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/REPORT.md'],
                    ['label' => '死亡設計目錄', 'url' => 'https://github.com/vito1317/adaptive-reasoning-consensus/blob/main/docs/GRAVEYARD.md'],
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
