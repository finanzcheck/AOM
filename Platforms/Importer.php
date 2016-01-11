<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Db;
use Psr\Log\LoggerInterface;

abstract class Importer
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The import period's start date.
     *
     * @var string
     */
    protected $startDate;

    /**
     * The import period's end date.
     *
     * @var string
     */
    protected $endDate;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Sets the period that should be imported.
     * Import yesterday's and today's data as default.
     *
     * TODO: Consider site timezone here?!
     *
     * @param null|string $startDate YYYY-MM-DD
     * @param null|string $endDate YYYY-MM-DD
     */
    public function setPeriod($startDate = null, $endDate = null)
    {
        if (null !== $startDate && null !== $endDate) {
            $this->startDate = $startDate;
            $this->endDate = $endDate;
        } else {
            $this->startDate = date('Y-m-d', strtotime('-1 day', time()));
            $this->endDate = date('Y-m-d');
        }
    }

    /**
     * Deletes all imported data for the given date.
     *
     * TODO: This might be more complicated when we already merged / assigned data to visits?!
     * TODO: There might be more than 100000 rows (although this is very unlikely).
     *
     * @param string $tableName
     * @param string $accountId
     * @param int $websiteId
     * @param string $date
     */
    public function deleteImportedData($tableName, $accountId, $websiteId, $date)
    {
        Db::deleteAllRows(
            $tableName,
            'WHERE id_account_internal = ? AND idsite = ? AND date = ?',
            'date',
            100000,
            [
                $accountId,
                $websiteId,
                $date,
            ]
        );
    }
}
