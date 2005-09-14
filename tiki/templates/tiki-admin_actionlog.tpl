{* $Header: /cvsroot/tikiwiki/tiki/templates/tiki-admin_actionlog.tpl,v 1.1 2005-09-14 21:45:41 sylvieg Exp $ *}

<h1><a href="tiki-admin_actionlog.php" class="pagetitle">{tr}Admin Action Log{/tr}</a></h1>
<a name="Setting" />
<h2>{tr}Setting{/tr}</h2>
<form method="post" action="tiki-admin_actionlog.php">
<table class="normal">
<tr><th class="heading">{tr}status{/tr}</th><th class="heading">{tr}action{/tr}</th><th class="heading">{tr}type{/tr}</th></tr>
{cycle values="even,odd" print=false}
{section name=ix loop=$actionlogConf}
<tr><td class="{cycle advance=false}"><input type="checkbox" name="{$actionlogConf[ix].code}" {if $actionlogConf[ix].status eq 'y'}checked="checked"{/if} /></td><td class="{cycle advance=false}">{tr}{$actionlogConf[ix].action}{/tr}</td><td class="{cycle}">{tr}{$actionlogConf[ix].objectType}{/tr}</td></tr>
{/section}
<tr><td colspan="3" class="button"><input type="submit" name="setConf" value="{tr}Set{/tr}" /></td></tr>
</table>
</form>

<a name="Report">
<h2>{tr}Report{/tr}</h2>
<form method="post" action="tiki-admin_actionlog.php#Report">
<h3>{tr}Filter{/tr}</h3>
<table class="normal">
<tr>
<td>{tr}User:{/tr}</td>
<td><select name="user">
<option value="" {if $reportUser eq  ''}selected="selected"{/if}>* {tr}All{/tr} *</option>
<option value="Anonymous" {if $reportUser eq  'Anonymous'}selected="selected"{/if}>* {tr}Anonymous{/tr} *</option>
<option value="Registered" {if $reportUser eq  'Registered'}selected="selected"{/if}>* {tr}Registered{/tr} *</option>
{foreach key=userId item=login from=$users}
<option value="{$login|escape}" {if $reportUser eq  $login}selected="selected"{/if}>{$login|escape}</option>
{/foreach}
</select>
</td>
<td>{tr}Start date:{/tr}</td>
<td>{html_select_date time=$startDate prefix="startDate_" end_year="-10" field_order=DMY}</td>
</tr><tr>
<td>{tr}Category:{/tr}</td>
<td><select name="categId">
<option value="" {if $reportCateg eq  '' or reportCateg eq 0}selected="selected"{/if}>* {tr}All{/tr} *</option>
 {section name=ix loop=$categories}
<option value="{$categories[ix].categId|escape}" {if $reportCateg eq  $categories[ix].name}selected="selected"{/if}>{$categories[ix].name|escape}</option>
{/section}
</select>
</td>
<td>{tr}End date:{/tr}</td>
<td>{html_select_date time=$endDate prefix="endDate_" end_year="-10" field_order=DMY}</td>
</tr>
<tr><td colspan="4" class="button"><input type="submit" name="list" value="{tr}Report{/tr}" /></td></tr>
</table>
</form>

<a name="List" />
<h3>{tr}List{/tr}{if $reportUser ne '' and $reportUser ne 'Anonymous' and $reportUser ne 'Registered'} &mdash; {tr}User:{/tr} {$reportUser}{/if}{if $reportCateg ne ''} &mdash; {tr}Category:{/tr} {$reportCateg}{/if}</h3>
<table class="normal">
<tr>
{if $reportUser eq '' or $reportUser eq 'Registered'}<th class="heading">{tr}user{/tr}</th>{/if}
<th class="heading">{tr}date{/tr}</th>
<th class="heading">{tr}action{/tr}</th>
<th class="heading">{tr}type{/tr}</th>
<th class="heading">{tr}object{/tr}</th>
{if !$reportCateg and $showCateg eq 'y'}<th class="heading">{tr}category{/tr}</th>{/if}
</tr>
{cycle values="even,odd" print=false}
{section name=ix loop=$actionlogs}
<tr>
{if $reportUser eq '' or $reportUser eq 'Registered'}<td class="{cycle advance=false}">{$actionlogs[ix].user}</td>{/if}
<td class="{cycle advance=false}">{$actionlogs[ix].lastModif|tiki_short_datetime}</td>
<td class="{cycle advance=false}">{$actionlogs[ix].action}</td>
<td class="{cycle advance=false}">{$actionlogs[ix].objectType}</td>
<td class="{cycle advance=false}">{if $actionlogs[ix].link}<a href="{$actionlogs[ix].link}" title="{tr}view{/tr}">{$actionlogs[ix].object|escape}</a>{else}{$actionlogs[ix].object|escape}{/if}</td>
{if !$reportCateg and $showCateg eq 'y'}<td class="{cycle}">{$actionlogs[ix].categName|escape}</td>{/if}
</tr>
{/section}
</table>

