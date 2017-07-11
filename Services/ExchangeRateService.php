<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 * @author André Kolell <andre.kolell@gmail.com>
 */
namespace Piwik\Plugins\AOM\Services;

class ExchangeRateService
{
    /**
     * Returns the exchange rate from the base currency to the target currency (for a given date).
     *
     * TODO: Establish a fallback solution.
     *
     * @param string $baseCurrency
     * @param string $targetCurrency
     * @param string $date
     * @return float
     * @throws \Exception
     */
    public static function getExchangeRate($baseCurrency, $targetCurrency, $date = null)
    {
        if ($baseCurrency !== $targetCurrency) {
            $ch = curl_init();
            curl_setopt(
                $ch,
                CURLOPT_URL,
                'http://api.fixer.io/' . (null === $date ? 'latest' : $date)
                . '?base=' . $baseCurrency . '&symbols=' . $targetCurrency
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $output = curl_exec($ch);
            $response = json_decode($output, true);
            if (!is_array($response) || !array_key_exists('rates', $response)
                || !is_array($response['rates'])  || !array_key_exists($targetCurrency, $response['rates'])
            ) {
                throw new \Exception('Could not retrieve exchange rate.');
            }

            $error = curl_errno($ch);
            if ($error > 0) {
                throw new \Exception('Could not retrieve exchange rate.');
            }
            curl_close($ch);

            return $response['rates'][$targetCurrency];
        }

        return 1.0;
    }
}
