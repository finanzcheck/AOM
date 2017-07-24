/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */

$(document).ready(function () {

    // Criteo
    $('.admin .criteoAccounts .addCriteoAccount').click(function () {
        piwikHelper.hideAjaxError();
        $(this).toggle();

        var numberOfRows = $('table#criteoAccounts')[0].rows.length;
        var newRowId = numberOfRows + 1;
        newRowId = 'row' + newRowId;

        // TODO: Placeholders must be translated!
        $($.parseHTML(' <tr id="' + newRowId + '">\
				<td><input id="addCriteoAccount_websiteId" placeholder="Website-ID" size="10" /></td>\
				<td><input id="addCriteoAccount_appToken" placeholder="App-Token" size="15" /></td>\
				<td><input id="addCriteoAccount_username" placeholder="Username" size="20" /></td>\
				<td><input id="addCriteoAccount_password" placeholder="Password" size="15" /></td>\
				<td><input type="submit" class="submit addCriteoAccount"  value="' + _pk_translate('General_Save') + '" />\
	  			<span class="cancel">' + sprintf(_pk_translate('General_OrCancel'), "", "") + '</span></td>\
	 		</tr>'))
            .appendTo('#criteoAccounts')
        ;

        $('.addCriteoAccount').click(function () {
            sendAddAccountAJAX('Criteo', {
                websiteId: $('tr#' + newRowId).find('input#addCriteoAccount_websiteId').val(),
                appToken: $('tr#' + newRowId).find('input#addCriteoAccount_appToken').val(),
                username: $('tr#' + newRowId).find('input#addCriteoAccount_username').val(),
                password: $('tr#' + newRowId).find('input#addCriteoAccount_password').val()
            });
        });

        $('.cancel').click(function () {
            piwikHelper.hideAjaxError();
            $(this).parents('tr').remove();
            $('.addCriteoAccount').toggle();
        });
    });
});
