

window.uesppatOnShowTierSubmit = function() {
	
	$("#uesppat_showiron_hidden").prop("checked", !$("#uesppat_showiron").is(":checked"));
	$("#uesppat_showsteel_hidden").prop("checked", !$("#uesppat_showsteel").is(":checked"));
	$("#uesppat_showelven_hidden").prop("checked", !$("#uesppat_showelven").is(":checked"));
	$("#uesppat_showorcish_hidden").prop("checked", !$("#uesppat_showorcish").is(":checked"));
	$("#uesppat_showglass_hidden").prop("checked", !$("#uesppat_showglass").is(":checked"));
	$("#uesppat_showdaedric_hidden").prop("checked", !$("#uesppat_showdaedric").is(":checked"));
	$("#uesppat_showother_hidden").prop("checked", !$("#uesppat_showother").is(":checked"));
	$("#uesppat_showactive_hidden").val($("#uesppat_showactive").is(":checked") ? "1" : "0");
	$("#uesppat_showinactive_hidden").val($("#uesppat_showinactive").is(":checked") ? "1" : "0");
	$("#uesppat_validaddress_hidden").val($("#uesppat_validaddress").is(":checked") ? "1" : "0");
	
	//console.log("uesppatOnShowTierSubmit", $("#uesppat_showiron_hidden").is(":checked"), $("#uesppat_showiron").is(":checked"));
	return true;
}

window.uesppatOnEditTagsSubmit = function() {
	
	$("#uesppatSaveTagsForm input[name='deletetagcheck[]']").each(function() {
		var $this = $(this);
		
		if(this.checked)
		{
			$this.prev().val('1');
		}
		else
		{
			$this.prev().val('0');
		}
	});
	
	$("#uesppatSaveTagsForm input[name='modifytagcheck[]']").each(function() {
		var $this = $(this);
		
		if(this.checked)
		{
			$this.prev().val('1');
		}
		else
		{
			$this.prev().val('0');
		}
	});
	
	return true;
}


window.uesppatOnPatronTableHeaderCheckbox = function() {
	var isChecked = $("#uesppatPatronTableHeaderCheckbox").is(":checked");
	
	$(".uesppatPatronRowCheckbox").prop("checked", isChecked);
	
	return true;
}

window.uesppatOnPatronShipmentHeaderCheckbox = function() {
	var isChecked = $("#uesppatShipmentTableHeaderCheckbox").is(":checked");
	
	$(".uesppatShipmentRowCheckbox").prop("checked", isChecked);
	
	return true;
}


window.uesppatOnCreateShipmentButton = function() {
	var checkedBoxes = $(".uesppatPatronRowCheckbox:checked");
	if (checkedBoxes.length == 0) return;
	
	$("#uesppatPatronTableAction").val("createship");
	$("#uesppatPatronTableForm").submit();
}


window.uesppatOnCreateCustomShipmentButton = function() {
	var checkedBoxes = $(".uesppatPatronRowCheckbox:checked");
	if (checkedBoxes.length == 0) return;
	
	$("#uesppatPatronTableAction").val("createcustomship");
	$("#uesppatPatronTableForm").submit();
}


window.uesppatOnEmailButton = function()
{
	var checkedBoxes = $(".uesppatPatronRowCheckbox:checked");
	if (checkedBoxes.length == 0) return;
	
	$("#uesppatPatronTableAction").val("createemail");
	$("#uesppatPatronTableForm").submit();
}


window.uesppatOnEditTagsButton = function()
{
	var checkedBoxes = $(".uesppatPatronRowCheckbox:checked");
	if (checkedBoxes.length == 0) return;
	
	$("#uesppatPatronTableAction").val("edittags");
	$("#uesppatPatronTableForm").submit();
}


window.uesppatOnExportEmailButton = function()
{
	var checkedBoxes = $(".uesppatPatronRowCheckbox:checked");
	if (checkedBoxes.length == 0) return;
	
	$("#uesppatPatronTableAction").val("exportemail");
	$("#uesppatPatronTableForm").submit();
}


window.uesppatOnExportCsvShipmentButton = function()
{
	var checkedBoxes = $(".uesppatShipmentRowCheckbox:checked");
	if (checkedBoxes.length == 0) return;
	
	$("#uesppatShipmentTableAction").val("exportshipment");
	$("#uesppatShipmentTableForm").submit();
}


