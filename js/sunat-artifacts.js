(function($){
  "use strict";
  $(function(){
    var type=window.appuSunatDocumentType;if(!type)return;
    var $anchor=$("#col-btn-save-facturaprod").parent();if(!$anchor.length)return;
    $anchor.append("<div id='sunat-artifacts-card' class='col-12 mt-3'><div class='card card-outline card-info mb-0'><div class='card-header py-2'><strong><i class='fas fa-file-code mr-1'></i>Archivos del documento</strong><span id='sunat-document-state' class='badge badge-secondary ml-2'>Sin guardar</span></div><div class='card-body py-2'><div class='btn-group btn-group-sm flex-wrap'><a id='sunat-view-pdf' target='_blank' class='btn btn-outline-primary disabled'>Ver PDF</a><a id='sunat-view-xml' target='_blank' class='btn btn-outline-secondary disabled'>Ver XML</a><a id='sunat-download-xml' class='btn btn-outline-secondary disabled'>Descargar XML</a><a id='sunat-view-cdr' target='_blank' class='btn btn-outline-success disabled'>Ver CDR</a><a id='sunat-download-cdr' class='btn btn-outline-success disabled'>Descargar CDR</a></div><div id='sunat-document-help' class='small text-muted mt-2'>Guarda o selecciona un documento para consultar sus archivos.</div></div></div></div>");
    var last=0;
    function disable(selector){$(selector).addClass("disabled").removeAttr("href");}
    function refresh(){
      var id=Number($('input[name="id_factura"]').val()||0);
      if(!id){disable("#sunat-view-pdf,#sunat-view-xml,#sunat-download-xml,#sunat-view-cdr,#sunat-download-cdr");last=0;$("#sunat-document-state").attr("class","badge badge-secondary ml-2").text("Sin guardar");$("#sunat-document-help").text("Guarda o selecciona un documento para consultar sus archivos.");return;}
      if(id===last)return;last=id;disable("#sunat-view-pdf,#sunat-view-xml,#sunat-download-xml,#sunat-view-cdr,#sunat-download-cdr");$("#sunat-view-pdf").removeClass("disabled").attr("href","../../modules/facturacion/pdf-documento.php?type="+encodeURIComponent(type)+"&id="+id);
      $.getJSON("../../modules/sunat/estado.php",{source_type:type,source_id:id}).done(function(data){
        var s=data.sunat,base="../../modules/sunat/artefacto.php?source_type="+encodeURIComponent(type)+"&source_id="+id+"&kind=";
        if(!data.sunat_enabled){$("#sunat-document-state").attr("class","badge badge-secondary ml-2").text("Serie interna");$("#sunat-document-help").text("El PDF esta disponible. Esta serie no viaja a SUNAT, por lo que no tendra XML ni CDR.");return;}
        if(!s){$("#sunat-document-state").attr("class","badge badge-info ml-2").text("Pendiente de envio");$("#sunat-document-help").text("El PDF esta disponible. XML y CDR apareceran despues de procesar el envio.");return;}
        var labels={accepted:["Aceptado","success"],observed:["Objetado","warning"],rejected:["Rechazado","danger"],queued:["En cola","info"],processing:["Procesando","info"],technical_error:["Error tecnico","danger"],draft:["Borrador","secondary"]},label=labels[s.status]||[s.status,"secondary"];
        $("#sunat-document-state").attr("class","badge badge-"+label[1]+" ml-2").text(label[0]);
        if(s.xml_path){$("#sunat-view-xml").removeClass("disabled").attr("href",base+"xml");$("#sunat-download-xml").removeClass("disabled").attr("href",base+"xml&download=1");}
        if(s.cdr_path){$("#sunat-view-cdr").removeClass("disabled").attr("href",base+"cdr");$("#sunat-download-cdr").removeClass("disabled").attr("href",base+"cdr&download=1");}
        $("#sunat-document-help").text(((s.sunat_code||"")+" "+(s.sunat_description||"")).trim()||"Consulta SUNAT procesada.");
      }).fail(function(){last=0;});
    }
    window.appuRefreshSunatArtifacts=function(){last=0;refresh();};setInterval(refresh,700);refresh();
  });
})(jQuery);
