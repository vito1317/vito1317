<?php

namespace App\Console\Commands;

use App\Mail\InboxAutoReply;
use App\Services\SiteAi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LoggerInterface;

/**
 * 讀取 service@vito1317.com 收件匣的新信，對「真人寫的個人 / 商務來信」由 V.AI 自動回覆。
 *
 * 安全設計：
 * - 收件匣以唯讀開啟、內文以 FT_PEEK 讀取 → 不會把信標成已讀，也不改任何旗標
 * - 第一次執行只記錄目前的 UID 起點，不回覆既有的舊信
 * - 通知 / 宣傳 / 自動回覆 / 郵件列表 / 退信 / noreply 一律略過（先看標頭，再由 AI 分類）
 * - 寄出的回覆帶 Auto-Submitted: auto-replied（RFC 3834）與 X-VAI-Auto，避免與其他自動回覆互相循環
 * - 同一寄件人 24 小時只回一次；全域每小時上限
 */
class InboxAiReply extends Command
{
    protected $signature = 'inbox:ai-reply
        {--dry-run : 只分類與產生回覆，不寄信}
        {--limit=10 : 每次最多處理幾封}
        {--uid=* : 測試用：只處理指定 UID（必須搭配 --dry-run）}';

    protected $description = '以 V.AI 自動回覆收件匣中的新來信（略過通知與宣傳郵件）';

    private const CACHE_PREFIX = 'inbox-ai:';

    private const MAX_PER_HOUR = 30;

    private const MAX_AGE_HOURS = 48;

    /** 寄件者帳號名稱看起來是系統 / 通知 / 行銷信箱 */
    private const SYSTEM_SENDER = '/^(no-?reply|do-?not-?reply|donotreply|mailer-daemon|postmaster|bounces?|notifications?|notify|notifier|alerts?|newsletters?|news|marketing|promo(tions?)?|campaigns?|digest|updates?|automated|system|robot|daemon)([._+\-].*)?@/i';

    /** 有這些標頭的信是群發 / 行銷平台寄出的 */
    private const BULK_HEADERS = [
        'list-id', 'list-unsubscribe', 'list-post', 'list-help',
        'x-autoreply', 'x-autorespond', 'x-auto-response-suppress',
        'feedback-id', 'x-campaign', 'x-campaign-id', 'x-campaignid', 'x-mailchimp-campaign', 'x-mc-user',
        'x-sg-eid', 'x-sendgrid-eid', 'x-mailgun-tag', 'x-mailgun-variables', 'x-marketoid', 'x-ems-mailid',
        'x-newsletter', 'x-mailer-recptid', 'x-vai-auto',
    ];

    private LoggerInterface $log;

    private string $server;

    public function handle(): int
    {
        $this->log = Log::build(['driver' => 'single', 'path' => storage_path('logs/inbox-ai.log')]);

        if (! config('services.inbox_ai.enabled')) {
            $this->line('inbox AI disabled');

            return self::SUCCESS;
        }

        $cfg = config('services.inbox_ai');
        $this->server = '{'.$cfg['host'].':'.$cfg['port'].'/imap/ssl}';

        imap_timeout(IMAP_OPENTIMEOUT, 15);
        imap_timeout(IMAP_READTIMEOUT, 30);
        $mb = @imap_open($this->server.'INBOX', $cfg['username'], $cfg['password'], OP_READONLY, 1);
        if (! $mb) {
            $this->log->warning('IMAP open failed', ['error' => imap_last_error()]);
            imap_errors();

            return self::FAILURE;
        }

        try {
            $this->process($mb);
        } finally {
            imap_close($mb);
            imap_errors(); // 清掉 imap 擴充累積的通知，避免結束時噴到 stderr
            imap_alerts();
        }

        return self::SUCCESS;
    }

    private function process($mb): void
    {
        if ($uids = $this->option('uid')) {
            if (! $this->option('dry-run')) {
                $this->error('--uid 只能搭配 --dry-run 使用');

                return;
            }
            foreach ($uids as $uid) {
                $this->handleMessage($mb, (int) $uid, ignoreAge: true);
            }

            return;
        }

        $status = imap_status($mb, $this->server.'INBOX', SA_UIDNEXT | SA_UIDVALIDITY);
        $validity = (string) $status->uidvalidity;
        $lastUid = Cache::get(self::CACHE_PREFIX.'last_uid');

        // 第一次執行（或信箱 UIDVALIDITY 改變）：只記錄起點，不處理任何既有信件
        if ($lastUid === null || Cache::get(self::CACHE_PREFIX.'uidvalidity') !== $validity) {
            Cache::forever(self::CACHE_PREFIX.'uidvalidity', $validity);
            Cache::forever(self::CACHE_PREFIX.'last_uid', $status->uidnext - 1);
            $this->log->info('baseline set', ['uid' => $status->uidnext - 1]);
            $this->info('baseline set at UID '.($status->uidnext - 1).' — existing mail will not be answered');

            return;
        }

        if ($status->uidnext - 1 <= $lastUid) {
            return; // 沒有新信
        }

        $overview = imap_fetch_overview($mb, ($lastUid + 1).':*', FT_UID) ?: [];
        $overview = array_values(array_filter($overview, fn ($o) => $o->uid > $lastUid));
        usort($overview, fn ($a, $b) => $a->uid <=> $b->uid);
        $overview = array_slice($overview, 0, (int) $this->option('limit'));

        foreach ($overview as $o) {
            try {
                $this->handleMessage($mb, (int) $o->uid);
            } catch (\Throwable $e) {
                $this->log->error('message failed', ['uid' => $o->uid, 'error' => $e->getMessage()]);
            }
            // 不論成功與否都往前推進，避免一封壞信卡住整個佇列
            if (! $this->option('dry-run')) {
                Cache::forever(self::CACHE_PREFIX.'last_uid', (int) $o->uid);
            }
        }
    }

