$(function(){
  "use strict";
  $("#m_panel_sunat").addClass("active");
  var labels={invoice:"Factura",receipt:"Boleta","credit-note":"Nota de credito"};
  var states={accepted:["Aceptado","success"],observed:["Objetado","warning"],rejected:["Rechazado","danger"],pending:["Pendiente","info"],technical_error:["Error tecnico","danger"],internal:["Serie interna","secondary"]};
  function e(value){return appuEscapeHtml(String(value==null?"":value));}
  function iso(date){return date.getFullYear()+"-"+String(date.getMonth()+1).padStart(2,"0")+"-"+String(date.getDate()).padStart(2,"0");}
  var today=new Date(),from=new Date(today);from.setDate(today.getDate()-29);$('input[name="date_from"]').val(iso(from));$('input[name="date_to"]').val(iso(today));
  function fileButtons(d){
    var base="../../modules/sunat/artefacto.php?source_type="+encodeURIComponent(d.source_type)+"&source_id="+Number(d.source_id)+"&kind=";
    var pdf="../../modules/facturacion/pdf-documento.php?type="+encodeURIComponent(d.source_type)+"&id="+Number(d.source_id);
    var buttons=["<a target='_blank' class='btn btn-outline-primary' href='"+pdf+"'>Ver PDF</a>"];
    if(d.xml_path){buttons.push("<a target='_blank' class='btn btn-outline-secondary' href='"+base+"xml'>Ver XML</a>","<a class='btn btn-outline-secondary' href='"+base+"xml&download=1'>Descargar XML</a>");}
    if(d.cdr_path){buttons.push("<a target='_blank' class='btn btn-outline-success' href='"+base+"cdr'>Ver CDR</a>","<a class='btn btn-outline-success' href='"+base+"cdr&download=1'>Descargar CDR</a>");}
    return "<div class='btn-group btn-group-sm flex-wrap'>"+buttons.join("")+"</div>";
  }
  function load(){
    var query=$("#sunat-filters").serialize();
    $.getJSON("../../modules/sunat/panel.php?"+query).done(function(data){
      var cards=[["accepted","Aceptados","check-circle","success"],["observed","Objetados","exclamation-triangle","warning"],["rejected","Rechazados","times-circle","danger"],["pending","Pendientes","clock","info"],["technical_error","Errores tecnicos","tools","danger"],["internal","Series internas","folder","secondary"]];
      $("#sunat-summary").html(cards.map(function(c){return "<div class='col-lg-2 col-md-4'><div class='small-box bg-"+c[3]+"'><div class='inner'><h3>"+Number(data.summary[c[0]]||0)+"</h3><p>"+c[1]+"</p></div><div class='icon'><i class='fas fa-"+c[2]+"'></i></div></div></div>";}).join(""));
      $("#sunat-documents tbody").html((data.documents||[]).map(function(d){var s=states[d.effective_status]||[d.effective_status,"secondary"];return "<tr><td><strong>"+e(labels[d.source_type]||d.source_type)+"</strong><br>"+e(d.series+"-"+d.number)+"</td><td>"+e(d.customer_name||"-")+"</td><td>S/ "+Number(d.total_net||0).toFixed(2)+"</td><td><span class='badge badge-"+s[1]+"'>"+e(s[0])+"</span></td><td><small>"+e((d.sunat_code||"")+" "+(d.sunat_description||""))+"</small></td><td>"+e(d.document_date||"")+"</td><td>"+fileButtons(d)+"</td></tr>";}).join("")||"<tr><td colspan='7' class='text-center text-muted'>No hay documentos para los filtros seleccionados.</td></tr>");
      $("#sunat-series tbody").html((data.series||[]).map(function(s){return "<tr><td>"+e(s.document_type)+"</td><td><strong>"+e(s.series)+"</strong></td><td>"+Number(s.next_number)+"</td><td><span class='badge badge-"+(Number(s.sunat_enabled)===1?"primary":"secondary")+"'>"+(Number(s.sunat_enabled)===1?"SUNAT":"Interna")+"</span></td><td>"+(Number(s.active_status)===1?"Activa":"Inactiva")+"</td></tr>";}).join(""));
    }).fail(function(xhr){toastr.error((xhr.responseJSON||{}).error||"No se pudo cargar el panel SUNAT.");});
  }
  $("#sunat-filters").on("submit",function(event){event.preventDefault();load();});$("#sunat-refresh").on("click",load);load();
});
