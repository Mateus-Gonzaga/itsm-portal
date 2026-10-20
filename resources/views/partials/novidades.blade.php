{{-- Pop-up "Novidades do portal": abre 1x por usuário a cada nova entrada em config/novidades.php. --}}
@php
    $novidade = collect(config('novidades', []))->first();
    $papel = auth()->user()->role->value;
    $itensNovidade = collect($novidade['itens'] ?? [])
        ->filter(fn ($i) => empty($i['perfis']) || in_array($papel, $i['perfis'], true))
        ->values();
@endphp
@if ($novidade && $itensNovidade->isNotEmpty())
<div class="modal fade" id="novidadesModal" tabindex="-1" aria-labelledby="novidadesTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content novidades">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <span class="novidades-icone"><i class="bi bi-stars"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="novidadesTitulo">{{ $novidade['titulo'] }}</h5>
                        <div class="small text-secondary">O que há de novo no portal · {{ $novidade['data'] }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="novidades-lista">
                    @foreach ($itensNovidade as $item)
                        <li><i class="bi {{ $item['icone'] ?? 'bi-check2-circle' }}"></i><span>{{ $item['texto'] }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Entendi</button>
            </div>
        </div>
    </div>
</div>
<style>
    .novidades-icone { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
        background: rgba(6,138,79,.12); color: var(--fl-green); font-size: 1.3rem; flex-shrink: 0; }
    .novidades-lista { list-style: none; padding: 0; margin: 0; }
    .novidades-lista li { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-c); font-size: .92rem; }
    .novidades-lista li:last-child { border-bottom: 0; }
    .novidades-lista li i { color: var(--fl-green); font-size: 1.05rem; margin-top: 1px; }
</style>
<script>
(function () {
    var el = document.getElementById('novidadesModal');
    if (!el || !window.bootstrap) return;
    var chave = 'novidades:{{ auth()->id() }}';
    var versao = {!! json_encode((string) $novidade['id']) !!};
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    window.abrirNovidades = function () { modal.show(); };
    el.addEventListener('hidden.bs.modal', function () {
        try { localStorage.setItem(chave, versao); } catch (e) {}
    });
    var vista = null;
    try { vista = localStorage.getItem(chave); } catch (e) {}
    if (vista !== versao) setTimeout(function () { modal.show(); }, 700);
})();
</script>
@endif
