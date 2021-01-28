window.uesppatOnShowTierSubmit = function() {
	
	$("#uesppat_showiron_hidden").prop("checked", !$("#uesppat_showiron").is(":checked"));
	$("#uesppat_showsteel_hidden").prop("checked", !$("#uesppat_showsteel").is(":checked"));
	$("#uesppat_showelven_hidden").prop("checked", !$("#uesppat_showelven").is(":checked"));
	$("#uesppat_showorcish_hidden").prop("checked", !$("#uesppat_showorcish").is(":checked"));
	$("#uesppat_showglass_hidden").prop("checked", !$("#uesppat_showglass").is(":checked"));
	$("#uesppat_showdaedric_hidden").prop("checked", !$("#uesppat_showdaedric").is(":checked"));
	$("#uesppat_showother_hidden").prop("checked", !$("#uesppat_showother").is(":checked"));
	
	//console.log("uesppatOnShowTierSubmit", $("#uesppat_showiron_hidden").is(":checked"), $("#uesppat_showiron").is(":checked"));
	return true;
}


window.uesppatOnPatronTableHeaderCheckbox = function() {
	var isChecked = $("#uesppatPatronTableHeaderCheckbox").is(":checked");
	
	$(".uesppatPatronRowCheckbox").prop("checked", isChecked);
	
	return true;
}


window.uesppatOnCreateShipmentButton = function() {
	var checkedBoxes = $(".uesppatPatronRowCheckbox:checked");
	if (checkedBoxes.length == 0) return;
	
	$("#uesppatPatronTableAction").val("createship");
	$("#uesppatPatronTableForm").submit();
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
}


window.uesppatSetEditShipmentValues = function(shipmentId, rowElement) {
	var cols = rowElement.children("td");
	
	uesppatEditShipId = shipmentId;
	
	var name = cols.eq(1).text();
	var tier = cols.eq(2).text();
	var status = cols.eq(3).text();
	var orderNumber = cols.eq(4).text();
	var orderSku = cols.eq(5).text();
	var shipMethod = cols.eq(6).text();
	var addressName = cols.eq(7).text();
	var addressLine1 = cols.eq(8).text();
	var addressLine2 = cols.eq(9).text();
	var addressCity = cols.eq(10).text();
	var addressState = cols.eq(11).text();
	var addressZip = cols.eq(12).text();
	var addressCountry = cols.eq(13).text();
	var email = cols.eq(14).text();
	var addressPhone = cols.eq(15).text();
	
	uesppatEditShipBox.children("#uesppatEditShipTitle").text("Editing Shipment #" + shipmentId);
	uesppatEditShipBox.children("#uesppatEditShipName").val(name);
	uesppatEditShipBox.children("#uesppatEditShipTier").val(tier);
	uesppatEditShipBox.children("#uesppatEditShipStatus").val(status);
	uesppatEditShipBox.children("#uesppatEditShipOrderNumber").val(orderNumber);
	uesppatEditShipBox.children("#uesppatEditShipOrderSku").val(orderSku);
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
}


window.uesppatGetEditShipmentValues = function() {
	if (uesppatEditShipId <= 0) return false;
	
	var rowElement = $("#uesppatCreateShipments tr[shipmentid='" + uesppatEditShipId + "']");
	if (rowElement.length == 0) return false;
	
	var cols = rowElement.children("td");
	
	var orderNumber = uesppatEditShipBox.children("#uesppatEditShipOrderNumber").val();
	var orderSku = uesppatEditShipBox.children("#uesppatEditShipOrderSku").val();
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
	
	cols.eq(4).text(orderNumber);
	cols.eq(5).text(orderSku);
	cols.eq(6).text(shipMethod);
	cols.eq(7).text(addressName);
	cols.eq(8).text(addressLine1);
	cols.eq(9).text(addressLine2);
	cols.eq(10).text(addressCity);
	cols.eq(11).text(addressState);
	cols.eq(12).text(addressZip);
	cols.eq(13).text(addressCountry);
	cols.eq(14).text(email);
	cols.eq(15).text(addressPhone);
	
	uesppatUpdatetEditShipmentBadStatus(uesppatEditShipId);
	
	return true;
}


