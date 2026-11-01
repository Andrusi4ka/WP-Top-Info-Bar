(function ($) {
 'use strict';
 $(function () {
  const $modal = $('[data-text-modal]'), $storage = $('[data-text-elements]');
  if (!$modal.length) return;
  const $form = $storage.closest('form'), editorId = 'wp-top-info-bar-text-element-editor';
  const $editor = $('#' + editorId), $color = $modal.find('.wp-top-info-bar-color-pick__value');
  const optionName = (window.wpTopInfoBarAdmin || {}).optionName || 'wp_top_info_bar_settings';
  let active = null, opener = null, loading = false, initialized = false;
  $modal.appendTo(document.body);
  $color.removeAttr('name');
  function row(id) { return $storage.find('[data-text-element]').filter(function () { return $(this).attr('data-text-element') === id; }).first(); }
  function notify() { $('.wp-top-info-bar-admin').trigger('wpTopInfoBarTextsChanged').trigger('change'); }
  function field(key, value) {
   if (active === null) return;
   row(active).find('[data-text-field="' + key + '"]').val(value);
  }
  function editorInstance() { return window.tinymce && tinymce.get(editorId); }
  function persist() {
   if (loading || active === null) return;
   const instance = editorInstance();
   field('message', instance && !instance.isHidden() ? instance.getContent() : $editor.val());
   notify();
  }
  function initializeEditor() {
   if (initialized || !window.wp || !wp.editor || !wp.editor.initialize) return;
   initialized = true;
   const palette = (window.wpTopInfoBarAdmin || {}).textColors || [];
   wp.editor.initialize(editorId, {
    tinymce: window.tinymce ? {
     toolbar1:'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo',
     toolbar2:'', height:180, block_formats:'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6',
     forecolor_map:palette, forecolor_cols:8, forecolor_rows:Math.ceil((palette.length / 2 + 1) / 8),
     setup:function (instance) { instance.on('change input keyup undo redo', persist); }
    } : false,
    quicktags:{buttons:'strong,em,link,ul,ol,li'}, mediaButtons:false
   });
  }
  function open(id, source) {
   if (!row(id).length) return;
   if (active !== null) persist();
   active = id; opener = source; loading = true;
   const values = {};
   row(id).find('[data-text-field]').each(function () { values[$(this).data('text-field')] = this.value; });
   $modal.prop('hidden', false); $('body').addClass('wp-top-info-bar-modal-open');
   $editor.val(values.message || '');
   const instance = editorInstance();
   if (instance) instance.setContent(values.message || '');
   $modal.find('[data-text-edit]').each(function () {
    const value = values[$(this).data('text-edit')] || '';
    this.value = /^(?:\d+(?:\.\d+)?|\.\d+)(?:px)?$/.test(value) ? parseFloat(value) : '';
   });
   $color.val(values.text_color || '#ffffff');
   $color.closest('.wp-top-info-bar-color-pick').trigger('wpTopInfoBarSetColor', [$color.val()]);
   initializeEditor();
   loading = false;
   $modal.find('.wp-top-info-bar-image-modal__close').trigger('focus');
  }
  function close() {
   persist(); active = null;
   $modal.find('.wp-top-info-bar-color-pick').each(function () { const picker = $(this).data('pickr'); if (picker) picker.hide(); });
   $modal.prop('hidden', true); $('body').removeClass('wp-top-info-bar-modal-open');
   const key = opener && $(opener).attr('data-layout-key');
   const $tile = $('[data-layout-tile]').filter(function () { return $(this).attr('data-layout-key') === key; });
   if ($tile.length) $tile.first().trigger('focus'); else $('[data-layout-zone="center"] [data-layout-add]').trigger('focus');
  }
  function add(source) {
   const id = 't' + Date.now().toString(36) + Math.random().toString(36).slice(2,7);
   const $row = $('<div></div>').attr('data-text-element',id).appendTo($storage);
   const values = {message:'',font_size:'14px',font_size_tablet:'',font_size_mobile:'',text_color:'#ffffff'};
   Object.keys(values).forEach(function (key) { $('<input type="hidden">').attr({'data-text-field':key,name:optionName + '[texts][' + id + '][' + key + ']'}).val(values[key]).appendTo($row); });
   const keys = $('[data-layout-tile]').map(function () { return $(this).attr('data-layout-key'); }).get();
   keys.push('text:' + id); $('[data-layout-order]').val(JSON.stringify(keys)).trigger('change');
   if (window.wpTopInfoBarLayout) window.wpTopInfoBarLayout.placeNew('text:' + id);
   notify(); open(id, source);
  }
  $editor.on('input change',persist);
  $modal.on('input change','[data-text-edit]',function () {
   if (loading) return;
   field($(this).data('text-edit'), this.value === '' ? '' : this.value + 'px'); notify();
  });
  $color.on('input change', function () { if (!loading) { field('text_color',this.value); notify(); } });
  $modal.find('[data-close-text-modal]').on('click',close);
  $modal.find('[data-clone-text-element]').on('click',function () {
   if (active === null) return;
   persist();
   const source = active, id = 't' + Date.now().toString(36) + Math.random().toString(36).slice(2,7);
   const $copy = row(source).clone(false,false).attr('data-text-element',id);
   $copy.find('[name]').each(function () { this.name = this.name.replace(/\[texts\]\[[^\]]+\]/, '[texts][' + id + ']'); });
   $copy.insertAfter(row(source));
   window.wpTopInfoBarLayout.cloneAfter('text:' + source, 'text:' + id);
   notify(); close();
   setTimeout(function () { $('[data-layout-key="text:' + id + '"]').trigger('focus'); },0);
  });
  $modal.find('[data-remove-text-element]').on('click',function () {
   const id = active; row(id).remove(); active = null;
   let keys = []; try { keys = JSON.parse($('[data-layout-order]').val() || '[]'); } catch(e) {}
   $('[data-layout-order]').val(JSON.stringify(keys.filter(key => key !== 'text:' + id))).trigger('change');
   notify(); close();
  });
  $form.on('submit.wpTopInfoBarTexts',persist);
  $(document).on('keydown.wpTopInfoBarTextModal', function (event) {
   if (active === null || $(event.target).closest('.pcr-app, .mce-window, .mce-floatpanel').length) return;
   if (event.key === 'Escape') { event.preventDefault(); close(); }
   if (event.key === 'Tab') {
    const $focusable = $modal.find('button, input, select, textarea, iframe, [tabindex="0"]').filter(':visible:not(:disabled)');
    const first = $focusable[0], last = $focusable[$focusable.length - 1];
    if (event.shiftKey && event.target === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && event.target === last) { event.preventDefault(); first.focus(); }
   }
  });
  window.wpTopInfoBarTexts = {open:open,add:add};
 });
})(jQuery);
