{if $academiaEdu}
{assign var=ae value=$academiaEdu}
<section class="item academia-edu-integration" id="academiaEduReady">
    <h2 class="label">Academia.edu</h2>
    <details>
        <summary style="cursor:pointer;font-weight:600">
            <span style="color:{if $ae.ready}#198754{else}#a15c00{/if}">{if $ae.ready}✓ {translate key="plugins.generic.academiaEdu.article.badgeReady"}{else}○ {translate key="plugins.generic.academiaEdu.article.badgeIncomplete"}{/if}</span>
            — {$ae.buttonLabel|escape}
        </summary>
        <div style="margin-top:1rem;padding:1rem;border:1px solid #ddd;border-radius:6px">
            <p><strong>{translate key="plugins.generic.academiaEdu.article.packageTitle"}</strong></p>

            {if !$ae.ready}
                <p style="color:#a15c00">{translate key="plugins.generic.academiaEdu.article.missingIntro"}: {foreach from=$ae.missing item=m name=mm}{$m|escape}{if !$smarty.foreach.mm.last}, {/if}{/foreach}</p>
            {/if}

            {include file=$academiaEduFieldTpl fid="academiaFTitle" flabel="plugins.generic.academiaEdu.article.fTitle" fvalue=$ae.title frows=2}
            {include file=$academiaEduFieldTpl fid="academiaFAuthors" flabel="plugins.generic.academiaEdu.article.fAuthors" fvalue=$ae.authorsText frows=2}
            {if $ae.abstract}{include file=$academiaEduFieldTpl fid="academiaFAbstract" flabel="plugins.generic.academiaEdu.article.fAbstract" fvalue=$ae.abstract frows=7}{/if}
            {if $ae.keywordsText}{include file=$academiaEduFieldTpl fid="academiaFKeywords" flabel="plugins.generic.academiaEdu.article.fKeywords" fvalue=$ae.keywordsText frows=2}{/if}
            {include file=$academiaEduFieldTpl fid="academiaFCitation" flabel="plugins.generic.academiaEdu.article.fCitation" fvalue=$ae.citation frows=3}
            {if $ae.doiUrl}{include file=$academiaEduFieldTpl fid="academiaFDoi" flabel="plugins.generic.academiaEdu.article.fDoi" fvalue=$ae.doiUrl frows=1}{/if}
            {include file=$academiaEduFieldTpl fid="academiaFUrl" flabel="plugins.generic.academiaEdu.article.fUrl" fvalue=$ae.articleUrl frows=1}

            <textarea id="academiaPackage" hidden>{$ae.package|escape}</textarea>
            {if $ae.bibtex}<textarea id="academiaBib" hidden>{$ae.bibtex|escape}</textarea>{/if}
            {if $ae.ris}<textarea id="academiaRis" hidden>{$ae.ris|escape}</textarea>{/if}

            <p style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
                {if $ae.pdfUrl}<a class="pkp_button" href="{$ae.pdfUrl|escape}" download>{translate key="plugins.generic.academiaEdu.article.downloadPdf"}</a>{/if}
                <button type="button" class="pkp_button" onclick="academiaEduDownload('academia-upload-package.txt','academiaPackage')">{translate key="plugins.generic.academiaEdu.article.downloadPackage"}</button>
                {if $ae.bibtex}<button type="button" class="pkp_button" onclick="academiaEduDownload('article.bib','academiaBib')">{translate key="plugins.generic.academiaEdu.article.downloadBib"}</button>{/if}
                {if $ae.ris}<button type="button" class="pkp_button" onclick="academiaEduDownload('article.ris','academiaRis')">{translate key="plugins.generic.academiaEdu.article.downloadRis"}</button>{/if}
            </p>
            <p style="font-size:.9em;color:#666">{translate key="plugins.generic.academiaEdu.article.note"}</p>
        </div>
    </details>
</section>
{literal}
<script>
function academiaEduCopy(id, btn) {
    var el = document.getElementById(id);
    var done = function () {
        var old = btn.getAttribute('data-label') || btn.innerText;
        btn.setAttribute('data-label', old);
        btn.innerText = btn.getAttribute('data-done');
        setTimeout(function () { btn.innerText = old; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(el.value).then(done);
    } else {
        el.focus(); el.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
    }
}
function academiaEduDownload(filename, id) {
    var blob = new Blob([document.getElementById(id).value], {type: 'text/plain;charset=utf-8'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
}
</script>
{/literal}
{/if}
