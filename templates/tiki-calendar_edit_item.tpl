{strip}
{title admpage="calendar"}{tr}Calendar Item{/tr}{/title}

<div class="navbar">
{if $tiki_p_view_calendar eq 'y'}
	{button href="tiki-calendar.php" _text="{tr}View Calendars{/tr}"}
{/if}
{if $tiki_p_admin_calendar eq 'y'}
	{button href="tiki-admin_calendars.php?calendarId=$calendarId" _text="{tr}Edit Calendar{/tr}"}
{/if}
{if $tiki_p_add_events eq 'y' and $id }
	{button href="tiki-calendar_edit_item.php" _text="{tr}New event{/tr}"}
{/if}
{if $id}
	{if $edit}
		{button href="tiki-calendar_edit_item.php?viewcalitemId=$id" _text="{tr}View event{/tr}"}
	{elseif $tiki_p_change_events eq 'y'}
		{button href="tiki-calendar_edit_item.php?calitemId=$id" _text="{tr}Edit event{/tr}"}
	{/if}
{/if}
{if $tiki_p_admin_calendar eq 'y'}
	{button href="tiki-admin_calendars.php" _text="{tr}Admin Calendars{/tr}"}
{/if}
</div>

<div class="wikitext">

{if $edit}
	{if $preview}
		<h2>{tr}Preview{/tr}</h2>
		{$calitem.parsedName}
		<div class="wikitext">{$calitem.parsed}</div>
		<h2>{if $id}{tr}Edit Calendar Item{/tr}{else}{tr}New Calendar Item{/tr}{/if}</h2>
	{/if}
	<form action="{$myurl}" method="post" name="f" id="editcalitem">
		<input type="hidden" name="save[user]" value="{$calitem.user}" />
		{if $id}
			<input type="hidden" name="save[calitemId]" value="{$id}" />
		{/if}
{/if}

<table class="normal{if !$edit} vevent{/if}">
<tr class="formcolor">
	<td>{tr}Calendar{/tr}</td>
	<td>{$listcals.$calendarId.name|escape}
	<input type="hidden" name="save[calendarId]" value="{$calendarId}" />
	{if !$id}<br />{tr}or{/tr}&nbsp;
		<input type="submit" name="act" value="{tr}Go to{/tr}" />
		<select name="save[calendarId]" id="calid" onchange="javascript:document.getElementById('editcalitem').submit();">
			{foreach item=it key=itid from=$listcals}
				<option value="{$it.calendarId}"{if $calendarId eq $itid} selected="selected"{/if}>{$it.name|escape}</option>
			{/foreach}
		</select>
	{elseif $edit and $tiki_p_add_events eq 'y'}
		&nbsp;<input type="submit" name="act" value="{tr}Duplicate to{/tr}" onclick="document.location='{$myurl}?calendarId='+document.getElementById('calid').value+'&amp;calitemId={$id}&amp;duplicate=1';return false;" />
		<select name="save[calendarId]" id="calid">
			{foreach item=it key=itid from=$listcals}
				<option value="{$it.calendarId}"{if $calendarId eq $itid} selected="selected"{/if}>{$it.name|escape}</option>
			{/foreach}
		</select>
	{/if}
	</td>
</tr>

