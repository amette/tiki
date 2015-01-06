{* $Id$ *} 
{if !$hide_page_header}
	{* display breadcrumbs here only if the feature is on and when site location feature is selected to appear above page in top of center column  *}
	{if $prefs.feature_siteloc eq 'page' and $prefs.feature_breadcrumbs eq 'y'}
		{if $prefs.feature_siteloclabel eq 'y'}{tr}Location : {/tr}{/if}
		{breadcrumbs type="trail" loc="page" crumbs=$crumbs}
		{if $prefs.feature_page_title eq 'y'}{breadcrumbs type="pagetitle" loc="page" crumbs=$crumbs machine_translate=$machine_translate_to_lang source_lang=$pageLang target_lang=$machine_translate_to_lang}{/if}
	{/if}

{if $beingStaged eq 'y'}
	<div class="tocnav">
		{tr}This is the staging copy of{/tr} <a class="link" href="tiki-index.php?page={$approvedPageName|escape:'url'}">{tr}the approved version of this page.{/tr}</a>
		{if $outOfSync eq 'y'}
			{if $canApproveStaging == 'y'}
				<div class="notif-pad">
					{if $lastSyncVersion}
						<a class="link" href="tiki-pagehistory.php?page={$page|escape:'url'}&amp;diff2={$lastSyncVersion}&amp;diff_style=sidediff">{tr}View changes since last approval.{/tr}</a>
					{else}
						{tr}Viewing of changes since last approval is possible only after first approval.{/tr}
					{/if}
					<form action="tiki-approve_staging_page.php" method="post">
						<input type="hidden" name="page" value="{$page|escape}" />
						<div class="notif-pad-2">
						<div class="notif-row">
							<input type="radio" name="action" value="approve" id="staging_approve" />&nbsp;<label for="staging_approve">{tr}Approve changes{/tr}</label>
							<div class="notif-pad-3" id="staging_approve_details">
							{if empty($pageLang) || ($pageLang == 'en') || ($pageLang =='en-US')}
								<input type="checkbox" name="outofdate" value="1" id="staging_outofdate">&nbsp;<label for="staging_outofdate">{tr}Mark other translations as out of date{/tr}</label>
								{if !empty($outofdate_desc)}
									<div class="notif-highlight" id="staging_outofdate_details">{$outofdate_desc|escape}</div>
								{/if}
							{/if}
							<div class="notif-pad-2">
								<label for="approve_summary">{tr}Feedback to the author (optional):{/tr}</label><br/>
								<textarea id="approve_summary" name="approve_comment" rows="3" cols="50"></textarea>
							</div>
						</div>
						<div class="notif-row">
							<input type="radio" name="action" value="reject" id="staging_reject" />&nbsp;<label for="staging_reject">{tr}Reject changes{/tr}</label>
							<div class="notif-pad-3" id="staging_reject_details">
								<label for="reject_summary">{tr}Reason for rejecting (will be e-mailed to editor):{/tr}</label><br/>
								<textarea id="reject_summary" name="reject_comment" rows="3" cols="50"></textarea>
							</div>
						</div>
						<input type="submit" name="staging_action" value="{tr}Submit{/tr}"/>
					</form>
				</div>
			{else}
				{tr}Latest changes will be synchronized after approval.{/tr}
			{/if}
		{/if}
	</div>
{/if}
{if $needsFirstApproval == 'y' and $canApproveStaging == 'y'}
	<div class="tocnav">
		{tr}This is a new staging page that has not been approved before. Edit and manually move it to the category for approved pages to approve it for the first time.{/tr}
	</div>
{/if}
{/if} {*hide_page_header*}

{if !$prefs.wiki_topline_position or $prefs.wiki_topline_position eq 'top' or $prefs.wiki_topline_position eq 'both'}
{include  file=tiki-wiki_topline.tpl}
{/if}
{if $print_page ne 'y'}
{if $prefs.page_bar_position eq 'top'}
{include  file=tiki-page_bar.tpl}
{/if}
{/if}

{if isset($saved_msg) && $saved_msg neq ''}
{remarksbox type="note" title="{tr}Note{/tr}"}{$saved_msg}{/remarksbox}
{/if}

<div class="categbar" style="clear: both; text-align: right">
    {if $user and $prefs.feature_user_watches eq 'y'}
        {if $category_watched eq 'y'}
            {tr}Watched by categories{/tr}:
            {section name=i loop=$watching_categories}
			    <a href="tiki-browse_categories.php?parentId={$watching_categories[i].categId}">{$watching_categories[i].name}</a>&nbsp;
            {/section}
        {/if}			
    {/if}