window.uesppatOnUpdateCustomShipmentRewardValueButton = function()
{
	var rows = $("#uesppatCreateShipments tr");
	var rewardValue = parseFloat($("#uesppatCustomShipRewardValue").val().replace(/[^0-9.]/g, '')).toFixed(2);
	var adjustYearly = $("#uesppatCustomShipAdjustValues").is(":checked");
	var yearlyValue = (rewardValue * 0.90).toFixed(2);
	
	rows.each(function(){
		var $this = $(this);
		var reward = $this.find("td").last();
		var cadence = parseInt($this.find("td").eq(-3).text());
		var isYearly = (cadence > 1);
		
		if (isYearly && adjustYearly)
		{
			reward.text("$" + yearlyValue);
		}
		else
		{
			reward.text("$" + rewardValue);
		}
	});
}


window.uesppatOnUpdateCustomShipmentSkuButton = function()
{
	var rows = $("#uesppatCreateShipments tr");
	var skuValue = $("#uesppatCustomShipSku").val().trim();
	
	rows.each(function(){
		var $this = $(this);
		var sku = $this.find("td").eq(5);
		
		sku.text(skuValue);
	});
}


window.uesppatEditWallElement = null;
window.uesppatEditShipBox = null;
window.uesppatEditShipId = -1;


window.uesppatCreateEditWall = function() {
	uesppatEditWallElement = $("<div />").attr("id", "uesppatEditWall")
									.appendTo("body")
									.hide()
									.on("click", uespPatOnEditWallClick);
}


window.uespPatOnEditWallClick = function() {
	uesppatHideEditWall();
}

window.uesppatShowEditWall = function() {
	if (uesppatEditWallElement == null) uesppatCreateEditWall();

	uesppatEditWallElement.show();
}


window.uesppatHideEditWall = function() {
	uesppatEditWallElement.hide();
	
	if (uesppatEditShipBox.is(":visible")) {
		uesppatEditShipBox.hide();
	}
}


window.uesppatShowEditShipment = function(shipmentId, rowElement) {
	if (uesppatEditShipBox == null) uesppatCreateEditShipment();
	
	uesppatSetEditShipmentValues(shipmentId, rowElement);
	
	uesppatEditShipBox.show();
	uespPatOnEditShipUpdateDeminisValue();
}


