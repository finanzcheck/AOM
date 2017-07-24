/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */

$(document).ready(function () {

    $('.admin .facebookAdsAccounts .addFacebookAdsAccount').click(function () {
        piwikHelper.hideAjaxError();
        $(this).toggle();

        var numberOfRows = $('table#facebookAdsAccounts')[0].rows.length;
        var newRowId = numberOfRows + 1;
        newRowId = 'row' + newRowId;

        // TODO: Placeholders must be translated!
        $($.parseHTML(' <tr id="' + newRowId + '">\
				<td><input id="addFacebookAdsAccount_websiteId" placeholder="Website-ID" size="10" /></td>\
				<td><input id="addFacebookAdsAccount_clientId" placeholder="Client-ID" size="15" /></td>\
				<td><input id="addFacebookAdsAccount_clientSecret" placeholder="Client-Secret" size="20" /></td>\
				<td><input id="addFacebookAdsAccount_accountId" placeholder="Account-ID" size="15" /></td>\
				<td></td>\
				<td><input type="submit" class="submit addFacebookAdsAccount"  value="' + _pk_translate('General_Save') + '" />\
	  			<span class="cancel">' + sprintf(_pk_translate('General_OrCancel'), "", "") + '</span></td>\
	 		</tr>'))
            .appendTo('#facebookAdsAccounts')
        ;

        $('.addFacebookAdsAccount').click(function () {
            sendAddAccountAJAX('FacebookAds', {
                websiteId: $('tr#' + newRowId).find('input#addFacebookAdsAccount_websiteId').val(),
                clientId: $('tr#' + newRowId).find('input#addFacebookAdsAccount_clientId').val(),
                clientSecret: $('tr#' + newRowId).find('input#addFacebookAdsAccount_clientSecret').val(),
                accountId: $('tr#' + newRowId).find('input#addFacebookAdsAccount_accountId').val()
            });
        });

        $('.cancel').click(function () {
            piwikHelper.hideAjaxError();
            $(this).parents('tr').remove();
            $('.addFacebookAdsAccount').toggle();
        });
    });
});
