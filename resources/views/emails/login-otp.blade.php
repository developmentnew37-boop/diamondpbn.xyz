<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Login verification — {{ config('app.name') }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f1f5f9; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    {{-- Preheader (hidden in body, shown in inbox preview) --}}
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        Your sign-in code is {{ $code }}. It expires in {{ $expiresMinutes }} minutes.
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f1f5f9;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 520px;">
                    {{-- Brand header --}}
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="background: linear-gradient(135deg, #ea580c 0%, #f97316 50%, #fb923c 100%); background-color: #ea580c; border-radius: 14px; padding: 12px 20px;">
                                        <span style="font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em;">
                                            {{ config('app.name') }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 12px 0 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; color: #64748b;">
                                PBN Link Management
                            </p>
                        </td>
                    </tr>

                    {{-- Main card --}}
                    <tr>
                        <td style="background-color: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06); overflow: hidden;">
                            {{-- Orange accent bar --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td height="4" style="background: linear-gradient(90deg, #ea580c, #f97316); background-color: #ea580c; font-size: 0; line-height: 0;">&nbsp;</td>
                                </tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 36px 32px 28px; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                        <p style="margin: 0 0 8px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #ea580c;">
                                            Sign-in verification
                                        </p>
                                        <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                            Hello, {{ $user->name }}
                                        </h1>
                                        <p style="margin: 0 0 28px; font-size: 15px; line-height: 1.6; color: #475569;">
                                            Use the verification code below to complete your login. For your security, do not share this code with anyone.
                                        </p>

                                        {{-- OTP box --}}
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td align="center" style="background-color: #fff7ed; border: 2px dashed #fdba74; border-radius: 12px; padding: 24px 16px;">
                                                    <p style="margin: 0 0 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #9a3412;">
                                                        Your verification code
                                                    </p>
                                                    <p style="margin: 0; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 36px; font-weight: 700; letter-spacing: 0.35em; color: #c2410c; line-height: 1;">
                                                        {{ $code }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 24px 0 0; font-size: 14px; line-height: 1.5; color: #64748b; text-align: center;">
                                            This code expires in <strong style="color: #334155;">{{ $expiresMinutes }} minutes</strong>.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- Security notice --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 0 32px 32px; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                            <tr>
                                                <td style="padding: 16px 18px;">
                                                    <p style="margin: 0; font-size: 13px; line-height: 1.55; color: #64748b;">
                                                        <strong style="color: #475569;">Didn't request this?</strong>
                                                        If you didn't try to sign in, you can safely ignore this email. Someone may have entered your email address by mistake.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding: 28px 16px 8px; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <p style="margin: 0 0 6px; font-size: 12px; color: #94a3b8; line-height: 1.5;">
                                This message was sent to <span style="color: #64748b;">{{ $user->email }}</span>
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #cbd5e1;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