window.uesppatUpdatetEditShipmentBadStatus = function(shipmentId) {
	var rowElement = $("#uesppatCreateShipments tr[shipmentid='" + uesppatEditShipId + "']");
	if (rowElement.length == 0) return false;
	
	var cols = rowElement.children("td");
	
	var orderNumber = uesppatEditShipBox.children("#uesppatEditShipOrderNumber").val();
	var orderSku = uesppatEditShipBox.children("#uesppatEditShipOrderSku").val();
	var addressName = uesppatEditShipBox.children("#uesppatEditShipAddressName").val();
	var addressLine1 = uesppatEditShipBox.children("#uesppatEditShipAddressLine1").val();
	var addressLine2 = uesppatEditShipBox.children("#uesppatEditShipAddressLine2").val();
	var addressCountry = uesppatEditShipBox.children("#uesppatEditShipAddressCountry").val();
	
	var isBad = (orderNumber == "") || (orderSku == "") || (addressName == "" ) || (addressLine1 == "" && addressLine2 == "") || (addressCountry == "" );
	
	if (isBad) 
		rowElement.addClass("uesppatBadShipment");
	else
		rowElement.removeClass("uesppatBadShipment");
}


window.uesppatHideEditShipment = function() {
	uesppatEditShipBox.hide();
	uesppatHideEditWall();
	uesppatEditShipId = -1;
}


window.uesppatCreateEditShipment = function() {
	var html = "\
<div style='display:none;' id='uesppatEditShipEditBox'>\
	<div id='uesppatEditShipTitle'>Editing Shipment #</div>\
	<div class='uesppatEditShipLabel'>Name</div><input type='text' id='uesppatEditShipName' readonly>\
	<div class='uesppatEditShipLabel'>Tier</div><input type='text' id='uesppatEditShipTier' readonly>\
	<div class='uesppatEditShipLabel'>Status</div><input type='text' id='uesppatEditShipStatus' readonly>\
	<div class='uesppatEditShipLabel'>Order #</div><input type='text' id='uesppatEditShipOrderNumber' >\
	<div class='uesppatEditShipLabel'>SKU</div><input type='text' id='uesppatEditShipOrderSku' >\
	<div class='uesppatEditShipLabel'>Ship Method</div><input type='text' id='uesppatEditShipMethod' >\
	<div class='uesppatEditShipLabel'>Addressee</div><input type='text' id='uesppatEditShipAddressName' >\
	<div class='uesppatEditShipLabel'>Line 1</div><input type='text' id='uesppatEditShipAddressLine1' >\
	<div class='uesppatEditShipLabel'>Line 2</div><input type='text' id='uesppatEditShipAddressLine2' >\
	<div class='uesppatEditShipLabel'>City</div><input type='text' id='uesppatEditShipAddressCity' >\
	<div class='uesppatEditShipLabel'>State</div><input type='text' id='uesppatEditShipAddressState' >\
	<div class='uesppatEditShipLabel'>Postal Code</div><input type='text' id='uesppatEditShipAddressZip' >\
	<div class='uesppatEditShipLabel'>Country</div><input type='text' id='uesppatEditShipAddressCountry' >\
	<div class='uesppatEditShipLabel'>Email</div><input type='text' id='uesppatEditShipEmail' >\
	<div class='uesppatEditShipLabel'>Phone</div><input type='text' id='uesppatEditShipAddressPhone' >\
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
	
	//console.log("uesppatOnPatronShipmentRowClicked", this, shipmentId);
	
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
	
	console.log("uesppatOnSaveNewShipments", rows);
	
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
		var shipMethod = cols.eq(6).text();
		var addressName = cols.eq(7).text();
		var addressLine1 = cols.eq(8).text();
		var addressLine2 = cols.eq(9).text();
		var addressCity = cols.eq(10).text();
		var addressState = cols.eq(11).text();
		var addressZip = cols.eq(12).text();
		var addressCountry = cols.eq(13).text();
		var email = cols.eq(14).text();
		var addressPhone = cols.eq(15).text();
		
		$("<input />").attr("type", "hidden").attr("name", "patreon_id[]").val(patronId).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "orderNumber[]").val(orderNumber).appendTo(form);
		$("<input />").attr("type", "hidden").attr("name", "orderSku[]").val(orderSku).appendTo(form);
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
	});
	
	return true;
}


$(function() {
	$("#uesppatPatronTableHeaderCheckbox").on("change", uesppatOnPatronTableHeaderCheckbox);
	$("#uesppatCreateShipments tr").not('thead tr').on("click", uesppatOnPatronShipmentRowClicked);
	
});