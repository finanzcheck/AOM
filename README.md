# Piwik Advanced Online Marketing Plugin 

## Description

Integrates additional data (costs, ad impressions, etc.) from advertisers (Google AdWords, Bing, Criteo, Facebook Ads) 
into Piwik and combine that data with individual visits - creating a whole bunch of new opportunities.


## Advertiser's platforms

To obtain data (costs, ad impressions, etc.) from the advertiser's platforms, API access must be granted and configured
within the settings of this plugin.

To map the data from the advertising platform to individual visits (which is optional), all links from the advertiser's 
platforms must have an additional param (with a configurable name, e.g. "ad_id") that supplies required data 
(as url-encoded JSON) to this plugin. The param's content must be as follows:


### Google AdWords

We use [ValueTrack params](https://support.google.com/adwords/answer/2375447?hl=en) to obtain the required data:

ad_id=%257B%2522platform%2522%253A%2522AdWords%2522%252C%2522campaignId%2522%253A%2522{campaignid}%2522%252C%2522adGroupId%2522%253A%2522{adgroupid}%2522%252C%2522targetId%2522%253A%2522{targetid}%2522%252C%2522creative%2522%253A%2522{creative}%2522%252C%2522placement%2522%253A%2522{placement}%2522%252C%2522network%2522%253A%2522{network}%2522%252C%2522device%2522%253A%2522{device}%2522%252C%2522adposition%2522%253A%2522{adposition}%2522%252C%2522locPhysical%2522%253A%2522{loc_physical_ms}%2522%252C%2522locInterest%2522%253A%2522{loc_interest_ms}%2522%257D

When a Google AdWords ad is clicked, data like the following can be found in piwik_log_visit.aom_ad_data:

{"platform":"AdWords","campaignId":"184418636","adGroupId":"9794351276","targetId":"kwd-118607649","creative":"47609133356","placement":"","network":"g","device":"m","adposition":"1t2","locPhysical":"20228","locInterest":"1004074"}
{"platform":"AdWords","campaignId":"171096476","adGroupId":"8837340236","targetId":"","creative":"47609140796","placement":"suchen.mobile.de/auto-inserat","network":"d","device":"c","adposition":"none","locPhysical":"9041542","locInterest":""}
{"platform":"AdWords","campaignId":"147730196","adGroupId":"7300245836","targetId":"aud-55070239676","creative":"47609140676","placement":"carfansofamerica.com","network":"d","device":"c","adposition":"none","locPhysical":"9042649","locInterest":""}


### Microsoft Bing Ads

We use [URL tracking](http://help.bingads.microsoft.com/apex/index/3/en-us/51091) to obtain the required data:

ad_id=%7B%22platform%22%3A%22Bing%22%2C%22campaignId%22%3A{CampaignId}%2C%22adGroupId%22%3A{AdGroupId}%2C%22orderItemId%22%3A{OrderItemId}%2C%22targetId%22%3A%22{TargetId}%22%2C%22adId%22%3A{AdId}%7D

When a Bing ad is clicked, data like the following can be found in piwik_log_visit.aom_ad_data:

{"platform":"Bing","campaignId":190561279,"adGroupId":2029114499,"orderItemId":40414589411,"targetId":"40414589411","adId":5222037942}


### Criteo

When using Criteo, all links must be created manually (replace {campaignId} manually with the correct ID):

ad_id=%7B%22platform%22%3A%22Criteo%22%2C%22campaignId%22%3A%22{campaignId}%22%7D

When a Criteo ad is clicked, data like the following can be found in piwik_log_visit.aom_ad_data:

{"platform":"Criteo","campaignId":"14340"}


### Facebook Ads

When using Facebook Ads, all links must be created manually (replace {campaignGroupId}, {campaignId} and {adGroupId} manually with the correct IDs):

ad_id=%7B%22platform%22%3A%22FacebookAds%22%2C%22campaignGroupId%22%3A%22{campaignGroupId}%22%2C%22campaignId%22%3A%22{campaignId}%22%2C%22adGroupId%22%3A%22{adGroupId}%22%7D

When a Facebook Ads ad is clicked, data like the following can be found in piwik_log_visit.aom_ad_data:

{"platform":"FacebookAds","campaignGroupId":"6028603577541","campaignId":"6028603577541","adGroupId":"6028603577541"}



## API

This plugin provides the following API endpoints (add `&token_auth=...` in production environment):

### AOM.getVisits

Returns all visits with marketing information within the given period.

Example: ?module=API&method=AOM.getVisits&idSite=1&period=day&date=2015-05-01&format=json


### AOM.getEcommerceOrderWithVisits

Returns a specific ecommerce order by orderId with all visits with marketing information that happened before the 
ecommerce order or false (when no order could be found for the given orderId).

Example: ?module=API&method=AOM.getEcommerceOrderWithVisits&orderId=123&idSite=1&format=json


### AOM.getEcommerceOrdersWithVisits

Returns all ecommerce orders with all visits with marketing information that happened before the ecommerce order within 
the given period.

Example: ?module=API&method=AOM.getEcommerceOrdersWithVisits&idSite=1&period=day&date=2015-05-01&format=json



## Installation / Update

See http://piwik.org/faq/plugins/#faq_21.
Run ``composer install`` to install dependencies, such as the Google AdWords SDK.

It is recommended to setup auto archiving (http://piwik.org/docs/setup-auto-archiving/) to improve performance.


## Tests

Run integration tests with `./console tests:run --testsuite integration AOM`.


## Changelog

__0.1.0__
* first release


## License

GPL v3 / fair use


## Support

...
