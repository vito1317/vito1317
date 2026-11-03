<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:24px 16px;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Noto Sans TC',Roboto,Arial,sans-serif;color:#1f2937;line-height:1.75;font-size:15px;">
  <div style="max-width:620px;">
    <div style="white-space:pre-wrap;">{{ $replyText }}</div>

    <p style="margin:28px 0 0;padding-top:14px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12.5px;line-height:1.6;">
      這封是 Vito 的 AI 助理 V.AI 自動寄出的初步回覆，Vito 本人看過你的信後會再親自回覆；合作、報價與時程都以他的回覆為準。<br>
      <a href="https://vito1317.com" style="color:#0d9488;text-decoration:none;">vito1317.com</a>
    </p>

    <div style="margin-top:22px;padding-left:12px;border-left:3px solid #e5e7eb;color:#6b7280;font-size:13px;">
      <p style="margin:0 0 6px;">於 {{ $originalDate }}，{{ $originalFrom }} 寫道：</p>
      <div style="white-space:pre-wrap;">{{ $originalBody }}</div>
    </div>
  </div>
</body>
</html>
