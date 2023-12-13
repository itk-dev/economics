<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class IssueData {
  public \DateTime $started;
  public string $name;
  public string $status;
  public string $projectTrackerId;
  public string $projectTrackerKey;
  public ?string $accountId = null;
  public ?string $accountKey = null;
  public ?string $epicName = null;
  public ?string $epicKey = null;
  /** @var Collection<string, VersionData> */
  public ?Collection $versions;
  public ?\DateTime $resolutionDate = null;
  public string $projectId;

  /**
   * Constructor for the class.
   *
   * @param \DateTime         $started
   * @param string|null       $name
   * @param string|null       $status
   * @param string|null       $projectTrackerId
   * @param string|null       $projectTrackerKey
   * @param string|null       $accountId
   * @param string|null       $accountKey
   * @param string|null       $epicName
   * @param string|null       $epicKey
   * @param Collection|null  $versions
   * @param \DateTime|null    $resolutionDate
   * @param string            $projectId
   */
  public function __construct(
    ?string $name,
    ?string $status,
    ?string $projectTrackerId,
    ?string $projectTrackerKey,
    ?string $accountId = null,
    ?string $accountKey = null,
    ?string $epicName = null,
    ?string $epicKey = null,
    ?Collection $versions = null,
    ?\DateTime $resolutionDate = null,
    ?string $projectId
  ) {
    $this->name = $name;
    $this->status = $status;
    $this->projectTrackerId = $projectTrackerId;
    $this->projectTrackerKey = $projectTrackerKey;
    $this->accountId = $accountId;
    $this->accountKey = $accountKey;
    $this->epicName = $epicName;
    $this->$epicKey = $epicKey;
    $this->versions = $versions;
    $this->resolutionDate = $resolutionDate;
    $this->projectId = $projectId;
  }
}