window.uesppatSetEditShipmentValues = function(shipmentId, rowElement) {
	var cols = rowElement.children("td");
	
	uesppatEditShipId = shipmentId;
	
	var name = cols.eq(1).text();
	var tier = cols.eq(2).text();
	var status = cols.eq(3).text();
	var orderNumber = cols.eq(4).text();
	var orderSku = cols.eq(5).text();
	var orderQnt = cols.eq(6).text();
	var shipMethod = cols.eq(7).text();
	var addressName = cols.eq(8).text();
	var addressLine1 = cols.eq(9).text();
	var addressLine2 = cols.eq(10).text();
	var addressCity = cols.eq(11).text();
	var addressState = cols.eq(12).text();
	var addressZip = cols.eq(13).text();
	var addressCountry = cols.eq(14).text();
	var email = cols.eq(15).text();
	var addressPhone = cols.eq(16).text();
	var pledgeCadence = cols.eq(17).text();
	var shippingValue = cols.eq(18).text();
	var rewardValue = cols.eq(19).text();
	
	uesppatEditShipBox.children("#uesppatEditShipTitle").text("Editing Shipment #" + shipmentId);
	uesppatEditShipBox.children("#uesppatEditShipName").val(name);
	uesppatEditShipBox.children("#uesppatEditShipTier").val(tier);
	uesppatEditShipBox.children("#uesppatEditShipStatus").val(status);
	uesppatEditShipBox.children("#uesppatEditShipCadence").val(pledgeCadence);
	uesppatEditShipBox.children("#uesppatEditShipOrderNumber").val(orderNumber);
	uesppatEditShipBox.children("#uesppatEditShipOrderSku").val(orderSku);
	uesppatEditShipBox.children("#uesppatEditShipOrderQnt").val(orderQnt);
	uesppatEditShipBox.children("#uesppatEditShipMethod").val(shipMethod);
	uesppatEditShipBox.children("#uesppatEditShipAddressName").val(addressName);
	uesppatEditShipBox.children("#uesppatEditShipAddressLine1").val(addressLine1);
	uesppatEditShipBox.children("#uesppatEditShipAddressLine2").val(addressLine2);
	uesppatEditShipBox.children("#uesppatEditShipAddressCity").val(addressCity);
	uesppatEditShipBox.children("#uesppatEditShipAddressState").val(addressState);
	uesppatEditShipBox.children("#uesppatEditShipAddressZip").val(addressZip);
	uesppatEditShipBox.children("#uesppatEditShipAddressCountry").val(addressCountry);
	uesppatEditShipBox.children("#uesppatEditShipEmail").val(email);
	uesppatEditShipBox.children("#uesppatEditShipAddressPhone").val(addressPhone);
	uesppatEditShipBox.children("#uesppatEditShipRewardValue").val(rewardValue);
	uesppatEditShipBox.children("#uesppatEditShipAddressCountryCode").text("");
	uesppatEditShipBox.children("#uesppatEditShipValue").val(shippingValue);
	
	var rewardList = $("#uespPatEditShipRewardList");
	rewardList.children("option").remove();
	
	if (window.g_uesppatTierValues && window.g_uesppatYearlyTierValues)
	{
		var tierValues = g_uesppatTierValues;
		if (pledgeCadence == 12) tierValues = g_uesppatYearlyTierValues;
		
		if (tierValues[tier])
		{
			for (var i in tierValues[tier])
			{
				var value = tierValues[tier][i];
				
				value = "$" + (value / 100).toFixed(2);
				rewardList.append("<option>" + value + "</option>");
			}
		}
	}
	
	if (!uesppatIsValidCountryCode(addressCountry))
	{
		var country = uesppatFindCountryCode(addressCountry);
		
		if (country)
			uesppatEditShipBox.children("#uesppatEditShipAddressCountryCode").text(country);
		else
			uesppatEditShipBox.children("#uesppatEditShipAddressCountryCode").text("BAD");
	}
	
	if (status == "Custom" || status == "custom")
	{
		$("#uesppatEditShipName").attr("readonly", false);
		$("#uesppatEditShipTier").attr("readonly", false);
		$("#uesppatEditShipTierButton").attr("disabled", false);
	}
	else
	{
		$("#uesppatEditShipName").attr("readonly", true);
		$("#uesppatEditShipTier").attr("readonly", true);
		$("#uesppatEditShipTierButton").attr("disabled", true);
	}
}


window.uesppatOnUpdateShipmentOrderNumberRequest = function(data)
{
	if (data && data.orderNumber) $('#uesppatEditShipOrderNumber').val(data.orderNumber);
	if (data && data.orderSku) $('#uesppatEditShipOrderSku').val(data.orderSku);
}


window.uespPatOnUpdateShipmentTierButton = function()
{
	var tier = $("#uesppatEditShipTier").val();
	var url = "/wiki/Special:UespPatreon/getordernumber?tier=" + tier;
	
	$.ajax({
		url: url,
	}).done(uesppatOnUpdateShipmentOrderNumberRequest);
}


window.uesppatGetEditShipmentValues = function() {
	if (uesppatEditShipId <= 0) return false;
	
	var rowElement = $("#uesppatCreateShipments tr[shipmentid='" + uesppatEditShipId + "']");
	if (rowElement.length == 0) return false;
	
	var cols = rowElement.children("td");
	
	var orderNumber = uesppatEditShipBox.children("#uesppatEditShipOrderNumber").val();
	var orderSku = uesppatEditShipBox.children("#uesppatEditShipOrderSku").val();
	var orderQnt = uesppatEditShipBox.children("#uesppatEditShipOrderQnt").val();
	var shipMethod = uesppatEditShipBox.children("#uesppatEditShipMethod").val();
	var addressName = uesppatEditShipBox.children("#uesppatEditShipAddressName").val();
	var addressLine1 = uesppatEditShipBox.children("#uesppatEditShipAddressLine1").val();
	var addressLine2 = uesppatEditShipBox.children("#uesppatEditShipAddressLine2").val();
	var addressCity = uesppatEditShipBox.children("#uesppatEditShipAddressCity").val();
	var addressState = uesppatEditShipBox.children("#uesppatEditShipAddressState").val();
	var addressZip = uesppatEditShipBox.children("#uesppatEditShipAddressZip").val();
	var addressCountry = uesppatEditShipBox.children("#uesppatEditShipAddressCountry").val();
	var email = uesppatEditShipBox.children("#uesppatEditShipEmail").val();
	var addressPhone = uesppatEditShipBox.children("#uesppatEditShipAddressPhone").val();
	var rewardValue = uesppatEditShipBox.children("#uesppatEditShipRewardValue").val();
	var shippingValue = uesppatEditShipBox.children("#uesppatEditShipValue").val();
	
	rewardValue = "$" + parseFloat(rewardValue.replace(/[^0-9.]/g, '')).toFixed(2);
	shippingValue = "$" + parseFloat(shippingValue.replace(/[^0-9.]/g, '')).toFixed(2);
	
	cols.eq(4).text(orderNumber);
	cols.eq(5).text(orderSku);
	cols.eq(6).text(orderQnt);
	cols.eq(7).text(shipMethod);
	cols.eq(8).text(addressName);
	cols.eq(9).text(addressLine1);
	cols.eq(10).text(addressLine2);
	cols.eq(11).text(addressCity);
	cols.eq(12).text(addressState);
	cols.eq(13).text(addressZip);
	cols.eq(14).text(addressCountry);
	cols.eq(15).text(email);
	cols.eq(16).text(addressPhone);
	cols.eq(18).text(shippingValue);
	cols.eq(19).text(rewardValue);
	
	uesppatUpdateEditShipmentBadStatus(uesppatEditShipId);
	
	return true;
}


