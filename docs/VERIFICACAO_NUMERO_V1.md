# Verificação do número WhatsApp — V1

## Atualização do servidor

Destino: `https://prodatastelecom.com.br/sites/whatsapp_gateway`.
Copiar somente estes arquivos, mantendo suas pastas:

- `app/bootstrap.php` (alterado: carregamento do serviço, ações administrativas e acesso à página).
- `app/phone-verification.php` (novo: Graph v27.0, descoberta e verificação).
- `app/views/pages/setup-meta.php` (alterado: inclusão da seção).
- `app/views/pages/phone-verification.php` (novo: interface administrativa).
- `docs/VERIFICACAO_NUMERO_V1.md` (este guia).

`tests/phone-verification-test.php` é opcional no servidor; destina-se à validação local.
Faça backup dos arquivos substituídos. Não substitua `storage/config.local.php`, chave de cifragem, tokens, App Secret, banco ou `.htaccess` existentes. Nenhuma migração é necessária: o recurso não escreve no banco. O webhook e a V2 não foram alterados. A versão v27.0 é fixa nesta ferramenta e não altera a versão dos demais clientes da API.

O PHP precisa de cURL e permissão de escrita em `storage`. O histórico e as pausas ficam em `storage/phone-verification-{tenant_id}.json`, com bloqueio exclusivo durante a operação e limite de 100 entradas. Preserve o bloqueio HTTP de `storage/.htaccess` (`Require all denied`); em outro servidor web configure proteção equivalente. Os arquivos contêm IDs e dados técnicos, mas não tokens nem o código recebido. Em instalação com múltiplos servidores, use armazenamento compartilhado com suporte a flock para preservar o bloqueio. Desabilite captura de corpos destes formulários em ferramentas externas de diagnóstico/APM.

## Operação

1. Entre como administrador autorizado e abra **Instalação Meta**. A autenticação e CSRF existentes protegem o fluxo.
2. Mantenha o App ID ZapAPI `1683987243242501`. Salve um token vigente autorizado usando o campo de senha existente; deixar campos secretos vazios preserva suas credenciais.
3. O WABA informado nesta sessão é `1672173261195301`. O Phone Number ID `1271976549341779` pertence ao teste americano e NÃO identifica o número brasileiro.
4. Clique **Consultar situação**. A ferramenta lista `/{WABA_ID}/phone_numbers`, compara somente dígitos com `5535997570768`, e consulta o ativo correspondente com `fields=display_phone_number,code_verification_status`.
5. Caso o WABA brasileiro seja outro, informe o Business ID do portfólio: a ferramenta consulta WABAs próprias e compartilhadas (`owned_whatsapp_business_accounts` e `client_whatsapp_business_accounts`). Isso requer acesso e permissões empresariais adequadas. Se a consulta não estiver completa ou autorizada, ela interrompe a operação. Se nenhum ativo corresponder, adicione o telefone no WhatsApp Manager à WABA correta. A ausência nas contas consultadas não comprova ausência em outras contas inacessíveis.
6. Para estado `NOT_VERIFIED` ou `EXPIRED`, marque a confirmação explícita e pressione o botão de ligação (VOICE, padrão) ou SMS. Cada clique autorizado pode enviar uma única solicitação. Não há tentativas automáticas. `VERIFIED` não solicita código.
7. Digite os seis dígitos, preservando zeros iniciais, e confirme. Caso tenha usado descoberta por Business ID, informe o mesmo ID neste formulário. A confirmação pode ser feita logo após a solicitação; a pausa de reenvio não a impede.
8. Consulte o histórico técnico: HTTP, código/subcódigo Meta, mensagem, detalhes, tipo, mensagem ao usuário, fbtrace_id, operação, sucesso e horário com fuso.

Há pausa local de cinco minutos para novo envio e de um minuto entre confirmações. Limites da Meta/Retry-After bloqueiam operações por pelo menos uma hora ou pelo prazo maior retornado. Esses intervalos são precauções locais, não uma garantia de liberação da Meta; se a Meta indicar prazo maior, respeite-o. Não alterne tokens para evitar limites. Uma falha de transporte pode ter ocorrido após a Meta aceitar o envio: aguarde o código e não repita imediatamente.

Após `VERIFIED`, registre o Phone Number ID brasileiro via `POST /v27.0/{PHONE_NUMBER_ID}/register`, com `messaging_product: whatsapp` e um PIN de duas etapas administrado com segurança (diferente do código SMS/ligação). Confira o registro, o faturamento, as permissões e a inscrição do app na WABA. Este recurso não realiza o registro nem altera webhook. Para usar o telefone em envios, revise conscientemente o Phone Number ID nas credenciais existentes: a descoberta não substitui automaticamente o ID configurado.

Referências oficiais da coleção Meta:
- [Solicitar código](https://www.postman.com/meta/whatsapp-business-platform/request/26ynhhh/request-verification-code)
- [Confirmar código](https://www.postman.com/meta/whatsapp-business-platform/request/6xlu022/verify-code)
- [Registro na Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api/reference/registration)

## Validação realizada

`C:/xampp/php/php.exe tests/phone-verification-test.php` — aprovado, usando transporte simulado sem rede. Abrange identidade brasileira, rejeição do ID de teste, descoberta de WABAs, paginação sem seguir URLs externas, ausência do número, já verificado, confirmação obrigatória, VOICE/SMS, pausas, seis dígitos e zeros iniciais, erros e supressão de segredos.

Todos os arquivos PHP de `app` passaram em `php -l`. A interface ainda precisa de validação autenticada no servidor com banco e configuração reais. A cópia local não contém `storage/config.local.php`; nenhum arquivo alternativo de credenciais foi ativado ou sobrescrito.

Consulta real exclusivamente de leitura, autorizada pelos dados fornecidos na sessão: `GET /v27.0/1672173261195301/phone_numbers`. Resultado: HTTP 401, erro Meta 190, subcódigo 463 (token expirado), fbtrace_id `A9z0Cl0cxJDRO8A-Aq9Yk2S`. Nenhum token foi salvo no projeto. Nenhuma chamada real a `request_code`, `verify_code` ou `register` foi realizada. Cadastro e elegibilidade do telefone brasileiro permanecem indeterminados até consulta com token vigente.