<tr class="formcolor">
<td>{tr}Title{/tr}</td>
<td>
{if $edit}
	<input type="text" name="save[name]" value="{$calitem.name|escape}" size="32" style="width:90%;"/>
{else}
	<span class="summary">{$calitem.name|escape}</span>
{/if}
</td>
</tr>
<tr class="formcolor">
<td>{tr}Start{/tr}</td>
<td>
{if $edit}
	<table cellpadding="0" cellspacing="0" border="0" style="border:0;">
		<tr>
			<td style="border:0;padding-top:2px;vertical-align:middle">
			{if $prefs.feature_jscalendar neq 'y' or $prefs.javascript_enabled neq 'y'}
				<a href="#" onclick="document.f.Time_Hour.selectedIndex=(document.f.Time_Hour.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a>
			{/if}
			</td>
			<td rowspan="2" style="border:0;padding-top:2px;vertical-align:middle">
			{if $prefs.feature_jscalendar eq 'y' and $prefs.javascript_enabled eq 'y'}
				{jscalendar id="start" date=$calitem.start fieldname="save[date_start]" align="Bc" showtime='n'}
			{else}
				{html_select_date prefix="start_date_" time=$calitem.start field_order=$prefs.display_field_order start_year=$prefs.calendar_start_year end_year=$prefs.calendar_end_year}
			{/if}
			</td>
			<td style="border:0;padding-top:2px;vertical-align:middle">
				<span id="starttimehourplus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.start_Hour.selectedIndex=(document.f.start_Hour.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a></span>
			</td>
			<td rowspan="2" style="border:0;vertical-align:middle" class="html_select_time">
				<span id="starttime" style="display: {if $calitem.allday} none {else} inline {/if}">{html_select_time prefix="start_" display_seconds=false time=$calitem.start minute_interval=$prefs.calendar_timespan hour_minmax=$hour_minmax}</span>
			</td>
			<td style="border:0;padding-top:2px;vertical-align:middle">
				<span id="starttimeminplus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.start_Minute.selectedIndex=(document.f.start_Minute.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a></span>
			</td>
			<td style="border:0;vertical-align:middle;" rowspan="2">
				<input type="checkbox" name="allday" 
					   onclick="toggleSpan('starttimehourplus');
					   			toggleSpan('starttimehourminus');
					   			toggleSpan('starttime');
					   			toggleSpan('starttimeminplus');
					   			toggleSpan('starttimeminminus');
					   			toggleSpan('endtimehourplus');
					   			toggleSpan('endtimehourminus');
					   			toggleSpan('endtime');
					   			toggleSpan('endtimeminplus');
					   			toggleSpan('endtimeminminus');
					   			toggleSpan('durhourplus');
					   			toggleSpan('durhourminus');
					   			toggleSpan('duration');
					   			toggleSpan('duratione');
					   			toggleSpan('durminplus');
					   			toggleSpan('durminminus');"
					   value="true" {if $calitem.allday} checked="checked" {/if}> All-Day</input>
			</td>
		</tr>
		<tr>
			<td style="border:0;vertical-align:middle">
			{if $prefs.feature_jscalendar neq 'y' or $prefs.javascript_enabled neq 'y'}
				<a href="#" onclick="document.f.Time_Hour.selectedIndex=(document.f.Time_Hour.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a>
			{/if}
			</td>
			<td style="border:0;vertical-align:middle">
				<span id="starttimehourminus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.start_Hour.selectedIndex=(document.f.start_Hour.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a>
			</td>
			<td style="border:0;vertical-align:middle">
				<span id="starttimeminminus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.start_Minute.selectedIndex=(document.f.start_Minute.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a>
			</td>
		</tr>
	</table>
{else}
    {if $calitem.allday}
	    <abbr class="dtstart" title="{$calitem.start|tiki_short_date}">{$calitem.start|tiki_long_date}</abbr>
    {else}
        <abbr class="dtstart" title="{$calitem.start|isodate}">{$calitem.start|tiki_long_datetime}</abbr>
    {/if}
{/if}
</td>
</tr>
<tr class="formcolor">
	<td>{tr}End{/tr}</td><td>
	{if $edit}
		<input type="hidden" name="save[end_or_duration]" value="end" id="end_or_duration" />
		<table cellpadding="0" cellspacing="0" border="0" style="border:0;display:block;" id="end_date">
		<tr>
			<td style="border:0;padding-top:2px;vertical-align:middle">
			{if $prefs.feature_jscalendar neq 'y' or $prefs.javascript_enabled neq 'y'}
				<span id="endtimehourplus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.Time_Hour.selectedIndex=(document.f.Time_Hour.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a></span>
			{/if}
			</td>
			<td rowspan="2" style="border:0;vertical-align:middle">
			{if $prefs.feature_jscalendar eq 'y' and $prefs.javascript_enabled eq 'y'}
				{jscalendar id="end" date=$calitem.end fieldname="save[date_end]" align="Bc" showtime='n'}
			{else}
				{html_select_date prefix="end_date_" time=$calitem.end field_order=$prefs.display_field_order  start_year=$prefs.calendar_start_year end_year=$prefs.calendar_end_year}
			{/if}
			</td>
			<td style="border:0;padding-top:2px;vertical-align:middle">
				<span id="endtimehourplus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.end_Hour.selectedIndex=(document.f.end_Hour.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a></span>
			</td>
			<td rowspan="2" style="border:0;vertical-align:middle" class="html_select_time">
				<span id="endtime" style="display: {if $calitem.allday} none {else} inline {/if}">{html_select_time prefix="end_" display_seconds=false time=$calitem.end minute_interval=$prefs.calendar_timespan hour_minmax=$hour_minmax}</span>
			</td>
			<td style="border:0;padding-top:2px;vertical-align:middle">
				<span id="endtimeminplus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.end_Minute.selectedIndex=(document.f.end_Minute.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a></span>
			</td>
			<td rowspan="2" style="border:0;padding-top:2px;vertical-align:middle">
				<span id="duration" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.getElementById('end_or_duration').value='duration';flip('end_duration');flip('end_date');return false;">{tr}Duration{/tr}</a></span>
			</td>
		</tr>
		<tr>
		<td style="border:0;vertical-align:middle">
		{if $prefs.feature_jscalendar neq 'y' or $prefs.javascript_enabled neq 'y'}
			<a href="#" onclick="document.f.Time_Hour.selectedIndex=(document.f.Time_Hour.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a>
		{/if}
		</td>
		<td style="border:0;vertical-align:middle">
			<span id="endtimehourminus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.end_Hour.selectedIndex=(document.f.end_Hour.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a></span>
		</td>
		<td style="border:0;vertical-align:middle">
			<span id="endtimeminminus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.end_Minute.selectedIndex=(document.f.end_Minute.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a></span>
		</td>
	</tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" style="border:0;display:none;" id="end_duration">
	<tr>
		<td style="border:0;padding-top:2px;vertical-align:middle">
			<span id="durhourplus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.duration_Hour.selectedIndex=(document.f.duration_Hour.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a></span>
		</td>
		<td style="border:0;vertical-align:middle" rowspan="2" class="html_select_time">
			<span id="duratione" style="display: {if $calitem.allday} none {else} inline {/if}">{html_select_time prefix="duration_" display_seconds=false time=$calitem.duration|default:'01:00' minute_interval=$prefs.calendar_timespan}</span>
		</td>
		<td style="border:0;padding-top:2px;vertical-align:middle">
			<span id="durminplus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.duration_Minute.selectedIndex=(document.f.duration_Minute.selectedIndex+1);">{icon _id='plus_small' align='left' width='11' height='8'}</a></span>
		</td>
		<td rowspan="2" style="border:0;padding-top:2px;vertical-align:middle">
			<a href="#" onclick="document.getElementById('end_or_duration').value='end';flip('end_date');flip('end_duration');return false;">{tr}Date and time of end{/tr}</a>
		</td>
	</tr>
	<tr>
		<td style="border:0;vertical-align:middle">
			<span id="durhourminus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.duration_Hour.selectedIndex=(document.f.duration_Hour.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a></span>
		</td>
		<td style="border:0;vertical-align:middle">
			<span id="durminminus" style="display: {if $calitem.allday} none {else} inline {/if}"><a href="#" onclick="document.f.duration_Minute.selectedIndex=(document.f.duration_Minute.selectedIndex-1);">{icon _id='minus_small' align='left' width='11' height='8'}</a></span>
		</td>
	</tr>
