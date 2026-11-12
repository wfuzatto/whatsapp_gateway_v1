<?php
// Exemplo SEM credenciais. NUNCA coloque seu arquivo config.local.php no GitHub.
return [
    'installed' => false,
    'app_key' => 'GERE_UMA_CHAVE_FORTE_FORA_DO_REPOSITORIO',
    'app_url' => 'https://seu-dominio.com.br/sites/whatsapp_gateway',
    'meta_graph_version' => 'v27.0',
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'seu_banco',
        'user' => 'seu_usuario',
        'password' => 'COLOQUE_SENHA_LOCALMENTE',
    ],
];
