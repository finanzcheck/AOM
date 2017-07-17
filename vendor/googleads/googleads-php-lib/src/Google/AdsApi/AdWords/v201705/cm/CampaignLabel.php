<?php

namespace Google\AdsApi\AdWords\v201705\cm;


/**
 * This file was generated from WSDL. DO NOT EDIT.
 */
class CampaignLabel
{

    /**
     * @var int $campaignId
     */
    protected $campaignId = null;

    /**
     * @var int $labelId
     */
    protected $labelId = null;

    /**
     * @param int $campaignId
     * @param int $labelId
     */
    public function __construct($campaignId = null, $labelId = null)
    {
      $this->campaignId = $campaignId;
      $this->labelId = $labelId;
    }

    /**
     * @return int
     */
    public function getCampaignId()
    {
      return $this->campaignId;
    }

    /**
     * @param int $campaignId
     * @return \Google\AdsApi\AdWords\v201705\cm\CampaignLabel
     */
    public function setCampaignId($campaignId)
    {
      $this->campaignId = (PHP_INT_SIZE === 4)
          ? floatval($campaignId) : $campaignId;
      return $this;
    }

    /**
     * @return int
     */
    public function getLabelId()
    {
      return $this->labelId;
    }

    /**
     * @param int $labelId
     * @return \Google\AdsApi\AdWords\v201705\cm\CampaignLabel
     */
    public function setLabelId($labelId)
    {
      $this->labelId = (PHP_INT_SIZE === 4)
          ? floatval($labelId) : $labelId;
      return $this;
    }

}
