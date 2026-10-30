$(function () {
  $('#m_almacen,#m_registro_producto').addClass('active');
  $('#m_almacen').parent().addClass('menu-open');
  $(document).prop('title', 'Maestro de artículos - APPU');
  window.appuProductoWorkspace = appuMasterWorkspace({
    form: '#FRM_INSERT_PRODUCTO', toolbar: '.appu-product-toolbar', search: '#appu-buscar-producto-modal',
    endpoint: '../../modules/productos/consultar-maestro.php', permission: 'inventory.manage',
    firstField: 'producto_code', customFields: '#product-custom-fields', fieldPrefix: 'product-custom-',
    columns: ['code', 'name', 'brand']
  });
  $('#btn-new').on('click', function () { appuProductoWorkspace.newRecord(); });
  $('#btn-product-list').on('click', function () {
    if ($('#FRM_INSERT_PRODUCTO').attr('aria-busy') === 'true') return;
    appuConfirmDiscard(function () { window.location.assign('../../views/productos/listado-producto'); });
  });
});
