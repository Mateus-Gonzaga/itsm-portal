<?php

/*
|--------------------------------------------------------------------------
| Novidades do portal (pop-up "O que há de novo")
|--------------------------------------------------------------------------
| O pop-up abre uma vez para cada usuário sempre que o 'id' da primeira
| entrada mudar. Para anunciar algo novo, acrescente uma entrada NO TOPO
| com um 'id' inédito (ex.: a data). Só a primeira entrada é exibida.
|
| 'perfis' (opcional, por item): limita a quem o item aparece
| ('cliente', 'tecnico', 'gestor'). Sem 'perfis' = todos.
| Ícones: https://icons.getbootstrap.com (ex.: 'bi-bell').
*/

$equipe = ['tecnico', 'gestor'];

return [
    [
        'id' => '2026-10-20',
        'titulo' => 'Novidades de outubro',
        'data' => '20/10/2026',
        'itens' => [
            ['icone' => 'bi-search', 'perfis' => $equipe,
                'texto' => 'Busca rápida: aperte Ctrl+K (ou /) e encontre chamado, cliente, equipamento ou tela de qualquer lugar.'],
            ['icone' => 'bi-phone',
                'texto' => 'No celular, as listas de chamados e do inventário aparecem em cartões, sem rolagem lateral.'],
            ['icone' => 'bi-person-check', 'perfis' => $equipe,
                'texto' => 'Chamados: botão "Atribuídos a mim" mostra só os chamados em que você é o técnico.'],
            ['icone' => 'bi-building', 'perfis' => $equipe,
                'texto' => 'A lista de chamados mostra só a unidade do cliente; o caminho completo fica dentro do chamado.'],
            ['icone' => 'bi-pc-display', 'perfis' => $equipe,
                'texto' => 'Inventário: histórico de chamados de cada equipamento e abertura de chamado direto pelo ativo.'],
            ['icone' => 'bi-bar-chart-line', 'perfis' => $equipe,
                'texto' => 'Relatórios refeitos: filtros, visão por cliente, técnico e categoria, Excel e relatório do cliente em PDF.'],
            ['icone' => 'bi-activity', 'perfis' => $equipe,
                'texto' => 'Dashboards: problemas agrupados, ruído oculto, saúde por cliente e chamado aberto a partir do alerta.'],
            ['icone' => 'bi-journal-text', 'perfis' => $equipe,
                'texto' => 'Manual técnico do portal publicado na Base de conhecimento (Documentos / Modelos).'],
            ['icone' => 'bi-envelope-check',
                'texto' => 'Ao abrir um chamado, o e-mail informado recebe a confirmação e as atualizações até a conclusão.'],
            ['icone' => 'bi-phone',
                'texto' => 'O portal pode ser instalado como aplicativo no celular (no iPhone: Compartilhar, Adicionar à Tela de Início).'],
        ],
    ],
];
