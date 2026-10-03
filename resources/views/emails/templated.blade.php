<x-email.shell :title="$heading">
    {!! $bodyHtml !!}

    @if ($actionUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 6px;">
            <tr>
                <td style="border-radius:8px; background:#e87722;">
                    <a href="{{ $actionUrl }}" style="display:inline-block; padding:13px 28px; font-family:Helvetica,Arial,sans-serif; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:8px;">{{ $actionText ?? 'Continue' }}</a>
                </td>
            </tr>
        </table>
        <p style="margin:16px 0 0; font-size:13px; line-height:1.6; color:#777777;">
            If the button above doesn’t work, copy and paste this link into your browser:<br>
            <a href="{{ $actionUrl }}" style="color:#e87722; word-break:break-all;">{{ $actionUrl }}</a>
        </p>
    @endif
</x-email.shell>
