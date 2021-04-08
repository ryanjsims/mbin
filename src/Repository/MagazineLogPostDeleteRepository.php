<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\MagazineLog;

/**
 * @method MagazineLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method MagazineLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method MagazineLog[]    findAll()
 * @method MagazineLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MagazineLogPostDeleteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MagazineLog::class);
    }
}
