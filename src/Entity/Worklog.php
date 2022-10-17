<?php

namespace App\Entity;

use App\Repository\WorklogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorklogRepository::class)]
class Worklog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $worklogId = null;

    #[ORM\ManyToOne(inversedBy: 'worklogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InvoiceEntry $invoiceEntry = null;

    #[ORM\Column]
    private ?bool $isBilled = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorklogId(): ?int
    {
        return $this->worklogId;
    }

    public function setWorklogId(int $worklogId): self
    {
        $this->worklogId = $worklogId;

        return $this;
    }

    public function getInvoiceEntry(): ?InvoiceEntry
    {
        return $this->invoiceEntry;
    }

    public function setInvoiceEntry(?InvoiceEntry $invoiceEntry): self
    {
        $this->invoiceEntry = $invoiceEntry;

        return $this;
    }

    public function isBilled(): ?bool
    {
        return $this->isBilled;
    }

    public function setIsBilled(bool $isBilled): self
    {
        $this->isBilled = $isBilled;

        return $this;
    }
}
