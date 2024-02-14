<?php

namespace App\Service;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Contracts\Translation\TranslatorInterface;

class TeamReportService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws \OpenSpout\Writer\Exception\WriterNotOpenedException
     * @throws \OpenSpout\Common\Exception\IOException
     */
    public function initWriter($dateInterval): Writer
    {
        $writer = new Writer();
        $writer->openToBrowser('team-report-'.$dateInterval['dateFrom'].'-'.$dateInterval['dateFrom'].'xlsx');

        $row = new Row([
            Cell::fromValue($this->translator->trans('reports.team_project_name')),
            Cell::fromValue($this->translator->trans('reports.team_user')),
            Cell::fromValue($this->translator->trans('reports.team_issue_id')),
            Cell::fromValue($this->translator->trans('reports.team_date')),
            Cell::fromValue($this->translator->trans('reports.team_time_spent')),
            Cell::fromValue($this->translator->trans('reports.team_description')),
        ]);
        $writer->addRow($row);

        return $writer;
    }

    public function spreadsheetWriteData($data, $writer): Writer
    {
        foreach ($data as $dataRow) {
            $row = new Row([
                Cell::fromValue($dataRow->getProject()->getName()),
                Cell::fromValue($dataRow->getWorker()),
                Cell::fromValue($dataRow->getIssue()->getName()),
                Cell::fromValue($dataRow->getStarted()->format('d-m-Y')),
                Cell::fromValue($dataRow->getTimeSpentSeconds() / 3600),
                Cell::fromValue($dataRow->getDescription()),
            ]);
            $writer->addRow($row);
        }

        return $writer;
    }
}
