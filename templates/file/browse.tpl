{extends "layout_view.tpl"}

{block name="title"}
	{title}{$title}{/title}
{/block}

{block name="content"}
	<div class="row file-browser">
		<div class="col-md-9">
			<form class="form-inline no-ajax" method="get" action="{service controller=file action=list_gallery plain=1}">
				<div class="form-group">
					<label class="sr-only" for="search-field">{tr}Search{/tr}</label>
					<input class="form-control" name="search" type="search" id="search-field" placeholder="{tr}Search...{/tr}"/>
					<input type="hidden" name="galleryId" value="{$galleryId|escape}"/>
					<input type="hidden" name="type" value="{$typeFilter|escape}"/>
				</div>
				<button class="btn btn-default">Search</button>
			</form>
			<h4>{tr}Select files{/tr}</h4>
			<div class="gallery-list">
				{service_inline controller=file action=list_gallery galleryId=$galleryId plain=1 type=$typeFilter}
			</div>
		</div>
		<div class="col-md-3 selection hidden">
			<form method="post" action="{service controller=file action=browse galleryId=$galleryId}" data-gallery-id="{$galleryId|escape}" data-limit="{$limit|escape}">
				<h4>{tr}Current Selection{/tr}</h4>
				<ul class="nav nav-pills nav-stacked">
				</ul>
				<div class="help-block">
					{tr}Click to remove{/tr}
				</div>
				<div class="submit">
					<input type="submit" class="btn btn-primary" value="{tr}Select{/tr}">
				</div>
			</form>
		</div>
	</div>
{/block}