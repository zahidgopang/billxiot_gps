<!-- resources/views/emails/contact/notification.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Message - FalconEyeGPS</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #ffffff; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .message-box { background: #fef2f2; border: 1px solid #fecaca; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .customer-info { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .btn { display: inline-block; background: linear-gradient(135deg, #0ea5e9, #3b82f6); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .priority-high { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .priority-medium { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .priority-low { background: linear-gradient(135deg, #10b981, #059669); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>New Contact Message Received</h1>
        <p>Priority: <span class="priority-{{ $contactMessage->priority }}">{{ strtoupper($contactMessage->priority) }}</span></p>
    </div>

    <div class="content">
        <div class="customer-info">
            <h3>Customer Information</h3>
            <p><strong>Name:</strong> {{ $contactMessage->name }}</p>
            <p><strong>Email:</strong> {{ $contactMessage->email }}</p>
            <p><strong>Phone:</strong> {{ $contactMessage->phone ?? 'Not provided' }}</p>
            <p><strong>Company:</strong> {{ $contactMessage->company ?? 'Not provided' }}</p>
            <p><strong>Ticket:</strong> {{ $ticketNumber }}</p>
            <p><strong>Submitted:</strong> {{ $contactMessage->created_at->format('F j, Y \a\t h:i A') }}</p>
            <p><strong>IP Address:</strong> {{ $contactMessage->ip_address }}</p>
        </div>

        <div class="message-box">
            <h3>Message Details</h3>
            <p><strong>Subject:</strong> {{ $contactMessage->subject }}</p>
            <p><strong>Message:</strong></p>
            <p>{{ $contactMessage->message }}</p>
        </div>

        <div style="margin: 30px 0; text-align: center;">
            <a href="{{ $dashboardUrl }}" class="btn">View in Dashboard</a>
        </div>

        <p><strong>Action Required:</strong><br>
            1. Review the message and assign to appropriate team member<br>
            2. Respond within 24 hours<br>
            3. Update ticket status in CRM</p>

        <p>This is an automated notification from FalconEyeGPS Contact System.</p>
    </div>
</div>
</body>
</html>
