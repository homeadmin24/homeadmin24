<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AiQueryResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiQueryResponse>
 */
class AiQueryResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiQueryResponse::class);
    }

    /**
     * Get good Claude answers for training Ollama
     *
     * @return AiQueryResponse[]
     */
    public function getGoodClaudeExamples(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.provider = :provider')
            ->andWhere('r.userRating = :rating')
            ->andWhere('r.wasUsedForTraining = false')
            ->setParameter('provider', 'claude')
            ->setParameter('rating', 'good')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get metrics for comparison
     */
    public function getProviderMetrics(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                provider,
                COUNT(*) as query_count,
                AVG(response_time) as avg_response_time,
                SUM(cost) as total_cost,
                SUM(CASE WHEN user_rating = "good" THEN 1 ELSE 0 END) as good_ratings,
                SUM(CASE WHEN user_rating = "bad" THEN 1 ELSE 0 END) as bad_ratings
            FROM ai_query_response
            GROUP BY provider
        ';

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Mark examples as used for training
     */
    public function markAsUsedForTraining(array $ids): void
    {
        $this->createQueryBuilder('r')
            ->update()
            ->set('r.wasUsedForTraining', 'true')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
