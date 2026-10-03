<?php

namespace App\Repository;

use App\Entity\HgaQualityFeedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HgaQualityFeedback>
 */
class HgaQualityFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HgaQualityFeedback::class);
    }

    /**
     * Get recent user-reported issues for learning.
     *
     * @param int $limit Number of recent issues to return
     *
     * @return HgaQualityFeedback[]
     */
    public function getRecentIssues(int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.userFeedbackType IN (:types)')
            ->setParameter('types', ['false_negative', 'new_check'])
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get unimplemented feedback that should be converted to checks.
     *
     * @return HgaQualityFeedback[]
     */
    public function getUnimplementedFeedback(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.implemented = :implemented')
            ->andWhere('f.userFeedbackType IN (:types)')
            ->setParameter('implemented', false)
            ->setParameter('types', ['false_negative', 'new_check'])
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get feedback statistics by provider.
     *
     * @return array{provider: string, total: int, helpful: int, not_helpful: int, accuracy: float}[]
     */
    public function getProviderStatistics(): array
    {
        $results = $this->createQueryBuilder('f')
            ->select('f.aiProvider as provider')
            ->addSelect('COUNT(f.id) as total')
            ->addSelect('SUM(CASE WHEN f.helpfulRating = 1 THEN 1 ELSE 0 END) as helpful')
            ->addSelect('SUM(CASE WHEN f.helpfulRating = 0 THEN 1 ELSE 0 END) as not_helpful')
            ->where('f.helpfulRating IS NOT NULL')
            ->groupBy('f.aiProvider')
            ->getQuery()
            ->getResult();

        foreach ($results as &$result) {
            $result['accuracy'] = $result['total'] > 0
                ? (float) $result['helpful'] / $result['total']
                : 0.0;
        }

        return $results;
    }

    /**
     * Count frequently reported issues (same description appears multiple times).
     *
     * @return array<array{description: string, count: int, feedback_type: string}>
     */
    public function getFrequentIssues(int $minOccurrences = 3): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.userDescription as description')
            ->addSelect('f.userFeedbackType as feedback_type')
            ->addSelect('COUNT(f.id) as count')
            ->where('f.userDescription IS NOT NULL')
            ->groupBy('f.userDescription, f.userFeedbackType')
            ->having('COUNT(f.id) >= :min')
            ->setParameter('min', $minOccurrences)
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
