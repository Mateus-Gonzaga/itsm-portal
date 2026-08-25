@extends('layouts.app')

@section('title', 'Perfil do Cliente — FOURLINE Connect')

@php
    $inf = $profile->infrastructure;
    $op = $profile->operations;
    $tel = $links::telLink($profile->telefone);
    $wa = $links::whatsappLink($profile->whatsapp ?: $profile->telefone);
    $endereco = $profile->enderecoLinha();
    $maps = $links::mapsLink($endereco);
    $waze = $links::wazeLink($endereco);
    $statusCores = ['ativo' => 'success', 'inativo' => 'secondary', 'prospecto' => 'info', 'suspenso' => 'warning'];
@endphp

@push('head')
<style>
    .cp-sec { border:1px solid var(--bs-border-color); border-radius:14px; padding:1.1rem 1.25rem; margin-bottom:1rem; }
    .cp-sec h2 { font-size:1rem; margin:0 0 .8rem; display:flex; align-items:center; gap:.5rem; }
    .cp-kv { display:grid; grid-template-columns:170px 1fr; gap:.35rem 1rem; font-size:.92rem; }
    .cp-kv dt { color:var(--bs-secondary-color); font-weight:500; }
    .cp-kv dd { margin:0; font-weight:600; }
    @media (max-width:576px){ .cp-kv{ grid-template-columns:1fr; gap:.1rem .5rem; } .cp-kv dd{ margin-bottom:.5rem; } }
    .cp-actions .btn { --bs-btn-padding-y:.35rem; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <a href="{{ route('modules.clients') }}" class="btn btn-link btn-sm text-decoration-none px-0"><i class="bi bi-arrow-left me-1"></i>Voltar aos clientes</a>
</div>

@if (session('status'))<div class="alert alert-success py-2 small">{{ session('status') }}</div>@endif
@if (session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if ($errors->any())
    <div class="alert alert-danger py-2 small"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

{{-- Cabeçalho --}}
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">{{ $profile->nome_fantasia ?: $ent['name'] }}</h1>
        <div class="text-secondary small">
            <span class="badge bg-secondary-subtle text-secondary-emphasis me-1">Entidade GLPI #{{ $ent['id'] }}</span>
            {{ $ent['completename'] }}
        </div>
        <div class="mt-1">
            <span class="badge bg-{{ $statusCores[$profile->status] ?? 'secondary' }}-subtle text-{{ $statusCores[$profile->status] ?? 'secondary' }}-emphasis text-uppercase">{{ $profile->status }}</span>
        </div>
    </div>
    <button class="btn btn-success btn-sm" id="btnEdit" onclick="cpToggleEdit(true)"><i class="bi bi-pencil-square me-1"></i>Editar informações</button>
</div>

{{-- ============================ MODO VISUALIZAÇÃO ============================ --}}
<div id="cpView">
    <div class="row">
        <div class="col-lg-6">
            <div class="cp-sec">
                <h2><i class="bi bi-info-circle text-success"></i>Dados gerais</h2>
                <dl class="cp-kv mb-0">
                    <dt>Razão social</dt><dd>{{ $profile->razao_social ?: '—' }}</dd>
                    <dt>Nome fantasia</dt><dd>{{ $profile->nome_fantasia ?: '—' }}</dd>
                    <dt>CNPJ</dt><dd>{{ $profile->cnpj ?: '—' }}</dd>
                    <dt>Segmento</dt><dd>{{ $profile->segmento ?: '—' }}</dd>
                </dl>
            </div>

            <div class="cp-sec">
                <h2><i class="bi bi-telephone text-success"></i>Contato da loja</h2>
                <dl class="cp-kv mb-2">
                    <dt>Telefone</dt><dd>{{ $profile->telefone ?: '—' }}</dd>
                    <dt>WhatsApp</dt><dd>{{ $profile->whatsapp ?: '—' }}</dd>
                    <dt>E-mail</dt><dd>{{ $profile->email ?: '—' }}</dd>
                    <dt>Site</dt><dd>{{ $profile->site ?: '—' }}</dd>
                </dl>
                <div class="cp-actions d-flex flex-wrap gap-2">
                    @if ($tel)<a href="{{ $tel }}" class="btn btn-sm btn-outline-success"><i class="bi bi-telephone me-1"></i>Ligar</a>@endif
                    @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>@endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="cp-sec">
                <h2><i class="bi bi-geo-alt text-success"></i>Endereço</h2>
                @if ($endereco !== '')
                    <p class="mb-2">{{ $endereco }}</p>
                    <div class="cp-actions d-flex flex-wrap gap-2">
                        @if ($maps)<a href="{{ $maps }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-map me-1"></i>Google Maps</a>@endif
                        @if ($waze)<a href="{{ $waze }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info"><i class="bi bi-signpost-2 me-1"></i>Waze</a>@endif
                    </div>
                @else
                    <p class="text-muted mb-0 small">Endereço não cadastrado.</p>
                @endif
            </div>

            <div class="cp-sec">
                <h2><i class="bi bi-hdd-network text-success"></i>Infraestrutura</h2>
                @if ($inf)
                    <dl class="cp-kv mb-0">
                        <dt>Firewall</dt><dd>{{ $inf->possui_firewall ? ('Sim'.($inf->firewall_modelo ? ' — '.$inf->firewall_modelo : '')) : 'Não' }}</dd>
                        <dt>Roteador</dt><dd>{{ $inf->possui_roteador ? ('Sim'.($inf->roteador_modelo ? ' — '.$inf->roteador_modelo : '')) : 'Não' }}</dd>
                        <dt>Switch principal</dt><dd>{{ $inf->switch_principal ?: '—' }}</dd>
                        <dt>Qtd. switches</dt><dd>{{ $inf->quantidade_switches ?? '—' }}</dd>
                        <dt>Wi-Fi corporativo</dt><dd>{{ $inf->wifi_corporativo ? 'Sim' : 'Não' }}</dd>
                        <dt>Qtd. access points</dt><dd>{{ $inf->quantidade_access_points ?? '—' }}</dd>
                    </dl>
                @else
                    <p class="text-muted mb-0 small">Sem informações de infraestrutura.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Contatos (N) --}}
    <div class="cp-sec">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="mb-0"><i class="bi bi-people text-success"></i>Contatos</h2>
            <button class="btn btn-sm btn-success" onclick="cpContact()"><i class="bi bi-plus-lg me-1"></i>Adicionar</button>
        </div>
        <div class="row g-2">
            @forelse ($profile->contacts as $c)
                @php $ct = $links::telLink($c->telefone); $cw = $links::whatsappLink($c->whatsapp ?: $c->telefone); @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded p-2 h-100">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $c->nome }}</strong>
                            <span class="text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Editar" onclick="cpContact(this)"
                                        data-id="{{ $c->id }}" data-nome="{{ $c->nome }}" data-cargo="{{ $c->cargo }}" data-telefone="{{ $c->telefone }}" data-whatsapp="{{ $c->whatsapp }}" data-email="{{ $c->email }}" data-tipo="{{ $c->tipo }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('clients.profile.contacts.destroy', [$ent['id'], $c->id]) }}" class="d-inline" onsubmit="return confirm('Remover este contato?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0 px-1" title="Remover"><i class="bi bi-trash"></i></button></form>
                            </span>
                        </div>
                        @if ($c->tipo)<div class="small text-secondary">{{ $c->tipo }}</div>@endif
                        @if ($c->cargo)<div class="small">{{ $c->cargo }}</div>@endif
                        @if ($c->telefone)<div class="small mt-1">{{ $c->telefone }}</div>@endif
                        @if ($c->email)<div class="small text-truncate">{{ $c->email }}</div>@endif
                        <div class="d-flex gap-1 mt-2">
                            @if ($ct)<a href="{{ $ct }}" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-telephone"></i></a>@endif
                            @if ($cw)<a href="{{ $cw }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-whatsapp"></i></a>@endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><p class="text-muted small mb-0">Nenhum contato adicional.</p></div>
            @endforelse
        </div>
    </div>

    {{-- Internet e Rede (N) --}}
    <div class="cp-sec">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="mb-0"><i class="bi bi-wifi text-success"></i>Internet e Rede</h2>
            <button class="btn btn-sm btn-success" onclick="cpNet()"><i class="bi bi-plus-lg me-1"></i>Adicionar link</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr class="small text-secondary"><th>Papel</th><th>Provedora</th><th>Plano</th><th>Down/Up</th><th>Tipo</th><th>IP púb.</th><th></th></tr></thead>
                <tbody>
                    @forelse ($profile->internetLinks as $l)
                        <tr>
                            <td>{!! $l->papel === 'contingencia' ? '<span class="badge bg-warning-subtle text-warning-emphasis">Contingência</span>' : '<span class="badge bg-success-subtle text-success-emphasis">Principal</span>' !!}</td>
                            <td>{{ $l->provedora ?: '—' }}</td>
                            <td class="small">{{ $l->plano ?: '—' }}</td>
                            <td class="small">{{ trim(($l->velocidade_download ?: '—').' / '.($l->velocidade_upload ?: '—')) }}</td>
                            <td class="small">{{ $l->tipo_conexao ?: '—' }}</td>
                            <td>{{ $l->ip_publico ? 'Sim' : 'Não' }}</td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="cpNet(this)"
                                        data-id="{{ $l->id }}" data-papel="{{ $l->papel }}" data-provedora="{{ $l->provedora }}" data-plano="{{ $l->plano }}" data-down="{{ $l->velocidade_download }}" data-up="{{ $l->velocidade_upload }}" data-tipo="{{ $l->tipo_conexao }}" data-ip="{{ $l->ip_publico ? 1 : 0 }}" data-obs="{{ $l->observacoes }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('clients.profile.internet.destroy', [$ent['id'], $l->id]) }}" class="d-inline" onsubmit="return confirm('Remover este link?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted small">Nenhum link cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Contratos (N) --}}
    <div class="cp-sec">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="mb-0"><i class="bi bi-file-earmark-text text-success"></i>Contratos</h2>
            <button class="btn btn-sm btn-success" onclick="cpContract()"><i class="bi bi-plus-lg me-1"></i>Adicionar</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr class="small text-secondary"><th>Número</th><th>Tipo</th><th>Vigência</th><th>Status</th><th class="text-end">Valor mensal</th><th>SLA</th><th></th></tr></thead>
                <tbody>
                    @forelse ($profile->contracts as $ct)
                        <tr>
                            <td>{{ $ct->numero ?: '—' }}</td>
                            <td class="small">{{ $ct->tipo ?: '—' }}</td>
                            <td class="small">{{ optional($ct->data_inicio)->format('d/m/Y') ?: '—' }} → {{ optional($ct->data_termino)->format('d/m/Y') ?: '—' }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary-emphasis text-uppercase">{{ $ct->status }}</span></td>
                            <td class="text-end">{{ $ct->valor_mensal !== null ? 'R$ '.number_format($ct->valor_mensal, 2, ',', '.') : '—' }}</td>
                            <td class="small">{{ $ct->sla ?: '—' }}</td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="cpContract(this)"
                                        data-id="{{ $ct->id }}" data-numero="{{ $ct->numero }}" data-tipo="{{ $ct->tipo }}" data-inicio="{{ optional($ct->data_inicio)->format('Y-m-d') }}" data-termino="{{ optional($ct->data_termino)->format('Y-m-d') }}" data-status="{{ $ct->status }}" data-valor="{{ $ct->valor_mensal }}" data-sla="{{ $ct->sla }}" data-obs="{{ $ct->observacoes }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('clients.profile.contracts.destroy', [$ent['id'], $ct->id]) }}" class="d-inline" onsubmit="return confirm('Remover este contrato?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted small">Nenhum contrato cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Operacional / Atendimento --}}
    <div class="cp-sec">
        <h2><i class="bi bi-headset text-success"></i>Operacional e Atendimento</h2>
        @if ($op)
            <dl class="cp-kv mb-0">
                <dt>Horário</dt><dd>{{ $op->horario_funcionamento ?: '—' }}</dd>
                <dt>Dias</dt><dd>{{ $op->dias_funcionamento ?: '—' }}</dd>
                <dt>Atende fora do horário</dt><dd>{{ $op->atende_fora_horario ? 'Sim' : 'Não' }}</dd>
                <dt>Restrições de horário</dt><dd>{{ $op->restricoes_horario ?: '—' }}</dd>
                <dt>Instruções de acesso</dt><dd style="white-space:pre-line">{{ $op->instrucoes_acesso ?: '—' }}</dd>
                <dt>Procedimentos especiais</dt><dd style="white-space:pre-line">{{ $op->procedimentos_especiais ?: '—' }}</dd>
                <dt>Info para técnicos</dt><dd style="white-space:pre-line">{{ $op->info_tecnicos ?: '—' }}</dd>
                <dt>Observações internas</dt><dd style="white-space:pre-line">{{ $op->observacoes_internas ?: '—' }}</dd>
            </dl>
        @else
            <p class="text-muted mb-0 small">Sem informações operacionais.</p>
        @endif
    </div>
