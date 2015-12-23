# Piwik Advanced Online Marketing Plugin 

## Description

Integrate additional data (costs, ad impressions, etc.) from advertisers (Google AdWords, Bing, Criteo, Facebook Ads) 
into Piwik and combine that data with individual visits - create a whole bunch of new opportunities!


## Advertiser's platforms

To obtain data (campaign names, costs, ad impressions, etc.) from the advertiser's platforms, API access must be granted 
and configured within the settings of this plugin.

To map individual visits with the data from the advertising platforms (which is optional), all links from the 
advertiser's platforms must have additional params that supply the required data to this plugin. This data is stored in
`piwik_log_visit.aom_ad_data`. The params are as follows:


### Google AdWords

We use [ValueTrack params](https://support.google.com/adwords/answer/2375447?hl=en) to obtain the required data.
The following params are supported:

| Param                 | Mandatory | Contents      |
| --------------------- | --------- | ------------- | 
| {prefix}_platform     | true      | AdWords       |  
| {prefix}_campaign_id  | false     | {campaignid}  |
| {prefix}_ad_group_id  | false     | {adgroupid}   |
| {prefix}_target_id    | false     | {targetid}    |
| {prefix}_creative     | false     | {creative}    |
| {prefix}_placement    | false     | {placement}   |
| {prefix}_network      | false     | {network}     |
| {prefix}_device       | false     | {device}      |
| {prefix}_ad_position  | false     | {adposition}  |
| {prefix}_loc_physical | false     | {locPhysical} |
| {prefix}_loc_interest | false     | {locInterest} |

A typical link at Google AdWords (with the prefix "aom") should have the following params:

    &aom_platform=AdWords&aom_campaign_id={campaignid}&aom_ad_group_id={adgroupid}&aom_target_id={targetid}&aom_creative={creative}&aom_placement={placement}&aom_network={network}&aom_device={device}&aom_ad_position={adposition}&aom_loc_physical={locPhysical}&aom_loc_Interest={locInterest}
    
When a Google AdWords ad is clicked, data like the following can be found in `piwik_log_visit.aom_ad_data`:

    {"platform":"AdWords","campaignId":"184418636","adGroupId":"9794351276","targetId":"kwd-118607649","creative":"47609133356","placement":"","network":"g","device":"m","adPosition":"1t2","locPhysical":"20228","locInterest":"1004074"}
    {"platform":"AdWords","campaignId":"171096476","adGroupId":"8837340236","targetId":"","creative":"47609140796","placement":"suchen.mobile.de/auto-inserat","network":"d","device":"c","adPosition":"none","locPhysical":"9041542","locInterest":""}
    {"platform":"AdWords","campaignId":"147730196","adGroupId":"7300245836","targetId":"aud-55070239676","creative":"47609140676","placement":"carfansofamerica.com","network":"d","device":"c","adPosition":"none","locPhysical":"9042649","locInterest":""}

Placements are shortened when the length of the entire JSON is more than 1,024 characters.


### Microsoft Bing Ads

We use [URL tracking](http://help.bingads.microsoft.com/apex/index/3/en-us/51091) to obtain the required data. 
The following params are supported:

| Param                   | Mandatory | Contents      |
| ----------------------- | --------- | ------------- | 
| {prefix}_platform       | true      | Bing          |  
| {prefix}_campaign_id    | false     | {CampaignId}  |
| {prefix}_ad_group_id    | false     | {AdGroupId}   |
| {prefix}_order_item_id  | false     | {OrderItemId} |
| {prefix}_target_id      | false     | {TargetId}    |
| {prefix}_ad_id          | false     | {AdId}        |

A typical link at Microsoft Bing Ads (with the prefix "aom") should have the following params:

    &aom_platform=Bing&aom_campaign_id={CampaignId}&aom_ad_group_id={AdGroupId}&aom_order_item_id={OrderItemId}&aom_target_id={TargetId}&aom_ad_id={AdId}

When a Bing ad is clicked, data like the following can be found in `piwik_log_visit.aom_ad_data`:

    {"platform":"Bing","campaignId":190561279,"adGroupId":2029114499,"orderItemId":40414589411,"targetId":"40414589411","adId":5222037942}


### Criteo

When using Criteo, all links must be created manually. 
The following params must be replaced manually with their corresponding IDs:

| Param                   | Mandatory | Contents |
| ----------------------- | --------- | -------- | 
| {prefix}_platform       | true      | Criteo   |  
| {prefix}_campaign_id    | true      | 14340    |

A typical link at Criteo (with the prefix "aom") should have the following params:

    &aom_platform=Criteo&aom_campaign_id=14340

When a Criteo ad is clicked, data like the following can be found in `piwik_log_visit.aom_ad_data`:

    {"platform":"Criteo","campaignId":"14340"}


### Facebook Ads

When using Facebook Ads, all links must be created manually.
The following params must be replaced manually with their corresponding IDs:

| Param                      | Mandatory | Contents      |
| -------------------------- | --------- | ------------- | 
| {prefix}_platform          | true      | Criteo        |  
| {prefix}_campaign_group_id | false     | 4160286035775 |
| {prefix}_campaign_id       | false     | 6028603577541 |
| {prefix}_ad_group_id       | false     | 5760286037541 |

A typical link at FacebookAds (with the prefix "aom") should have the following params:

    &aom_platform=FacebookAds&aom_campaign_group_id=4160286035775&aom_campaign_id=6028603577541&aom_ad_group_id=5760286037541
    
When a Facebook Ads ad is clicked, data like the following can be found in `piwik_log_visit.aom_ad_data`:

    {"platform":"FacebookAds","campaignGroupId":"4160286035775","campaignId":"6028603577541","adGroupId":"5760286037541"}


## Other advertising platforms and Piwik's default tracking params

You should not use any of the params listed above when the advertising platform you are using is not listed here.
 
Piwik's [default tracking params](http://piwik.org/docs/tracking-campaigns/) (pk_kwd and the even more important 
pk_campaign) should be used for both supported and unsupported advertising platforms.



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



## Requirements, installation & updates

You must use MySQL 5.0.3 or higher to store more than 255 characters in VARCHAR.

See http://piwik.org/faq/plugins/#faq_21.
Run `composer install` to install dependencies, such as the Google AdWords SDK.

It is recommended to setup auto archiving (http://piwik.org/docs/setup-auto-archiving/) to improve performance.


## Tests

Run unit tests with `./console tests:run --group AOM_Unit`.
Run integration tests with `./console tests:run --group AOM_Integration`.


## Changelog

__0.1.0__
* first release


## License

GPL v3 / fair use


## Support

...