</table>
{else}
    {if $calitem.allday}
        {if $calitem.end}<abbr class="dtend" title="{$calitem.end|tiki_short_date}">{/if}{$calitem.end|tiki_long_date}{if $calitem.end}</abbr>{/if}
    {else}
        {if $calitem.end}<abbr class="dtend" title="{$calitem.end|isodate}">{/if}{$calitem.end|tiki_long_datetime}{if $calitem.end}</abbr>{/if}
    {/if}
{/if}
</td>
</tr>
<tr class="formcolor">
<td>{tr}Description{/tr}
{if $edit}
  <br /><br />
  {include file="textareasize.tpl" area_name="editwiki" formId="editcalitem"}<br /><br />
  {if $prefs.quicktags_over_textarea neq 'y'}
    {include file="tiki-edit_help_tool.tpl" area_name="save[description]"}
  {/if}
{/if}

</td><td>
{if $edit}
  {if $prefs.quicktags_over_textarea eq 'y'}
    {include file="tiki-edit_help_tool.tpl" area_name="save[description]"}
  {/if}
  <textarea id='editwiki' class="wikiedit" cols="{$cols}" rows="{$rows}" name="save[description]" style="width:98%">{$calitem.description}</textarea>
  <input type="hidden" name="rows" value="{$rows}"/>
  <input type="hidden" name="cols" value="{$cols}"/>
{else}
  <span class="description">{$calitem.parsed|default:"<i>No description</i>"}</span>
{/if}
</td></tr>

