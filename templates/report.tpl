<div class="academiaEduReport">
    <h3>{translate key="plugins.generic.academiaEdu.report.oaiTitle"}</h3>
    <p>{if $oaiEnabled}<strong style="color:#198754">✓ {translate key="plugins.generic.academiaEdu.report.oaiOn"}</strong>{else}<strong style="color:#b00020">✗ {translate key="plugins.generic.academiaEdu.report.oaiOff"}</strong>{/if}</p>
    <p><code>{$oaiUrl|escape}</code></p>
    {if $oaiRepositoryId}<p>Repository ID: <code>{$oaiRepositoryId|escape}</code></p>{/if}
    <p>
        <button type="button" class="pkp_button" data-url="{$oaiUrl|escape}" data-ok="{translate key="plugins.generic.academiaEdu.report.oaiOk"}" data-fail="{translate key="plugins.generic.academiaEdu.report.oaiFail"}" onclick="academiaEduTestOai(this)">{translate key="plugins.generic.academiaEdu.report.oaiTest"}</button>
        <span id="academiaEduOaiResult"></span>
    </p>
    <p style="font-size:.9em;color:#555">{translate key="plugins.generic.academiaEdu.report.oaiHelp"}</p>

    <h3>{translate key="plugins.generic.academiaEdu.report.articlesTitle"}</h3>
    {if $reportRows}
    <div style="max-height:320px;overflow:auto">
    <table class="pkp_table" style="width:100%">
        <thead><tr>
            <th>{translate key="plugins.generic.academiaEdu.report.colTitle"}</th>
            <th>{translate key="plugins.generic.academiaEdu.report.colStatus"}</th>
            <th>{translate key="plugins.generic.academiaEdu.report.colMissing"}</th>
            <th>{translate key="plugins.generic.academiaEdu.report.colRecommended"}</th>
        </tr></thead>
        <tbody>
        {foreach from=$reportRows item=row}
            <tr>
                <td><a href="{$row.url|escape}" target="_blank" rel="noopener">{if $row.title}{$row.title|escape}{else}#{$row.id}{/if}</a></td>
                <td>{if $row.ready}<span style="color:#198754">✓ {translate key="plugins.generic.academiaEdu.article.badgeReady"}</span>{else}<span style="color:#a15c00">○ {translate key="plugins.generic.academiaEdu.article.badgeIncomplete"}</span>{/if}</td>
                <td>{foreach from=$row.missing item=m name=a}{$m|escape}{if !$smarty.foreach.a.last}, {/if}{/foreach}</td>
                <td>{foreach from=$row.recommended item=m name=b}{$m|escape}{if !$smarty.foreach.b.last}, {/if}{/foreach}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    </div>
    <p style="font-size:.85em;color:#666">{translate key="plugins.generic.academiaEdu.report.limit" limit=$reportLimit}</p>
    {else}
    <p>{translate key="plugins.generic.academiaEdu.report.none"}</p>
    {/if}
</div>
{literal}
<script>
function academiaEduTestOai(btn) {
    var out = document.getElementById('academiaEduOaiResult');
    var base = btn.getAttribute('data-url');
    var url = base + (base.indexOf('?') > -1 ? '&' : '?') + 'verb=Identify';
    out.textContent = '…';
    fetch(url, {credentials: 'same-origin'})
        .then(function (r) { return r.text().then(function (t) { return {ok: r.ok, text: t}; }); })
        .then(function (res) {
            out.textContent = (res.ok && res.text.indexOf('repositoryName') > -1) ? btn.getAttribute('data-ok') : btn.getAttribute('data-fail');
        })
        .catch(function () { out.textContent = btn.getAttribute('data-fail'); });
}
</script>
{/literal}