    private function handleMessage($mb, int $uid, bool $ignoreAge = false): void
    {
        $rawHeader = imap_fetchheader($mb, $uid, FT_UID);
        $headers = $this->parseHeaders($rawHeader);
        $parsed = imap_rfc822_parse_headers($rawHeader);

        $fromAddr = $this->address($parsed->from[0] ?? null);
        $replyTo = $this->address($parsed->reply_to[0] ?? null) ?: $fromAddr;
        $fromName = $this->decode($parsed->from[0]->personal ?? '') ?: strstr($fromAddr, '@', true);
        $subject = $this->decode($headers['subject'] ?? '');
        $messageId = trim($headers['message-id'] ?? '', " <>\t");
        $date = strtotime($headers['date'] ?? '') ?: time();

        $ctx = ['uid' => $uid, 'from' => $fromAddr, 'subject' => mb_substr($subject, 0, 120)];

        if ($reason = $this->skipReason($headers, $fromAddr, $replyTo, $ignoreAge ? time() : $date)) {
            $this->log->info('skip', $ctx + ['reason' => $reason]);
            $this->line("skip #{$uid} {$fromAddr}: {$reason}");

            return;
        }

        $body = $this->fetchText($mb, $uid);
        if (mb_strlen(trim($body)) < 2 && trim($subject) === '') {
            $this->log->info('skip', $ctx + ['reason' => 'empty']);

            return;
        }

        $verdict = SiteAi::classifyEmail($fromName, $fromAddr, $subject, $body);
        if (! ($verdict['reply'] ?? false)) {
            $this->log->info('skip', $ctx + ['reason' => 'ai:'.($verdict['category'] ?? 'unknown'), 'why' => $verdict['reason'] ?? null]);
            $this->line("skip #{$uid} {$fromAddr}: ai says ".($verdict['category'] ?? 'unknown'));

            return;
        }

        $senderKey = self::CACHE_PREFIX.'sender:'.strtolower($replyTo);
        if (Cache::has($senderKey)) {
            $this->log->info('skip', $ctx + ['reason' => 'sender replied within 24h']);

            return;
        }
        $hourKey = self::CACHE_PREFIX.'hour:'.date('YmdH');
        if (Cache::get($hourKey, 0) >= self::MAX_PER_HOUR) {
            $this->log->warning('hourly cap reached', $ctx);

            return;
        }

        $reply = SiteAi::draftEmailReplyFor($fromName, $subject, $body, 'email');
        if ($reply === null) {
            $this->log->warning('draft failed', $ctx);

            return;
        }

        if ($this->option('dry-run')) {
            $this->info("[dry-run] would reply #{$uid} → {$replyTo} ({$verdict['category']})\n{$reply}\n");

            return;
        }

        $mail = new InboxAutoReply(
            replyText: $reply,
            originalSubject: $subject,
            originalFrom: trim($fromName.' <'.$fromAddr.'>'),
            originalDate: date('Y-m-d H:i', $date),
            originalBody: mb_substr($body, 0, 3000),
            inReplyTo: $messageId ?: null,
            references: $this->references($headers, $messageId),
        );

        $sent = Mail::to($replyTo, $fromName ?: null)->send($mail);

        Cache::put($senderKey, true, now()->addDay());
        Cache::put($hourKey, Cache::get($hourKey, 0) + 1, now()->addHours(2));
        $this->log->info('replied', $ctx + ['to' => $replyTo, 'category' => $verdict['category'] ?? null]);
        $this->info("replied #{$uid} → {$replyTo}");

        // 存一份到「Sent」，讓你在信箱裡看得到 V.AI 回了什麼
        if ($sent) {
            $this->appendToSent($sent->toString());
        }
    }

