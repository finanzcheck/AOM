/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */

/**
 * @param id
 */
function sendDeleteCampaignAJAX(id) {

    var ajaxHandler = new ajaxHelper();

    ajaxHandler.addParams({
        module: 'AOM',
        action: 'platformAction',
        method: 'deleteCampaign',
        platform: 'IndividualCampaigns',
        params: JSON.stringify({
            id: id
        })
    }, 'GET');

    ajaxHandler.redirectOnSuccess();
    ajaxHandler.setLoadingElement('#ajaxLoadingAOM');
    ajaxHandler.setErrorElement('#ajaxErrorAOM');
    ajaxHandler.send(true);
}

/**
 * @param params
 */
function sendAddCampaignAJAX(params) {

    var ajaxHandler = new ajaxHelper();

    ajaxHandler.addParams({
        module: 'AOM',
        action: 'platformAction',
        method: 'addCampaign',
        platform: 'IndividualCampaigns',
        params: JSON.stringify(params)
    }, 'GET');

    ajaxHandler.redirectOnSuccess();
    ajaxHandler.setLoadingElement('#ajaxLoadingAOM');
    ajaxHandler.setErrorElement('#ajaxErrorAOM');
    ajaxHandler.send(true);
}


$(document).ready(function () {

    $('.admin .individualCampaigns .addIndividualCampaign').click(function () {
        piwikHelper.hideAjaxError();
        $(this).toggle();

        var numberOfRows = $('table#individualCampaigns')[0].rows.length;
        var newRowId = numberOfRows + 1;
        newRowId = 'row' + newRowId;

        // TODO: Placeholders must be translated!
        $($.parseHTML(' <tr id="' + newRowId + '">\
				<td><input id="addIndividualCampaign_websiteId" placeholder="Website-ID" size="10" /></td>\
				<td><input id="addIndividualCampaign_startDate" placeholder="YYYY-MM-DD" size="10" style="width: 100px;" /></td>\
				<td><input id="addIndividualCampaign_endDate" placeholder="YYYY-MM-DD" size="10" style="width: 100px;" /></td>\
				<td><input id="addIndividualCampaign_campaign" placeholder="Campaign Name" size="20" /></td>\
				<td><input id="addIndividualCampaign_params" placeholder="Params" size="15" /></td>\
				<td><input id="addIndividualCampaign_referrer" placeholder="Referrer" size="15" /></td>\
				<td><input id="addIndividualCampaign_cost" placeholder="Cost" size="15" /></td>\
				<td></td>\
				<td><input type="submit" class="submit addIndividualCampaign"  value="' + _pk_translate('General_Save') + '" />\
	  			<span class="cancel">' + sprintf(_pk_translate('General_OrCancel'), "", "") + '</span></td>\
	 		</tr>'))
            .appendTo('#individualCampaigns')
        ;

        $('.addIndividualCampaign').click(function () {
            sendAddCampaignAJAX({
                websiteId: $('tr#' + newRowId).find('input#addIndividualCampaign_websiteId').val(),
                dateStart: $('tr#' + newRowId).find('input#addIndividualCampaign_startDate').val(),
                dateEnd: $('tr#' + newRowId).find('input#addIndividualCampaign_endDate').val(),
                campaign: $('tr#' + newRowId).find('input#addIndividualCampaign_campaign').val(),
                params: $('tr#' + newRowId).find('input#addIndividualCampaign_params').val(),
                referrer: $('tr#' + newRowId).find('input#addIndividualCampaign_referrer').val(),
                cost: $('tr#' + newRowId).find('input#addIndividualCampaign_cost').val()
            });
        });

        $('.cancel').click(function () {
            piwikHelper.hideAjaxError();
            $(this).parents('tr').remove();
            $('.addIndividualCampaign').toggle();
        });
    });

    $('.deleteCampaign').click(function () {
            piwikHelper.hideAjaxError();

            var id = $(this).attr('id');

            piwikHelper.modalConfirm(
                '#confirmCampaignRemove',
                { yes: function() { sendDeleteCampaignAJAX(id); }}
            );
        }
    );
});
