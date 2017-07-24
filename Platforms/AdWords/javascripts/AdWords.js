/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */

$(document).ready(function () {

    $('.admin .adWordsAccounts .addAdWordsAccount').click(function () {
        piwikHelper.hideAjaxError();
        $(this).toggle();

        var numberOfRows = $('table#adWordsAccounts')[0].rows.length;
        var newRowId = numberOfRows + 1;
        newRowId = 'row' + newRowId;

        // TODO: Placeholders must be translated!
        $($.parseHTML(' <tr id="' + newRowId + '">\
				<td><input id="addAdWordsAccount_websiteId" placeholder="Website-ID" size="10" /></td>\
				<td><input id="addAdWordsAccount_clientId" placeholder="Client-ID" size="15" /></td>\
				<td><input id="addAdWordsAccount_clientSecret" placeholder="Client-Secret" size="20" /></td>\
				<td><input id="addAdWordsAccount_clientCustomerId" placeholder="Client-Customer-ID" size="10" /></td>\
				<td><input id="addAdWordsAccount_developerToken" placeholder="Developer-Token" size="15" /></td>\
				<td></td>\
				<td><input type="submit" class="submit addAdWordsAccount"  value="' + _pk_translate('General_Save') + '" />\
	  			<span class="cancel">' + sprintf(_pk_translate('General_OrCancel'), "", "") + '</span></td>\
	 		</tr>'))
            .appendTo('#adWordsAccounts')
        ;

        $('.addAdWordsAccount').click(function () {
            sendAddAccountAJAX('AdWords', {
                websiteId: $('tr#' + newRowId).find('input#addAdWordsAccount_websiteId').val(),
                clientId: $('tr#' + newRowId).find('input#addAdWordsAccount_clientId').val(),
                clientSecret: $('tr#' + newRowId).find('input#addAdWordsAccount_clientSecret').val(),
                clientCustomerId: $('tr#' + newRowId).find('input#addAdWordsAccount_clientCustomerId').val(),
                developerToken: $('tr#' + newRowId).find('input#addAdWordsAccount_developerToken').val()
            });
        });

        $('.cancel').click(function () {
            piwikHelper.hideAjaxError();
            $(this).parents('tr').remove();
            $('.addAdWordsAccount').toggle();
        });
    });
});
