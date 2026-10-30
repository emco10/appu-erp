<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
        	<div class="row mb-0">
                <div class="col-md-12">
                    <div class="m-0 text-dark text-center text-lg">
                        <i class="fas fa-file-invoice"></i>&nbsp;&nbsp;Factura de proveedor
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
        	<div style="max-width: 1140px; margin: 0 auto;">
				<div class="row mb-3">
	                <div class="col-md-6">
	                    <div class="row">
	                        <div class="col-md-8 float-right">
	                            <select class="select2 form-control" name="orden_listado" required></select>
	                        </div>
	                        <div class="col-md-4 float-left">
	                            <button type="button" id="btn-select-orden" class="btn btn-primary">Seleccionar</button>
	                        </div>
	                    </div>
	                </div>
	                <div id="col-btn-nuevacompra" class="col-md-6">
                        <button type="button" id="btn-nuevacompra" class="btn btn-primary btn-block"><i class="fa fa-plus fa-1x"></i>&nbsp;&nbsp;<font>Nueva factura de proveedor</font></button>
                    </div>
	            </div>
	            <form id="FRM_INSERT_DETA_ORDCOMPRA" method="post" action="<?php echo $functions->direct_sistema(); ?>/modules/compras/insert-update-compra.php" enctype="multipart/form-data">
	                <input type="hidden" name="orden_id">
	                <div class="form-group mb-3">
	                    <label for="purchase-order-source"><i class="fas fa-link mr-1"></i>Orden de compra origen</label>
	                    <select id="purchase-order-source" name="orden_compra_id" class="form-control select2">
	                        <option value="">Sin orden de compra</option>
	                    </select>
	                    <small class="form-text text-muted">Vincula esta compra con una orden aprobada para mantener la trazabilidad y evitar duplicidades.</small>
	                </div>
	                <div class="card card-primary">
	                    <div class="card-header">
	                    	<div class="row">
	                    		<div class="col-md-3">
	                    			<div class="card-title">Datos de Compra</div>
	                    		</div>
	                    		<div class="col-md-9">
	                    			<div class="row">
	                    				<div class="col-md-8"></div>
	                    				<div class="col-md-4">
	                    					<div class="row">
			                            		<div class="col-md-5 text-right">
				                                    <label>Estado:</label>
			                            		</div>
			                            		<div class="col-md-7">
			                            			<select class="form-control" name="orden_estado" disabled>
				                                        <option value="Aprobado">Aprobada</option>
				                                        <option value="Anulado">Anulada</option>
				                                        <option value="Pendiente" selected>Pendiente</option>
			                                    	</select>
			                            		</div>
			                            	</div>
	                    				</div>
	                    			</div>
	                    		</div>
	                    	</div>
	                    </div>
	                    <div class="card-body pt-3">
	                    	<div class="row">
	                    		<!--
	                            <div class="col-md-3">
	                            	<div class="row">
	                            		<div class="col-md-5 text-right">
		                                    <label>TC Venta:</label>
	                            		</div>
	                            		<div class="col-md-7">
	                            			<div class="input-group">
	                                            <div class="input-group-prepend">
	                                                <span class="input-group-text">
	                                                    <i class="fas fa-dollar-sign"></i>
	                                                </span>
	                                            </div>
	                                            <input type="number" step="0.01" min="0" class="form-control" placeholder="Venta" name="orden_tcventa">
	                                        </div>
	                            		</div>
	                            	</div>
	                            </div>
	                            <div class="col-md-3">
	                            	<div class="row">
	                            		<div class="col-md-5 text-right">
		                                    <label>TC Compra:</label>
	                            		</div>
	                            		<div class="col-md-7">
	                            			<div class="input-group">
	                                            <div class="input-group-prepend">
	                                                <span class="input-group-text">
	                                                    <i class="fas fa-dollar-sign"></i>
	                                                </span>
	                                            </div>
	                                            <input type="number" step="0.01" min="0" class="form-control" placeholder="Compra" name="orden_tccompra">
	                                        </div>
	                            		</div>
	                            	</div>
	                            </div>
	                        	-->
	                        	<div class="col-md-4">
	                        		<div class="form-group">
	                                    <label>N° Compra</label>
	                                    <div class="input-group mb-3">
	                                        <input type="text" name="orden_nro" class="form-control" placeholder="Número de documento de compra" value="" required>
	                                    </div>
	                                </div>
	                        	</div>
	                         	<div class="col-md-4">
	                         		<div class="form-group">
	                                    <label>Tipo de factura</label>
	                                    <select name="orden_tipo_documento" class="form-control" required>
	                                        <option value="STANDARD" selected>Factura estándar de proveedor</option>
	                                        <option value="RESERVE">Factura de reserva de proveedor</option>
	                                    </select>
	                                    <small class="form-text text-muted">La reserva genera la CxP al aprobarse; la estándar la genera al completar la recepción.</small>
	                                </div>
	                         	</div>
	                            <div class="col-md-4"></div>
                    		</div>
	                        
	                        <div class="row">
	                        	<div class="col-md-4">
	                                <div class="form-group">
	                                    <label>Fecha de Emisión</label>
	                                    <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="far fa-calendar-alt"></i>
                                                </span>
                                            </div>
                                            <input type="date" name="orden_fecemision" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
	                                </div>
	                            </div>
	                            <div class="col-md-4">
	                                <div class="form-group">
	                                    <label>Fecha de Entrega</label>
	                                    <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="far fa-calendar-alt"></i>
                                                </span>
                                            </div>
                                            <input type="date" name="orden_fecentrega" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
	                                </div>
	                            </div>
	                            <div class="col-md-4">
	                                <div class="form-group">
	                                    <label>Tipo de Moneda</label>
	                                    <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-coins"></i>
                                                </span>
                                            </div>
                                            <select name="orden_tipomoneda" class="form-control" required>
	                                        <option value="">Seleccione</option>
	                                        <option value="MN" selected>Moneda Nacional</option>
	                                        <option value="ME">Moneda Extranjera</option>
	                                    </select>
                                        </div>
	                                    
	                                </div>
	                            </div>
	                            <!--
	                            <div class="col-md-3">
	                                <div class="form-group">
	                                    <label>N° Cotización</label>
	                                    <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </span>
                                            </div>
                                            <input type="text" name="orden_cotizacion" class="form-control" placeholder="Número Cotización">
                                        </div>
	                                </div>
	                            </div>
	                        	-->
	                        </div>
	                        <div class="row">
	                            <div class="col-md-2">
	                                <div class="form-group">
	                                    <label>Forma de Pago</label>
	                                    <select name="orden_tipopagotext" class="form-control select2" required>
	                                        <option value="">Seleccione</option>
	                                        <option value="0">Contado</option>
	                                        <option value="15">15 días</option>
	                                        <option value="30">30 días</option>
	                                        <option value="45">45 días</option>
	                                        <option value="60">60 días</option>
	                                        <option value="Otro">Específicar</option>
	                                    </select>
	                                </div>
	                            </div>
	                            <div class="col-md-2">
	                                <div class="form-group" id="div_diaspago">
	                                    <label>Días de Pago</label>
	                                    <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-calendar-day"></i>
                                                </span>
                                            </div>
                                            <input type="number" min="0" max="365" step="1"  class="form-control" value="" placeholder="Núm. de días" name="orden_tipopago">
                                        </div>
	                                </div>
	                            </div>
	                            <div class="col-md-4">
	                                <div class="form-group">
	                                    <label>Solicitado por</label>
	                                    <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-user-tie"></i>
                                                </span>
                                            </div>
                                            <input type="text" class="form-control" name="orden_solicitante" placeholder="Nombre de solicitante" required>
                                        </div>
	                                </div>
	                            </div>
	                            <div class="col-md-4">
	                                <div class="form-group">
	                                    <label>Autorizado por</label>
	                                    <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-user-check"></i>
                                                </span>
                                            </div>
                                            <input type="text" name="orden_autorizador" class="form-control" placeholder="Nombre de quien autoriza" required>
                                        </div>
	                                </div>
	                            </div>
	                            <div class="col-md-12">
	                                <div class="form-group">
	                                    <label>Observaciones</label>
	                                    <input type="text" class="form-control" name="orden_observ" placeholder="Observaciones de la orden">
	                                </div>
	                            </div>
	                            
	                        </div>
	                    </div>
	                </div>

	                <div class="card card-info">
	                    <div class="card-header">
	                    	<div class="card-title"><i class="fas fa-people-carry"></i>&nbsp;&nbsp;Datos del Proveedor</div>
	                    </div>
	                    <div class="card-body">
	                        <div class="row">
	                            <div class="col-md-4">
	                                <div class="form-group">
	                                    <label>Proveedor</label>
                                        <select name="orden_proveedor" class="form-control select2" required></select>
	                                </div>
	                            </div>
	                            <div class="col-md-4">
	                                <div class="form-group">
	                                    <label>RUC / N° Proveedor</label>
	                                    <input type="text" class="form-control" name="orden_provruc" value="" placeholder="Seleccione un proveedor" readonly>
	                                </div>
	                            </div>
	                            <div class="col-md-4">
	                                <div class="form-group">
	                                    <label>N° Cuenta</label>
	                                    <input type="text" name="orden_nrocuenta" placeholder="Número de cuenta" class="form-control">
	                                </div>
	                            </div>
	                            <div class="col-md-8"><div class="form-group"><label>Dirección del proveedor</label><select name="orden_direccion_proveedor_id" class="form-control" required><option value="">Seleccione primero un proveedor</option></select><small class="form-text text-muted">Si parte de una orden de compra, se conservará su dirección histórica.</small></div></div>
	                            <div class="col-md-4"><div class="form-group"><label>Dirección seleccionada</label><input type="text" name="orden_direccion_proveedor" class="form-control" readonly></div></div>
	                            <input type="hidden" name="orden_nomprov">
	                        </div>
	                    </div>
	                </div>

	                <div class="row">
	                    <div class="col-sm-12">
	                        <?php $purchaseCustomFields = $pdo->query("SELECT id,label,field_type,value_list_code,required_status FROM tbl_custom_field_definition WHERE entity='purchase' AND active_status=1 ORDER BY id")->fetchAll(); if ($purchaseCustomFields): ?>
	                        <div class="card card-outline card-secondary"><div class="card-header"><div class="card-title"><i class="fas fa-tags"></i>&nbsp;&nbsp;Campos adicionales</div></div><div class="card-body"><div class="row"><?php foreach ($purchaseCustomFields as $customField): ?><div class="col-md-4 form-group"><label><?php echo e($customField['label']); ?></label><?php if ($customField['field_type'] === 'select'): ?><select class="form-control" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><option value="">Seleccione</option><?php $options = $pdo->prepare('SELECT label,value_code FROM tbl_app_value_option WHERE list_id=(SELECT id FROM tbl_app_value_list WHERE code=?) AND active_status=1 ORDER BY sort_order,label'); $options->execute([$customField['value_list_code']]); foreach ($options->fetchAll() as $option): ?><option value="<?php echo e($option['value_code']); ?>"><?php echo e($option['label']); ?></option><?php endforeach; ?></select><?php elseif ($customField['field_type'] === 'checkbox'): ?><div class="custom-control custom-checkbox"><input class="custom-control-input" id="purchase-custom-<?php echo (int) $customField['id']; ?>" type="checkbox" value="1" name="custom_field[<?php echo (int) $customField['id']; ?>]"><label class="custom-control-label" for="purchase-custom-<?php echo (int) $customField['id']; ?>">Sí / No</label></div><?php else: ?><input class="form-control" type="<?php echo $customField['field_type'] === 'number' ? 'number' : ($customField['field_type'] === 'date' ? 'date' : 'text'); ?>" name="custom_field[<?php echo (int) $customField['id']; ?>]" <?php echo $customField['required_status'] ? 'required' : ''; ?>><?php endif; ?></div><?php endforeach; ?></div></div></div>
                        <?php endif; ?>
	                        <div class="card card-danger">
	                            <div class="card-header">
	                            	<div class="card-title"><i class="fas fa-box"></i>&nbsp;&nbsp;Datos del Artículo</div>
	                            </div>
	                            <div class="card-body">
	                                <div class="row">
	                                    <div class="col-md-4">
	                                        <div class="form-group">
	                                            <label>Artículo registrado</label>
	                                            <select name="orden_producto_select" class="form-control select2" style="width:100%"><option value="">Buscar por código o descripción</option></select>
	                                        </div>
	                                    </div>
	                                    <div class="col-md-4">
	                                        <div class="form-group">
	                                            <label>Código</label>
	                                            <input type="text" name="orden_codprod" placeholder="Código de producto" class="form-control">
	                                        </div>
	                                    </div>
	                                    <div class="col-md-8">
	                                        <div class="form-group">
	                                            <label>Descripción</label>
	                                            <input type="text" name="orden_descprod" placeholder="Descripción de producto" class="form-control">
	                                        </div>
	                                    </div>
	                                </div>
	                                <div class="row">
	                                    <div class="col-md-12">
	                                        <div class="form-group">
	                                            <label>Glosa</label>
	                                            <input type="text" name="orden_glosa" placeholder="Glosa" class="form-control">
	                                        </div>
	                                    </div>
	                                </div>
	                                <div class="row">
	                                    <div class="col-md-2">
	                                        <div class="form-group">
	                                            <label>Precio Unitario</label>
	                                            <input type="text" name="orden_precunit" class="form-control" placeholder="Precio unidad">
	                                        </div>
	                                    </div>
	                                    <div class="col-md-2">
	                                        <div class="form-group">
	                                            <label>Cantidad</label>
	                                            <input type="number" min="0" name="orden_cantidad" class="form-control" placeholder="Cantidad">
	                                        </div>
	                                    </div>
	                                     <div class="col-md-2">
	                                        <div class="form-group">
	                                            <label>% Dscto.</label>
	                                            <input type="number" value="0" min="0" step="0.1" name="orden_porcdscto" class="form-control" placeholder="Porcentaje de descuento">
	                                        </div>
	                                    </div>
	                                    <div class="col-md-2">
	                                        <div class="form-group">
	                                            <label>Valor Total c/Dscto.</label>
	                                            <input type="text" name="orden_valordscto" value="0" class="form-control" readonly>
	                                        </div>
	                                    </div>
	                                    <div class="col-md-2">
	                                        <div class="form-group">
	                                            <label>Valor IGV</label>
	                                            <input type="text" name="orden_valorigv" value="0" class="form-control" readonly>
	                                        </div>
	                                    </div>
	                                    <div class="col-md-2">
		                                    <div class="form-group">
		                                        <label>Valor de Medida</label>
		                                        <select class="form-control select2" name="orden_valunit">
		                                            <option value="">Seleccione</option>
		                                            <option value="gr">gr (Gramos)</option>
		                                            <option value="mg">mg (Miligramos)</option>
		                                            <option value="lt">lt (Litros)</option>
		                                            <option value="ml">ml (Mililitros)</option>
		                                            <option value="gl">gl (Galones)</option>
		                                            <option value="und">und (Unidad)</option>
		                                        </select>
		                                    </div>
		                                </div>
	                                    <input type="hidden" name="orden_nameprod">
	                                    <input type="hidden" name="orden_stockprod">
	                                </div>
	                                <div class="mt-3">
	                                    <button id="btn-add-prodtporden" class="btn btn-primary btn-block"><i class="fa fa-plus fa-1x"></i> Añadir Artículo</button>
	                                </div>
	                            </div>
	                        </div>
	                    </div>
	                </div>

	                <div class="card">
	                    <div class="card-body">
	                    	<div class="row">
	                    		<div>
                                	<label>Haga doble clic sobre un ítem para eliminarlo del detalle</label>
                            	</div>
	                    	</div>
	                    	<div class="row">
		                        <div class="table-responsive">
		                            <table id="table-ord-compras" class="table table-bordered table-hover">
		                                <thead>
		                                    <tr>
		                                        <th>Nro.</th>
		                                        <th>Código</th>
		                                        <th>Descripción</th>
		                                        <th>Glosa</th>
		                                        <th>Valor Unidad</th>
		                                        <th>Precio Unitario</th>
		                                        <th>Cantidad</th>
		                                        <th>% Dscto.</th>
		                                        <th>Valor Dscto.</th>
		                                    </tr>
		                                </thead>
		                                <tbody></tbody>
		                            </table>
		                        </div>
	                        </div>
	                        <div class="row mt-3">
			                	<div class="col-md-4"></div>
			                	<div class="col-md-4"></div>
			                	<div class="col-md-4">
			                		<div class="row">
			                			<div class="col-md-5 text-right">
			                				<label>Total Compra</label>
			                			</div>
			                			<div class="col-md-7">
			                				<input type="text" name="orden_totcompra" class="form-control" readonly>
			                			</div>
			                		</div>
			                		<div class="row mt-2">
			                			<div class="col-md-5 text-right">
			                				<label>IGV</label>
			                			</div>
			                			<div class="col-md-7">
			                				<input type="text" name="orden_igv" class="form-control" readonly>
			                			</div>
			                		</div>
			                		<div class="row mt-2">
			                			<div class="col-md-5 text-right">
			                				<label>Total Neto</label>
			                			</div>
			                			<div class="col-md-7">
			                				<input type="text" name="orden_totneto" class="form-control" readonly>
			                			</div>
			                		</div>
			                	</div>
	                		</div>
	                    </div>
	                </div>
	                <div class="row mt-3 mb-3">
	                    <div id="col-btn-save-orden" class="col-md-12">
	                        <button type="submit" id="btn-save-orden" class="btn btn-success btn-block"><i class="fa fa-save fa-1x"></i>&nbsp;&nbsp;<font>Registrar Compra</font></button>
	                    </div>
	                    <div id="col-btn-delete-orden" class="col-md-6">
	                        <button type="button" id="btn-delete-orden" class="btn btn-danger btn-block"><i class="fa fa-trash fa-1x"></i>&nbsp;&nbsp;<font>Eliminar Compra</font></button>
	                    </div>
	                </div>
	            </form>
	    	</div>
        </div>
    </div>
</div>
