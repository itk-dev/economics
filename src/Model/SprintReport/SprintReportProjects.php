<?php

namespace App\Model\SprintReport;

use Doctrine\Common\Collections\ArrayCollection;

class SprintReportProjects {
  /** @var ArrayCollection<string, SprintReportProject> */
  public ArrayCollection $projects;

  public function __construct() {
    $this->projects = new ArrayCollection();
  }
}
