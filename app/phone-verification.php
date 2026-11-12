<?php
declare(strict_types=1);

const VERIFICATION_PHONE = '5535997570768';

// Separate transport: never retries, never follows a Meta-supplied URL.
function phone_graph(string $token, string $method, string $path, ?array $body=null): array {
    $ch=curl_init('https://graph.facebook.com/v27.0/'.$path);
    $retry=0;
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$retry){if(preg_match('/^Retry-After:\s*(.+)/i',$line,$m))$retry=ctype_digit(trim($m[1]))?(int)trim($m[1]):max(0,(int)strtotime(trim($m[1]))-time());return strlen($line);}]);
    if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
    $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return ['http'=>$http,'data'=>$raw===false?[]:(json_decode($raw,true)??[]),'retry_after'=>$retry];
}
function phone_redact(mixed $value, array $secrets): mixed {
    if(is_array($value)){foreach($value as $k=>$v)$value[$k]=preg_match('/token|secret|^code$/i',(string)$k)?'[oculto]':phone_redact($v,$secrets);return $value;}
    if(!is_string($value))return $value;
    foreach($secrets as $secret)if((string)$secret!=='')$value=str_replace((string)$secret,'[oculto]',$value);
    return preg_replace('/Bearer\s+\S+|EAA[A-Za-z0-9_-]+/i','[oculto]',$value);
}
function phone_verification_state(int $tid): array {
    $path=APP_ROOT.'/storage/phone-verification-'.$tid.'.json';
    return is_file($path)?(json_decode((string)file_get_contents($path),true)?:[]):[];
}
function phone_verification_run(array $config,array &$state,string $action,array $input,callable $transport): string {
    if(empty($config['access_token']))throw new RuntimeException('Salve um token autorizado na configuração existente.');
    if(!empty($config['meta_app_id'])&&$config['meta_app_id']!=='1683987243242501')throw new RuntimeException('A configuração deve corresponder ao aplicativo ZapAPI 1683987243242501.');
    if(!in_array($action,['phone_lookup','phone_request','phone_verify'],true))throw new RuntimeException('Ação inválida.');
    $method=(string)($input['code_method']??'VOICE');$code=(string)($input['verification_code']??'');
    if($action==='phone_request'&&(($input['confirm_request']??'')!=='yes'||!in_array($method,['VOICE','SMS'],true)))throw new RuntimeException('Confirme explicitamente a solicitação e selecione VOICE ou SMS.');
    if($action==='phone_verify'&&!preg_match('/^[0-9]{6}$/D',$code))throw new RuntimeException('Informe exatamente seis dígitos.');
    if(($state['blocked_until']??0)>time())throw new RuntimeException('Aguarde até '.date('d/m/Y H:i:s',(int)$state['blocked_until']).' antes de nova tentativa.');
    if($action==='phone_verify' && ($state['verify_until']??0)>time())throw new RuntimeException('Aguarde um minuto antes de confirmar novamente.');
    $secrets=[$config['access_token'],$config['app_secret']??'',$config['verify_token']??'',$code];
    $call=function(string $verb,string $path,?array $body=null)use($transport,$config,&$state,$secrets){
        $r=$transport($config['access_token'],$verb,$path,$body);$d=is_array($r['data']??null)?$r['data']:[];$err=$d['error']??[];$http=(int)($r['http']??0);
        $entry=['at'=>date(DATE_ATOM),'operation'=>explode('?',$path)[0],'method'=>$verb,'http'=>$http,'meta_code'=>$err['code']??null,'subcode'=>$err['error_subcode']??null,'message'=>$err['message']??($http>=200&&$http<300?'Sucesso':'Resposta inválida ou falha de transporte; situação incerta.'),'details'=>$err['error_data']??null,'type'=>$err['type']??null,'user_title'=>$err['error_user_title']??null,'user_message'=>$err['error_user_msg']??null,'fbtrace_id'=>$err['fbtrace_id']??null,'success'=>$d['success']??null];
        $state['history']=array_slice(array_merge($state['history']??[],[phone_redact($entry,$secrets)]),-100);
        $limited=$http===429||in_array((int)($err['code']??0),[4,17,32,613,80007,130429,131048,131056],true)||preg_match('/too many|rate limit|excess|muitas|multiple times/i',json_encode($err));
        if($limited||!empty($r['retry_after']))$state['blocked_until']=time()+max(3600,(int)($r['retry_after']??0));
        if($http<200||$http>=300||$err||!$d){
            if(preg_match('/already verified|j? verificado/i',json_encode($err)))throw new RuntimeException('A Meta informa que o n?mero j? est? verificado. N?o solicite outro c?digo; consulte a situa??o.');
            $hint=$limited?'Limite da Meta atingido. Aguarde o prazo informado pela Meta; não repita as solicitações.':(in_array((int)($err['code']??0),[10,190,200],true)?'Permissões insuficientes ou token inválido. Revise o acesso ao ativo.':((int)($err['code']??0)===100?'Ativo inexistente ou indisponível para este token. Confira WABA e permissões.':'Falha da Meta. Consulte o histórico técnico antes de tentar novamente.'));
            throw new RuntimeException($hint);
        }
        return $d;
    };
    // Always rediscover the Brazilian number, ignoring the configured (possibly test) Phone Number ID.
    unset($state['phone_number_id'],$state['status'],$state['waba_id']);
    $waba=trim((string)($config['waba_id']??''));$business=trim((string)($input['business_id']??''));$wabas=[];
    $pages=function(string $path)use($call){$rows=[];$cursor='';$seen=[];do{$d=$call('GET',$path.($cursor!==''?'&after='.rawurlencode($cursor):''));if(!isset($d['data'])||!is_array($d['data']))throw new RuntimeException('Lista inválida retornada pela Meta.');$rows=array_merge($rows,$d['data']);$cursor=!empty($d['paging']['next'])?(string)($d['paging']['cursors']['after']??''):'';if(!empty($d['paging']['next'])&&($cursor===''||isset($seen[$cursor])))throw new RuntimeException('Paginação incompleta. Não é possível concluir a consulta.');$seen[$cursor]=true;}while($cursor!=='');return $rows;};
    if($waba!==''){if(!ctype_digit($waba))throw new RuntimeException('WABA ID inválido.');$wabas[]=$waba;}
    if($business!==''){
        if(!ctype_digit($business))throw new RuntimeException('Business ID inválido.');
        foreach(['owned_whatsapp_business_accounts','client_whatsapp_business_accounts'] as $edge)foreach($pages($business.'/'.$edge.'?fields=id&limit=100') as $row)$wabas[]=(string)$row['id'];
    }
    if(!$wabas)throw new RuntimeException('Informe o WABA ID nas credenciais ou o Business ID do portfólio para localizar as WABAs.');
    $matches=[];
    foreach(array_unique($wabas) as $id)foreach($pages($id.'/phone_numbers?fields=id,display_phone_number&limit=100') as $number)if(preg_replace('/\D/','',(string)($number['display_phone_number']??''))===VERIFICATION_PHONE)$matches[]=['waba_id'=>$id,'phone_number_id'=>(string)$number['id']];
    if(!$matches){$state['status']='NOT_FOUND';return 'Número não encontrado nas WABAs consultadas. Adicione +5535997570768 à conta WhatsApp Business correta antes da verificação.';}
    if(count($matches)!==1)throw new RuntimeException('Mais de um ativo corresponde ao telefone. Revise a conta no WhatsApp Manager.');
    $match=$matches[0];if(!ctype_digit($match['phone_number_id']))throw new RuntimeException('Phone Number ID inválido retornado pela Meta.');
    $number=$call('GET',$match['phone_number_id'].'?fields=display_phone_number,code_verification_status');
    if(preg_replace('/\D/','',(string)($number['display_phone_number']??''))!==VERIFICATION_PHONE)throw new RuntimeException('O ativo consultado não corresponde ao número brasileiro.');
    $state=array_merge($state,$match,['status'=>$number['code_verification_status']??'UNKNOWN','checked_at'=>date(DATE_ATOM)]);
    if($state['status']==='VERIFIED')return 'Número já verificado. Nenhum código foi solicitado. Próximo passo: registrar o número na Cloud API com PIN de duas etapas.';
    if($action==='phone_lookup')return 'Número cadastrado. Estado: '.$state['status'];
    if(!in_array($state['status'],['NOT_VERIFIED','EXPIRED'],true))throw new RuntimeException('Estado não permite verificação segura. Consulte o WhatsApp Manager.');
    if($action==='phone_request'){
        if(($state['request_until']??0)>time())throw new RuntimeException('Aguarde cinco minutos antes de solicitar outro c?digo.');
        $state['request_until']=time()+300; // Even a timeout can mean that Meta dispatched the code.
        $r=$call('POST',$match['phone_number_id'].'/request_code',['code_method'=>$method,'language'=>'pt_BR']);
    }else{
        $state['verify_until']=time()+60;
        $r=$call('POST',$match['phone_number_id'].'/verify_code',['code'=>$code]);
    }
    if(($r['success']??false)!==true)throw new RuntimeException('A Meta não confirmou sucesso. Consulte o histórico.');
    if($action==='phone_verify')$state['status']='VERIFIED';
    return $action==='phone_verify'?'Meta confirmou a verificação. Próximo passo: registro na Cloud API com PIN de duas etapas.':'Meta aceitou a solicitação por '.$method.'. Aguarde o código; não repita a solicitação.';
}
function phone_verification_action(string $action): void {
    $tid=tenant_id();$path=APP_ROOT.'/storage/phone-verification-'.$tid.'.json';$f=fopen($path,'c+');
    if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Não foi possível bloquear o histórico de verificação.');
    try{
        $state=json_decode(stream_get_contents($f),true)?:[];
        try{$message=phone_verification_run(meta_config($tid),$state,$action,$_POST,'phone_graph');flash($message);}catch(Throwable $e){flash($e instanceof RuntimeException?$e->getMessage():'Falha interna na verificação.','error');}
        $json=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);rewind($f);if(!ftruncate($f,0)||fwrite($f,$json)!==strlen($json)||!fflush($f))throw new RuntimeException('Falha ao salvar o histórico.');
    }finally{flock($f,LOCK_UN);fclose($f);unset($_POST['verification_code']);}
}
