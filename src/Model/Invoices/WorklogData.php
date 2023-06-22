<?php

namespace App\Model\Invoices;

use DateTime;

class WorklogData
{
    public int $projectTrackerId;
    public string $comment;
    public string $worker;
    public int $timeSpentSeconds;
    public DateTime $started;
    public ?bool $projectTrackerIsBilled = null;
    public string $projectTrackerIssueId;
}
