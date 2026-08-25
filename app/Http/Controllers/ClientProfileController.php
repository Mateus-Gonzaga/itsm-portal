<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientContact;
use App\Models\ClientContract;
use App\Models\ClientInternetLink;
use App\Models\ClientProfile;
use App\Support\ClientLinks;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Perfil do Cliente — ficha operacional/administrativa vinculada à ENTIDADE do
 * GLPI por glpi_entity_id. Acesso restrito à equipe (role:tecnico,gestor).
 * Dados de identidade/hierarquia vêm do GLPI; a ficha vive no banco do portal.
 */
class ClientProfileController extends Controller
{
    /**
     * Guard de acesso à entidade (S1 — anti-IDOR): confirma que o ID é uma
     * entidade de cliente REAL sob "CLIENTES" (nunca confia só na URL).
     * Como as rotas já são staff-only, basta garantir que é uma entidade de
     * negócio válida (exclui raiz/nó CLIENTES). Retorna os dados da entidade.
     *
     * @return array{id:int,name:string,completename:string,level:int}
     */
    private function entityOrFail(int $entityId, GlpiDirectoryRepositoryInterface $dir): array
    {
        $entity = $dir->entities()->firstWhere('id', $entityId);
        abort_if($entity === null, 404, 'Entidade não encontrada.');

        $path = html_entity_decode((string) $entity['completename']);
        abort_unless(
            ($entity['level'] ?? 0) >= 3 && str_contains($path, 'CLIENTES'),
            403,
            'Esta entidade não é um cliente sob CLIENTES.'
        );

        return $entity;
    }

    /** Perfil (linha) da entidade; cria on-demand ao salvar algo. */
    private function profileFor(int $entityId, bool $create = false): ClientProfile
    {
        return $create
            ? ClientProfile::firstOrCreate(['glpi_entity_id' => $entityId])
            : ClientProfile::firstOrNew(['glpi_entity_id' => $entityId]);
    }

    public function show(Request $request, int $entity, GlpiDirectoryRepositoryInterface $dir): View
    {
        $ent = $this->entityOrFail($entity, $dir);
        $profile = $this->profileFor($entity);
        if ($profile->exists) {
            $profile->load(['contacts', 'internetLinks', 'contracts', 'infrastructure', 'operations']);
        } else {
            $profile->status = 'ativo'; // default de exibição p/ perfil ainda não salvo
        }

        return view('modules.client-profile', [
            'ent' => $ent,
            'profile' => $profile,
            'statusList' => ClientProfile::STATUS,
            'contatoTipos' => ClientContact::TIPOS,
            'internetTipos' => ClientInternetLink::TIPOS,
            'contratoTipos' => ClientContract::TIPOS,
            'contratoStatus' => ClientContract::STATUS,
            'links' => ClientLinks::class,
        ]);
    }

