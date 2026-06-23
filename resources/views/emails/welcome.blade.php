<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $appName }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8fafc;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            border: 1px solid #e2e8f0;
            border-top: none;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .user-details {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e2e8f0;
        }
        .detail-row {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            width: 120px;
            flex-shrink: 0;
        }
        .detail-value {
            color: #2d3748;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Welcome to {{ $appName }}!</h1>
        <p>Your account has been created successfully</p>
    </div>

    <div class="content">
        <p>Hello {{ $user->name }},</p>

        <p>Thank you for joining {{ $appName }}! We're excited to have you on board.</p>

        <div class="user-details">
            <h3 style="margin-top: 0; color: #2d3748;">Your Account Details:</h3>

            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value">{{ $user->name }}</span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">{{ $user->email }}</span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">{{ $user->country_code }} {{ $user->phone }}</span>
            </div>

            <div class="detail-row">
                <span class="detail-label">Registered:</span>
                <span class="detail-value">{{ $user->created_at?->format('F d, Y \a\t h:i A') ?? now()->format('F d, Y \a\t h:i A') }}</span>
            </div>
        </div>

        <p><strong>Important:</strong> Please verify your email address to access all features of your account.</p>

        <p style="text-align: center;">
            <a href="{{ route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]) }}" class="button">
                Verify Email Address
            </a>
        </p>

        <p>Or copy and paste this link into your browser:<br>
            <small>{{ route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]) }}</small>
        </p>

        <p>If you did not create this account, please ignore this email or contact our support team.</p>

        <div class="footer">
            <p>© {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</div>
</body>
</html>