</div>

{{-- ============================ MODO EDIÇÃO (1×1) ============================ --}}
<form id="cpEdit" method="POST" action="{{ route('clients.profile.update', $ent['id']) }}" class="d-none">
    @csrf @method('PUT')
    <div class="cp-sec">
        <h2><i class="bi bi-info-circle text-success"></i>Dados gerais</h2>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label small">Razão social</label><input name="razao_social" class="form-control" maxlength="255" value="{{ old('razao_social', $profile->razao_social) }}"></div>
            <div class="col-md-6"><label class="form-label small">Nome fantasia</label><input name="nome_fantasia" class="form-control" maxlength="255" value="{{ old('nome_fantasia', $profile->nome_fantasia) }}"></div>
            <div class="col-md-4"><label class="form-label small">CNPJ</label><input name="cnpj" class="form-control" maxlength="18" placeholder="00.000.000/0000-00" value="{{ old('cnpj', $profile->cnpj) }}"></div>
            <div class="col-md-5"><label class="form-label small">Segmento / ramo</label><input name="segmento" class="form-control" maxlength="255" value="{{ old('segmento', $profile->segmento) }}"></div>
            <div class="col-md-3"><label class="form-label small">Status</label>
                <select name="status" class="form-select">@foreach ($statusList as $s)<option value="{{ $s }}" @selected(old('status', $profile->status) === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
            </div>
        </div>
    </div>

    <div class="cp-sec">
        <h2><i class="bi bi-geo-alt text-success"></i>Endereço</h2>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label small">CEP</label><input name="cep" class="form-control" maxlength="9" value="{{ old('cep', $profile->cep) }}"></div>
            <div class="col-md-7"><label class="form-label small">Logradouro</label><input name="logradouro" class="form-control" maxlength="255" value="{{ old('logradouro', $profile->logradouro) }}"></div>
            <div class="col-md-2"><label class="form-label small">Número</label><input name="numero" class="form-control" maxlength="20" value="{{ old('numero', $profile->numero) }}"></div>
            <div class="col-md-4"><label class="form-label small">Complemento</label><input name="complemento" class="form-control" maxlength="255" value="{{ old('complemento', $profile->complemento) }}"></div>
            <div class="col-md-4"><label class="form-label small">Bairro</label><input name="bairro" class="form-control" maxlength="255" value="{{ old('bairro', $profile->bairro) }}"></div>
            <div class="col-md-3"><label class="form-label small">Cidade</label><input name="cidade" class="form-control" maxlength="255" value="{{ old('cidade', $profile->cidade) }}"></div>
            <div class="col-md-1"><label class="form-label small">UF</label><input name="estado" class="form-control text-uppercase" maxlength="2" value="{{ old('estado', $profile->estado) }}"></div>
        </div>
    </div>

    <div class="cp-sec">
        <h2><i class="bi bi-telephone text-success"></i>Contato da loja</h2>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label small">Telefone</label><input name="telefone" class="form-control" maxlength="30" value="{{ old('telefone', $profile->telefone) }}"></div>
            <div class="col-md-3"><label class="form-label small">WhatsApp</label><input name="whatsapp" class="form-control" maxlength="30" value="{{ old('whatsapp', $profile->whatsapp) }}"></div>
            <div class="col-md-3"><label class="form-label small">E-mail</label><input name="email" type="email" class="form-control" maxlength="255" value="{{ old('email', $profile->email) }}"></div>
            <div class="col-md-3"><label class="form-label small">Site</label><input name="site" class="form-control" maxlength="255" value="{{ old('site', $profile->site) }}"></div>
        </div>
    </div>

    <div class="cp-sec">
        <h2><i class="bi bi-hdd-network text-success"></i>Infraestrutura</h2>
        <div class="row g-3">
            <div class="col-md-3 form-check ms-2 mt-4"><input type="checkbox" class="form-check-input" id="i_fw" name="infra[possui_firewall]" value="1" @checked(optional($inf)->possui_firewall)><label class="form-check-label" for="i_fw">Possui firewall</label></div>
            <div class="col-md-3"><label class="form-label small">Modelo do firewall</label><input name="infra[firewall_modelo]" class="form-control" value="{{ optional($inf)->firewall_modelo }}"></div>
            <div class="col-md-3 form-check ms-2 mt-4"><input type="checkbox" class="form-check-input" id="i_rt" name="infra[possui_roteador]" value="1" @checked(optional($inf)->possui_roteador)><label class="form-check-label" for="i_rt">Possui roteador</label></div>
            <div class="col-md-3"><label class="form-label small">Modelo do roteador</label><input name="infra[roteador_modelo]" class="form-control" value="{{ optional($inf)->roteador_modelo }}"></div>
            <div class="col-md-4"><label class="form-label small">Switch principal</label><input name="infra[switch_principal]" class="form-control" value="{{ optional($inf)->switch_principal }}"></div>
            <div class="col-md-2"><label class="form-label small">Qtd. switches</label><input name="infra[quantidade_switches]" type="number" min="0" max="9999" class="form-control" value="{{ optional($inf)->quantidade_switches }}"></div>
            <div class="col-md-3 form-check ms-2 mt-4"><input type="checkbox" class="form-check-input" id="i_wifi" name="infra[wifi_corporativo]" value="1" @checked(optional($inf)->wifi_corporativo)><label class="form-check-label" for="i_wifi">Wi-Fi corporativo</label></div>
            <div class="col-md-3"><label class="form-label small">Qtd. access points</label><input name="infra[quantidade_access_points]" type="number" min="0" max="9999" class="form-control" value="{{ optional($inf)->quantidade_access_points }}"></div>
            <div class="col-12"><label class="form-label small">Observações da rede</label><textarea name="infra[observacoes]" rows="2" class="form-control">{{ optional($inf)->observacoes }}</textarea></div>
        </div>
    </div>

    <div class="cp-sec">
        <h2><i class="bi bi-headset text-success"></i>Operacional e Atendimento</h2>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label small">Horário de funcionamento</label><input name="op[horario_funcionamento]" class="form-control" value="{{ optional($op)->horario_funcionamento }}"></div>
            <div class="col-md-4"><label class="form-label small">Dias de funcionamento</label><input name="op[dias_funcionamento]" class="form-control" value="{{ optional($op)->dias_funcionamento }}"></div>
            <div class="col-md-4 form-check ms-2 mt-4"><input type="checkbox" class="form-check-input" id="o_fora" name="op[atende_fora_horario]" value="1" @checked(optional($op)->atende_fora_horario)><label class="form-check-label" for="o_fora">Atende fora do horário comercial</label></div>
            <div class="col-md-12"><label class="form-label small">Restrições de horário</label><input name="op[restricoes_horario]" class="form-control" value="{{ optional($op)->restricoes_horario }}"></div>
            <div class="col-md-6"><label class="form-label small">Instruções de acesso</label><textarea name="op[instrucoes_acesso]" rows="2" class="form-control">{{ optional($op)->instrucoes_acesso }}</textarea></div>
            <div class="col-md-6"><label class="form-label small">Procedimentos especiais</label><textarea name="op[procedimentos_especiais]" rows="2" class="form-control">{{ optional($op)->procedimentos_especiais }}</textarea></div>
            <div class="col-md-6"><label class="form-label small">Informações para técnicos</label><textarea name="op[info_tecnicos]" rows="2" class="form-control">{{ optional($op)->info_tecnicos }}</textarea></div>
            <div class="col-md-6"><label class="form-label small">Observações internas</label><textarea name="op[observacoes_internas]" rows="2" class="form-control">{{ optional($op)->observacoes_internas }}</textarea></div>
            <div class="col-12"><label class="form-label small">Observações gerais do cliente</label><textarea name="observacoes" rows="2" class="form-control">{{ old('observacoes', $profile->observacoes) }}</textarea></div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Salvar informações</button>
        <button type="button" class="btn btn-outline-secondary" onclick="cpToggleEdit(false)">Cancelar</button>
    </div>
</form>

@include('modules.partials.client-profile-modals', ['ent' => $ent, 'contatoTipos' => $contatoTipos, 'internetTipos' => $internetTipos, 'contratoTipos' => $contratoTipos, 'contratoStatus' => $contratoStatus])
@endsection

@push('scripts')
<script>
function cpToggleEdit(on) {
    document.getElementById('cpView').classList.toggle('d-none', on);
    document.getElementById('cpEdit').classList.toggle('d-none', !on);
    document.getElementById('btnEdit').classList.toggle('d-none', on);
    if (on) window.scrollTo({ top: 0, behavior: 'smooth' });
}
@if ($errors->any()) cpToggleEdit(true); @endif

(function () {
    const $ = (id) => document.getElementById(id);
    const ENT = @json($ent['id']);
    const base = "{{ url('clientes') }}/" + ENT + "/perfil";

    // Contatos
    const cModal = new bootstrap.Modal($('cModal'));
    window.cpContact = function (el) {
        const f = $('cForm');
        if (el) { f.action = base + '/contatos/' + el.dataset.id; $('c_method').value = 'PUT'; $('c_ttl').textContent = 'Editar contato';
            $('c_nome').value = el.dataset.nome || ''; $('c_cargo').value = el.dataset.cargo || ''; $('c_tel').value = el.dataset.telefone || '';
            $('c_wa').value = el.dataset.whatsapp || ''; $('c_email').value = el.dataset.email || ''; $('c_tipo').value = el.dataset.tipo || '';
        } else { f.reset(); f.action = base + '/contatos'; $('c_method').value = 'POST'; $('c_ttl').textContent = 'Novo contato'; }
        cModal.show();
    };

    // Internet
    const nModal = new bootstrap.Modal($('nModal'));
    window.cpNet = function (el) {
        const f = $('nForm');
        if (el) { f.action = base + '/internet/' + el.dataset.id; $('n_method').value = 'PUT'; $('n_ttl').textContent = 'Editar link';
            $('n_papel').value = el.dataset.papel || 'principal'; $('n_prov').value = el.dataset.provedora || ''; $('n_plano').value = el.dataset.plano || '';
            $('n_down').value = el.dataset.down || ''; $('n_up').value = el.dataset.up || ''; $('n_tipo').value = el.dataset.tipo || ''; $('n_obs').value = el.dataset.obs || ''; $('n_ip').checked = el.dataset.ip === '1';
        } else { f.reset(); f.action = base + '/internet'; $('n_method').value = 'POST'; $('n_ttl').textContent = 'Novo link de internet'; }
        nModal.show();
    };

    // Contratos
    const kModal = new bootstrap.Modal($('kModal'));
    window.cpContract = function (el) {
        const f = $('kForm');
        if (el) { f.action = base + '/contratos/' + el.dataset.id; $('k_method').value = 'PUT'; $('k_ttl').textContent = 'Editar contrato';
            $('k_num').value = el.dataset.numero || ''; $('k_tipo').value = el.dataset.tipo || ''; $('k_ini').value = el.dataset.inicio || '';
            $('k_fim').value = el.dataset.termino || ''; $('k_status').value = el.dataset.status || 'ativo'; $('k_valor').value = el.dataset.valor || ''; $('k_sla').value = el.dataset.sla || ''; $('k_obs').value = el.dataset.obs || '';
        } else { f.reset(); f.action = base + '/contratos'; $('k_method').value = 'POST'; $('k_ttl').textContent = 'Novo contrato'; }
        kModal.show();
    };
})();
</script>
@endpush
