/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */

/**
 * @param platform
 * @param id
 */
function sendDeleteAccountAJAX(platform, id) {

    var ajaxHandler = new ajaxHelper();

    ajaxHandler.addParams({
        module: 'AOM',
        action: 'platformAction',
        method: 'deleteAccount',
        platform: platform,
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
function sendAddAccountAJAX(platform, params) {

    var ajaxHandler = new ajaxHelper();

    ajaxHandler.addParams({
        module: 'AOM',
        action: 'platformAction',
        method: 'addAccount',
        platform: platform,
        params: JSON.stringify(params)
    }, 'GET');

    ajaxHandler.redirectOnSuccess();
    ajaxHandler.setLoadingElement('#ajaxLoadingAOM');
    ajaxHandler.setErrorElement('#ajaxErrorAOM');
    ajaxHandler.send(true);
}



$(document).ready(function () {

    // AdWords
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


    // Bing
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


    // Facebook Ads
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


    // Generic method for all platforms to delete an account
    $('.deleteAccount').click(function () {
            piwikHelper.hideAjaxError();

            var platform = $(this).attr('data-platform');
            var id = $(this).attr('id');

            piwikHelper.modalConfirm(
                '#confirmAccountRemove',
                { yes: function() { sendDeleteAccountAJAX(platform, id); }}
            );
        }
    );


    // Show abbreviated string
    $('.abbreviated').click(function () {
        var full = $(this).data('full');
        if ($(this).text() != full) {
            $(this).text(full);
        }
    });
});
