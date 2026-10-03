<script>
    $(function() {ldelim}
        $('#academiaEduSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="academiaEduSettingsForm" method="post" action="{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
    {csrf}
    {include file="controllers/notification/inPlaceNotification.tpl" notificationId="academiaEduSettingsFormNotification"}

    {fbvFormArea id="academiaEduSettingsArea"}
        <p>{translate key="plugins.generic.academiaEdu.settings.intro"}</p>
        {fbvFormSection}
            {fbvElement type="text" id="buttonLabel" value=$buttonLabel label="plugins.generic.academiaEdu.settings.buttonLabel"}
            {fbvElement type="select" id="panelVisibility" from=$panelVisibilityOptions selected=$panelVisibility translate=false label="plugins.generic.academiaEdu.settings.panelVisibility"}
        {/fbvFormSection}
        {fbvFormSection list=true}
            {fbvElement type="checkbox" id="showMetaTags" value="1" checked=$showMetaTags label="plugins.generic.academiaEdu.settings.showMetaTags"}
            {fbvElement type="checkbox" id="showJsonLd" value="1" checked=$showJsonLd label="plugins.generic.academiaEdu.settings.showJsonLd"}
            {fbvElement type="checkbox" id="showDublinCore" value="1" checked=$showDublinCore label="plugins.generic.academiaEdu.settings.showDublinCore"}
            {fbvElement type="checkbox" id="addOaiLink" value="1" checked=$addOaiLink label="plugins.generic.academiaEdu.settings.addOaiLink"}
            {fbvElement type="checkbox" id="showBibtex" value="1" checked=$showBibtex label="plugins.generic.academiaEdu.settings.showBibtex"}
            {fbvElement type="checkbox" id="showRis" value="1" checked=$showRis label="plugins.generic.academiaEdu.settings.showRis"}
        {/fbvFormSection}
    {/fbvFormArea}

    {fbvFormButtons submitText="common.save"}
</form>
