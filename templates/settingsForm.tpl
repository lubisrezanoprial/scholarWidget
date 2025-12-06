<script>
	$(function() {
		$('#scholarWidgetSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	});
</script>

<form class="pkp_form" id="scholarWidgetSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="scholarWidgetSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.scholarWidget.settings.description"}</div>

	{fbvFormArea id="scholarWidgetSettingsFormArea"}
		{fbvFormSection}
			{fbvElement type="text" id="scholarUserId" value=$scholarUserId label="plugins.generic.scholarWidget.settings.scholarUserId" required=true}
			<p class="description">
				Google Scholar User ID Anda (contoh: T2zmL94AAAAJ dari URL https://scholar.google.com/citations?user=T2zmL94AAAAJ)
			</p>
		{/fbvFormSection}

		{fbvFormSection}
			{fbvElement type="text" id="widgetTitle" value=$widgetTitle label="plugins.generic.scholarWidget.settings.widgetTitle"}
			<p class="description">
				Judul widget yang akan ditampilkan (default: "Statistik Sitasi")
			</p>
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.scholarWidget.settings.widgetPosition" list=true}
			{foreach from=$positionOptions key=optionValue item=optionLabel}
				{fbvElement type="radio" id="widgetPosition-$optionValue" name="widgetPosition" value=$optionValue checked=$widgetPosition|compare:$optionValue label=$optionLabel}
			{/foreach}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.scholarWidget.settings.displayOptions" list=true}
			{fbvElement type="checkbox" id="showHIndex" value="1" checked=$showHIndex label="plugins.generic.scholarWidget.settings.showHIndex"}
			{fbvElement type="checkbox" id="showI10Index" value="1" checked=$showI10Index label="plugins.generic.scholarWidget.settings.showI10Index"}
			{fbvElement type="checkbox" id="showCitationBar" value="1" checked=$showCitationBar label="plugins.generic.scholarWidget.settings.showCitationBar"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>

<style>
	#scholarWidgetSettingsForm .description {
		font-size: 0.9em;
		color: #666;
		margin-top: 5px;
	}
</style>