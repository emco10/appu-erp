<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../global/peru-tax-rules.php';
require __DIR__.'/../global/peru-tax-profile.php';

final class PeruEvaluationPDOException extends PDOException
{
    public function __construct(int $driver){parent::__construct('Synthetic integrity failure');$this->code='23000';$this->errorInfo=['23000',$driver,'Synthetic'];}
}
final class PeruEvaluationMemoryPDO extends PDO
{
    public array $rows=[];
    public int $insertCalls=0;
    public ?PDOException $insertFailure=null;
    public bool $concurrentInsert=false;
    public function __construct(){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new PeruEvaluationStatement($this,$query);}
}
final class PeruEvaluationStatement extends PDOStatement
{
    private array|false $row=false;
    public function __construct(private PeruEvaluationMemoryPDO $pdo,private string $sql){}
    public function execute(?array $params=null):bool
    {
        if(str_starts_with($this->sql,'SELECT ')){$this->row=$this->pdo->rows[$params[0]]??false;return true;}
        if(!str_starts_with($this->sql,'INSERT INTO tbl_peru_tax_evaluation('))throw new RuntimeException('Unexpected SQL in pure persistence test.');
        $this->pdo->insertCalls++;
        [$key,$hash,$type,$reference,$json,$user]=$params;
        if($this->pdo->insertFailure!==null)throw $this->pdo->insertFailure;
        $this->pdo->rows[$key]=['id'=>count($this->pdo->rows)+1,'request_sha256'=>$hash,'operation_type'=>$type,'document_reference'=>$reference,'evaluation_json'=>$json,'created_by'=>$user];
        if($this->pdo->concurrentInsert)throw new PeruEvaluationPDOException(1062);
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->row;}
}
$checks=0;
$assert=static function(bool $condition,string $message)use(&$checks):void{$checks++;if(!$condition)throw new RuntimeException($message);};
$reject=static function(callable $call,string $class,string $message)use($assert):void{try{$call();}catch(Throwable $error){$assert($error instanceof $class,$message);return;}$assert(false,$message);};
$settings=['retention_enabled'=>1,'detraction_enabled'=>1,'company_retention_agent'=>1];
$input=['regime'=>'retention','operation_type'=>'purchase','operation_date'=>date('Y-m-d'),'tax_obligation_date'=>date('Y-m-d'),'document_reference'=>'20123456789 F001-123','gross_operation'=>'1180.00','gross_payment'=>'590.00','exclusions_reviewed'=>'1','igv_taxed'=>'1','spot_applicability'=>'not_applicable','supplier_retention_agent'=>'0','supplier_perception_agent'=>'0','supplier_good_taxpayer'=>'0','request_key'=>str_repeat('a',40)];
$input['evaluation_signature']=app_peru_tax_evaluation_signature(app_peru_tax_evaluate(app_peru_tax_request_inputs($input),$settings));
$pdo=new PeruEvaluationMemoryPDO();
$first=app_peru_tax_save_evaluation($pdo,$input,$settings,8);
$assert($first['saved']===true&&$first['evaluation_id']===1&&$first['tax_cents']===1770&&$pdo->insertCalls===1,'Initial explicit save persists exactly one previewed evaluation.');
$stored=json_decode($pdo->rows[$input['request_key']]['evaluation_json'],true,32,JSON_THROW_ON_ERROR);
$assert($stored['settings']['company_retention_agent']===1&&$stored['inputs']['tax_obligation_date']===$input['tax_obligation_date'],'Persist the reviewed dates, inputs and relevant settings as audit evidence.');
$reordered=array_reverse($input,true);
$reordered['action']='save_evaluation';$reordered['csrf_token']='not-part-of-operation';
$assert(app_peru_tax_save_evaluation($pdo,$reordered,[],8)===$first&&$pdo->insertCalls===1,'A reordered retry replays the original stored result after features are disabled.');
$assert(app_peru_tax_save_evaluation($pdo,$input,array_replace($settings,['company_retention_agent'=>0]),8)===$first&&$pdo->insertCalls===1,'A changed company designation cannot recalculate an existing saved evaluation.');
$reject(fn()=>app_peru_tax_save_evaluation($pdo,array_replace($input,['gross_payment'=>'500.00']),$settings,8),DomainException::class,'One key cannot be reused for different operation inputs.');
$reject(fn()=>app_peru_tax_save_evaluation($pdo,$input,$settings,9),DomainException::class,'One user cannot replay another user record.');
$fresh=array_replace($input,['request_key'=>str_repeat('b',40)]);
$reject(fn()=>app_peru_tax_save_evaluation($pdo,$fresh,array_replace($settings,['company_retention_agent'=>0]),8),DomainException::class,'First save must reject a changed fiscal result since preview.');
$reject(fn()=>app_peru_tax_save_evaluation($pdo,array_replace($fresh,['evaluation_signature'=>'']),$settings,8),DomainException::class,'First save requires a reviewed preview signature.');
$assert(count($pdo->rows)===1&&$pdo->insertCalls===1,'Invalid first saves and replays must not insert another row.');
$race=new PeruEvaluationMemoryPDO();$race->concurrentInsert=true;
$assert(app_peru_tax_save_evaluation($race,$fresh,$settings,8)['tax_cents']===1770&&$race->insertCalls===1,'A concurrent identical insert can replay the winner on MySQL duplicate1062.');
$failure=new PeruEvaluationMemoryPDO();$failure->insertFailure=new PeruEvaluationPDOException(1452);
$reject(fn()=>app_peru_tax_save_evaluation($failure,$fresh,$settings,8),PDOException::class,'Other integrity errors cannot be swallowed as duplicate retries.');
$reject(fn()=>app_peru_tax_request_inputs(array_replace($input,['gross_payment'=>[]])),DomainException::class,'Malformed arrays must not be string-cast into evaluation inputs.');
$spot=array_replace($input,['request_key'=>str_repeat('c',40),'regime'=>'detraction','spot_applicability'=>'applicable','spot_category'=>'annex3_other_services','fiscal_credit_document'=>'1','spot_rounding'=>'soles','already_deposited'=>'142']);
$spot['evaluation_signature']=app_peru_tax_evaluation_signature(app_peru_tax_evaluate(app_peru_tax_request_inputs($spot),$settings));
$rounded=app_peru_tax_save_evaluation($pdo,$spot,$settings,8);
$stored=json_decode($pdo->rows[$spot['request_key']]['evaluation_json'],true,32,JSON_THROW_ON_ERROR);
$assert($rounded['tax_cents']===0&&$stored['inputs']['spot_rounding']==='soles'&&$stored['result']['total_detraction_cents']===14200,'The chosen SUNAT rounding and prior deposit must survive the immutable saved snapshot.');
echo 'PASS '.$checks." tax evaluation persistence and concurrency invariants; fake PDO, no database or network\n";
