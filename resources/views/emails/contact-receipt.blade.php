<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>感謝您的訊息！</title>
  <style>
    body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif; background-color: #f4f7f6; }
    .email-wrapper { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .header { background: linear-gradient(135deg, #0d9488, #2dd4bf); color: white; padding: 40px; text-align: center; }
    .header h1 { margin: 0; font-size: 28px; }
    .content { padding: 40px; color: #333; line-height: 1.6; }
    .content h2 { color: #0d9488; font-size: 20px; margin-top: 0; }
    .message-box { background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 5px; padding: 20px; margin-top: 20px; }
    .message-box p { white-space: pre-wrap; margin: 0; }
    .ai-box { margin-top: 28px; border-left: 4px solid #0d9488; background: #f0fdfa; border-radius: 0 6px 6px 0; padding: 18px 20px; }
    .ai-label { margin: 0 0 10px; color: #0d9488; font-size: 13px; font-weight: 700; letter-spacing: .08em; }
    .ai-text { margin: 0; white-space: pre-wrap; }
    .ai-note { margin: 14px 0 0; color: #6b7280; font-size: 12px; }
    .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
    .footer a { color: #0d9488; text-decoration: none; }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="header">
        <img src="https://vito1317.com/images/icon.jpg" alt="vito1317 Logo" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 20px auto; border: 3px solid white;">
        <h1>訊息已收到！</h1>
    </div>
    <div class="content">
        <h2>您好，{{ $contact->name }}！</h2>
        <p>非常感謝您與我聯繫。我已經收到了您透過 vito1317.com 傳送的訊息，並會盡快親自閱讀與回覆。</p>
        <p>以下是您的訊息副本，供您備查：</p>
        
        <div class="message-box">
            <p><strong>您的 Email：</strong> {{ $contact->email }}</p>
            <p><strong>訊息內容：</strong></p>
            <p>{{ $contact->message }}</p>
        </div>

        @if (!empty($aiReply))
        <div class="ai-box">
            <p class="ai-label">V.AI 助理的初步回覆</p>
            <p class="ai-text">{{ $aiReply }}</p>
            <p class="ai-note">以上由 Vito 的 AI 助理自動撰寫，僅供初步參考；所有合作、報價與時程以 Vito 本人回覆為準。</p>
        </div>
        @endif

        <p style="margin-top: 30px;">期待與您有更進一步的交流！</p>
        <p><strong>vito1317 (柯瑋宸)</strong></p>
    </div>
    <div class="footer">
        <p>這是一封自動發送的確認信。如果您並未在 vito1317.com 上提交聯絡表單，請忽略此郵件。</p>
        <p><a href="https://vito1317.com">vito1317.com</a></p>
    </div>
  </div>
</body>
</html>