</div>

{if $prefs.feature_urgent_translation eq 'y'}
	{section name=i loop=$translation_alert}
	<div class="cbox">
	<div class="cbox-title">
	{icon _id=information style="vertical-align:middle"} {tr}Content may be out of date{/tr}
	</div>
	<div class="cbox-data">
		<p>{tr}An urgent request for translation has been sent. Until this page is updated, you can see a corrected version in the following pages:{/tr}</p>
		<ul>
		{section name=j loop=$translation_alert[i]}
			<li>
				<a href="{if $translation_alert[i][j].approvedPage && $hasStaging == 'y'}{$translation_alert[i][j].approvedPage|sefurl:wiki:with_next}{else}{$translation_alert[i][j].page|sefurl:wiki:with_next}{/if}bl=n">{if $translation_alert[i][j].approvedPage && $hasStaging == 'y'}{$translation_alert[i][j].approvedPage}{else}{$translation_alert[i][j].page}{/if}</a>
				({$translation_alert[i][j].lang})
				{if $editable and ($tiki_p_edit eq 'y' or $page|lower eq 'sandbox') and $beingEdited ne 'y' or $canEditStaging eq 'y'} 
				<a href="tiki-editpage.php?page={if isset($stagingPageName) && $hasStaging == 'y'}{$stagingPageName|escape:'url'}{else}{$page|escape:'url'}{/if}&amp;source_page={$translation_alert[i][j].page|escape:'url'}&amp;oldver={$translation_alert[i][j].last_update|escape:'url'}&amp;newver={$translation_alert[i][j].current_version|escape:'url'}&amp;diff_style=htmldiff" title="{tr}update from it{/tr}">{icon _id=arrow_refresh alt="{tr}update from it{/tr}" style="vertical-align:middle"}</a>
				{/if}
			</li>
		{/section}
		</ul>
	</div>
	</div>
	{/section}
{/if}

<div id="top" class="wikitext clearfix">

{if !$hide_page_header}
{if $prefs.feature_freetags eq 'y' and $tiki_p_view_freetags eq 'y' and isset($freetags.data[0]) and $prefs.freetags_show_middle eq 'y'}
{include file='freetag_list.tpl'}
{/if}

{if $pages > 1 and $prefs.wiki_page_navigation_bar neq 'bottom'}
	<div align="center">
		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$first_page}">{icon _id='resultset_first' alt="{tr}First page{/tr}"}</a>

		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$prev_page}">{icon _id='resultset_previous' alt="{tr}Previous page{/tr}"}</a>

		<small>{tr}page{/tr}:{$pagenum}/{$pages}</small>

		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$next_page}">{icon _id='resultset_next' alt="{tr}Next page{/tr}"}</a>


		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$last_page}">{icon _id='resultset_last' alt="{tr}Last page{/tr}"}</a>
	</div>
{/if}

{if $prefs.feature_page_title eq 'y'}
	<h1 class="pagetitle">{breadcrumbs type="pagetitle" loc="page" crumbs=$crumbs machine_translate=$machine_translate_to_lang source_lang=$pageLang target_lang=$machine_translate_to_lang}</h1>
{/if}

{if $structure eq 'y' and ($prefs.wiki_structure_bar_position ne 'bottom')}
	{include file=tiki-wiki_structure_bar.tpl}
{/if}

{if $prefs.feature_wiki_ratings eq 'y'}{include file='poll.tpl'}{/if}
{/if} {*hide_page_header*}

{if $machine_translate_to_lang != ''}
	{remarksbox type="warning" title="{tr}Warning{/tr}" highlight="y"}
       {tr}This text was automatically translated by Google Translate from the following page: {/tr}<a href="tiki-index.php?page={$page}">{$page}</a>
	{/remarksbox}
{/if}

{if $pageLang eq 'ar' or $pageLang eq 'he'}
<div style="direction:RTL; unicode-bidi:embed; text-align: right; {if $pageLang eq 'ar'}font-size: large;{/if}">
{$parsed}
</div>
{else}
{$parsed}
{/if}

{* Information below the wiki content must not overlap the wiki content that could contain floated elements *}
<hr class="hrwikibottom" /> 

{if $structure eq 'y' and (($prefs.wiki_structure_bar_position eq 'bottom') or ($prefs.wiki_structure_bar_position eq 'both'))}
	{include file=tiki-wiki_structure_bar.tpl}
{/if}