window.g_uesppatShipmentBadMessages = {};


window.uesppatUpdateEditShipmentBadStatus = function(shipmentId) 
{
	var rowElement = $("#uesppatCreateShipments tr[shipmentid='" + shipmentId + "']");
	if (rowElement.length == 0) return false;
	
	uesppatUpdateEditShipmentBadStatusRow(rowElement);
}


window.uesppatUpdateEditShipmentBadStatusRow = function(rowElement)
{
	var shipmentId = rowElement.attr("shipmentid");
	var cols = rowElement.children("td");
	var isCustom = rowElement.attr("iscustom");
	
	if (isCustom == null) isCustom = "0";
	isCustom = parseInt(isCustom);
	
	var orderNumber = uesppatEditShipBox.children("#uesppatEditShipOrderNumber").val();
	var orderSku = uesppatEditShipBox.children("#uesppatEditShipOrderSku").val();
	var orderQnt = uesppatEditShipBox.children("#uesppatEditShipOrderQnt").val();
	var addressName = uesppatEditShipBox.children("#uesppatEditShipAddressName").val();
	var addressLine1 = uesppatEditShipBox.children("#uesppatEditShipAddressLine1").val();
	var addressLine2 = uesppatEditShipBox.children("#uesppatEditShipAddressLine2").val();
	var addressCountry = uesppatEditShipBox.children("#uesppatEditShipAddressCountry").val();
	
	var isBad = false;
	var badMessages = [];
	
	if (orderNumber == "" && !isCustom) {
		isBad = true;
		badMessages.push("Missing order number!");
	} 
	
	if (orderSku == "" && !isCustom) {
		isBad = true;
		badMessages.push("Missing order SKU!");
	}
	
	if (addressName == "") {
		isBad = true;
		badMessages.push("Missing name!");
	}
	
	if (addressLine1 == "" && addressLine2 == "") {
		isBad = true;
		badMessages.push("Missing address Line 1/2!");
	}
	if (addressCountry == "" || !uesppatIsValidCountryCode(addressCountry)) {
		isBad = true;
		badMessages.push("Missing or invalid address country!");
	}
	
	if (isBad) 
		rowElement.addClass("uesppatBadShipment");
	else
		rowElement.removeClass("uesppatBadShipment");
	
	g_uesppatShipmentBadMessages[shipmentId] = badMessages;
	rowElement.attr("title", badMessages.join("\n"));
}


window.uesppatHideEditShipment = function() {
	uesppatEditShipBox.hide();
	uesppatHideEditWall();
	uesppatEditShipId = -1;
}

window.uesppatCreateShipMethodDataList = function(id) {
	var datalist = "<datalist id='" + id + "'>";
	
	for (var i in g_uesppatShippingMethods)
	{
		var method = g_uesppatShippingMethods[i];
		datalist += "<option value='" + method + "'>";
	}
	
	datalist += "</datalist>";
	return datalist;
}


