/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */

$(document).ready(function () {

    $('.admin .TaboolaAccounts .addTaboolaAccount').click(function () {
        piwikHelper.hideAjaxError();
        $(this).toggle();

        var numberOfRows = $('table#TaboolaAccounts')[0].rows.length;
        var newRowId = numberOfRows + 1;
        newRowId = 'row' + newRowId;

        // TODO: Placeholders must be translated!
        $($.parseHTML(' <tr id="' + newRowId + '">\
				<td><input id="addTaboolaAccount_websiteId" placeholder="Website-ID" size="10" /></td>\
				<td><input id="addTaboolaAccount_accountName" placeholder="Account-Name" size="2" /></td>\
				<td><input id="addTaboolaAccount_clientId" placeholder="Client-ID" size="20" /></td>\
				<td><input id="addTaboolaAccount_clientSecret" placeholder="Client-Secret" size="20" /></td>\
				<td><input type="submit" class="submit addTaboolaAccount"  value="' + _pk_translate('General_Save') + '" />\
	  			<span class="cancel">' + sprintf(_pk_translate('General_OrCancel'), "", "") + '</span></td>\
	 		</tr>'))
            .appendTo('#TaboolaAccounts')
        ;

        $('.addTaboolaAccount').click(function () {
            sendAddAccountAJAX('Taboola', {
                websiteId: $('tr#' + newRowId).find('input#addTaboolaAccount_websiteId').val(),
                appToken: $('tr#' + newRowId).find('input#addTaboolaAccount_accountName').val(),
                username: $('tr#' + newRowId).find('input#addTaboolaAccount_clientId').val(),
                password: $('tr#' + newRowId).find('input#addTaboolaAccount_clientSecret').val()
            });
        });

        $('.cancel').click(function () {
            piwikHelper.hideAjaxError();
            $(this).parents('tr').remove();
            $('.addTaboolaAccount').toggle();
        });
    });
});
