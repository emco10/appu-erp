<?php
require '../../global/connection.php';
require_once '../../global/sunat-api.php';
require_once '../../global/peru-tax-profile.php';
require_once '../../global/peru-tax-rules.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
$method=$_SERVER['REQUEST_METHOD']??'GET';
$actionValue=$_POST['action']??$_GET['action']??'settings';
$action=is_string($actionValue)?$actionValue:'';
app_require_permission($method==='GET'?'management.view':'management.manage');
try{
    if($method==='GET'&&$action==='settings'){
        echo json_encode(['settings'=>app_peru_settings($pdo),'catalog'=>app_peru_spot_catalog(),'schema_ready'=>app_peru_table_exists($pdo,'tbl_peru_integration_settings')],JSON_UNESCAPED_UNICODE);exit;
    }
    if($method!=='POST'){http_response_code(405);throw new DomainException('Usa una acción manual válida.');}
    if($action==='check'){
        $response=app_sunat_api_request('GET','/api/v1/integrations/status');
        http_response_code($response['status']);echo json_encode($response['body'],JSON_UNESCAPED_UNICODE);exit;
    }
    if(!app_peru_table_exists($pdo,'tbl_peru_integration_settings'))throw new DomainException('Falta aplicar la migración 20261106_create_peru_integrations.sql.');
    if($action==='settings'){
        $values=[];
        foreach(['identity_enabled','gre_enabled','sire_enabled','retention_enabled','detraction_enabled'] as $key){
            $value=$_POST[$key]??'0';if((!is_string($value)&&!is_int($value))||!in_array((string)$value,['0','1'],true))throw new DomainException('Configuración de activación inválida.');$values[]=(int)$value;
        }
        $agent=$_POST['company_retention_agent']??'';
        if((!is_string($agent)&&!is_int($agent))||!in_array((string)$agent,['','0','1'],true))throw new DomainException('Condición de agente retenedor inválida.');$agent=(string)$agent;
        $values[]=$agent===''?null:(int)$agent;$values[]=(int)$_SESSION['loggedInUser']['USERID'];
        $pdo->prepare('UPDATE tbl_peru_integration_settings SET identity_enabled=?,gre_enabled=?,sire_enabled=?,retention_enabled=?,detraction_enabled=?,company_retention_agent=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=1')->execute($values);
        echo json_encode(['message'=>'Configuración guardada. Las claves y habilitaciones de la API se configuran por separado en el servidor.']);exit;
    }
    if(in_array($action,['evaluate','save_evaluation'],true)){
        app_require_permission('finance.manage');
        $settings=app_peru_settings($pdo);
        $evaluation=$action==='save_evaluation'
            ?app_peru_tax_save_evaluation($pdo,$_POST,$settings,(int)$_SESSION['loggedInUser']['USERID'])
            :app_peru_tax_evaluate(app_peru_tax_request_inputs($_POST),$settings);
        if($action==='evaluate')$evaluation['evaluation_signature']=app_peru_tax_evaluation_signature($evaluation);
        echo json_encode(['data'=>$evaluation],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    }
    throw new DomainException('Acción no reconocida.');
}catch(DomainException $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
catch(Throwable $e){error_log('Peru integrations request failed: '.$e::class);http_response_code(503);echo json_encode(['message'=>'No se pudo completar la operación. Verifica las migraciones y la conexión privada de la API.']);}
