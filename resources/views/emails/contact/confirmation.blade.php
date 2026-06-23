<!-- resources/views/emails/contact/confirmation.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FalconEyeGPS - Message Received</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #0ea5e9, #3b82f6); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #ffffff; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .ticket { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .info-box { background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #64748b; font-size: 14px; }
        .btn { display: inline-block; background: linear-gradient(135deg, #0ea5e9, #3b82f6); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Thank You for Contacting FalconEyeGPS</h1>
        <p>We've received your message and will respond shortly</p>
    </div>

    <div class="content">
        <p>Dear {{ $contactMessage->name }},</p>

        <p>Thank you for reaching out to FalconEyeGPS. We've received your inquiry and our support team is reviewing it.</p>

        <div class="ticket">
            <h3>Your Ticket Details</h3>
            <p><strong>Ticket Number:</strong> {{ $ticketNumber }}</p>
            <p><strong>Subject:</strong> {{ $contactMessage->subject }}</p>
            <p><strong>Submitted:</strong> {{ $contactMessage->created_at->format('F j, Y \a\t h:i A') }}</p>
        </div>

        <div class="info-box">
            <h4>What happens next?</h4>
            <p>1. Our team will review your message within {{ $estimatedResponseTime }}</p>
            <p>2. You'll receive a personalized response from our experts</p>
            <p>3. We'll work with you to find the best solution</p>
        </div>

        <div style="margin: 30px 0; text-align: center;">
            <a href="{{ config('app.url') }}/contact" class="btn">View Your Message</a>
        </div>

        <p><strong>Need immediate assistance?</strong><br>
            WhatsApp: {{ $supportPhone }} · Email: {{ $supportEmail }}</p>

        <p>Best regards,<br>
            <strong>The FalconEyeGPS Team</strong></p>
    </div>

    <div class="footer">
        <p>FalconEyeGPS • Enterprise Fleet Management Solutions</p>
        <p>{{ config('app.url') }} • {{ $supportEmail }}</p>
        <p>This is an automated message, please do not reply to this email.</p>
    </div>
</div>
</body>
</html>
