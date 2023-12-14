<?php

namespace App\Model\Invoices;

class WorklogData
{
    public int $projectTrackerId;
    public string $comment;
    public string $worker;
    public int $timeSpentSeconds;
    public \DateTime $started;
    public ?bool $projectTrackerIsBilled = null;
    public string $projectTrackerIssueId;

    public function __construct(
        int $projectTrackerId = null,
        string $comment = null,
        string $worker = null,
        int $timeSpentSeconds = null,
        \DateTime $started = null,
        ?bool $projectTrackerIsBilled = null,
        string $projectTrackerIssueId = null
    ) {
        $this->projectTrackerId = $projectTrackerId;
        $this->comment = $comment;
        $this->worker = $worker;
        $this->timeSpentSeconds = $timeSpentSeconds;
        $this->started = $started;
        $this->projectTrackerIsBilled = $projectTrackerIsBilled;
        $this->projectTrackerIssueId = $projectTrackerIssueId;
    }
}
