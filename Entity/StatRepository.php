<?php

namespace MauticPlugin\MauticFocusBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\LeadBundle\Entity\TimelineTrait;

/**
 * @extends CommonRepository<Stat>
 */
class StatRepository extends CommonRepository
{
    use TimelineTrait;

    /**
     * Fetch the base stat data from the database.
     *
     * @param int  $id
     * @param      $type
     * @param null $fromDate
     *
     * @return mixed
     */
    public function getStats($id, $type, $fromDate = null)
    {
        $q = $this->createQueryBuilder('s');

        $expr = $q->expr()->andX(
            $q->expr()->eq('IDENTITY(s.focus)', (int) $id),
            $q->expr()->eq('s.type', ':type')
        );

        if ($fromDate) {
            $expr->add(
                $q->expr()->gte('s.dateAdded', ':fromDate')
            );
            $q->setParameter('fromDate', $fromDate);
        }

        $q->where($expr)
            ->setParameter('type', $type);

        return $q->getQuery()->getArrayResult();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function getStatsViewByLead(int $leadId, array $options = []): array
    {
        return $this->getStatsByLeadAndType($leadId, Stat::TYPE_NOTIFICATION, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function getStatsClickByLead(int $leadId, array $options = []): array
    {
        return $this->getStatsByLeadAndType($leadId, Stat::TYPE_CLICK, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function getStatsByLeadAndType(int $leadId, string $type, array $options = []): array
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $q->from(MAUTIC_TABLE_PREFIX.'focus_stats', 's')
            ->select('s.id, s.lead_id, s.type, s.date_added, f.id as focus_id, f.name as focus_name')
            ->leftJoin('s', MAUTIC_TABLE_PREFIX.'focus', 'f', 's.focus_id=f.id');

        $q->where($q->expr()->andX(
            $q->expr()->eq('s.lead_id', (int) $leadId),
            $q->expr()->like('s.type', '"'.$type.'"')
        ));

        if (isset($options['search']) && $options['search']) {
            $q->andWhere($q->expr()->orX(
                $q->expr()->like('f.name', $q->expr()->literal('%'.$options['search'].'%')),
                $q->expr()->like('f.description', $q->expr()->literal('%'.$options['search'].'%')),
                $q->expr()->like('s.type', $q->expr()->literal($options['search']))
            ));
        }

        return $this->getTimelineResults($q, $options, 'f.name', 's.date_added');
    }
}
