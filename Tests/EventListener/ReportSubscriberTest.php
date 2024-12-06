<?php

declare(strict_types=1);

namespace MauticPlugin\MauticFocusBundle\Tests\EventListener;

use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use MauticPlugin\MauticFocusBundle\EventListener\ReportSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReportSubscriberTest extends TestCase
{
    /**
     * @var MockObject&ReportBuilderEvent
     */
    private MockObject $reportBuilderEventMock;
    /**
     * @var MockObject&ReportGeneratorEvent
     */
    private MockObject $reportGeneratorEventMock;
    private ReportSubscriber $reportSubscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportBuilderEventMock   = $this->createMock(ReportBuilderEvent::class);
        $this->reportGeneratorEventMock = $this->createMock(ReportGeneratorEvent::class);

        $this->reportSubscriber = new ReportSubscriber();
    }

    public function testNotRelevantContextBuilder(): void
    {
        $this->reportBuilderEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(false);

        $this->reportBuilderEventMock->expects($this->never())
            ->method('addTable');

        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
    }

    public function testNotRelevantContextGenerate(): void
    {
        $this->reportGeneratorEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(false);

        $this->reportGeneratorEventMock->expects($this->never())
            ->method('getQueryBuilder');

        $this->reportSubscriber->onReportGenerate($this->reportGeneratorEventMock);
    }

    public function testOnReportBuilder(): void
    {
        $this->reportBuilderEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(true);

        $this->reportBuilderEventMock->expects($this->once())
            ->method('addTable')
            ->with(
                ReportSubscriber::CONTEXT_FOCUS_STATS,
                [
                    'display_name' => 'mautic.focus.graph.stats',
                    'columns'      => $this->getExpectedColumns(),
                ]
            );

        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);
    }

    public function testOnReportGenerate(): void
    {
        $this->reportGeneratorEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(true);

        $queryBuilder = $this->createMock(\Doctrine\DBAL\Query\QueryBuilder::class);
        $this->reportGeneratorEventMock->expects($this->once())
            ->method('getQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('from')
            ->with(MAUTIC_TABLE_PREFIX.'focus_stats', ReportSubscriber::PREFIX_STATS)
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->exactly(3))
            ->method('leftJoin')
            ->withConsecutive(
                [
                    ReportSubscriber::PREFIX_STATS,
                    MAUTIC_TABLE_PREFIX.'focus',
                    ReportSubscriber::PREFIX_FOCUS,
                    ReportSubscriber::PREFIX_FOCUS.'.id = '.ReportSubscriber::PREFIX_STATS.'.focus_id',
                ],
                [
                    ReportSubscriber::PREFIX_STATS,
                    MAUTIC_TABLE_PREFIX.'channel_url_trackables',
                    ReportSubscriber::PREFIX_TRACKABLES,
                    ReportSubscriber::PREFIX_TRACKABLES.'.channel_id = '.ReportSubscriber::PREFIX_STATS.'.focus_id AND '.
                    ReportSubscriber::PREFIX_TRACKABLES.'.channel = "focus"',
                ],
                [
                    ReportSubscriber::PREFIX_STATS,
                    MAUTIC_TABLE_PREFIX.'page_redirects',
                    ReportSubscriber::PREFIX_REDIRECTS,
                    ReportSubscriber::PREFIX_REDIRECTS.'.id = '.ReportSubscriber::PREFIX_TRACKABLES.'.redirect_id',
                ]
            )
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('groupBy')
            ->with(ReportSubscriber::PREFIX_STATS.'.focus_id', ReportSubscriber::PREFIX_STATS.'.type')
            ->willReturn($queryBuilder);

        $this->reportSubscriber->onReportGenerate($this->reportGeneratorEventMock);
    }

    /**
     * @return array<string, array{label: string, type: string, formula?: string}>
     */
    private function getExpectedColumns(): array
    {
        return [
            ReportSubscriber::PREFIX_FOCUS.'.name' => [
                'label'   => 'mautic.core.name',
                'type'    => 'html',
                'alias'   => 'focus_name',
                'formula' => 'MAX('.ReportSubscriber::PREFIX_FOCUS.'.name)',
            ],
            ReportSubscriber::PREFIX_FOCUS.'.description' => [
                'label'   => 'mautic.core.description',
                'type'    => 'html',
                'alias'   => 'focus_desc',
                'formula' => 'MAX('.ReportSubscriber::PREFIX_FOCUS.'.description)',
            ],
            ReportSubscriber::PREFIX_FOCUS.'.focus_type' => [
                'label'   => 'mautic.focus.thead.type',
                'type'    => 'html',
                'alias'   => 'focus_type',
                'formula' => 'MAX('.ReportSubscriber::PREFIX_FOCUS.'.focus_type)',
            ],
            ReportSubscriber::PREFIX_FOCUS.'.style' => [
                'label'   => 'mautic.focus.tab.focus_style',
                'type'    => 'html',
                'alias'   => 'focus_style',
                'formula' => 'MAX('.ReportSubscriber::PREFIX_FOCUS.'.style)',
            ],
            ReportSubscriber::PREFIX_STATS.'.type' => [
                'label' => 'mautic.focus.interaction',
                'type'  => 'html',
                'alias' => 'interaction_type',
            ],
            ReportSubscriber::PREFIX_TRACKABLES.'.hits' => [
                'label'   => 'mautic.page.graph.line.hits',
                'type'    => 'html',
                'alias'   => 'hit_count',
                'formula' => 'CASE 
                    WHEN '.ReportSubscriber::PREFIX_STATS.'.type = "view" THEN (
                        SELECT COUNT(fs2.id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2 
                        INNER JOIN '.MAUTIC_TABLE_PREFIX.'focus f2 
                        ON f2.id = fs2.focus_id 
                        WHERE fs2.type = "view" 
                        AND f2.id = '.ReportSubscriber::PREFIX_FOCUS.'.id
                        GROUP BY f2.id
                    )
                    ELSE MAX('.ReportSubscriber::PREFIX_TRACKABLES.'.hits)
                END',
            ],
            ReportSubscriber::PREFIX_TRACKABLES.'.unique_hits' => [
                'label'   => 'mautic.report.focus.uniquehits',
                'type'    => 'html',
                'alias'   => 'unique_hit_count',
                'formula' => 'CASE 
                    WHEN '.ReportSubscriber::PREFIX_STATS.'.type = "view" THEN (
                        SELECT COUNT(DISTINCT fs2.lead_id) 
                        FROM '.MAUTIC_TABLE_PREFIX.'focus_stats fs2 
                        WHERE fs2.type = "view" 
                        AND fs2.focus_id = '.ReportSubscriber::PREFIX_STATS.'.focus_id
                    )
                    ELSE MAX('.ReportSubscriber::PREFIX_TRACKABLES.'.unique_hits)
                END',
            ],
            ReportSubscriber::PREFIX_REDIRECTS.'.url' => [
                'label'   => 'url',
                'type'    => 'html',
                'alias'   => 'redirect_url',
                'formula' => 'MAX('.ReportSubscriber::PREFIX_REDIRECTS.'.url)',
            ],
        ];
    }
}
