{* $Id$ *}
{title help="Newsletters"}{tr}Admin newsletters{/tr}{/title}

<div class="t_navbar btn-group form-group">
	{button href="tiki-admin_newsletters.php?cookietab=2" _class="btn btn-default" _text="{tr}Create Newsletter{/tr}"}
	{button href="tiki-newsletters.php" _class="btn btn-default" _text="{tr}List Newsletters{/tr}"}
	{button href="tiki-send_newsletters.php" _class="btn btn-default" _text="{tr}Send Newsletters{/tr}"}
</div>

{tabset}

	{tab name="{tr}Newsletters{/tr}"}
		<h2>{tr}Newsletters{/tr}</h2>

		{if $channels or ($find ne '')}
			{include file='find.tpl'}
		{/if}

		<div class="table-responsive">
			<table class="table normal">
				<tr>
					<th>{self_link _sort_arg='sort_mode' _sort_field='nlId'}{tr}ID{/tr}{/self_link}</th>
					<th>{self_link _sort_arg='sort_mode' _sort_field='name'}{tr}Newsletter{/tr}{/self_link}</th>
					<th>{self_link _sort_arg='sort_mode' _sort_field='author'}{tr}Author{/tr}{/self_link}</th>
					<th>{self_link _sort_arg='sort_mode' _sort_field='users'}{tr}Users{/tr}{/self_link}</th>
					<th>{self_link _sort_arg='sort_mode' _sort_field='editions'}{tr}Editions{/tr}{/self_link}</th>
					<th>{tr}Drafts{/tr}</th>
					<th>{self_link _sort_arg='sort_mode' _sort_field='lastSent'}{tr}Last Sent{/tr}{/self_link}</th>
					<th>{tr}Action{/tr}</th>
				</tr>

				{section name=user loop=$channels}
					<tr>
						<td class="id">{self_link cookietab='2' _anchor='anchor2' nlId=$channels[user].nlId _title="{tr}Edit{/tr}"}{$channels[user].nlId}{/self_link}</td>
						<td class="text">
							{self_link cookietab='2' _anchor='anchor2' nlId=$channels[user].nlId _title="{tr}Edit{/tr}"}{$channels[user].name|escape}{/self_link}
							<div class="subcomment">{$channels[user].description|escape|nl2br}</div>
						</td>
						<td class="username">{$channels[user].author}</td>
						<td class="integer">{$channels[user].users} ({$channels[user].confirmed})</td>
						<td class="integer">{$channels[user].editions}</td>
						<td class="integer">{$channels[user].drafts}</td>
						<td class="date">{$channels[user].lastSent|tiki_short_datetime}</td>
						<td class="action">
							{permission_link mode=icon type=newsletter permType=newsletters id=$channels[user].nlId title=$channels[user].name}
							{self_link _icon='page_edit' cookietab='2' _anchor='anchor2' nlId=$channels[user].nlId}{tr}Edit{/tr}{/self_link}
							<a class="link" href="tiki-admin_newsletter_subscriptions.php?nlId={$channels[user].nlId}" title="{tr}Subscriptions{/tr}">{icon _id='group' alt="{tr}Subscriptions{/tr}"}</a>
							<a class="link" href="tiki-send_newsletters.php?nlId={$channels[user].nlId}" title="{tr}Send Newsletter{/tr}">{icon _id='email' alt="{tr}Send Newsletter{/tr}"}</a>
							<a class="link" href="tiki-newsletter_archives.php?nlId={$channels[user].nlId}" title="{tr}Archives{/tr}">{icon _id='database' alt="{tr}Archives{/tr}"}</a>
							{self_link _icon='cross' remove=$channels[user].nlId}{tr}Remove{/tr}{/self_link}
						</td>
					</tr>
				{sectionelse}
					{norecords _colspan=8}
				{/section}
			</table>
		</div>

		{pagination_links cant=$cant_pages step=$prefs.maxRecords offset=$offset}{/pagination_links}
	{/tab}

	{tab name="{tr}Create/Edit Newsletters{/tr}"}
		<h2>{tr}Create/Edit Newsletters{/tr}</h2>
		{if isset($individual) && $individual eq 'y'}
			{permission_link mode=link type=newsletter permType=newsletters id=$info.nlId title=$info.name label="{tr}There are individual permissions set for this newsletter{/tr}"}
		{/if}

		<form action="tiki-admin_newsletters.php" method="post">
			<input type="hidden" name="nlId" value="{$info.nlId|escape}">
			<input type="hidden" name="author" value="{$user|escape}">
			<table class="formcolor">
				<tr>
					<td>{tr}Name:{/tr}</td>
					<td>
						<input type="text" name="name" value="{$info.name|escape}">
					</td>
				</tr>
				<tr>
					<td>{tr}Description:{/tr}</td>
					<td>
						<textarea name="description" rows="4" cols="40">{$info.description|escape}</textarea>
					</td>
				</tr>
				<tr>
					<td>{tr}Users can subscribe/unsubscribe to this list{/tr}</td>
					<td>
						<input type="checkbox" name="allowUserSub" {if $info.allowUserSub eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Users can subscribe any email address{/tr}</td>
					<td>
						<input type="checkbox" name="allowAnySub" {if $info.allowAnySub eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Add unsubscribe instructions to each newsletter{/tr}</td>
					<td>
						<input type="checkbox" name="unsubMsg" {if $info.unsubMsg eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Validate email addresses{/tr}</td>
					<td>
						<input type="checkbox" name="validateAddr" {if $info.validateAddr eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Allow customized text message to be sent with the HTML version{/tr}</td>
					<td>
						<input type="checkbox" name="allowTxt" {if $info.allowTxt eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Allow clipping of articles into newsletter{/tr}</td>
					<td>
						<input type="checkbox" name="allowArticleClip" {if $info.allowArticleClip eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Automatically clip articles into newsletter{/tr}</td>
					<td>
						<input type="checkbox" name="autoArticleClip" {if $info.autoArticleClip eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Do not send newsletter if clip is empty{/tr}</td>
					<td>
						<input type="checkbox" name="emptyClipBlocksSend" {if $info.emptyClipBlocksSend eq 'y'}checked="checked"{/if}>
					</td>
				</tr>
				<tr>
					<td>{tr}Clip articles published in the past number of days{/tr}</td>
					<td>
						<input type="text" size="4" name="articleClipRangeDays" value="{$info.articleClipRangeDays|escape}">
					</td>
				</tr>
				<tr>
					<td>{tr}Article types to clip{/tr}</td>
					<td>
						<select name="articleClipTypes[]" size="5" multiple="multiple">
							{section name=type loop=$articleTypes}
								<option value="{$articleTypes[type]}" {if in_array($articleTypes[type], $info.articleClipTypes)}selected="selected"{/if}>{$articleTypes[type]|escape}</option>
							{/section}
						</select>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td>
						<input type="submit" class="btn btn-primary btn-sm" name="save" value="{tr}Save{/tr}">
					</td>
				</tr>
			</table>
		</form>
	{/tab}

{/tabset}
