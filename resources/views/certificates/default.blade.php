<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #061627; }
        .sheet { padding: 34px; }
        .frame {
            border: 3px solid {{ $accentColor }};
            padding: 46px 60px 54px;
            text-align: center;
        }
        .rule { width: 70px; height: 4px; background: {{ $accentColor }}; margin: 0 auto 26px; }
        .kicker {
            letter-spacing: 6px;
            text-transform: uppercase;
            font-size: 12px;
            color: {{ $accentColor }};
            font-weight: bold;
        }
        .brand { font-size: 26px; font-weight: bold; margin: 10px 0 34px; }
        .to { font-size: 13px; color: #55708a; }
        .name {
            font-size: 42px;
            font-weight: bold;
            margin: 12px 0 8px;
            padding: 0 30px 10px;
            border-bottom: 2px solid #cfd9e4;
        }
        .event { font-size: 18px; margin-top: 24px; }
        .event strong { color: {{ $accentColor }}; }
        .meta { margin-top: 40px; font-size: 12px; color: #55708a; line-height: 1.7; }
        .code { font-family: "DejaVu Sans Mono", monospace; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="frame">
            <div class="kicker">Certificado de finalización</div>
            <div class="brand">escenia</div>
            <div class="rule"></div>
            <div class="to">Se otorga a</div>
            <div class="name">{{ $recipientName }}</div>
            <div class="event">por completar <strong>{{ $eventTitle }}</strong></div>
            <div class="meta">
                Emitido el {{ $issuedOnLabel }}<br>
                Código de verificación: <span class="code">{{ $code }}</span>
                @if ($verifyUrl)
                    <br>Verifícalo en {{ $verifyUrl }}
                @endif
            </div>
        </div>
    </div>
</body>
</html>