window.uesppatCreateEditShipment = function() {
	var datalist = uesppatCreateShipMethodDataList('uesppatShipMethodDataList');
	var html = "\
<div style='display:none;' id='uesppatEditShipEditBox'>\
	<div id='uesppatEditShipTitle'>Editing Shipment #</div>\
	<div class='uesppatEditShipLabel'>Name</div><input type='text' id='uesppatEditShipName' readonly>\
	<div class='uesppatEditShipLabel'>Tier</div><input type='text' id='uesppatEditShipTier' readonly>\
	<button type='button' id='uesppatEditShipTierButton' disabled onclick='uespPatOnUpdateShipmentTierButton();'>Update</button>\
	<div class='uesppatEditShipLabel'>Status</div><input type='text' id='uesppatEditShipStatus' readonly>\
	<div class='uesppatEditShipLabel'>Cadence</div><input type='text' id='uesppatEditShipCadence' readonly>\
	<div class='uesppatEditShipLabel'>Order #</div><input type='text' id='uesppatEditShipOrderNumber' >\
	<div class='uesppatEditShipLabel'>SKU</div><input type='text' id='uesppatEditShipOrderSku' >\
	<div class='uesppatEditShipLabel'>Qnt</div><input type='text' id='uesppatEditShipOrderQnt' >\
	<div class='uesppatEditShipLabel'>Ship Method</div><input type='text' id='uesppatEditShipMethod' list='uesppatShipMethodDataList'>\
			" + datalist + "\
	<div class='uesppatEditShipLabel'>Addressee</div><input type='text' id='uesppatEditShipAddressName' >\
	<div class='uesppatEditShipLabel'>Line 1</div><input type='text' id='uesppatEditShipAddressLine1' >\
	<div class='uesppatEditShipLabel'>Line 2</div><input type='text' id='uesppatEditShipAddressLine2' >\
	<div class='uesppatEditShipLabel'>City</div><input type='text' id='uesppatEditShipAddressCity' >\
	<div class='uesppatEditShipLabel'>State</div><input type='text' id='uesppatEditShipAddressState' >\
	<div class='uesppatEditShipLabel'>Postal Code</div><input type='text' id='uesppatEditShipAddressZip' >\
	<div class='uesppatEditShipLabel'>Country</div><input type='text' id='uesppatEditShipAddressCountry' > <div id='uesppatEditShipAddressCountryCode'></div>\
	<div class='uesppatEditShipLabel'>Email</div><input type='text' id='uesppatEditShipEmail' >\
	<div class='uesppatEditShipLabel'>Phone</div><input type='text' id='uesppatEditShipAddressPhone' >\
	<div class='uesppatEditShipLabel'>Reward Value</div><input type='text' id='uesppatEditShipRewardValue' list='uespPatEditShipRewardList' >\
	<div class='uesppatEditShipLabel'>Shipping Value</div><input type='text' id='uesppatEditShipValue' >\
	<div id='uesppatEditShipDeminsValue'></div>\
	<datalist id='uespPatEditShipRewardList'>\
	</datalist>\
	<br clear='all'/><p/>\
	<button id='uesppatEditShipDeleteButton'>Delete</button>\
	<button id='uesppatEditShipSaveButton'>Save</button>\
	<button id='uesppatEditShipCancelButton'>Cancel</button>\
</div>";
	
	$("body").append(html);
	
	uesppatEditShipBox = $("#uesppatEditShipEditBox");
	
	$("#uesppatEditShipDeleteButton").on("click", uesppatOnEditShipDeleteClicked);
	$("#uesppatEditShipSaveButton").on("click", uesppatOnEditShipSaveClicked);
	$("#uesppatEditShipCancelButton").on("click", uesppatOnEditShipCancelClicked);
	$("#uesppatEditShipRewardValue").on("focus", uesppatOnEditShipRewardListFocused);
	$("#uesppatEditShipRewardValue").on("change", uesppatOnEditShipRewardListChanged);
	$("#uesppatEditShipAddressCountry").on("input", uesppatOnEditShipCountryChanged);
	$("#uesppatEditShipAddressCountryCode").on("click", uesppatOnEditShipCountryCodeClicked)
	//$("#uesppatEditShipRewardValue").on("input", uespPatOnEditShipUpdateDeminisValue);
	$("#uesppatEditShipValue").on("input", uespPatOnEditShipUpdateDeminisValue);
}


