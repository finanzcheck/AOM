/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */

$(document).ready(function () {

    $('.admin .bingAccounts .addBingAccount').click(function () {
        piwikHelper.hideAjaxError();
        $(this).toggle();

        var numberOfRows = $('table#bingAccounts')[0].rows.length;
        var newRowId = numberOfRows + 1;
        newRowId = 'row' + newRowId;

        // TODO: Placeholders must be translated!
        $($.parseHTML(' <tr id="' + newRowId + '">\
				<td><input id="addBingAccount_websiteId" placeholder="Website-ID" size="10" /></td>\
				<td><input id="addBingAccount_clientId" placeholder="Client-ID" size="15" /></td>\
				<td><input id="addBingAccount_clientSecret" placeholder="Client-Secret" size="20" /></td>\
				<td><input id="addBingAccount_accountId" placeholder="Account-ID" size="10" /></td>\
				<td><input id="addBingAccount_developerToken" placeholder="Developer-Token" size="15" /></td>\
				<td></td>\
				<td></td>\
				<td><input type="submit" class="submit addBingAccount"  value="' + _pk_translate('General_Save') + '" />\
	  			<span class="cancel">' + sprintf(_pk_translate('General_OrCancel'), "", "") + '</span></td>\
	 		</tr>'))
            .appendTo('#bingAccounts')
        ;

        $('.addBingAccount').click(function () {
            sendAddAccountAJAX('Bing', {
                websiteId: $('tr#' + newRowId).find('input#addBingAccount_websiteId').val(),
                clientId: $('tr#' + newRowId).find('input#addBingAccount_clientId').val(),
                clientSecret: $('tr#' + newRowId).find('input#addBingAccount_clientSecret').val(),
                accountId: $('tr#' + newRowId).find('input#addBingAccount_accountId').val(),
                developerToken: $('tr#' + newRowId).find('input#addBingAccount_developerToken').val()
            });
        });

        $('.cancel').click(function () {
            piwikHelper.hideAjaxError();
            $(this).parents('tr').remove();
            $('.addBingAccount').toggle();
        });
    });
});
