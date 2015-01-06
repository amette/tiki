{* $Id$ *}
{popup_init src="lib/overlib.js"}
{remarksbox type="tip" title="{tr}Tip{/tr}"}
	{tr}Look under "Articles" on the application menu for links to{/tr} "<a class="rbox-link" href="tiki-admin_topics.php">{tr}Admin topics{/tr}</a>" {tr}and{/tr} "<a class="rbox-link" href="tiki-article_types.php">{tr}Admin types{/tr}</a>".
{/remarksbox}

{if !empty($msgs)}
	<div class="simplebox highlight">
	{foreach from=$msgs item=msg}
	{$msg}			 
	{/foreach}
	</div>
{/if}

<form method="post" action="tiki-admin.php?page=cms">
	<div class="input_submit_container clear" style="text-align: right;">
		<input type="submit" value="{tr}Change preferences{/tr}" />
	</div>

	{tabset name="admin_cms"}
		{tab name="{tr}General Settings{/tr}"}
			<input type="hidden" name="cmsprefs" />

			{preference name=art_home_title}
			{preference name=maxArticles}

			<fieldset>
				<legend>
					{tr}Features{/tr}{help url="Articles"}
				</legend>

				{preference name=feature_submissions}
				{preference name=feature_cms_rankings}

				{preference name=feature_article_comments}
				<div class="adminoptionboxchild" id="feature_article_comments_childcontainer">
					{preference name=article_comments_per_page}
					{preference name=article_comments_default_ordering}
				</div>

				{preference name=cms_spellcheck}
				{preference name=feature_cms_templates}
				{preference name=feature_cms_print}
				{preference name=feature_cms_emails}

				<input type="hidden" name="cmsfeatures" />

			</fieldset>

			<fieldset>
				<legend>{tr}Import CSV file{/tr}</legend>
				<div class="adminoptionbox">
					<div class="adminoptionlabel">
						<label for="csvlist">{tr}Batch upload (CSV file){/tr}:</label>
						<input type="file" name="csvlist" id="csvlist" /> 
						<br />
						<em>{tr}File format: title,authorName,heading,body,lang,user{/tr}....</em>
						<div align="center">
							<input type="submit" name="import" value="{tr}Import{/tr}" />
						</div>
					</div>
				</div>
			</fieldset>
		{/tab}

		{tab name="{tr}Articles Listing{/tr}"}
			<div class="adminoptionbox">
				{tr}Select which items to display when listing articles{/tr}: 	  
				<a class="rbox-link" href="tiki-list_articles.php">tiki-list_articles.php</a>
			</div>
			<input type="hidden" name="artlist" />

			{preference name=art_list_title}
			<div class="adminoptionboxchild" id="art_list_title_childcontainer">
				{preference name=art_list_title_len}
			</div>
			{preference name=art_list_type}
			{preference name=art_list_topic}
			{preference name=art_list_date}
			{preference name=art_list_expire}
			{preference name=art_list_visible}
			{preference name=art_list_lang}
			{preference name=art_list_author}
			{preference name=art_list_rating}
			{preference name=art_list_reads}
			{preference name=art_list_size}
			{preference name=art_list_img}
		{/tab}
	{/tabset}
	<div class="input_submit_container clear" style="text-align: center;">
		<input type="submit" value="{tr}Change preferences{/tr}" />
	</div>
</form>
