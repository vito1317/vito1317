{!! $replyText !!}

--
這封是 Vito 的 AI 助理 V.AI 自動寄出的初步回覆，Vito 本人看過你的信後會再親自回覆；合作、報價與時程都以他的回覆為準。
https://vito1317.com

於 {!! $originalDate !!}，{!! $originalFrom !!} 寫道：
{!! collect(preg_split("/\r?\n/", $originalBody))->map(fn ($l) => '> '.$l)->implode("\n") !!}