window.uespPatOnEditShipUpdateDeminisValue = function()
{
	var deminisElement = $("#uesppatEditShipDeminsValue");
	var countryCode = $("#uesppatEditShipAddressCountry").val();
	var deminis = uesppatGetDeminisValue(countryCode);
	var rewardValue = $("#uesppatEditShipValue").val().replace("$", "");
	
	if (deminis < 0)
	{
		deminisElement.text("");
		$("#uesppatEditShipValue").css("background-color", "");
	}
	else 
	{
		if (deminis <= rewardValue)
			$("#uesppatEditShipValue").css("background-color", "#fcc");
		else
			$("#uesppatEditShipValue").css("background-color", "");
		
		deminisElement.text("$" + deminis.toFixed(2));
	}
	
}

window.uespPatUpdateAllShipmentsDeminis = function()
{
	var shipments = $("#uesppatCreateShipments tbody tr");
	
	shipments.each(function() {
		var $this = $(this);
		var flagDeminisValue = false;
		var cells = $this.find("td");
		var shippingvalue = parseFloat(cells.eq(-2).text().replace(/[^0-9.]/g, ''));
		var shipMethodCell = cells.eq(7);
		var countryCode = cells.eq(14).text();
		var deminis = 0;
		
		if (shippingvalue > 0 && countryCode != "")
		{
			deminis = uesppatGetDeminisValue(countryCode);
			if (shippingvalue > deminis) flagDeminisValue = true;
		}
		
		if (flagDeminisValue)
		{
			shipMethodCell.addClass("uesppatShipMethodDeminisWarning");
		}
		else
		{
			shipMethodCell.removeClass("uesppatShipMethodDeminisWarning");
		}
	});
}


window.uesppatOnEditShipCountryCodeClicked = function()
{
	var code = $("#uesppatEditShipAddressCountryCode").text();
	
	if (code == "" || code == "BAD") return;
	$("#uesppatEditShipAddressCountry").val(code);
	
	uespPatOnEditShipUpdateDeminisValue();
}


window.uesppatOnEditShipCountryChanged = function()
{
	var input = $("#uesppatEditShipAddressCountry");
	var addressCountry = input.val();
	
	if (!uesppatIsValidCountryCode(addressCountry))
	{
		var country = uesppatFindCountryCode(addressCountry);
		
		if (country) 
			$("#uesppatEditShipAddressCountryCode").text(country);
		else
			$("#uesppatEditShipAddressCountryCode").text("BAD");
	}
	else
	{
		$("#uesppatEditShipAddressCountryCode").text("");
	}
	
	uespPatOnEditShipUpdateDeminisValue();
}

window.uesppatOnEditShipRewardListChanged = function()
{
	this.blur();
}


window.uesppatOnEditShipRewardListFocused = function() 
{
	this.value = '';
}


window.uesppatOnEditShipDeleteClicked = function() {
	if (uesppatEditShipId <= 0) return false;
	
	var rowElement = $("#uesppatCreateShipments tr[shipmentid='" + uesppatEditShipId + "']");
	if (rowElement.length <= 0) return false
	
		//TODO move deleted record?
	var deletedTable = $("#uesppatDeletedShipments");
	
	rowElement.detach().off("click").appendTo(deletedTable.children("tbody")).on("click", uesppatOnEditShipRestoreDeletedRow);
	
	uesppatHideEditShipment();
	
	return true;
}


window.uesppatOnEditShipSaveClicked = function() {
	if (uesppatGetEditShipmentValues()) uesppatHideEditShipment();
	uespPatUpdateAllShipmentsDeminis();
}


window.uesppatOnEditShipCancelClicked = function() {
	uesppatHideEditShipment();
}


window.uesppatOnEditShipRestoreDeletedRow = function(e) {
	$(this).detach().off("click").appendTo("#uesppatCreateShipments tbody").on("click", uesppatOnPatronShipmentRowClicked);
}


window.uesppatOnPatronShipmentRowClicked = function(e) {
	var patronId = $(this).attr("patronid");
	var shipmentId = $(this).attr("shipmentid");
	
	uesppatShowEditWall();
	uesppatShowEditShipment(shipmentId, $(this));
}


window.uesppatEscapeHtml = function(unsafeText) {
    let div = document.createElement('div');
    div.innerText = unsafeText;
    return div.innerHTML;
}


