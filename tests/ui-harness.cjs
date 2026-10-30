const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const jquery = require('jquery');
const root = path.resolve(__dirname, '..');

exports.setup = async function (view, scripts, query = '', settings = {}) {
  const html = fs.readFileSync(path.join(root, view), 'utf8').replace(/<\?php[\s\S]*?\?>/g, '');
  const dom = new JSDOM(html, { url: 'http://localhost/' + view.replace(/\.php$/, '') + query, runScripts: 'outside-only', pretendToBeVisual: true });
  const window = dom.window, $ = jquery(window), requests = [], notifications = [], rows = [], contextMenus=[];
  window.$ = window.jQuery = $;
  window.HTMLElement.prototype.scrollIntoView = function () {};
  $.fn.select2 = function (options) {
    if (options && options.data) this.each(function () { const $select = $(this).empty(); options.data.forEach(option => $select.append($('<option>').val(option.id).text(option.text))); });
    if (typeof options !== 'string') this.each(function () { $(this).addClass('select2-hidden-accessible'); if (!$(this).next('.select2').length) $(this).after('<span class="select2"><span class="select2-selection" tabindex="0"></span></span>'); });
    return this;
  };
  $.fn.modal = function (action) {
    if (action === 'hide') { const event = $.Event('hide.bs.modal'); this.trigger(event); if (!event.isDefaultPrevented()) this.removeClass('show'); }
    else if (action === 'show') this.addClass('show');
    return this;
  };
  const table = { columns: () => ({ visible() {} }), rows: Object.assign(() => ({ indexes: () => [], data:()=>({toArray:()=>settings.rowData?[settings.rowData]:rows}) }), { add: added => {rows.push(...added);return table;} }), clear() { rows.length = 0; return this; }, draw() { return this; }, row: () => ({ data: () => settings.rowData || null }) };
  $.fn.DataTable = () => table; $.fn.dataTable = { ext: { search: [] } }; $.fn.contextMenu = function (options) { contextMenus.push(options);return this; };$.fn.autocomplete=function(){return this;};
  function request(url, data, method) { const deferred = $.Deferred(); requests.push({ url, data, method, deferred }); return deferred.promise(); }
  $.getJSON = (url, data, success) => {if(typeof data==='function'){success=data;data=undefined;}const promise=request(url,data,'GET');if(success)promise.done(success);return promise;};
  $.ajax = options => {const promise=request(options.url,options.data,options.method||options.type);if(options.success)promise.done(options.success);if(options.error)promise.fail(options.error);if(options.complete)promise.always(options.complete);return promise;};
  $.post = (url, data, success) => {if(typeof data==='function'){success=data;data=undefined;}const promise=request(url,data,'POST');if(success)promise.done(success);return promise;};
  window.toastr = Object.fromEntries(['error', 'success', 'warning', 'info'].map(name => [name, text => notifications.push({ name, text })]));
  window.appuCan = settings.can || (() => true); window.appuEscapeHtml = value => $('<div>').text(value).html(); window.appuStatusBadge = value => value;
  window.appuMarkSaved = () => { window.appuFormDirty = false; }; window.appuMarkDirty = () => { window.appuFormDirty = true; };
  window.appuConfirmDiscard = callback => { window.appuMarkSaved(); callback(); }; window.mostrarHistorialEstados = () => {};
  if(settings.beforeScripts)settings.beforeScripts(window,$);
  for (const file of ['js/document-ui.js', ...scripts]) window.eval(fs.readFileSync(path.join(root, file), 'utf8'));
  const flush = () => new Promise(resolve => setTimeout(resolve, 10)); await flush();
  return { dom, window, $, requests, notifications, rows, contextMenus, flush, pending: predicate => requests.filter(r => r.deferred.state() === 'pending' && predicate(r)) };
};
