<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$script=file_get_contents($root.'/js/footer-functions.js');
$required=['window.appuFormDirty','window.appuMarkDirty','window.appuMarkSaved','window.appuConfirmDiscard','beforeunload.appuDirty','click.appuDirtyLinks','ajaxSuccess','MODIFICADO'];
foreach($required as $token){if(strpos($script,$token)===false){fwrite(STDERR,"FAIL unsaved changes contract: {$token}\n");exit(1);}}
if(strpos($script,"type: 'warning'")===false||strpos($script,"icon: 'warning'")!==false){fwrite(STDERR,"FAIL unsaved changes contract: SweetAlert2 v8 warning configuration\n");exit(1);}
$forms=['FRM_INSERT_CLIENTE','FRM_INSERT_PROVEEDOR','FRM_INSERT_PRODUCTO','FRM_INSERT_EMPLEADO','FRM_INSERT_USUARIO','FRM_INSERT_COTIZACION','FRM_INSERT_FACTURA','FRM_INSERT_DETA_ORDCOMPRA'];
$views=glob($root.'/views/*/*.php');$source='';foreach($views as $view)$source.=file_get_contents($view);
foreach($forms as $form){if(strpos($source,$form)===false){fwrite(STDERR,"FAIL missing protected form: {$form}\n");exit(1);}}
echo "PASS unsaved changes navigation contract\n";