{if $calendar.customstatus ne 'n'}
<tr class="formcolor"><td>{tr}Status{/tr}</td><td>

<div class="statusbox{if $calitem.status eq 0} status0{/if}">
{if $edit}
<input id="status0" type="radio" name="save[status]" value="0"{if (!empty($calitem) and $calitem.status eq 0) or (empty($calitem) and $calendar.defaulteventstatus eq 0)} checked="checked"{/if} />
<label for="status0">{tr}Tentative{/tr}</label>
{else}
{tr}Tentative{/tr}
{/if}
</div>
<div class="statusbox{if $calitem.status eq 1} status1{/if}">
{if $edit}
<input id="status1" type="radio" name="save[status]" value="1"{if $calitem.status eq 1} checked="checked"{/if} />
<label for="status1">{tr}Confirmed{/tr}</label>
{else}
{tr}Confirmed{/tr}
{/if}
</div>
<div class="statusbox{if $calitem.status eq 2} status2{/if}">
{if $edit}
<input id="status2" type="radio" name="save[status]" value="2"{if $calitem.status eq 2} checked="checked"{/if} />
<label for="status2">{tr}Cancelled{/tr}</label>
{else}
{tr}Cancelled{/tr}
{/if}
</div>
</td></tr>
{/if}

{if $calendar.custompriorities eq 'y'}
<tr class="formcolor"><td>
{tr}Priority{/tr}</td><td>
{if $edit}
<select name="save[priority]" style="background-color:#{$listprioritycolors[$calitem.priority]};font-size:150%;width:40%;"
onchange="this.style.bacgroundColor='#'+this.selectedIndex.value;">
{foreach item=it from=$listpriorities}
<option value="{$it}" style="background-color:#{$listprioritycolors[$it]};"{if $calitem.priority eq $it} selected="selected"{/if}>{$it}</option>
{/foreach}
</select>
{else}
<span style="background-color:#{$listprioritycolors[$calitem.priority]};font-size:150%;width:90%;padding:1px 4px">{$calitem.priority}</span>
{/if}

</td></tr>
{/if}
<tr class="formcolor" style="display:{if $calendar.customcategories eq 'y'}tablerow{else}none{/if};" id="calcat">
<td>{tr}Category{/tr}</td>
<td>
{if $edit}
{if count($listcats)}
<select name="save[categoryId]">
<option value=""></option>
{foreach item=it from=$listcats}
<option value="{$it.categoryId}"{if $calitem.categoryId eq $it.categoryId} selected="selected"{/if}>{$it.name}</option>
{/foreach}
</select>
{tr}or new{/tr} {/if}
<input type="text" name="save[newcat]" value="" />
{else}
<span class="category">{$calitem.categoryName|escape}</span>
{/if}
</td>
</tr>
<tr class="formcolor" style="display:{if $calendar.customlocations eq 'y'}tablerow{else}none{/if};" id="calloc">
<td>{tr}Location{/tr}</td>
<td>
{if $edit}
{if count($listlocs)}
<select name="save[locationId]">
<option value=""></option>
{foreach item=it from=$listlocs}
<option value="{$it.locationId}"{if $calitem.locationId eq $it.locationId} selected="selected"{/if}>{$it.name}</option>
{/foreach}
</select>
{tr}or new{/tr} {/if}
<input type="text" name="save[newloc]" value="" />
{else}
<span class="location">{$calitem.locationName|escape}</span>
{/if}
</td>
</tr>
<tr class="formcolor">
<td>{tr}URL{/tr}</td>
<td>
{if $edit}
<input type="text" name="save[url]" value="{$calitem.url}" size="32" style="width:90%;" />
{else}
<a class="url" href="{$calitem.url}">{$calitem.url|escape}</a>
{/if}
</td>
</tr>
<tr class="formcolor" style="display:{if $calendar.customlanguages eq 'y'}tablerow{else}none{/if};" id="callang">
<td>{tr}Language{/tr}</td>
<td>
{if $edit}
<select name="save[lang]">
<option value=""></option>
{foreach item=it from=$listlanguages}
<option value="{$it.value}"{if $calitem.lang eq $it.value} selected="selected"{/if}>{$it.name}</option>
{/foreach}
</select>
{else}
{$calitem.lang}
{/if}
</td>
</tr>

