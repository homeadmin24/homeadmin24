<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\KategorisierungCorrection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KategorisierungCorrection>
 */
class KategorisierungCorrectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KategorisierungCorrection::class);
    }

    /**
     * Find corrections for a similar partner name
     */
    public function findByPartnerPattern(string $partner, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.zahlungPartner LIKE :partner')
            ->andWhere('c.createdAt > :since')
            ->setParameter('partner', '%' . $partner . '%')
            ->setParameter('since', new \DateTime('-6 months'))
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get correction statistics
     */
    public function getCorrectionStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $totalCorrections = $conn->fetchOne('SELECT COUNT(*) FROM kategorisierung_correction');

        $byType = $conn->fetchAllAssociative(
            'SELECT correction_type, COUNT(*) as count
             FROM kategorisierung_correction
             GROUP BY correction_type'
        );

        $topCorrectedPatterns = $conn->fetchAllAssociative(
            'SELECT zahlung_partner, COUNT(*) as correction_count
             FROM kategorisierung_correction
             WHERE zahlung_partner IS NOT NULL
             GROUP BY zahlung_partner
             ORDER BY correction_count DESC
             LIMIT 10'
        );

        return [
            'total_corrections' => $totalCorrections,
            'by_type' => $byType,
            'top_corrected_patterns' => $topCorrectedPatterns,
        ];
    }
}
