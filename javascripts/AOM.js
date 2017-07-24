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
 * @param platform
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