<a name="Statistic" />
<h3>{tr}Statistic{/tr}{if $reportUser ne '' and $reportUser ne 'Anonymous' and $reportUser ne 'Registered'} &mdash; {tr}User:{/tr} {$reportUser}{/if}{if $reportCateg ne ''} &mdash; {tr}Category:{/tr} {$reportCateg}{/if}</h3>

{if $showLogin eq 'y'}
<table class="normal">
<tr>
<th class="heading">{tr}user{/tr}</th>
<th class="heading">{tr}connection time{/tr}</th>
<th class="heading">{tr}connection seconds{/tr}</th>
<th class="heading">{tr}login{/tr}</th>
</tr>
{foreach key=user item=time from=$logTimes}
<tr>
<td class="{cycle advance=false}">{$user}</td>
<td class="{cycle advance=false}">{$time.days} {tr}days{/tr} {$time.hours} {tr}hours{/tr} {$time.mins} {tr}mns{/tr}</td>
<td class="{cycle advance=false}">{$time.time}</td>
<td class="{cycle}">{$time.nbLogins}</td>
</tr>
{/foreach}
</table>
{/if}

<table class="normal">
<tr>
<th class="heading">{tr}user{/tr}</th>
{foreach key=title item=nb from=$statUser[0]}
{if $title ne 'user'}<th class="heading">{$title|replace:"/":" "}</th>{/if}
{/foreach}
</tr>
{cycle values="even,odd" print=false}
{foreach item=stat from=$statUser}
<tr>
<td class="{cycle advance=false}">{$stat.user}</td>
{foreach key=a item=nb from=$stat}
{if $a ne 'user'}<td class="{cycle advance=false}">{$nb}</td>{/if}
{/foreach}
<!-- {cycle} -->
</tr>
{/foreach}
</table>

{if $showCateg eq 'y'}
<table class="normal">
<tr>
<th class="heading">{tr}category{/tr}</th>
{foreach  key=title item=nb from=$statUser[0]}
{if $title ne 'user'}<th class="heading">{$title|replace:"/":" "}</th>{/if}
{/foreach}
</tr>
{foreach key=categId item=stat from=$statCateg}
<tr>
<td class="{cycle advance=false}">{$stat.category}</td>
{foreach  key=a item=nb from=$statCateg[$categId]}
{if $a ne 'category'}<td class="{cycle advance=false}">{$nb}</td>{/if}
{/foreach}
<!-- {cycle} -->
</tr>
{/foreach}
</table>
{/if}

{if $showCateg eq 'y'}
<table class="normal">
<tr>
<th class="heading">{tr}category{/tr}</th>
<th class="heading">{tr}user{/tr}</th>
{foreach  key=title item=nb from=$statUser[0]}
{if $title ne 'user'}<th class="heading">{$title|replace:"/":" "}</th>{/if}
{/foreach}
</tr>
{foreach key=categUser item=stat from=$statUserCateg}
<tr>
<td class="{cycle advance=false}">{$stat.category}</td>
<td class="{cycle advance=false}">{$stat.user}</td>
{foreach key=a item=nb from=$stat}
{if $a ne 'category' and $a ne 'user'}<td class="{cycle advance=false}">{$nb}</td>{/if}
{/foreach}
<!-- {cycle} -->
</tr>
{/foreach}
</table>
{/if}
