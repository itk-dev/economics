<?php

namespace App\Model\Reports;

use App\Entity\Worker;
use Doctrine\Common\Collections\ArrayCollection;

class InvoicingRateReportWorker
{
    private Worker $worker;

    public float $average;

    /** @var ArrayCollection<int, array> */
    public ArrayCollection $dataByPeriod;

    /** @var ArrayCollection<string, array> */
    public ArrayCollection $projectData;

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
        $this->average = 0.0;
        $this->dataByPeriod = new ArrayCollection();
        $this->projectData = new ArrayCollection();
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }

    // Proxy methods to access Worker fields
    public function getEmail(): ?string
    {
        return $this->worker->getEmail();
    }

    public function setEmail(string $email): self
    {
        $this->worker->setEmail($email);

        return $this;
    }

    public function getWorkload(): ?float
    {
        return $this->worker->getWorkload();
    }

    public function setWorkload(?float $workload): self
    {
        $this->worker->setWorkload($workload);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->worker->getName();
    }

    public function setName(?string $name): self
    {
        $this->worker->setName($name);

        return $this;
    }

    public function getIncludeInReports(): bool
    {
        return $this->worker->getIncludeInReports();
    }

    public function setIncludeInReports(bool $includeInReports): self
    {
        $this->worker->setIncludeInReports($includeInReports);

        return $this;
    }
}
