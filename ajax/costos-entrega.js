(function(){
  var receiptDetails=[],receiptEpoch=0,saving=false,loadingReceipt=false,costCurrency='',endpoint='../../modules/compras/costos-entrega.php';
  function error(xhr,fallback){var response=xhr.responseJSON||{};return response.error||response.message||fallback;}
  function money(value,currency){return(currency||'PEN')+' '+Number(value||0).toFixed(2);}
  function allocate(total,weights){var cents=Math.round(total*100),den=weights.reduce(function(sum,value){return sum+value;},0),assigned=0,shares=weights.map(function(weight,index){var exact=den?cents*weight/den:0,whole=Math.floor(exact);assigned+=whole;return{index:index,cents:whole,remainder:exact-whole};});shares.slice().sort(function(a,b){return b.remainder-a.remainder||a.index-b.index;}).forEach(function(share){if(assigned<cents){share.cents++;assigned++;}});return shares.map(function(share){return share.cents/100;});}
  function lock(){ $('#landed-cost-form [type=submit]').prop('disabled',saving||loadingReceipt||!appuCan('purchases.manage')); }
  function loadReceipts(){
    $.getJSON(endpoint,{action:'receipts'}).done(function(response){
      var $select=$('[name="goods_receipt_id"]'),selected=$select.val();$select.empty().append('<option value="">Seleccione una recepción</option>');
      (response.receipts||[]).forEach(function(row){$('<option>').val(row.id).text(row.number+' | '+row.purchase_number+' | '+row.provider_name).data('currency',row.currency).appendTo($select);});
      $select.val(selected||'');
    }).fail(function(xhr){toastr.error(error(xhr,'No se pudieron cargar las recepciones.'));});
  }
  function preview(){
    var total=Math.max(0,Number($('[name="total_cost"]').val())||0),method=$('[name="allocation_method"]').val(),key=method==='VALUE'?'base_value':'quantity',amounts=allocate(total,receiptDetails.map(function(row){return Number(row[key]||0);})),assigned=0,$body=$('#landed-cost-preview tbody').empty();
    receiptDetails.forEach(function(row,index){var amount=amounts[index];assigned+=amount;$('<tr>').append($('<td>').text(row.code),$('<td>').text(row.name),$('<td class="text-right">').text(Number(row.quantity).toFixed(3)),$('<td class="text-right">').text(Number(row.base_value).toFixed(2)),$('<td class="text-right">').text(amount.toFixed(2))).appendTo($body);});
    if(!receiptDetails.length)$body.html('<tr><td colspan="5" class="text-center text-muted">'+(loadingReceipt?'Cargando recepción…':'Selecciona una recepción.')+'</td></tr>');
    $('#landed-cost-preview-total').text((costCurrency?costCurrency+' ':'')+assigned.toFixed(2));
  }
  $('[name="goods_receipt_id"]').on('change',function(){
    var id=this.value,epoch=++receiptEpoch;receiptDetails=[];costCurrency='';$('#landed-cost-currency').val('');loadingReceipt=!!id;preview();lock();if(!id)return;
    $.getJSON(endpoint,{action:'receipt',receipt_id:id}).done(function(response){if(epoch!==receiptEpoch)return;receiptDetails=response.details||[];costCurrency=response.receipt.cost_currency||response.receipt.valuation_currency||'';$('#landed-cost-currency').val(costCurrency);loadingReceipt=false;preview();lock();}).fail(function(xhr){if(epoch!==receiptEpoch)return;loadingReceipt=false;preview();lock();toastr.error(error(xhr,'No se pudo cargar la recepción.'));});
  });
  $('[name="total_cost"],[name="allocation_method"]').on('input change',preview);
  $('#landed-cost-form').on('input change',':input',function(event){if(!saving&&!event.isTrigger&&window.appuMarkDirty)window.appuMarkDirty();});
  function loadCosts(){
    $.getJSON(endpoint).done(function(response){
      var requested=new URLSearchParams(window.location.search).get('record_id'),$body=$('#landed-cost-list tbody').empty();
      (response.costs||[]).forEach(function(row){
        var $actions=$('<td>');if(row.status==='Registrado'&&appuCan('purchases.manage'))$('<button type="button" class="btn btn-sm btn-outline-danger js-cancel-cost" title="Anular costo"><i class="fas fa-ban"></i></button>').attr('data-id',row.id).appendTo($actions);
        $('<a class="btn btn-sm btn-outline-secondary ml-1">').attr('href','../trazabilidad/flujo?type=landed_cost&id='+encodeURIComponent(row.id)).text('Relaciones').appendTo($actions);
        var $row=$('<tr>').attr('data-id',row.id).append($('<td>').text(row.number),$('<td>').text(row.cost_date),$('<td>').text(row.receipt_number),$('<td>').text(row.provider_name),$('<td>').text(row.concept),$('<td>').text(row.allocation_method==='VALUE'?'Por valor':'Por cantidad'),$('<td>').text(money(row.total_cost,row.currency)),$('<td>').html(appuStatusBadge(row.status)),$actions).appendTo($body);if(requested&&String(row.id)===requested)$row.addClass('table-info');
      });
      if(!(response.costs||[]).length)$body.append('<tr><td colspan="9" class="text-center text-muted">No hay costos de entrega registrados.</td></tr>');
    }).fail(function(xhr){toastr.error(error(xhr,'No se pudieron cargar los costos de entrega.'));});
  }
  $('#landed-cost-form').on('submit',function(event){
    event.preventDefault();if(saving||loadingReceipt||!appuCan('purchases.manage'))return;if(!receiptDetails.length)return toastr.warning('Selecciona una recepción con artículos.');
    var data=$(this).serializeArray();data.push({name:'action',value:'create'});saving=true;lock();
    $.post(endpoint,$.param(data)).done(function(response){toastr.success(response.message);$('#landed-cost-form')[0].reset();receiptEpoch++;receiptDetails=[];preview();loadReceipts();loadCosts();if(window.appuMarkSaved)window.appuMarkSaved();}).fail(function(xhr){toastr.error(error(xhr,'No se pudo registrar el costo.'));}).always(function(){saving=false;lock();});
  });
  $(document).on('click','.js-cancel-cost',function(){
    var $button=$(this);if($button.prop('disabled'))return;var reason=window.prompt('Motivo de anulación:','');if(reason===null)return;if(reason.trim().length<5)return toastr.warning('Indica un motivo de al menos 5 caracteres.');$button.prop('disabled',true);
    $.post(endpoint,{action:'cancel',landed_cost_id:$button.data('id'),reason:reason.trim()}).done(function(response){toastr.success(response.message);loadCosts();}).fail(function(xhr){toastr.error(error(xhr,'No se pudo anular.'));}).always(function(){$button.prop('disabled',false);});
  });
  $('#landed-cost-refresh').on('click',loadCosts);$('#m_compras').addClass('active').parent().addClass('menu-open');$('#m_costos_entrega').addClass('active');loadReceipts();loadCosts();preview();lock();
})();
