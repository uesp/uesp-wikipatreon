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


$(function() {
	$("#uesppatPatronTableHeaderCheckbox").on("change", uesppatOnPatronTableHeaderCheckbox);
});