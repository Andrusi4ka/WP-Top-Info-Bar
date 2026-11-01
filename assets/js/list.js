(function($){'use strict';$(function(){
 const config=window.wpTopInfoBarList||{};
 $(document).on('change','[data-toggle-bar]',function(){
  const $input=$(this),previous=!this.checked,$status=$input.closest('td').find('[data-toggle-status]');
  $input.prop('disabled',true);$status.text('');
  $.post(ajaxurl,{action:'wp_top_info_bar_toggle',nonce:config.toggleNonce,post_id:$input.data('post-id'),enabled:this.checked?'1':'0'})
   .done(function(response){if(response.success){$input.prop('checked',!!response.data.enabled)}else{$input.prop('checked',previous);$status.text(response.data&&response.data.message||config.toggleError)}})
   .fail(function(){$input.prop('checked',previous);$status.text(config.toggleError)})
   .always(function(){$input.prop('disabled',false)});
 });
 $(document).on('click','[data-copy-shortcode]',function(){
  const input=this;if(input.dataset.copying==='1')return;
  input.select();const original=input.value;
  function copied(){input.dataset.copying='1';input.value=config.copied||'Copied!';setTimeout(function(){input.value=original;delete input.dataset.copying},900)}
  function fallback(){try{input.select();if(document.execCommand('copy'))copied()}catch(error){}}
  if(navigator.clipboard&&window.isSecureContext)navigator.clipboard.writeText(original).then(copied,fallback);else fallback();
 });
 $('[data-priority-row]').each(function(){$(this).data('savedPriority',Number($(this).find('[data-priority-input]').val())||1)});
 function sortRows(){
  const params=new URL(window.location.href).searchParams,orderby=params.get('orderby');
  if(orderby&&orderby!=='wp_top_info_bar_priority')return;
  const direction=params.get('order')==='desc'?-1:1;
  const rows=$('[data-priority-row]').closest('tr').get();
  rows.sort(function(a,b){const ar=$(a).find('[data-priority-row]'),br=$(b).find('[data-priority-row]');return direction*(ar.data('savedPriority')-br.data('savedPriority'))||Number(ar.data('priority-row'))-Number(br.data('priority-row'))});
  rows.forEach(row=>row.parentElement.appendChild(row));
 }
 $(document).on('click','[data-save-priority]',function(){
  const $button=$(this),$row=$button.closest('[data-priority-row]'),$input=$row.find('[data-priority-input]'),$status=$row.find('[data-priority-status]');
  if(!$input[0].reportValidity())return;
  $button.prop('disabled',true);$status.text('');
  $.post(ajaxurl,{action:'wp_top_info_bar_priority',nonce:config.nonce,post_id:$row.data('priority-row'),priority:$input.val()})
   .done(function(response){if(response.success){$input.val(response.data.priority);$row.data('savedPriority',response.data.priority);$status.text(' '+config.saved);sortRows()}else{$status.text(' '+(response.data&&response.data.message||config.error))}})
   .fail(function(){$status.text(' '+config.error)})
   .always(function(){$button.prop('disabled',false)});
 });
 $(document).on('keydown','[data-priority-input]',function(event){if(event.key==='Enter'){event.preventDefault();$(this).closest('[data-priority-row]').find('[data-save-priority]').trigger('click')}});
});})(jQuery);
