$(document).ready(function(){
  $("#m_almacen").attr("class","nav-link active");
  $("#m_almacen").parent().attr("class","nav-item has-treeview menu-open");
  $("#m_historial_movimiento").attr("class","nav-link active");
  $(document).prop('title', 'Historial de Movimientos - APPU');
});

var tabla_movimientos = $('#table-movimientos');

tabla_movimientos.DataTable({
    "ajax": {
        "url": "../../modules/productos/consultar-movimientos.php",
        "type": "POST",
        "data": function (request) { ($('#movement-filters').serializeArray() || []).forEach(function (item) { request[item.name] = item.value; }); }
    },
    "columns": [
        { "data": "ID" },
        { "data": "TIPO" },
        { "data": "CODIGO_PRODUCTO" },
        { "data": "NOMBRE_PRODUCTO" },
        { "data": "NOMBRE_ALMACEN" },
        { "data": "CANTIDAD" },

        { "data": "NOMBRE_PROVEEDOR" },
        { "data": "FECVENC" },
        { "data": "DOC_REFERENCIA" },

        { "data": "OBSERVACION" },
        { "data": "NOMBRE_USUARIO" },
        { "data": "FECREG" }
    ],
    "order": [[0, "DESC"]],
    dom: 'Bfrtip',
    buttons: [
            {
                text: '<i class="fa fa-plus-square fa-1x"></i>&nbsp;&nbsp;Registrar movimiento',
                action: function ( e, dt, node, config ) {
                    window.location.assign("../../views/productos/actualizar-stock");
                }
            },
            {
                extend: 'csv',
                text: '<i class="fa fa-file-csv"></i>&nbsp;&nbsp;Descargar CSV'
            },
            {
                extend: 'excel',
                text: '<i class="fa fa-file-excel"></i>&nbsp;&nbsp;Descargar Excel'
            },
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i>&nbsp;&nbsp;Imprimir'
            }
        ],
    "language": {
            "url": "../../plugins/datatables/Spanish.json"
        }
});

$.getJSON('../../modules/inventory/transfers.php').done(function (response) {
    (response.warehouses || []).forEach(function (row) { $('<option>').val(row.id).text(row.code + ' - ' + row.name).appendTo('#movement-warehouse'); });
    (response.products || []).forEach(function (row) { $('<option>').val(row.id).text(row.code + ' - ' + row.name).appendTo('#movement-product'); });
});
$('#movement-filters').on('submit', function (event) { event.preventDefault(); tabla_movimientos.DataTable().ajax.reload(); });