    /** 回傳略過原因；null 代表可以交給 AI 判斷 */
    private function skipReason(array $h, string $from, string $replyTo, int $date): ?string
    {
        if ($from === '' || ! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            return 'no valid sender';
        }

        $own = strtolower(config('services.inbox_ai.username'));
        foreach ([$from, $replyTo] as $addr) {
            $addr = strtolower($addr);
            if ($addr === $own || str_ends_with($addr, '@vito1317.com')) {
                return 'own address';
            }
            if (preg_match(self::SYSTEM_SENDER, $addr)) {
                return 'system/notification sender';
            }
        }

        $auto = strtolower(trim($h['auto-submitted'] ?? 'no'));
        if ($auto !== '' && $auto !== 'no') {
            return 'auto-submitted';
        }
        if (preg_match('/\b(bulk|list|junk|auto_reply)\b/i', $h['precedence'] ?? '')) {
            return 'precedence: '.$h['precedence'];
        }
        foreach (self::BULK_HEADERS as $name) {
            if (isset($h[$name])) {
                return 'bulk header: '.$name;
            }
        }
        if (preg_match('#multipart/report#i', $h['content-type'] ?? '')) {
            return 'delivery report';
        }
        if (time() - $date > self::MAX_AGE_HOURS * 3600) {
            return 'too old';
        }

        return null;
    }

    private function parseHeaders(string $raw): array
    {
        $raw = preg_replace("/\r?\n[ \t]+/", ' ', $raw); // 展開折行
        $out = [];
        foreach (preg_split("/\r?\n/", $raw) as $line) {
            if (preg_match('/^([A-Za-z0-9\-]+):\s*(.*)$/', $line, $m)) {
                $out[strtolower($m[1])] ??= $m[2];
            }
        }

        return $out;
    }

    private function references(array $h, string $messageId): array
    {
        preg_match_all('/<([^>]+)>/', $h['references'] ?? '', $m);
        $refs = $m[1] ?? [];
        if ($messageId !== '') {
            $refs[] = $messageId;
        }

        return array_values(array_unique(array_slice($refs, -10)));
    }

    private function address($obj): string
    {
        if (! $obj || empty($obj->mailbox) || empty($obj->host)) {
            return '';
        }

        return strtolower($obj->mailbox.'@'.$obj->host);
    }

    private function decode(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $d = @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return trim($d !== false ? $d : $s);
    }

    /** 取出純文字內文（優先 text/plain，其次 text/html 去標籤）；全程 FT_PEEK，不改已讀狀態 */
    private function fetchText($mb, int $uid): string
    {
        $structure = imap_fetchstructure($mb, $uid, FT_UID);
        $parts = [];
        $this->walk($structure, '', $parts);

        $pick = collect($parts)->firstWhere('subtype', 'PLAIN') ?? collect($parts)->firstWhere('subtype', 'HTML');
        if (! $pick) {
            return '';
        }

        $raw = $pick['section'] === ''
            ? imap_body($mb, $uid, FT_UID | FT_PEEK)
            : imap_fetchbody($mb, $uid, $pick['section'], FT_UID | FT_PEEK);

        $text = match ($pick['encoding']) {
            ENCBASE64 => base64_decode($raw),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($raw),
            default => $raw,
        };

        $charset = strtoupper($pick['charset'] ?: 'UTF-8');
        if ($charset !== 'UTF-8') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
            $text = $converted !== false ? $converted : $text;
        }

        if ($pick['subtype'] === 'HTML') {
            $text = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $text);
            $text = preg_replace('#<br\s*/?>|</p>|</div>#i', "\n", $text);
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // 去掉引用的舊往來（> 開頭的行），收斂空白
        $lines = array_filter(preg_split("/\r?\n/", $text), fn ($l) => ! str_starts_with(ltrim($l), '>'));
        $text = preg_replace("/\n{3,}/", "\n\n", trim(implode("\n", $lines)));

        return mb_substr($text, 0, 6000);
    }

    private function walk($part, string $prefix, array &$out): void
    {
        $section = $prefix;
        if ($part->type === TYPEMULTIPART) {
            foreach ($part->parts ?? [] as $i => $child) {
                $this->walk($child, $prefix === '' ? (string) ($i + 1) : $prefix.'.'.($i + 1), $out);
            }

            return;
        }
        if ($part->type !== TYPETEXT) {
            return;
        }
        $isAttachment = ($part->ifdisposition && strtolower($part->disposition) === 'attachment');
        if ($isAttachment) {
            return;
        }

        $charset = '';
        foreach (($part->ifparameters ? $part->parameters : []) as $p) {
            if (strtolower($p->attribute) === 'charset') {
                $charset = $p->value;
            }
        }
        $out[] = [
            'section' => $section,
            'subtype' => strtoupper($part->subtype ?? 'PLAIN'),
            'encoding' => $part->encoding,
            'charset' => $charset,
        ];
    }

    private function appendToSent(string $raw): void
    {
        $cfg = config('services.inbox_ai');
        $mb = @imap_open($this->server.$cfg['sent_folder'], $cfg['username'], $cfg['password'], 0, 1);
        if (! $mb) {
            $this->log->warning('append to Sent failed (open)', ['error' => imap_last_error()]);
            imap_errors();

            return;
        }
        if (! @imap_append($mb, $this->server.$cfg['sent_folder'], str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw)), '\\Seen')) {
            $this->log->warning('append to Sent failed', ['error' => imap_last_error()]);
        }
        imap_close($mb);
        imap_errors();
    }
}
