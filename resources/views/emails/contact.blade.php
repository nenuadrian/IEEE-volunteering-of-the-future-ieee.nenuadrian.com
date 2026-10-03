<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New contact message</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family:Helvetica,Arial,sans-serif; color:#2a2a2a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:#3a3a3a; padding:24px 32px;">
                            <p style="margin:0; font-size:13px; letter-spacing:0.08em; text-transform:uppercase; color:#f9c9a0;">IEEE Volunteering</p>
                            <h1 style="margin:6px 0 0; font-size:20px; color:#ffffff;">New contact form message</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; line-height:1.5;">
                                <tr>
                                    <td style="padding:6px 0; color:#777777; width:90px;">From</td>
                                    <td style="padding:6px 0; color:#2a2a2a;"><strong>{{ $senderName }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; color:#777777;">Email</td>
                                    <td style="padding:6px 0;"><a href="mailto:{{ $senderEmail }}" style="color:#e87722;">{{ $senderEmail }}</a></td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0; color:#777777;">Subject</td>
                                    <td style="padding:6px 0; color:#2a2a2a;">{{ $messageSubject }}</td>
                                </tr>
                            </table>

                            <hr style="border:none; border-top:1px solid #e4e4e4; margin:22px 0;">

                            <div style="font-size:15px; line-height:1.65; color:#2a2a2a; white-space:pre-wrap;">{{ $messageBody }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px; background:#fafafa; border-top:1px solid #e4e4e4; font-size:12px; color:#888888;">
                            Sent from the contact form on {{ config('app.name') }}. Reply directly to this email to respond to {{ $senderName }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
