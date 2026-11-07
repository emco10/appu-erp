$(function () {
  'use strict';
  if (!$('#sire-workspace').length) return;
  var busy = false, version = 0, ticketPage = 1, proposalPage = 1, ticketTotal = 0, proposalTotal = 0;
  var canManage = typeof window.appuCan === 'function' && window.appuCan('management.manage');
  $('#sire-export').toggle(canManage);
  var today = new Date();
  $('#sire-period').val(today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0'));

  function message(text, state) { $('#sire-status').attr('class', 'alert alert-' + (state || 'info')).text(text); }
  function escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' }[character]; }); }
  function selection(needsTicket) {
    var period = String($('#sire-period').val() || '').replace('-', ''), book = $('#sire-book').val(), ticket = String($('#sire-ticket').val() || '').trim();
    if (!/^(19|20)\d{2}(0[1-9]|1[0-2])$/.test(period)) { message('Selecciona un período válido.', 'warning'); return null; }
    if (needsTicket && !/^\d{12,14}$/.test(ticket)) { message('Indica un ticket de exportación válido.', 'warning'); return null; }
    return { book: book, period: period, ticket: ticket };
  }
  function updateButtons() {
    $('#sire-workspace button').prop('disabled', busy);
    $('#sire-ticket-prev').prop('disabled', busy || ticketPage <= 1);
    $('#sire-ticket-next').prop('disabled', busy || ticketPage * 50 >= ticketTotal);
    $('#sire-proposal-prev').prop('disabled', busy || proposalPage <= 1);
    $('#sire-proposal-next').prop('disabled', busy || proposalPage * Number($('#sire-per-page').val()) >= proposalTotal);
  }
  function request(endpoint, data, method, done) {
    if (busy) return;
    busy = true; updateButtons(); message('Consultando SIRE…');
    var active = version;
    $.ajax({ url: '../../modules/sire/' + endpoint + '.php', method: method || 'GET', data: data, dataType: 'json' })
      .done(function (response) { if (active === version) done(response.data || response); })
      .fail(function (xhr) { if (active === version) message((xhr.responseJSON || {}).message || 'No se pudo completar la consulta SIRE. Intenta nuevamente.', 'danger'); })
      .always(function () { busy = false; updateButtons(); });
  }
  function loadTickets(page) {
    var selected = selection(false); if (!selected) return;
    var query = { book: selected.book, from: selected.period, to: selected.period, page: page, per_page: 50 };
    request('tickets', query, 'GET', function (data) {
      ticketPage = page; ticketTotal = Number((data.pagination || {}).total || 0);
      $('#sire-ticket-page').text('Página ' + page + ' · ' + ticketTotal + ' tickets');
      $('#sire-ticket-table tbody').html((data.tickets || []).map(function (row) {
        var ready = row.status === 'completed', files = row.files || [];
        var downloads = ready ? files.map(function (_file, index) { return '<button type="button" class="btn btn-sm btn-outline-secondary mr-1" data-sire-download="' + index + '" data-ticket="' + escape(row.ticket) + '" data-period="' + escape(row.period) + '">ZIP ' + (index + 1) + '</button>'; }).join('') : '—';
        return '<tr><td>' + escape(row.ticket) + '</td><td>' + escape(row.period) + '</td><td>' + escape(row.status_description || row.status) + '</td><td>' + downloads + '</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-sire-use="' + escape(row.ticket) + '" data-period="' + escape(row.period) + '">Usar ticket</button></td></tr>';
      }).join('') || '<tr><td colspan="5" class="text-muted">No hay tickets para este período.</td></tr>');
      message('Consulta de tickets completada.', 'success');
    });
  }
  function loadProposal(page) {
    var selected = selection(true); if (!selected) return;
    request('proposal', { book: selected.book, period: selected.period, ticket: selected.ticket, page: page, per_page: Number($('#sire-per-page').val()) }, 'GET', function (data) {
      proposalPage = page; proposalTotal = Number((data.pagination || {}).total || 0);
      $('#sire-proposal-table thead').html('<tr>' + (data.columns || []).map(function (column) { return '<th>' + escape(column) + '</th>'; }).join('') + '</tr>');
      $('#sire-proposal-table tbody').html((data.records || []).map(function (row) { return '<tr>' + row.map(function (value) { return '<td>' + escape(value) + '</td>'; }).join('') + '</tr>'; }).join(''));
      $('#sire-proposal-page').text('Página ' + page + ' · ' + proposalTotal + ' registros');
      message(proposalTotal ? 'Propuesta consultada. No se modificó el libro.' : 'La exportación no contiene registros.', 'success');
    });
  }
  async function download(ticket, period, index) {
    if (busy) return;
    var selected = selection(false); if (!selected || !/^\d{12,14}$/.test(ticket) || !/^\d{6}$/.test(period)) { message('Indica un ticket válido para descargar.', 'warning'); return; }
    busy = true; updateButtons(); message('Preparando la descarga SIRE…');
    try {
      var query = $.param({ book: selected.book, period: period, ticket: ticket, file_index: index });
      var response = await window.fetch('../../modules/sire/download.php?' + query, { credentials: 'same-origin' });
      if (!response.ok) {
        var problem = await response.json();
        throw new Error(problem.message || 'No se pudo descargar el archivo SIRE.');
      }
      var file = await response.blob(), url = window.URL.createObjectURL(file), link = document.createElement('a');
      link.href = url; link.download = 'SIRE-' + selected.book + '-' + period + '-' + ticket + '.zip';
      document.body.appendChild(link); link.click(); link.remove(); window.URL.revokeObjectURL(url);
      message('Archivo SIRE descargado.', 'success');
    } catch (error) { message(error.message || 'No se pudo descargar el archivo SIRE.', 'danger'); }
    finally { busy = false; updateButtons(); }
  }

  $('#sire-periods').on('click', function () {
    request('periods', { book: $('#sire-book').val() }, 'GET', function (data) {
      $('#sire-period-list').html((data.periods || []).map(function (row) { return '<span class="badge badge-light border mr-1 mb-1">' + escape(row.period) + ' · ' + escape(row.status_description) + '</span>'; }).join('') || 'No se encontraron períodos habilitados.');
      message('Consulta de períodos completada.', 'success');
    });
  });
  $('#sire-filters').on('submit', function (event) { event.preventDefault(); loadTickets(1); });
  $('#sire-export').on('click', function () {
    if (!canManage) return;
    var selected = selection(false); if (!selected) return;
    request('export', { book: selected.book, period: selected.period }, 'POST', function (data) {
      $('#sire-ticket').val(data.ticket || '');
      message('Exportación solicitada. Ticket: ' + (data.ticket || '') + '. Pulsa Consultar tickets para revisar su avance.', 'success');
    });
  });
  $('#sire-proposal').on('click', function () { loadProposal(1); });
  $('#sire-download').on('click', function () { var selected = selection(true); if (selected) download(selected.ticket, selected.period, 0); });
  $('#sire-ticket-prev').on('click', function () { loadTickets(ticketPage - 1); });
  $('#sire-ticket-next').on('click', function () { loadTickets(ticketPage + 1); });
  $('#sire-proposal-prev').on('click', function () { loadProposal(proposalPage - 1); });
  $('#sire-proposal-next').on('click', function () { loadProposal(proposalPage + 1); });
  $('#sire-ticket-table').on('click', '[data-sire-use]', function () {
    $('#sire-ticket').val($(this).attr('data-sire-use'));
    var period = $(this).attr('data-period') || ''; if (/^\d{6}$/.test(period)) $('#sire-period').val(period.slice(0, 4) + '-' + period.slice(4));
    message('Ticket seleccionado. Pulsa Consultar propuesta o Descargar ZIP.');
  }).on('click', '[data-sire-download]', function () { download(String($(this).attr('data-ticket')), String($(this).attr('data-period')), Number($(this).attr('data-sire-download'))); });
  $('#sire-book,#sire-period').on('change', function () {
    version++; ticketPage = proposalPage = 1; ticketTotal = proposalTotal = 0;
    $('#sire-ticket').val(''); $('#sire-period-list,#sire-proposal-table thead,#sire-proposal-table tbody,#sire-ticket-table tbody,#sire-ticket-page,#sire-proposal-page').empty();
    message('Selección actualizada. Pulsa Consultar cuando lo necesites.'); updateButtons();
  });
  updateButtons();
});
