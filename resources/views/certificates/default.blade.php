<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #061627; }
        .sheet { padding: 30px; }
        .frame { border: 3px solid {{ $accentColor }}; padding: 34px 60px 40px; text-align: center; }

        /* Brand lockup */
        .logo-img { max-height: 48px; margin-bottom: 8px; }
        .logo { margin: 0 auto 10px; border-collapse: collapse; }
        .logo td { vertical-align: middle; }
        .logo-badge {
            width: 30px; height: 30px;
            background: #061627;
            border-radius: 8px;
            border: 3px solid {{ $accentColor }};
        }
        .logo-word { font-size: 24px; font-weight: bold; padding-left: 12px; letter-spacing: 0.5px; }

        .rule { width: 66px; height: 4px; background: {{ $accentColor }}; margin: 18px auto 24px; }
        .kicker { letter-spacing: 6px; text-transform: uppercase; font-size: 12px; color: {{ $accentColor }}; font-weight: bold; }
        .to { font-size: 13px; color: #55708a; margin-top: 22px; }
        .name { font-size: 40px; font-weight: bold; margin: 12px 0 8px; padding: 0 30px 10px; border-bottom: 2px solid #cfd9e4; }
        .event { font-size: 18px; margin-top: 22px; }
        .event strong { color: {{ $accentColor }}; }

        /* Verification block */
        .verify { width: 420px; margin: 34px auto 0; border-collapse: collapse; }
        .verify td { vertical-align: middle; text-align: left; }
        .qr { width: 92px; }
        .qr-img { width: 92px; height: 92px; }
        .verify-text { padding-left: 18px; font-size: 11px; color: #55708a; line-height: 1.7; }
        .verify-text .lead { font-size: 12px; font-weight: bold; color: #061627; }
        .code { font-family: "DejaVu Sans Mono", monospace; letter-spacing: 1px; color: #061627; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="frame">
            @if ($logoDataUri)
                <img class="logo-img" src="{{ $logoDataUri }}" alt="">
            @else
                <table class="logo">
                    <tr>
                        <td class="logo-badge"></td>
                        <td class="logo-word">escenia</td>
                    </tr>
                </table>
            @endif

            <div class="kicker">Certificado de finalización</div>
            <div class="rule"></div>
            <div class="to">Se otorga a</div>
            <div class="name">{{ $recipientName }}</div>
            <div class="event">por completar <strong>{{ $eventTitle }}</strong></div>

            <table class="verify">
                <tr>
                    @if ($qrDataUri)
                        <td class="qr"><img class="qr-img" src="{{ $qrDataUri }}" alt=""></td>
                    @endif
                    <td class="verify-text">
                        <span class="lead">Verificación</span><br>
                        Emitido el {{ $issuedOnLabel }}<br>
                        Código: <span class="code">{{ $code }}</span>
                        @if ($verifyUrl)
                            <br>{{ $verifyUrl }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
