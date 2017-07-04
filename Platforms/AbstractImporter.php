<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Plugins\AOM\AOM;
use Psr\Log\LoggerInterface;

abstract class AbstractImporter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The start date of the period to import (YYYY-MM-DD).
     *
     * @var string
     */
    protected $startDate;

    /**
     * The end date of the period to import (YYYY-MM-DD).
     *
     * @var string
     */
    protected $endDate;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = (null === $logger ? AOM::getLogger() : $logger);
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
     * @return null|string
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @return null|string
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Imports platform data.
     *
     * @return mixed
     */
    abstract public function import();

    /**
     * Deletes all imported data for the given combination of platform account, website and date.
     *
     * @param string $platformName
     * @param string $accountId
     * @param int $websiteId
     * @param string $date
     */
    public function deleteExistingData($platformName, $accountId, $websiteId, $date)
    {
        // Delete all imported data for the given combination of platform account, website and date
        list($deletedImportedDataRecords, $timeToDeleteImportedData) =
            AbstractPlatform::deleteImportedData($platformName, $accountId, $websiteId, $date);

        $this->logger->debug(
            sprintf(
                'Deleted existing %s data (%fs for %d imported data records).',
                $platformName,
                $timeToDeleteImportedData,
                is_int($deletedImportedDataRecords) ? $deletedImportedDataRecords : 0
            ),
            ['platform' => $platformName, 'task' => 'import']
        );
    }
}
