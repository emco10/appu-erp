  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">
      
    </div>
    <!-- Default to the left -->
    <strong>&copy; <?php echo date('Y'); ?> APPU</strong>
  </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
  $.ajaxSetup({
    headers: {
      'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
    }
  });
</script>

<!-- Script for Notifications -->
<script src="<?php echo $functions->direct_sistema(); ?>/js/notifications.js"></script>

<!-- jQuery UI -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js" integrity="sha256-T0Vest3yCU7pafRw9r+settMBX6JkKN06dqBnpQ8d30=" crossorigin="anonymous"></script>

<!-- Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
  $(document).on("click.appuModalDismiss", '[data-bs-dismiss="modal"]', function (event) {
    event.preventDefault();
    var modal = this.closest(".modal");
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).hide();
  });
</script>

<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

<!-- Flot - Attractive JavaScript plotting for jQuery -->
<script src="https://cdn.jsdelivr.net/npm/flot@4.2.2/dist/es5/jquery.flot.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>

<!-- Select2 Spanish Language -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>

<!-- Toastr -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>

<!-- Font Awesome Icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/js/all.min.js"></script>

<!-- Context Menu JS -->
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/context-menu/jquery.contextMenu.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/context-menu/jquery.ui.position.js"></script>

<!-- DataTables -->
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/jquery.dataTables.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables-bs4/dataTables.bootstrap4.js"></script>

<!-- DataTables Buttons -->
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/dataTables.buttons.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/buttons.html5.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/buttons.flash.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/buttons.print.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/jszip.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/pdfmake.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/datatables/vfs_fonts.js"></script>

<!-- Moment JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment-range@4.0.2/dist/moment-range.js"></script>
<script>
  window['moment-range'].extendMoment(moment);
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/8.11.8/sweetalert2.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/notifyjs/dist/notify.min.js"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/plugins/notifications/notify-metro.js"></script>
<script>
  $(document).ajaxError(function (_event, xhr) {
    if (![401, 403, 419, 422].includes(xhr.status)) return;

    var response = xhr.responseJSON || {};
    var fallback = xhr.status === 403
      ? 'No tienes permiso para realizar esta operación.'
      : (xhr.status === 419 ? 'La sesión de seguridad expiró. Recarga la página.' : 'No se pudo completar la operación.');

    if (window.Swal && Swal.isVisible()) Swal.close();
    if (window.toastr) toastr.error(response.message || fallback);
  });
</script>

<!-- Footer functions -->
<script src="<?php echo $functions->direct_sistema(); ?>/js/document-ui.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/js/master-workspace.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/js/legacy-master-workspace.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/js/sales-document-list.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/js/delivery-billing.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/js/sunat-artifacts.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/js/footer-functions.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>
<script src="<?php echo $functions->direct_sistema(); ?>/ajax/business-partner-details.js?v=<?php echo rawurlencode(SCRIPT_VER); ?>"></script>

</body>
</html>
