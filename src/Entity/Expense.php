<?php

namespace App\Entity;

use App\Repository\ExpenseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
class Expense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $isBilled = null;

    #[ORM\ManyToOne(inversedBy: 'expenses')]
    private ?InvoiceEntry $invoiceEntry = null;

    #[ORM\Column]
    private ?int $expenseId = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getInvoiceEntry(): ?InvoiceEntry
    {
        return $this->invoiceEntry;
    }

    public function setInvoiceEntry(?InvoiceEntry $invoiceEntry): self
    {
        $this->invoiceEntry = $invoiceEntry;

        return $this;
    }

    public function getExpenseId(): ?int
    {
        return $this->expenseId;
    }

    public function setExpenseId(int $expenseId): self
    {
        $this->expenseId = $expenseId;

        return $this;
    }
}