window.uesppatOnSaveNewShipments = function() {
	var form = $("#uesppatSaveNewShipmentForm");
	var rows = $("#uesppatCreateShipments tbody tr");
	var customDate = $("#uespPatCustomShipDate");
	var markAsProcessed = $("#uesppatCustomShipMarkProcess");
	
	console.log("uesppatOnSaveNewShipments", rows);
	
	if (customDate.length > 0)
	{
		$("<input />").attr("type", "hidden").attr("name", "customshipdate").val(customDate.val()).appendTo(form);
	}
	
	if (markAsProcessed.length > 0)
	{
		$("<input />").attr("type", "hidden").attr("name", "customshipmarkprocess").val(markAsProcessed.is(":checked") ? 1 : 0).appendTo(form);
	}
	
	rows.each(function(i,e) {
		if ($(this).hasClass("uesppatBadShipment")) return;
		
		var cols = $(this).children("td");
		var patronId = $(this).attr("patronid");
		var shipmentId = $(this).attr("shipmentid");
		
		var name = cols.eq(1).text();
		var tier = cols.eq(2).text();
		var status = cols.eq(3).text();
		var orderNumber = cols.eq(4).text();
		var orderSku = cols.eq(5).text();
		var orderQnt = cols.eq(6).text();
		var shipMethod = cols.eq(7).text();
		var addressName = cols.eq(8).text();
		var addressLine1 = cols.eq(9).text();
		var addressLine2 = cols.eq(10).text();
		var addressCity = cols.eq(11).text();
		var addressState = cols.eq(12).text();
		var addressZip = cols.eq(13).text();
		var addressCountry = cols.eq(14).text();
		var email = cols.eq(15).text();
		var addressPhone = cols.eq(16).text();
		var pledgeCadence = cols.eq(17).text();
		var shipValue = cols.eq(18).text().replace("$", "");
		var rewardValue = cols.eq(19).text().replace("$", "");
		
		$("<input />").attr("type", "hidden").attr("name", "patreon_id[]").val(patronId).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "orderNumber[]").val(orderNumber).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "orderSku[]").val(orderSku).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "orderQnt[]").val(orderQnt).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "shipMethod[]").val(shipMethod).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressName[]").val(addressName).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressLine1[]").val(addressLine1).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressLine2[]").val(addressLine2).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressCity[]").val(addressCity).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressState[]").val(addressState).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressZip[]").val(addressZip).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressCountry[]").val(addressCountry).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "email[]").val(email).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "addressPhone[]").val(addressPhone).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "rewardValue[]").val(rewardValue).appendTo(form);
	});
	
	return true;
}


window.uesppatOnWikiPatronCopyClick = function()
{
	var copyText = $("#uespWikiPatrons").text();
	var tempTextArea = $("<textarea>");
	
	tempTextArea.css("position", "fixed");
	tempTextArea.css("top", "0");
	tempTextArea.css("left", "0");
	tempTextArea.css("width", "2em");
	tempTextArea.css("height", "2em");
	tempTextArea.css("padding", "0");
	tempTextArea.css("border", "none");
	tempTextArea.css("outline", "none");
	tempTextArea.css("boxshadow", "none");
	tempTextArea.css("background", "transparent");
	tempTextArea.appendTo("body").text(copyText)
	tempTextArea.focus().select();
	
	console.log("uesppatOnWikiPatronCopyClick");
	
	try {
		var successful = document.execCommand('copy');
		var msg = successful ? 'successful' : 'unsuccessful';
		//console.log('Copying text command was ' + msg);
	} catch (err) {
		window.alert("Error: Failed to copy text for some reason....you'll have to do it manually!")
		//console.log('Oops, unable to copy');
	}
	
	tempTextArea.remove();
}


window.uesppatOnGetShipOrderNumberRequest = function(data)
{
	if (data && data.orderNumber) $('input[name="shipmentOrder"]').val(data.orderNumber);
	if (data && data.orderSku) $('input[name="shipmentSku"]').val(data.orderSku);
}


window.uesppatOnGetShipmentOrderNumberButton = function()
{
	var tier = $("#uespPatShipmentOrderTier").val();
	var url = "/wiki/Special:UespPatreon/getordernumber?tier=" + tier;
	
	$.ajax({
		url: url,
	}).done(uesppatOnGetShipOrderNumberRequest);
}


window.uesppatCreateCountryCodeList = function()
{
	window.g_uesppatCountryCodeList = {};
	
	var newCountries = {};
	
	for (var country in g_uesppatCountryToCodes)
	{
		var lCountry = country.toLowerCase();
		var code = g_uesppatCountryToCodes[country];
		
		g_uesppatCountryCodeList[code] = country;
		newCountries[lCountry] = code;
	}
	
	$.extend(g_uesppatCountryToCodes, newCountries);
}