    /** Salva os blocos 1×1: identidade + endereço + infraestrutura + operacional. */
    public function update(Request $request, int $entity, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->entityOrFail($entity, $dir);

        $data = $request->validate([
            'razao_social' => ['nullable', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'segmento' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(ClientProfile::STATUS)],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:2'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
            // Infraestrutura (1×1)
            'infra' => ['nullable', 'array'],
            'infra.firewall_modelo' => ['nullable', 'string', 'max:255'],
            'infra.roteador_modelo' => ['nullable', 'string', 'max:255'],
            'infra.switch_principal' => ['nullable', 'string', 'max:255'],
            'infra.quantidade_switches' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'infra.quantidade_access_points' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'infra.observacoes' => ['nullable', 'string', 'max:5000'],
            // Operacional (1×1)
            'op' => ['nullable', 'array'],
            'op.horario_funcionamento' => ['nullable', 'string', 'max:255'],
            'op.dias_funcionamento' => ['nullable', 'string', 'max:255'],
            'op.restricoes_horario' => ['nullable', 'string', 'max:255'],
            'op.instrucoes_acesso' => ['nullable', 'string', 'max:5000'],
            'op.procedimentos_especiais' => ['nullable', 'string', 'max:5000'],
            'op.observacoes_operacionais' => ['nullable', 'string', 'max:5000'],
            'op.info_tecnicos' => ['nullable', 'string', 'max:5000'],
            'op.observacoes_internas' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($data['cnpj']) && ! ClientLinks::isValidCnpj($data['cnpj'])) {
            return back()->withInput()->with('error', 'CNPJ inválido.');
        }

        $infra = $data['infra'] ?? [];
        $op = $data['op'] ?? [];

        $profile = $this->profileFor($entity, create: true);
        $profile->fill(collect($data)->except(['infra', 'op'])->all());
        $profile->save();

        $profile->infrastructure()->updateOrCreate([], [
            'possui_firewall' => $request->boolean('infra.possui_firewall'),
            'firewall_modelo' => $infra['firewall_modelo'] ?? null,
            'possui_roteador' => $request->boolean('infra.possui_roteador'),
            'roteador_modelo' => $infra['roteador_modelo'] ?? null,
            'switch_principal' => $infra['switch_principal'] ?? null,
            'quantidade_switches' => $infra['quantidade_switches'] ?? null,
            'wifi_corporativo' => $request->boolean('infra.wifi_corporativo'),
            'quantidade_access_points' => $infra['quantidade_access_points'] ?? null,
            'observacoes' => $infra['observacoes'] ?? null,
        ]);

        $profile->operations()->updateOrCreate([], [
            'horario_funcionamento' => $op['horario_funcionamento'] ?? null,
            'dias_funcionamento' => $op['dias_funcionamento'] ?? null,
            'atende_fora_horario' => $request->boolean('op.atende_fora_horario'),
            'restricoes_horario' => $op['restricoes_horario'] ?? null,
            'instrucoes_acesso' => $op['instrucoes_acesso'] ?? null,
            'procedimentos_especiais' => $op['procedimentos_especiais'] ?? null,
            'observacoes_operacionais' => $op['observacoes_operacionais'] ?? null,
            'info_tecnicos' => $op['info_tecnicos'] ?? null,
            'observacoes_internas' => $op['observacoes_internas'] ?? null,
        ]);

        AuditLog::record('client.profile.update', "Editou perfil do cliente (entidade #{$entity})");

        return back()->with('status', 'Perfil do cliente atualizado.');
    }

    // ---------------------------------------------------------------- Contatos

    public function storeContact(Request $request, int $entity, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->entityOrFail($entity, $dir);
        $profile = $this->profileFor($entity, create: true);
        $profile->contacts()->create($this->validateContact($request));
        AuditLog::record('client.contact.create', "Adicionou contato ao cliente (entidade #{$entity})");

        return back()->with('status', 'Contato adicionado.');
    }

    public function updateContact(Request $request, int $entity, ClientContact $contact, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->guardChild($entity, $contact->client_profile_id, $dir);
        $contact->update($this->validateContact($request));
        AuditLog::record('client.contact.update', "Editou contato #{$contact->id} (entidade #{$entity})");

        return back()->with('status', 'Contato atualizado.');
    }

    public function destroyContact(int $entity, ClientContact $contact, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->guardChild($entity, $contact->client_profile_id, $dir);
        $contact->delete();
        AuditLog::record('client.contact.delete', "Removeu contato #{$contact->id} (entidade #{$entity})");

        return back()->with('status', 'Contato removido.');
    }

    // ---------------------------------------------------------- Links internet

    public function storeInternet(Request $request, int $entity, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->entityOrFail($entity, $dir);
        $profile = $this->profileFor($entity, create: true);
        $profile->internetLinks()->create($this->validateInternet($request));
        AuditLog::record('client.internet.create', "Adicionou link de internet (entidade #{$entity})");

        return back()->with('status', 'Link de internet adicionado.');
    }

    public function updateInternet(Request $request, int $entity, ClientInternetLink $link, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->guardChild($entity, $link->client_profile_id, $dir);
        $link->update($this->validateInternet($request));
        AuditLog::record('client.internet.update', "Editou link de internet #{$link->id} (entidade #{$entity})");

        return back()->with('status', 'Link de internet atualizado.');
    }

    public function destroyInternet(int $entity, ClientInternetLink $link, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->guardChild($entity, $link->client_profile_id, $dir);
        $link->delete();
        AuditLog::record('client.internet.delete', "Removeu link de internet #{$link->id} (entidade #{$entity})");

        return back()->with('status', 'Link de internet removido.');
    }

    // -------------------------------------------------------------- Contratos

    public function storeContract(Request $request, int $entity, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->entityOrFail($entity, $dir);
        $profile = $this->profileFor($entity, create: true);
        $profile->contracts()->create($this->validateContract($request));
        AuditLog::record('client.contract.create', "Adicionou contrato (entidade #{$entity})");

        return back()->with('status', 'Contrato adicionado.');
    }

    public function updateContract(Request $request, int $entity, ClientContract $contract, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->guardChild($entity, $contract->client_profile_id, $dir);
        $contract->update($this->validateContract($request));
        AuditLog::record('client.contract.update', "Editou contrato #{$contract->id} (entidade #{$entity})");

        return back()->with('status', 'Contrato atualizado.');
    }

    public function destroyContract(int $entity, ClientContract $contract, GlpiDirectoryRepositoryInterface $dir): RedirectResponse
    {
        $this->guardChild($entity, $contract->client_profile_id, $dir);
        $contract->delete();
        AuditLog::record('client.contract.delete', "Removeu contrato #{$contract->id} (entidade #{$entity})");

        return back()->with('status', 'Contrato removido.');
    }

    // ---------------------------------------------------------------- Helpers

    /** Confirma acesso à entidade E que o filho pertence ao perfil dela (anti-IDOR). */
    private function guardChild(int $entity, int $childProfileId, GlpiDirectoryRepositoryInterface $dir): void
    {
        $this->entityOrFail($entity, $dir);
        $profile = ClientProfile::where('glpi_entity_id', $entity)->first();
        abort_if($profile === null || $profile->id !== $childProfileId, 404);
    }

    /** @return array<string, mixed> */
    private function validateContact(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'cargo' => ['nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'tipo' => ['nullable', Rule::in(ClientContact::TIPOS)],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateInternet(Request $request): array
    {
        $data = $request->validate([
            'papel' => ['required', Rule::in(ClientInternetLink::PAPEIS)],
            'provedora' => ['nullable', 'string', 'max:255'],
            'plano' => ['nullable', 'string', 'max:255'],
            'velocidade_download' => ['nullable', 'string', 'max:40'],
            'velocidade_upload' => ['nullable', 'string', 'max:40'],
            'tipo_conexao' => ['nullable', Rule::in(ClientInternetLink::TIPOS)],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['ip_publico'] = $request->boolean('ip_publico');

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateContract(Request $request): array
    {
        return $request->validate([
            'numero' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(ClientContract::TIPOS)],
            'data_inicio' => ['nullable', 'date'],
            'data_termino' => ['nullable', 'date'],
            'status' => ['required', Rule::in(ClientContract::STATUS)],
            'valor_mensal' => ['nullable', 'numeric', 'min:0'],
            'sla' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
