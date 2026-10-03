<div style="margin-bottom:.75rem">
    <label for="{$fid}"><strong>{translate key=$flabel}</strong></label>
    <textarea id="{$fid}" rows="{$frows}" readonly style="width:100%">{$fvalue|escape}</textarea>
    <button type="button" class="pkp_button" data-done="{translate key="plugins.generic.academiaEdu.article.copied"}" onclick="academiaEduCopy('{$fid}', this)">{translate key="plugins.generic.academiaEdu.article.copy"}</button>
</div>
