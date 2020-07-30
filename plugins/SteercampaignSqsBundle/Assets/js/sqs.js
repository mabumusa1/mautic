/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

Mautic.testSqsApi = function (btn) {
    mQuery(btn).prop('disabled', true);
    var username = mQuery('#integration_details_apiKeys_username').val();
    var password = mQuery('#integration_details_apiKeys_password').val();
    var region = mQuery('#integration_details_apiKeys_region').val();
    var url = mQuery('#integration_details_apiKeys_url').val();
    if(!username || !password || !region || !url){
        mQuery(btn).prop('disabled', false);
        alert('Please fill in the require fields');
        return;
    }
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: "POST",
        data: {
            'action':'plugin:steercampaignSqs:getTestSqs',
            'region': region,
            'username': username,
            'password': password
        },
        dataType: "json",
        success: function (response) {
            if (typeof response.html != 'undefined') {
                // pushes response into container element
                mQuery('#integration_details_apiKeys_stats').html(response.html);
            }
        },
        error: function (request, textStatus, errorThrown) {
            //mQuery('#integration_details_apiKeys_stats').val((error.responseJSON && error.responseJSON.message)?error.responseJSON.message:'Error: ' + JSON.stringify(error));
            Mautic.processAjaxError(request, textStatus, errorThrown);
        },
        complete: function() {
            mQuery(btn).prop('disabled', false);
        }
    });

};


