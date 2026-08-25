{{-- Modal: Contato --}}
<div class="modal fade" id="cModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="cForm" method="POST" class="modal-content">
            @csrf <input type="hidden" name="_method" id="c_method" value="POST">
            <div class="modal-header"><h5 class="modal-title" id="c_ttl">Novo contato</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-7"><label class="form-label small">Nome</label><input id="c_nome" name="nome" class="form-control" maxlength="150" required></div>
                    <div class="col-md-5"><label class="form-label small">Tipo</label>
                        <select id="c_tipo" name="tipo" class="form-select"><option value="">—</option>@foreach ($contatoTipos as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select></div>
                    <div class="col-md-12"><label class="form-label small">Cargo / função</label><input id="c_cargo" name="cargo" class="form-control" maxlength="120"></div>
                    <div class="col-md-4"><label class="form-label small">Telefone</label><input id="c_tel" name="telefone" class="form-control" maxlength="30"></div>
                    <div class="col-md-4"><label class="form-label small">WhatsApp</label><input id="c_wa" name="whatsapp" class="form-control" maxlength="30"></div>
                    <div class="col-md-4"><label class="form-label small">E-mail</label><input id="c_email" name="email" type="email" class="form-control" maxlength="255"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Salvar</button></div>
        </form>
    </div>
</div>

{{-- Modal: Link de internet --}}
<div class="modal fade" id="nModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="nForm" method="POST" class="modal-content">
            @csrf <input type="hidden" name="_method" id="n_method" value="POST">
            <div class="modal-header"><h5 class="modal-title" id="n_ttl">Novo link de internet</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small">Papel</label>
                        <select id="n_papel" name="papel" class="form-select"><option value="principal">Principal</option><option value="contingencia">Contingência</option></select></div>
                    <div class="col-md-6"><label class="form-label small">Tipo de conexão</label>
                        <select id="n_tipo" name="tipo_conexao" class="form-select"><option value="">—</option>@foreach ($internetTipos as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label small">Provedora</label><input id="n_prov" name="provedora" class="form-control" maxlength="255"></div>
                    <div class="col-md-6"><label class="form-label small">Plano</label><input id="n_plano" name="plano" class="form-control" maxlength="255"></div>
                    <div class="col-md-4"><label class="form-label small">Download</label><input id="n_down" name="velocidade_download" class="form-control" maxlength="40" placeholder="ex.: 500 Mbps"></div>
                    <div class="col-md-4"><label class="form-label small">Upload</label><input id="n_up" name="velocidade_upload" class="form-control" maxlength="40"></div>
                    <div class="col-md-4 form-check ms-2 mt-4"><input type="checkbox" class="form-check-input" id="n_ip" name="ip_publico" value="1"><label class="form-check-label" for="n_ip">IP público</label></div>
                    <div class="col-12"><label class="form-label small">Observações</label><textarea id="n_obs" name="observacoes" rows="2" class="form-control" maxlength="2000"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Salvar</button></div>
        </form>
    </div>
</div>

{{-- Modal: Contrato --}}
<div class="modal fade" id="kModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form id="kForm" method="POST" class="modal-content">
            @csrf <input type="hidden" name="_method" id="k_method" value="POST">
            <div class="modal-header"><h5 class="modal-title" id="k_ttl">Novo contrato</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label small">Número</label><input id="k_num" name="numero" class="form-control" maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label small">Tipo</label>
                        <select id="k_tipo" name="tipo" class="form-select"><option value="">—</option>@foreach ($contratoTipos as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label small">Status</label>
                        <select id="k_status" name="status" class="form-select">@foreach ($contratoStatus as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label small">Início</label><input id="k_ini" name="data_inicio" type="date" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label small">Término</label><input id="k_fim" name="data_termino" type="date" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label small">Valor mensal (R$)</label><input id="k_valor" name="valor_mensal" type="number" step="0.01" min="0" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label small">SLA</label><input id="k_sla" name="sla" class="form-control" maxlength="255"></div>
                    <div class="col-12"><label class="form-label small">Observações</label><textarea id="k_obs" name="observacoes" rows="2" class="form-control" maxlength="2000"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Salvar</button></div>
        </form>
    </div>
</div>
