<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM;

use DOMDocument;
use DOMXPath;
use Piwik\Db;
use Piwik\Plugins\AOM\Platforms\Platform;

class VisitorProfilePopup
{
    public static function enrich(&$result)
    {
        $doc = new DOMDocument();
        $doc->loadHTML($result);

        $xpath = new DOMXpath($doc);

        // TODO: Enrich first/last visit in left column (would require to detect these visits on our own)!
        // TODO: Add platform images to visits?!
        // TODO: Add total marketing costs during customer lifetime?!

        self::enrichVisits($doc, $xpath);

        $result = $doc->saveHTML();
    }

    private static function enrichVisits(DOMDocument &$doc, DOMXPath &$xpath)
    {
        $visitsNodes = $xpath->query('//h2[@class="visitor-profile-visit-title"]/@data-idvisit');
        if (!is_null($visitsNodes)) {
            foreach ($visitsNodes as $visitNode) {
                $visitId = $visitNode->value;
                if ($visitId  > 0) {
                    $additionalDescription = Platform::getHumanReadableDescriptionForVisit($visitId);
                    if ($additionalDescription) {

                        $el = $doc->createElement('div');
                        $styleAttribute = $doc->createAttribute('style');
                        $styleAttribute->value = 'padding-top: 10px; font-size: 12px !important; '
                            . ' font-weight: normal !important; line-height: 15px !important';
                        $el->appendChild($styleAttribute);

                        $template = $doc->createDocumentFragment();
                        $template->appendXML($additionalDescription);
                        $el->appendChild($template);

                        $visitNode->parentNode->appendChild($el);
                    }
                }
            }
        }
    }
}