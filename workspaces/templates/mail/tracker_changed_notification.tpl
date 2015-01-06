{if $mail_action eq 'deleted'}
	{tr}ItemID {$mail_itemId} was deleted in the tracker {$mail_trackerName}{/tr}
{elseif $mail_action eq 'status'}
	{tr}New status ItemID {$mail_itemId} for the tracker {$mail_trackerName}:{/tr} {if $status eq 'o'}{tr}open{/tr}{elseif $status eq 'p'}{tr}pending{/tr}{elseif $status eq 'c'}{tr}closed{/tr}{/if}
{else}
	{$mail_action}
	{tr}View the tracker item at:{/tr} {$mail_machine_raw}/tiki-view_tracker_item.php?trackerId={$mail_trackerId}&offset=0&sort_mode=lastModif_desc&itemId={$mail_itemId}
{/if}

{tr}Author{/tr}: {$mail_user}
{tr}Date{/tr}: {$mail_date|tiki_short_datetime}

{$mail_data}
{* {$mail_data|replace:"\n\n":"\n"|replace:":\n":": "} to reduce the number of line *}

{if isset($mail_attId)}
	{tr}Download the file at:{/tr}  {$mail_machine_raw}/tiki-download_item_attachment.php?attId={$mail_attId}
{/if}