{* $Header: /cvsroot/tikiwiki/tiki/templates/styles/musus/modules/mod-last_articles.tpl,v 1.2 2004-01-16 18:35:05 musus Exp $ *}

{if $feature_articles eq 'y'}
{if $nonums eq 'y'}
{eval var="<a href=\"tiki-view_articles.php\">{tr}Last `$module_rows` articles{/tr}</a>" assign="tpl_module_title"}
{else}
{eval var="<a href=\"tiki-view_articles.php\">{tr}Last articles{/tr}</a>" assign=""}
{/if}
{tikimodule title=$tpl_module_title name="last_articles"}
  <table width="100%" border="0" cellpadding="0" cellspacing="2">
    {section name=ix loop=$modLastArticles}
      <tr class="module">
        {if $nonums != 'y'}<td>{$smarty.section.ix.index_next})</td>{/if}
        <td>
          <a class="linkmodule" href="tiki-read_article.php?articleId={$modLastArticles[ix].articleId}">
            {$modLastArticles[ix].title}<br />{$modLastArticles[ix].publishDate|tiki_short_datetime}
          </a>
        </td>
      </tr>
    {/section}
  </table>
{/tikimodule}
{/if}
