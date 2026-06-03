<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ __('Your :app workspace is ready', ['app' => $clientName]) }}</title>
    <!--[if mso]><style>table,td{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

    {{-- Preheader (hidden preview text) --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f4f5f7; opacity:0;">
        {{ __('Your sign-in details for :app — including a temporary password.', ['app' => $clientName]) }}
        &#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;&#8199;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">

                    {{-- ───────── Brand header ───────── --}}
                    <tr>
                        <td style="padding:8px 8px 20px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="40" height="40" align="center" valign="middle"
                                                    style="width:40px; height:40px; background-color:#0f766e; border-radius:10px; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:20px; font-weight:bold; line-height:40px;">P</td>
                                                <td style="padding-left:10px; font-family:Arial,Helvetica,sans-serif; font-size:18px; font-weight:bold; color:#0f172a; letter-spacing:-0.3px;">PMS</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ───────── Card ───────── --}}
                    <tr>
                        <td style="background-color:#ffffff; border-radius:14px; box-shadow:0 1px 3px rgba(16,24,40,0.06);">

                            {{-- Teal accent ribbon --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="height:4px; background-color:#0f766e; border-top-left-radius:14px; border-top-right-radius:14px; font-size:0; line-height:0;">&nbsp;</td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:36px 40px 8px 40px; font-family:Arial,Helvetica,sans-serif;">
                                        <p style="margin:0 0 6px 0; font-size:12px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#0f766e;">{{ __('Workspace ready') }}</p>
                                        <h1 style="margin:0; font-size:24px; line-height:1.25; font-weight:bold; color:#0f172a;">{{ __('Welcome, :name', ['name' => $userName]) }}</h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 40px 0 40px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#475467;">
                                        {{ __('Your :app workspace on PMS has been set up. You can sign in now to manage properties, renters, leases, invoices and payments — all in one place.', ['app' => $clientName]) }}
                                    </td>
                                </tr>

                                {{-- ───────── Credentials card ───────── --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0fdfa; border:1px solid #99f6e4; border-radius:10px;">
                                            <tr>
                                                <td style="padding:18px 20px 6px 20px; font-family:Arial,Helvetica,sans-serif; font-size:11px; font-weight:bold; letter-spacing:0.8px; text-transform:uppercase; color:#0f766e;">
                                                    {{ __('Your sign-in details') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 20px; font-family:Arial,Helvetica,sans-serif;">
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td style="padding:8px 0; font-size:13px; color:#667085; width:150px;">{{ __('Email') }}</td>
                                                            <td style="padding:8px 0; font-size:14px; font-weight:bold; color:#0f172a;">{{ $email }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="border-top:1px solid #ccfbf1; padding:8px 0; font-size:13px; color:#667085;">{{ __('Temporary password') }}</td>
                                                            <td style="border-top:1px solid #ccfbf1; padding:8px 0;">
                                                                <span style="display:inline-block; font-family:'Courier New',Courier,monospace; font-size:15px; font-weight:bold; color:#0f172a; background-color:#ffffff; border:1px solid #99f6e4; border-radius:6px; padding:6px 10px; letter-spacing:1px;">{{ $temporaryPassword }}</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <tr><td style="height:8px; font-size:0; line-height:0;">&nbsp;</td></tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- ───────── CTA (bulletproof button) ───────── --}}
                                <tr>
                                    <td style="padding:28px 40px 8px 40px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" bgcolor="#0f766e" style="border-radius:8px;">
                                                    <a href="{{ $loginUrl }}" target="_blank"
                                                       style="display:inline-block; padding:14px 32px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none; border-radius:8px;">
                                                        {{ __('Sign in to your workspace') }} &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- ───────── Security note ───────── --}}
                                <tr>
                                    <td style="padding:20px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fffbeb; border-radius:8px;">
                                            <tr>
                                                <td style="padding:14px 16px; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.55; color:#92400e;">
                                                    <strong>{{ __('For your security:') }}</strong>
                                                    {{ __("You'll be asked to choose a new password the first time you sign in. Never share this email or your password with anyone.") }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:24px 40px 36px 40px; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#98a2b3;">
                                        {{ __("If the button doesn't work, copy and paste this link into your browser:") }}<br>
                                        <a href="{{ $loginUrl }}" target="_blank" style="color:#0f766e; word-break:break-all;">{{ $loginUrl }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ───────── Footer ───────── --}}
                    <tr>
                        <td style="padding:24px 16px; text-align:center; font-family:Arial,Helvetica,sans-serif;">
                            <p style="margin:0 0 4px 0; font-size:13px; font-weight:bold; color:#475467;">PMS &mdash; {{ __('Property Management System') }}</p>
                            <p style="margin:0 0 10px 0; font-size:12px; color:#98a2b3;">{{ __('A BJP Technologies product') }}</p>
                            <p style="margin:0; font-size:11px; color:#b0b7c3;">
                                {{ __('You received this email because a workspace was created for you on PMS.') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
