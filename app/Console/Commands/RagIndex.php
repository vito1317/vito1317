<?php

namespace App\Console\Commands;

use App\Services\SiteRag;
use Illuminate\Console\Command;

class RagIndex extends Command
{
    protected $signature = 'rag:index {--query= : 建好後順便測試檢索這句話}';

    protected $description = '重建 V.AI 的 RAG 索引（資料庫研究內容 + storage/app/rag/ 底下的論文 PDF / md / txt）';

    public function handle(): int
    {
        if (! is_dir(SiteRag::docsPath())) {
            mkdir(SiteRag::docsPath(), 0775, true);
        }

        [$docs, $chunks] = SiteRag::build();
        $this->info("索引完成：{$docs} 份文件、{$chunks} 個段落 → ".SiteRag::indexPath());

        if ($q = $this->option('query')) {
            foreach (SiteRag::search($q) as $i => $hit) {
                $this->line(($i + 1).". [{$hit['score']}] {$hit['source']} #{$hit['part']}：".mb_substr(preg_replace('/\s+/u', ' ', $hit['text']), 0, 90).'…');
            }
        }

        return self::SUCCESS;
    }
}
