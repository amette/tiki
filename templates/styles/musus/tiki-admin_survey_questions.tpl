<a class="pagetitle" href="tiki-admin_survey_questions.php?surveyId={$surveyId}">{tr}Edit survey questions{/tr}</a><br /><br />
<a class="linkbut" href="tiki-list_surveys.php">{tr}List surveys{/tr}</a>
<a class="linkbut" href="tiki-survey_stats.php">{tr}Survey statistics{/tr}</a>
<a class="linkbut" href="tiki-survey_stats_survey.php?surveyId={$surveyId}">{tr}This survey stats{/tr}</a>
<a class="linkbut" href="tiki-admin_surveys.php?surveyId={$surveyId}">{tr}Edit this survey{/tr}</a>
<a class="linkbut" href="tiki-admin_surveys.php">{tr}Admin surveys{/tr}</a><br /><br />
<h2>{tr}Create/edit questions for survey{/tr}: <a href="tiki-admin_survey.php?surveyId={$survey_info.surveyId}" class="pagetitle">{$survey_info.name}</a></h2>
<form action="tiki-admin_survey_questions.php" method="post">
<input type="hidden" name="surveyId" value="{$surveyId|escape}" />
<input type="hidden" name="questionId" value="{$questionId|escape}" />
<table>
<tr><td>{tr}Question{/tr}:</td><td><textarea name="question" rows="5" cols="40">{$info.question|escape}</textarea></td></tr>
<tr><td>{tr}Position{/tr}:</td><td><select name="position">{html_options values=$positions output=$positions selected=$info.position}</select></td></tr>
<tr><td>{tr}Type{/tr}:</td><td>
<select name="type">
<option value="c" {if $info.type eq 'c'}selected=selected{/if}>{tr}One choice{/tr}</option>
<option value="m" {if $info.type eq 'm'}selected=selected{/if}>{tr}Multiple choices{/tr}</option>
<option value="t" {if $info.type eq 't'}selected=selected{/if}>{tr}Short text{/tr}</option>
<option value="r" {if $info.type eq 'r'}selected=selected{/if}>{tr}Rate (1..5){/tr}</option>
<option value="s" {if $info.type eq 's'}selected=selected{/if}>{tr}Rate (1..10){/tr}</option>
</select></td></tr>
<tr><td>{tr}Options (if applicable){/tr}:</td><td><input type="text" name="options" value="{$info.options|escape}" /></td></tr>
<tr><td >&nbsp;</td><td><input type="submit" name="save" value="{tr}Save{/tr}" /></td></tr>
</table>
</form>
<h2>{tr}Questions{/tr}</h2>
<div align="center">
<table class="findtable">
<tr><td>{tr}Find{/tr}</td>
   <td>
   <form method="get" action="tiki-admin_survey_questions.php">
     <input type="text" name="find" value="{$find|escape}" />
     <input type="submit" value="{tr}find{/tr}" name="search" />
     <input type="hidden" name="sort_mode" value="{$sort_mode|escape}" />
     <input type="hidden" name="surveyId" value="{$surveyId|escape}" />
   </form>
   </td>
</tr>
</table>
<table>
<tr>
<th><a href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;offset={$offset}&amp;sort_mode={if $sort_mode eq 'questionId_desc'}questionId_asc{else}questionId_desc{/if}">{tr}ID{/tr}</a></th>
<th><a href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;offset={$offset}&amp;sort_mode={if $sort_mode eq 'position_desc'}position_asc{else}position_desc{/if}">{tr}position{/tr}</a></th>
<th><a href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;offset={$offset}&amp;sort_mode={if $sort_mode eq 'question_desc'}question_asc{else}question_desc{/if}">{tr}question{/tr}</a></th>
<th><a href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;offset={$offset}&amp;sort_mode={if $sort_mode eq 'type_desc'}type_asc{else}type_desc{/if}">{tr}type{/tr}</a></th>
<th><a href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;offset={$offset}&amp;sort_mode={if $sort_mode eq 'options_desc'}options_asc{else}options_desc{/if}">{tr}options{/tr}</a></th>
<th>{tr}action{/tr}</th>
</tr>
{cycle print=false values="odd,even}
{section name=user loop=$channels}
<tr>
<td class="{cycle advance=false}">{$channels[user].questionId}</td>
<td class="{cycle advance=false}">{$channels[user].position}</td>
<td class="{cycle advance=false}">{$channels[user].question}</td>
<td class="{cycle advance=false}">{$channels[user].type}</td>
<td class="{cycle advance=false}">{$channels[user].options}</td>
<td class="odd">
   <a href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;offset={$offset}&amp;sort_mode={$sort_mode}&amp;remove={$channels[user].questionId}">{tr}remove{/tr}</a>
   <a href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;offset={$offset}&amp;sort_mode={$sort_mode}&amp;questionId={$channels[user].questionId}">{tr}edit{/tr}</a>
</td>
</tr>
{/section}
</table>
<div class="mini">
{if $prev_offset >= 0}
[<a class="prevnext" href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;find={$find}&amp;offset={$prev_offset}&amp;sort_mode={$sort_mode}">{tr}prev{/tr}</a>]&nbsp;
{/if}
{tr}Page{/tr}: {$actual_page}/{$cant_pages}
{if $next_offset >= 0}
&nbsp;[<a class="prevnext" href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;find={$find}&amp;offset={$next_offset}&amp;sort_mode={$sort_mode}">{tr}next{/tr}</a>]
{/if}
{if $direct_pagination eq 'y'}
<br />
{section loop=$cant_pages name=foo}
{assign var=selector_offset value=$smarty.section.foo.index|times:$maxRecords}
<a class="prevnext" href="tiki-admin_survey_questions.php?surveyId={$surveyId}&amp;find={$find}&amp;offset={$selector_offset}&amp;sort_mode={$sort_mode}">
{$smarty.section.foo.index_next}</a>&nbsp;
{/section}
{/if}
</div>
</div>
