<?php
/**
 * AOM - Piwik Advanced Online Marketing Plugin
 *
 * @author Daniel Stonies <daniel.stonies@googlemail.com>
 */
namespace Piwik\Plugins\AOM\Platforms;

use Piwik\Db;
use Psr\Log\LoggerInterface;

abstract class Merger
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The merge-period's start date.
     *
     * @var string
     */
    protected $startDate;

    /**
     * The merge-period's end date.
     *
     * @var string
     */
    protected $endDate;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Sets the period that should be merged.
     * Merges yesterday's and today's data as default.
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
}
