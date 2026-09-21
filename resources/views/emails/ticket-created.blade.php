<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Chamado #{{ $ticket->id }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 24px; color: #333333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e1e4e8; }
        .header { background: #00875a; padding: 24px 32px; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; letter-spacing: 0.5px; }
        .header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.9; }
        .content { padding: 32px; }
        .greeting { font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #172b4d; }
        .intro { font-size: 14px; line-height: 1.5; color: #42526e; margin-bottom: 24px; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 24px; }
        .row { display: table; width: 100%; margin-bottom: 10px; font-size: 14px; }
        .row:last-child { margin-bottom: 0; }
        .label { display: table-cell; width: 35%; font-weight: 600; color: #64748b; padding: 4px 0; }
        .value { display: table-cell; width: 65%; color: #0f172a; padding: 4px 0; }
        .badge { display: inline-block; padding: 3px 8px; font-size: 12px; font-weight: 600; border-radius: 4px; background: #e2e8f0; color: #334155; }
        .desc-box { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 12px; margin-top: 8px; font-size: 13px; line-height: 1.5; color: #334155; }
        .btn-wrapper { text-align: center; margin: 30px 0 10px 0; }
        .btn { display: inline-block; background: #00875a; color: #ffffff !important; padding: 12px 28px; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 6px; }
        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 32px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FOURLINE CONNECT</h1>
            <p>Central de Atendimento e Suporte TI</p>
        </div>

        <div class="content">
            @if ($isStaffNotification)
                <div class="greeting">Olá equipe de Suporte,</div>
                <div class="intro">Um novo chamado foi aberto no portal e aguarda atendimento.</div>
            @else
                <div class="greeting">Olá, {{ $ticket->requesterName }}!</div>
                <div class="intro">Seu chamado foi registrado com sucesso em nossa central de atendimento. A equipe técnica já foi notificada.</div>
            @endif

            <div class="card">
                <div class="row">
                    <div class="label">Chamado:</div>
                    <div class="value"><strong>#{{ $ticket->id }}</strong> &mdash; {{ $ticket->title }}</div>
                </div>
                <div class="row">
                    <div class="label">Cliente / Loja:</div>
                    <div class="value">{{ $ticket->entity }}</div>
                </div>
                <div class="row">
                    <div class="label">Solicitante:</div>
                    <div class="value">{{ $ticket->requesterName }}</div>
                </div>
                <div class="row">
                    <div class="label">Categoria:</div>
                    <div class="value">{{ $ticket->category ?? 'Não informada' }}</div>
                </div>
                <div class="row">
                    <div class="label">Prioridade:</div>
                    <div class="value"><span class="badge">{{ $ticket->priority->label() }}</span></div>
                </div>
                <div class="row">
                    <div class="label">Aberto em:</div>
                    <div class="value">{{ $ticket->createdAt->format('d/m/Y H:i') }}</div>
                </div>
                @if ($ticket->dueDate)
                    <div class="row">
                        <div class="label">Prazo (SLA):</div>
                        <div class="value">{{ $ticket->dueDate->format('d/m/Y H:i') }}</div>
                    </div>
                @endif
                <div class="row" style="margin-top: 12px;">
                    <div class="label" style="display:block; width:100%;">Descrição:</div>
                    <div class="value" style="display:block; width:100%;">
                        <div class="desc-box">{!! nl2br(e($ticket->description)) !!}</div>
                    </div>
                </div>
            </div>

            <div class="btn-wrapper">
                <a href="{{ $ticketUrl }}" class="btn" target="_blank">Acompanhar Chamado no Portal</a>
            </div>
        </div>

        <div class="footer">
            <strong>Fourline Soluções em TI</strong><br>
            contato@fourline.com.br<br>
            Este é um e-mail automático gerado pelo portal de suporte. Por favor, não responda diretamente a esta mensagem.
        </div>
    </div>
</body>
</html>
