<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/peru-tax-rules.php';
require __DIR__.'/../global/peru-tax-profile.php';
$checks=0;
$assert=static function(bool $condition,string $message)use(&$checks):void{$checks++;if(!$condition)throw new RuntimeException($message);};
$reject=static function(callable $call,string $message)use($assert):void{try{$call();}catch(DomainException){$assert(true,$message);return;}$assert(false,$message);};
$settings=['retention_enabled'=>1,'detraction_enabled'=>1,'company_retention_agent'=>1];
$base=['regime'=>'retention','operation_type'=>'purchase','operation_date'=>date('Y-m-d'),'tax_obligation_date'=>date('Y-m-d'),'gross_operation'=>'1180.00','gross_payment'=>'590.00','exclusions_reviewed'=>'1','igv_taxed'=>'1','spot_applicability'=>'not_applicable','supplier_retention_agent'=>'0','supplier_perception_agent'=>'0','supplier_good_taxpayer'=>'0'];
$r=app_peru_tax_evaluate($base,$settings);
$assert($r['status']==='calculated'&&$r['tax_cents']===1770&&$r['net_to_supplier_cents']===57230,'3% must apply to each gross partial payment, including IGV.');
$assert($r['accounting_posted']===false,'An evaluation must not claim a journal entry or payment.');
foreach(['supplier_retention_agent','supplier_perception_agent','supplier_good_taxpayer'] as $key){
    $r=app_peru_tax_evaluate(array_replace($base,[$key=>'']),$settings);$assert($r['status']==='review_required'&&$r['tax_cents']===null,'Unknown tax condition cannot become false.');
    $r=app_peru_tax_evaluate(array_replace($base,[$key=>'1']),$settings);$assert($r['status']==='excluded'&&$r['tax_cents']===0,'An excluded supplier must not be withheld.');
}
$r=app_peru_tax_evaluate(array_replace($base,['gross_operation'=>'700','gross_payment'=>'700']),$settings);$assert($r['status']==='excluded','700 inclusive payment and operation threshold.');
$r=app_peru_tax_evaluate(array_replace($base,['gross_operation'=>'700.01','gross_payment'=>'100']),$settings);$assert($r['tax_cents']===300,'A small payment for an operation exceeding 700 remains subject.');
$r=app_peru_tax_evaluate($base,array_replace($settings,['company_retention_agent'=>null]));$assert($r['status']==='review_required','Unknown buyer designation needs review.');
$r=app_peru_tax_evaluate($base,array_replace($settings,['company_retention_agent'=>0]));$assert($r['status']==='excluded','A buyer not designated cannot withhold.');
$r=app_peru_tax_evaluate(array_replace($base,['spot_applicability'=>'applicable']),$settings);$assert($r['status']==='excluded','SPOT excludes retention.');
$r=app_peru_tax_evaluate(array_replace($base,['operation_type'=>'sale','buyer_retention_agent'=>'1']),array_replace($settings,['company_retention_agent'=>0]));$assert($r['tax_cents']===1770,'Sales use the buyer designation, not company designation.');
foreach(['',null,'unknown','2',2,false,true,[]] as $unknown){
    $r=app_peru_tax_evaluate(array_replace($base,['operation_type'=>'sale','buyer_retention_agent'=>$unknown]),$settings);
    $assert($r['status']==='review_required'&&$r['tax_cents']===null,'An unknown or malformed buyer designation must never become a verified no.');
    $r=app_peru_tax_evaluate($base,array_replace($settings,['company_retention_agent'=>$unknown]));
    $assert($r['status']==='review_required'&&$r['tax_cents']===null,'Malformed company designation requires review.');
}
$r=app_peru_tax_evaluate(array_replace($base,['tax_obligation_date'=>'2014-02-28']),$settings);$assert($r['status']==='review_required'&&$r['tax_cents']===null&&!isset($r['rate_bps']),'Do not apply the current retention rate to a historic IGV obligation.');
$r=app_peru_tax_evaluate(array_replace($base,['tax_obligation_date'=>'2014-03-01']),$settings);$assert($r['tax_cents']===1770,'The retention rate starts at the legal IGV obligation date, not the payment date.');
$spot=array_replace($base,['regime'=>'detraction','spot_applicability'=>'applicable','spot_category'=>'annex3_other_services','fiscal_credit_document'=>'1']);
$r=app_peru_tax_evaluate($spot,$settings);$assert($r['tax_cents']===14160&&$r['total_detraction_cents']===14160&&$r['net_to_supplier_cents']===44840,'SPOT uses the whole operation, not the partial payment.');
$r=app_peru_tax_evaluate(array_replace($spot,['already_deposited'=>'141.60']),$settings);$assert($r['tax_cents']===0,'An already declared deposit must not be duplicated.');
$r=app_peru_tax_evaluate(array_replace($spot,['spot_rounding'=>'soles','already_deposited'=>'142']),$settings);$assert($r['tax_cents']===0&&$r['total_detraction_cents']===14200&&$r['net_to_supplier_cents']===59000&&$r['spot_rounding']==='soles','A lawful whole-sol142 deposit must not be rejected against the141.60 unrounded amount.');
$r=app_peru_tax_evaluate(array_replace($spot,['spot_rounding'=>'soles','gross_operation'=>'1179.16']),$settings);$assert($r['total_detraction_cents']===14100,'Do not double-round141.4992 through141.50 into142 soles.');
$r=app_peru_tax_evaluate(array_replace($spot,['spot_rounding'=>'soles','gross_operation'=>'1179.17']),$settings);$assert($r['total_detraction_cents']===14200,'A fraction above half a sol rounds upward.');
$r=app_peru_tax_evaluate(array_replace($spot,['spot_category'=>'annex3_construction']),$settings);$assert($r['tax_cents']===4720,'Construction rate differs from general services.');
$r=app_peru_tax_evaluate(array_replace($spot,['gross_payment'=>'100']),$settings);$assert($r['status']==='review_required'&&$r['tax_cents']===null,'A deposit larger than this payment requires review.');
$r=app_peru_tax_evaluate(array_replace($spot,['spot_category'=>'cargo_transport']),$settings);$assert($r['status']==='review_required','Do not apply service rules to cargo transport or goods.');
$r=app_peru_tax_evaluate(array_replace($spot,['gross_operation'=>'700','gross_payment'=>'100']),$settings);$assert($r['status']==='excluded','Annex3 threshold is exclusive above700.');
$r=app_peru_tax_evaluate(array_replace($spot,['tax_obligation_date'=>'2018-03-31']),$settings);$assert($r['status']==='review_required'&&$r['tax_cents']===null&&!isset($r['rate_bps']),'Historical SPOT must not assume the current catalogue rates.');
$r=app_peru_tax_evaluate(array_replace($spot,['tax_obligation_date'=>'2018-04-01']),$settings);$assert($r['tax_cents']===14160,'Current service catalogue starts at the documented legal boundary.');
foreach(['operation_date','tax_obligation_date'] as $field){
    $r=app_peru_tax_evaluate(array_replace($base,[$field=>(new DateTimeImmutable('tomorrow'))->format('Y-m-d')]),$settings);
    $assert($r['status']==='review_required'&&$r['tax_cents']===null&&!isset($r['rate_bps']),'No promise of future fiscal rates.');
}
foreach(['-1','NaN','1e2','10,00','1.001','',[],INF] as $amount)$reject(fn()=>app_peru_money_cents($amount,'Amount'),'Invalid money must be rejected.');
$reject(fn()=>app_peru_tax_evaluate(array_replace($base,['operation_date'=>'2026-02-30']),$settings),'Invalid calendar date.');
$reject(fn()=>app_peru_tax_evaluate(array_replace($base,['tax_obligation_date'=>'']),$settings),'The IGV obligation date cannot be inferred from the payment date.');
$reject(fn()=>app_peru_tax_evaluate(array_replace($base,['tax_obligation_date'=>'2026-02-30']),$settings),'Invalid IGV obligation date.');
$reject(fn()=>app_peru_tax_evaluate(array_replace($base,['currency'=>'USD']),$settings),'No silent foreign-currency threshold conversion.');
$reject(fn()=>app_peru_tax_evaluate(array_replace($base,['spot_applicability'=>'bypass']),$settings),'No arbitrary SPOT values.');
$reject(fn()=>app_peru_tax_evaluate($base,[]),'Features start disabled.');
$reject(fn()=>app_peru_tax_evaluate($base,array_replace($settings,['retention_enabled'=>2])),'Malformed flags do not activate assistance.');
$reject(fn()=>app_peru_tax_evaluate(array_replace($spot,['spot_rounding'=>'bypass']),$settings),'Invalid SPOT rounding cannot silently change the deposit.');
$profile=['document_number'=>'20123456789','tax_profile'=>['retention_agent'=>true,'good_taxpayer'=>false],'source'=>'decolecta','consulted_at'=>'2026-11-06T11:00:00-05:00','tax_profile_evidence'=>['retention_agent'=>['provider'=>'decolecta','field'=>'es_agente_retencion','consulted_at'=>'2026-11-06T11:00:00-05:00']],'cache_hit'=>true];
$normal=app_partner_tax_profile_normalize('20123456789',json_encode($profile));
$assert($normal['tax_profile']['perception_agent']===null&&$normal['tax_profile']['good_taxpayer']===false,'Unknown and false flags are distinct.');
$assert($normal['tax_profile_evidence']['retention_agent']['field']==='es_agente_retencion'&&$normal['cache_hit']===true,'Preserve provenance and cache observation.');
$reject(fn()=>app_partner_tax_profile_normalize('20999999991',json_encode($profile)),'Profile document mismatch.');
$reject(fn()=>app_partner_tax_profile_normalize('20123456789',json_encode(array_replace($profile,['tax_profile'=>['retention_agent'=>'false']]))),'String booleans must not silently authorize tax behavior.');
foreach(['document_number'=>[],'tax_profile'=>'false','detraction_account'=>[],'source'=>[],'consulted_at'=>[]]as$field=>$value)$reject(fn()=>app_partner_tax_profile_normalize('20123456789',json_encode(array_replace($profile,[$field=>$value]))),'Malformed profile fields must report a domain error without string-cast warnings.');
$normal=app_partner_tax_profile_normalize('12345678',json_encode(array_replace($profile,['document_number'=>'12345678'])));
$assert($normal['tax_profile']['retention_agent']===null,'DNI cannot carry RUC-designated tax conditions.');
echo 'PASS '.$checks." Peru tax and profile invariants; no network or financial writes\n";
