ï»¿(function ($) {
 'use strict';
 $(function () {
  const $modal = $('[data-button-modal]'), $storage = $('[data-button-elements]');
  if (!$modal.length) return;
  const optionName = (window.wpTopInfoBarAdmin || {}).optionName || 'wp_top_info_bar_settings';
  let active = null, opener = null, loading = false;
  $modal.find('[name]').each(function () {
   const match = /\[(button_[a-z_]+)\]$/.exec(this.name);
   $(this).removeAttr('name');
   if (match && (this.type !== 'hidden' || $(this).hasClass('wp-top-info-bar-color-pick__value'))) $(this).attr('data-button-edit',match[1]);
  });
  const $controls = $modal.find('[data-button-edit]');
  $modal.appendTo(document.body);
  function row(id) { return $storage.find('[data-button-element]').filter(function () { return $(this).attr('data-button-element') === id; }).first(); }
  function notify() { $('.wp-top-info-bar-admin').trigger('wpTopInfoBarButtonsChanged').trigger('change'); }
  function persist() {
   if (loading || active === null) return;
   $controls.each(function () { row(active).find('[data-button-field="' + $(this).attr('data-button-edit') + '"]').val(this.type === 'checkbox' ? (this.checked ? '1' : '0') : this.value); });
   notify();
  }
  function open(id, source) {
   if (!row(id).length) return;
   persist(); active = id; opener = source; loading = true;
   $controls.each(function () {
    const value = row(id).find('[data-button-field="' + $(this).attr('data-button-edit') + '"]').val() || '';
    if (this.type === 'checkbox') this.checked = value === '1'; else this.value = value;
    if ($(this).hasClass('wp-top-info-bar-color-pick__value')) $(this).closest('.wp-top-info-bar-color-pick').trigger('wpTopInfoBarSetColor',[value]);
   });
   loading = false;
   $modal.prop('hidden',false); $('body').addClass('wp-top-info-bar-modal-open');
   $('#wp-top-info-bar-button-text').trigger('focus');
  }
  function close() {
   persist(); active = null;
   $modal.find('.wp-top-info-bar-color-pick').each(function () { const picker = $(this).data('pickr'); if (picker) picker.hide(); });
   $modal.prop('hidden',true); $('body').removeClass('wp-top-info-bar-modal-open');
   const key = opener && $(opener).attr('data-layout-key');
   const $tile = $('[data-layout-tile]').filter(function () { return $(this).attr('data-layout-key') === key; });
   if ($tile.length) $tile.first().trigger('focus'); else $('[data-layout-zone="center"] [data-layout-add]').trigger('focus');
  }
  function add(source) {
   const id = 'b' + Date.now().toString(36) + Math.random().toString(36).slice(2,7);
   const values = {button_text:'',button_size:'default',button_shape:'square',button_style:'filled',button_color:'#2271b1',button_text_color:'#ffffff',button_url:'',button_new_tab:'0',button_position:'text-right'};
   const $row = $('<div></div>').attr('data-button-element',id).appendTo($storage);
   Object.keys(values).forEach(function (key) { $('<input type="hidden">').attr({'data-button-field':key,name:optionName+'[buttons]['+id+']['+key+']'}).val(values[key]).appendTo($row); });
   const keys = $('[data-layout-tile]').map(function () { return $(this).attr('data-layout-key'); }).get();
   keys.push('button:' + id); $('[data-layout-order]').val(JSON.stringify(keys)).trigger('change');
   if (window.wpTopInfoBarLayout) window.wpTopInfoBarLayout.placeNew('button:' + id);
   notify(); open(id,source);
  }
  $controls.on('input change',persist);
  $modal.find('[data-close-button-modal]').on('click',close);
  $modal.find('[data-remove-button-element]').on('click',function () {
   const id = active; row(id).remove(); active = null;
   let keys = []; try { keys = JSON.parse($('[data-layout-order]').val() || '[]'); } catch(e) {}
   $('[data-layout-order]').val(JSON.stringify(keys.filter(key => key !== 'button:' + id))).trigger('change');
   notify(); close();
  });
  $storage.closest('form').on('submit.wpTopInfoBarButtons',persist);
  $(document).on('keydown.wpTopInfoBarButtonModal',function (event) {
   if (active === null || $(event.target).closest('.pcr-app').length) return;
   if (event.key === 'Escape') { event.preventDefault(); close(); }
   if (event.key === 'Tab') {
    const $focusable=$modal.find('button, input, select, textarea, [tabindex="0"]').filter(':visible:not(:disabled)');
    const first=$focusable[0],last=$focusable[$focusable.length-1];
    if (event.shiftKey && event.target===first) { event.preventDefault();last.focus(); }
    else if (!event.shiftKey && event.target===last) { event.preventDefault();first.focus(); }
   }
  });
  window.wpTopInfoBarButtons = {open:open,add:add};
 });
})(jQuery);