{if $pages > 1 and $prefs.wiki_page_navigation_bar neq 'top'}
	<br />
	<div align="center">
		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$first_page}">{icon _id='resultset_first' alt="{tr}First page{/tr}"}</a>

		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$prev_page}">{icon _id='resultset_previous' alt="{tr}Previous page{/tr}"}</a>

		<small>{tr 0=$pagenum 1=$pages}page: %0/%1{/tr}</small>

		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$next_page}">{icon _id='resultset_next' alt="{tr}Next page{/tr}"}</a>


		<a href="tiki-index.php?{if $page_info}page_ref_id={$page_info.page_ref_id}{else}page={$page|escape:"url"}{/if}&amp;pagenum={$last_page}">{icon _id='resultset_last' alt="{tr}Last page{/tr}"}</a>
	</div>
{/if}
</div> {* End of main wiki page *}
<!-- 1234 -->

{if $has_footnote eq 'y'}<div class="wikitext" id="wikifootnote">{$footnote}</div>{/if}
{if $wiki_authors_style neq 'none' || $prefs.wiki_feature_copyrights eq 'y'|| $print_page eq 'y'}
  <p class="editdate"> {* begining editdate *}
{/if}
{if isset($wiki_authors_style) && $wiki_authors_style eq 'business'}
  {tr}Last edited by{/tr} {$lastUser|userlink}
  {section name=author loop=$contributors}
   {if $smarty.section.author.first}, {tr}based on work by{/tr}
   {else}
    {if !$smarty.section.author.last},
    {else} {tr}and{/tr}
    {/if}
   {/if}
   {$contributors[author]|userlink}
  {/section}.<br />
  {tr}Page last modified on{/tr} {$lastModif|tiki_long_datetime}. {if $prefs.wiki_show_version eq 'y'}({tr}Version{/tr} {$lastVersion}){/if}
{elseif isset($wiki_authors_style) && $wiki_authors_style eq 'collaborative'}
  {tr}Contributors to this page{/tr}: {$lastUser|userlink}
  {section name=author loop=$contributors}
   {if !$smarty.section.author.last},
   {else} {tr}and{/tr}
   {/if}
   {$contributors[author]|userlink}
  {/section}.<br />
  {tr 0=$lastModif|tiki_long_datetime 1=$lastUser|userlink}Page last modified on %0 by %1{/tr}. {if $prefs.wiki_show_version eq 'y'}({tr}Version{/tr} {$lastVersion}){/if}
{elseif empty($wiki_authors_style) || $wiki_authors_style eq 'none'}
{elseif isset($wiki_authors_style) && $wiki_authors_style eq 'lastmodif'}
	{tr}Page last modified on{/tr} {$lastModif|tiki_long_datetime}
{else}
  {tr 0=$creator|userlink}Created by %0{/tr}.
  {tr 0=$lastModif|tiki_long_datetime 1=$lastUser|userlink}Last Modification: %0 by %1{/tr}. {if $prefs.wiki_show_version eq 'y'}({tr}Version{/tr} {$lastVersion}){/if}
{/if}

{if $prefs.wiki_feature_copyrights eq 'y' and $prefs.wikiLicensePage}
  {if $prefs.wikiLicensePage == $page}
    {if $tiki_p_edit_copyrights eq 'y'}
      <br />
      {tr}To edit the copyright notices{/tr} <a href="copyrights.php?page={$copyrightpage}">{tr}Click Here{/tr}</a>.
    {/if}
  {else}
    <br />
    {tr}The content on this page is licensed under the terms of the{/tr} <a href="{$prefs.wikiLicensePage|sefurl:wiki:with_next}copyrightpage={$page|escape:"url"}">{$prefs.wikiLicensePage}</a>.
  {/if}
{/if}

{if $print_page eq 'y'}
    <br />
    {tr}The original document is available at{/tr} <a href="{$base_url}{$page|sefurl}">{$base_url}{$page|sefurl}</a>
{/if}

{if $wiki_authors_style neq 'none' || $prefs.wiki_feature_copyrights eq 'y'|| $print_page eq 'y'}
  </p> {* end editdate *}
{/if}

{if $is_categorized eq 'y' and $prefs.feature_categories eq 'y' and $prefs.feature_categoryobjects eq 'y'}
{$display_catobjects}
{/if}

{if $prefs.wiki_topline_position eq 'bottom' or $prefs.wiki_topline_position eq 'both'}
{include  file=tiki-wiki_topline.tpl}
{/if}
{if $print_page ne 'y'}
{if (!$prefs.page_bar_position or $prefs.page_bar_position eq 'bottom' or $prefs.page_bar_position eq 'both') and $machine_translate_to_lang == ''}
{include  file=tiki-page_bar.tpl}
{/if}
{/if}