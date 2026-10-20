<?php

namespace App\Console\Commands;

use App\Models\KnowledgeArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Publica (ou atualiza) o Manual Técnico do portal na Base de Conhecimento,
 * com o PDF anexado. Idempotente: rodar de novo substitui o PDF anterior.
 *
 *   php artisan kb:publicar-manual [--arquivo=docs/manual/Manual-Tecnico-FOURLINE-Connect-v1.1.pdf]
 */
class PublicarManualKb extends Command
{
    protected $signature = 'kb:publicar-manual
        {--arquivo=docs/manual/Manual-Tecnico-FOURLINE-Connect-v1.1.pdf : PDF do manual (relativo à raiz do projeto)}
        {--versao=1.1 : Versão exibida no registro}';

    protected $description = 'Publica o Manual Técnico do portal na Base de Conhecimento (com o PDF anexado)';

    private const TITULO = 'Manual Técnico FOURLINE Connect (Usabilidade e Funcionalidades)';

    /** Título usado na v1.0; o registro antigo é reaproveitado em vez de duplicado. */
    private const TITULO_ANTIGO = 'Manual Técnico — FOURLINE Connect (Usabilidade e Funcionalidades)';

    public function handle(): int
    {
        $arquivo = base_path((string) $this->option('arquivo'));
        if (! is_file($arquivo)) {
            $this->error("PDF não encontrado: {$arquivo}");

            return self::FAILURE;
        }
        $versao = (string) $this->option('versao');

        $conteudo = <<<TXT
        Manual técnico oficial do portal FOURLINE Connect, versão {$versao}, com as telas do sistema. Público: novos técnicos (integração) e gestão.

        O PDF anexo contém:
        1. Visão geral e objetivos do portal
        2. Arquitetura e integrações (GLPI como fonte oficial dos dados, Zabbix, e-mail, cache)
        3. Acesso, perfis e matriz de permissões (cliente × equipe/gestor) e isolamento entre clientes
        4. Navegação, notificações, tema e instalação como aplicativo
        5. Painel e indicadores
        6. Chamados: lista, "Atribuídos a mim", abertura em nome do cliente, atendimento, SLA, anexos, ciclo de vida e e-mails
        7. Agenda (atendimentos, tarefas recorrentes, prazos) e quadros Kanban
        8. Inventário: cartões por tipo, detalhe técnico, histórico de chamados do equipamento, cadastro/edição/movimentação
        9. Dashboards de monitoramento (Zabbix): saúde por cliente, hosts, problemas agrupados, ruído, preventivo
        10. Relatórios: filtros, abas, exportação Excel e relatório do cliente em PDF
        11. Clientes e perfil do cliente
        12 a 15. Base de conhecimento, Técnicos, Auditoria e Configurações
        16. Procedimento padrão de atendimento e rotina da gestão
        17. Operação, monitoramento do sistema, publicação de atualizações e segurança
        18. Glossário e dúvidas frequentes

        Leitura obrigatória das seções 1 a 4 e 16 na integração de novos técnicos.
        TXT;

        $artigo = KnowledgeArticle::where('titulo', self::TITULO)->first()
            ?? KnowledgeArticle::where('titulo', self::TITULO_ANTIGO)->first()
            ?? new KnowledgeArticle;
        $novo = ! $artigo->exists;
        $artigo->fill([
            'titulo' => self::TITULO,
            'cliente' => 'FOURLINE (interno)',
            'categoria' => 'Documentos / Modelos',
            'conteudo' => $conteudo,
        ]);
        $artigo->save();

        // Substitui o PDF anterior (o model apaga o arquivo físico ao excluir).
        $nome = 'Manual Tecnico - FOURLINE Connect v'.$versao.'.pdf';
        $artigo->attachments()->where('original_name', 'like', 'Manual Tecnico - FOURLINE Connect%')->get()->each->delete();

        $path = 'kb/manual-fourline-connect-v'.preg_replace('/[^0-9.]/', '', $versao).'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, file_get_contents($arquivo));
        $artigo->attachments()->create([
            'original_name' => $nome,
            'path' => $path,
            'mime' => 'application/pdf',
        ]);

        $this->info(($novo ? 'Manual publicado' : 'Manual atualizado').' na Base de Conhecimento (registro #'.$artigo->id.').');
        $this->line('Portal → Base de conhecimento → categoria "Documentos / Modelos" → "'.self::TITULO.'".');

        return self::SUCCESS;
    }
}