{if $groupforalert ne ''}
{if $showeachuser eq 'y' }
<tr class="formcolor">
<td>{tr}Choose users to alert{/tr}</td>
<td>
{/if}
{section name=idx loop=$listusertoalert}
{if $showeachuser eq 'n' }
<input type="hidden"  name="listtoalert[]" value="{$listusertoalert[idx].user}">
{else}
<input type="checkbox" name="listtoalert[]" value="{$listusertoalert[idx].user}"> {$listusertoalert[idx].user}
{/if}
{/section}
</td>
</tr>
{/if}


{if $calendar.customparticipants eq 'y'}
	<tr class="formcolor"><td colspan="2">&nbsp;</td></tr>
{/if}

<tr class="formcolor" style="display:{if $calendar.customparticipants eq 'y'}tablerow{else}none{/if};" id="calorg">
<td>{tr}Organized by{/tr}</td>
<td>
{if $edit}
<input type="text" name="save[organizers]" value="{foreach item=org from=$calitem.organizers}{$org}, {/foreach}" style="width:90%;" />
{else}
{foreach item=org from=$calitem.organizers}
{$org|escape}<br />
{/foreach}
{/if}
</td>
</tr>

<tr class="formcolor" style="display:{if $calendar.customparticipants eq 'y'}tablerow{else}none{/if};" id="calpart">
<td>{tr}Participants{/tr}
{if $edit}
<a href="#" onclick="flip('calparthelp');">{icon _id='help'}</a>
{/if}
</td>
<td>
{if $edit}
<input type="text" name="save[participants]" value="{foreach item=ppl from=$calitem.participants}{if $ppl.role}{$ppl.role}:{/if}{$ppl.name}, {/foreach}" style="width:90%;" />
{else}
{foreach item=ppl from=$calitem.participants}
{$ppl.name|escape} {if $listroles[$ppl.role]}({$listroles[$ppl.role]}){/if}<br />
{/foreach}
{/if}
</td>
</tr>




<tr><td colspan="2">
{if $edit}
<div style="display:{if $calendar.customparticipants eq 'y' and (isset($cookie.show_calparthelp) and $cookie.show_calparthelp eq 'y')}block{else}none{/if};" id="calparthelp">
{tr}Roles{/tr}<br />
0: {tr}chair{/tr} ({tr}default role{/tr})<br />
1: {tr}required participant{/tr}<br />
2: {tr}optional participant{/tr}<br />
3: {tr}non participant{/tr}<br />
<br />
{tr}Give participant list separated by commas. Roles have to be given in a prefix separated by a column like in:{/tr}
<tt>{tr}role:login_or_email,login_or_email{/tr}</tt>
<br />
{tr}If no role is provided, default role will be "Chair participant".{/tr}
{/if}
</div>

</td></tr>


</table>

{if $edit}
<table class="normal">
<tr><td><input type="submit" name="preview" value="{tr}Preview{/tr}" /></td></tr>
<tr><td><input type="submit" name="act" value="{tr}Save{/tr}" />
&nbsp;{tr}in{/tr}&nbsp;
<span class="button2" style="{if $listcals.$calendarId.custombgcolor ne ''}background-color:#{$listcals.$calendarId.custombgcolor};{/if}{if $listcals.$calendarId.customfgcolor ne ''}color:#{$listcals.$calendarId.customfgcolor};{/if}">{$listcals.$calendarId.name}</span>
{if $id}&nbsp;<input type="submit" onclick='document.location="tiki-calendar_edit_item.php?calitemId={$id}&amp;delete=y";return false;' value="{tr}Delete Item{/tr}"/>{/if}
</td></tr>
</table>
{/if}
</form>
</div>
{/strip}
