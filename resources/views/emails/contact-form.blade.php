<!DOCTYPE html>
<html>
<head>
    <title>新的聯絡訊息</title>
</head>
<body>
    <h2>您在 vito1317.com 上收到了一則新訊息：</h2>
    <p><strong>姓名：</strong> {{ $contact->name }}</p>
    <p><strong>Email：</strong> {{ $contact->email }}</p>
    <p><strong>訊息內容：</strong></p>
    <p style="white-space: pre-wrap;">{{ $contact->message }}</p>
    @if (!empty($aiReply))
    <hr>
    <h3>V.AI 已寄給對方的初步回覆（供你參考／接續回信）：</h3>
    <p style="white-space: pre-wrap; background:#f0fdfa; border-left:4px solid #0d9488; padding:12px 16px;">{{ $aiReply }}</p>
    @endif
</body>
</html>