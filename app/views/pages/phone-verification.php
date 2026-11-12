<?php $pv=$data['phone_verification']??[]; ?>
<section class="panel connect-panel" style="margin-top:24px">
<div class="panel-head"><div><h2>Verificação do número de telefone</h2><p>ZapAPI · App 1683987243242501 · Graph API v27.0</p></div></div>
<dl><dt>Número de telefone</dt><dd>+5535997570768</dd><dt>WABA ID</dt><dd><?=e($pv['waba_id']??'Ainda não identificado')?></dd><dt>Phone Number ID</dt><dd><?=e($pv['phone_number_id']??'Ainda não identificado')?></dd><dt>Estado de verificação</dt><dd><?=e($pv['status']??'Não consultado')?></dd><dt>Última consulta</dt><dd><?=e($pv['checked_at']??'—')?></dd></dl>
<p>O Phone Number ID é identificado pelo telefone retornado pela Meta. A ferramenta não usa o ID do número de teste. A WABA configurada será consultada; para pesquisar outras contas, informe o Business ID do portfólio.</p>
<?php if(($pv['blocked_until']??0)>time()): ?><div class="notice info">Aguarde até <?=e(date('d/m/Y H:i:s',(int)$pv['blocked_until']))?> (America/Sao_Paulo). Se a Meta indicar prazo maior, respeite esse prazo.</div><?php endif; ?>
<form method="post" class="form-stack">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="r" value="setup-meta">
<label>Business ID do portfólio (opcional)<input name="business_id" inputmode="numeric" pattern="[0-9]+" placeholder="Descobrir WABAs próprias e compartilhadas"><small>Requer acesso aos ativos e permissões empresariais adequadas.</small></label>
<button type="submit" class="button secondary" name="action" value="phone_lookup" formnovalidate>Consultar situação</button>
<label><input type="checkbox" name="confirm_request" value="yes"> Confirmo explicitamente a solicitação de um único código para +5535997570768 pelo método do botão selecionado.</label>
<button type="submit" class="button primary" name="code_method" value="VOICE" formaction="?r=setup-meta&amp;phone_action=request">Solicitar código por ligação</button>
<button type="submit" class="button secondary" name="code_method" value="SMS" formaction="?r=setup-meta&amp;phone_action=request">Solicitar código por SMS</button>
<p>Ligação (VOICE) é o padrão. Não há reenvio automático. Após pedir, aguarde o código e os limites da Meta.</p>
</form>
<form method="post" class="form-stack" autocomplete="off">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="r" value="setup-meta"><input type="hidden" name="action" value="phone_verify">
<label>Business ID do portfólio (se usado na consulta)<input name="business_id" inputmode="numeric" pattern="[0-9]+"></label>
<label>Código recebido (seis dígitos)<input name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" required autocomplete="off"></label>
<button type="submit" class="button primary">Confirmar código</button>
</form>
<p>A confirmação do código comprova a posse do telefone. Depois, registre o Phone Number ID na Cloud API com um PIN de duas etapas, confira faturamento, permissões e a inscrição do aplicativo na WABA. O PIN de registro é diferente do código recebido.</p>
<h3>Histórico técnico das solicitações</h3><p>Até 100 operações, com horário e fuso. Tokens, segredos e o código recebido não são registrados.</p>
<?php foreach(array_reverse($pv['history']??[]) as $entry): ?><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?=e(json_encode($entry,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre><?php endforeach; ?>
<?php if(empty($pv['history'])): ?><p>Nenhuma operação registrada.</p><?php endif; ?>
</section>
