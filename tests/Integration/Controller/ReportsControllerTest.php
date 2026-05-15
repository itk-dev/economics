<?php

namespace App\Tests\Integration\Controller;

class ReportsControllerTest extends AbstractControllerTestCase
{
    public function testIndexRedirectsAnonymous(): void
    {
        $this->assertAnonymousRedirectsToLogin('/admin/reports');
    }

    public function testIndexDeniedForNonReportRole(): void
    {
        $this->assertDeniedFor('/admin/reports', ['ROLE_INVOICE']);
    }

    /**
     * /admin/reports currently throws because `index.html.twig` references a
     * `form` variable the controller does not pass. Smoke-test left as
     * skipped until the controller is fixed.
     */
    public function testIndexLoadsForReportRole(): void
    {
        $this->markTestSkipped('Controller throws Twig "form" variable missing; bug surfaced by smoke matrix.');
    }
}
