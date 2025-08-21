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
</body>
</html>