{* $Header: /cvsroot/tikiwiki/tiki/templates/modules/mod-users_rank.tpl,v 1.3 2005-05-18 11:03:32 mose Exp $ *}

{tikimodule title="<a href=\"tiki-list_users.php\">{tr}Top users{/tr}</a>" name="users_rank" flip=$module_params.flip decorations=$module_params.decorations}
<table border="0" cellpadding="0" cellspacing="0">
{section loop=$users_rank name=u}
  <tr>
    <td class="module">{$users_rank[u].position})&nbsp;</td>
    <td class="module">{$users_rank[u].score}</td>
    <td class="module">&nbsp;{$users_rank[u].login|userlink}</td>
  </tr>
{/section}
</table>
{/tikimodule}