window.uesppatIsValidCountryCode = function(code)
{
	code = code.toUpperCase();
	return (g_uesppatCountryCodeList[code] != null);
}


window.uesppatFindCountryCode = function(country)
{
	var lCountry = country.toLowerCase();
	
	if (g_uesppatCountryToCodes[country] != null) return g_uesppatCountryToCodes[country];
	if (g_uesppatCountryToCodes[lCountry] != null) return g_uesppatCountryToCodes[lCountry];
	
	//TODO: Partial matches?
	
	return false;
}


window.uesppatGetDeminisRecord = function(code)
{
	return g_uesppatDeminis[code.toUpperCase()];
}


window.uesppatGetDeminisValue = function(code)
{
	var deminis = g_uesppatDeminis[code.toUpperCase()];
	if (deminis == null) return -1;
	return deminis.TaxUSD;
}


window.uesppatDoesShipmentExceedDeminisValue = function(countryCode, shipmentValue)
{
	if (shipmentValue == "" || shipmentValue <= 0) return false;
	
	var deminis = uesppatGetDeminisRecord(countryCode);
	if (deminis == null) return false;

	return shipmentValue >= deminis.TaxUSD;
}


window.uesppatUpdateCreateShipments = function()
{
	var rows = $("#uesppatCreateShipments").children("tr");
	
	rows.each(function() {
		uesppatUpdateEditShipmentBadStatusRow(this);
	});
	
}


window.uesppatOnEditShipmentOrderChange = function()
{
	var order = $(this).val();
	var match = order.match(/[a-zA-z]+/);
	var tier = "Other";
	var shipValue = 0;
	
	if (match) tier = match[0];
	if (window.g_uesppatTierShippingValues && g_uesppatTierShippingValues[tier]) shipValue = g_uesppatTierShippingValues[tier];
	
	var shipFmt = "$" + (shipValue/100).toFixed(2);
	$("input[name='shipmentValue']").val(shipFmt);
	
	uespatUpdateEditShipmentDeminis();
}


window.uespatOnEditShipmentCountryChange = function()
{
	uespatUpdateEditShipmentDeminis();
}


window.uespatUpdateEditShipmentDeminis = function()
{
	var shipmentValue = $("input[name='shipmentValue']").val();
	var country = $("input[name='shipmentCountry']").val();
	var deminisElement = $("#uesppatEditShipmentDeminis");
	
	var deminisValue = uesppatGetDeminisValue(country);
	shipmentValue = parseFloat(shipmentValue.replace("$", ""));
	
	if (deminisValue < 0)
	{
		deminisElement.text("");
		$("input[name='shipmentValue']").css("background-color", "");
	}
	else if (deminisValue <= shipmentValue)
	{
		deminisElement.text("$" + deminisValue.toFixed(2));
		$("input[name='shipmentValue']").css("background-color", "#fcc");
	}
	else
	{
		deminisElement.text("$" + deminisValue.toFixed(2));
		$("input[name='shipmentValue']").css("background-color", "");
	}
}


window.uesppatOnEditTagButtonClick = function()
{
	var $this = $(this);
	
	$this.prev().val("");
}


$(function() {
	$("#uesppatPatronTableHeaderCheckbox").on("change", uesppatOnPatronTableHeaderCheckbox);
	$("#uesppatShipmentTableHeaderCheckbox").on("change", uesppatOnPatronShipmentHeaderCheckbox);
	$("#uesppatCreateShipments tr").not('thead tr').on("click", uesppatOnPatronShipmentRowClicked);
	
	$("#uespWikiPatronCopyButton").click(uesppatOnWikiPatronCopyClick);
	
	$("#uesppatRewardValue").on("focus", function() { this.value = ''; });
	$("#uesppatRewardValue").on("change", function() { this.blur(); });
	
	uesppatCreateCountryCodeList();
	
	if ($("#uesppatCreateShipments").children("tr").length > 0)
	{
		uesppatUpdateCreateShipments();
	}
	
	$("#uespPatEditShipmentForm input[name='shipmentOrder']").on("change", uesppatOnEditShipmentOrderChange);
	$("#uespPatEditShipmentForm input[name='shipmentCountry']").on("input", uespatOnEditShipmentCountryChange);
	
	if ($("#uespPatEditShipmentForm").length > 0) uespatUpdateEditShipmentDeminis();
	
	$(".uesppatEditTagButton").on("click", uesppatOnEditTagButtonClick);
	
	uespPatUpdateAllShipmentsDeminis();
});