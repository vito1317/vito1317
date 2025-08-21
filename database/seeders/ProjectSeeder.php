<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Project;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        Project::truncate();

        $projects = [
            [
                'title' => 'Aura News 全端新聞平台',
                'description' => 'Aura News 是一個全端新聞平台專案，包含後端（Laravel, PHP）與前端（Vue 3, Vite, TailwindCSS），支援 AI 自動抓取與撰寫新聞、RESTful API、現代化前台介面與自動化部署。',
                'technologies' => 'Laravel, PHP, MySQL, Vue 3, Vite, TailwindCSS, Node.js, Composer, Gemini AI, GitHub Actions',
                'github_url' => 'https://github.com/vito1317/aura-news',
                'live_url' => 'https://news.vito1317.com',
                'image' => '/images/aura-news.png',
            ],
            [
                'title' => 'Dwep Minecraft Server Website',
                'description' => '一個為 Dwep Minecraft 伺服器打造的專屬入口網站。提供即時的伺服器狀態（如線上人數）、世界地圖、聊天同步等功能。網站整合了 Discord 登入，並詳細介紹伺服器特色、規則與加入方式，為玩家提供一站式的資訊與社群入口。',
                'technologies' => 'Laravel, PHP, MySQL, JavaScript, HTML, CSS, jQuery, Bootstrap, Minecraft Server API, Discord API',
                'github_url' => null,
                'live_url' => 'https://dwep.vito1317.com',
                'image' => '/images/dwep-server.png',
            ],
            [
                'title' => '奈奈 - 智能陪伴機器人',
                'description' => '一個基於 Google Gemini 模型的多功能 Discord 機器人。它不僅提供溫暖、理解和專業的陪伴，還具備自行上網搜尋資料、瀏覽網站的能力，以提供更豐富精確的回覆，並整合了多項伺服器管理與點數系統功能。',
                'technologies' => 'Python, Gemini, Discord.py',
                'github_url' => 'https://github.com/vito1317/nana-bot',
                'live_url' => null,
                'image' => '/images/nana-bot.png',
            ],
            [
                'title' => '蝦皮自動化工具 (Chrome 擴充功能)',
                'description' => '一個非官方 Chrome 擴充功能，旨在自動化蝦皮內部物流平台的重複性操作，如自動叫號、結帳、TTS語音播報、物流箱與TO單自動刷取、無感修正無效訂單等，大幅提升工作效率。僅供內部員工使用。',
                'technologies' => 'JavaScript, Chrome Extension',
                'github_url' => 'https://github.com/vito1317/Shopee-Automation-tool',
                'live_url' => 'https://chromewebstore.google.com/detail/gjlkkpgkdecjgcnekbgbcidokfcnciig',
                'image' => '/images/shopee-tool.png',
            ],
            [
                'title' => 'Taipei Booking (台北旅遊預訂系統)',
                'description' => '一個使用 Java Spring Boot 開發的全功能台北景點預訂網站。提供景點瀏覽、關鍵字搜尋、分頁功能，並整合了使用 JWT 的安全使用者註冊與登入系統。使用者登入後可以預訂行程、管理訂單，並設有後台管理面板。',
                'technologies' => 'Java, Spring Boot, Spring Security, MySQL, JavaScript',
                'github_url' => 'https://github.com/vito1317/Taipei-Booking',
                'live_url' => null,
                'image' => '/images/taipei-booking.png',
            ],
            [
                'title' => 'Search Engine Tool (多平台搜尋API)',
                'description' => '一個 Python 版本的搜尋引擎工具 API，使用 Selenium、requests 和 BeautifulSoup4 進行開發。它封裝了對 Google、Bing、Yahoo 等主流搜尋引擎的調用，讓開發者可以透過簡單的函式呼叫來獲取結構化的搜尋結果。',
                'technologies' => 'Python, Selenium, BeautifulSoup4',
                'github_url' => 'https://github.com/vito1317/search-engine-tool-vito1317',
                'live_url' => null,
                'image' => '/images/search-engine.png',
            ],
            [
                'title' => 'HD-C 指令程式碼產生器',
                'description' => '一個為硬體裝修丙級檢定術科所設計的工具。它能根據使用者輸入的崗位號碼、姓名等資料，即時產生檢定所需的 HTML 網頁程式碼與預覽，並能自動生成 Windows (.bat) 與 Linux 的腳本，大幅簡化檢定流程。',
                'technologies' => 'JavaScript, HTML, CSS',
                'github_url' => 'https://github.com/vito1317/HD-C',
                'live_url' => 'https://vito1317.github.io/HD-C/',
                'image' => '/images/hd-c.png',
            ],
            [
                'title' => '台南女中合作社範例網站',
                'description' => '為台南女中合作社製作的靜態網頁範例。',
                'technologies' => 'HTML, CSS',
                'github_url' => 'https://github.com/vito1317/Tainan-Girls-High-School-Cooperative-Sample-Website',
                'live_url' => 'https://vito1317.github.io/Tainan-Girls-High-School-Cooperative-Sample-Website/',
                'image' => '/images/tainan-girls.png',
            ]
        ];

        foreach ($projects as $index => $projectData) {
            $projectData['display_order'] = $index;
            Project::create($projectData);
        }
    }
}