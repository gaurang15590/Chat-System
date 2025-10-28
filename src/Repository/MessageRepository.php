<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 *
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findRecentByRoom(string $roomId, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'u')
            ->addSelect('u')
            ->where('m.roomId = :roomId')
            ->setParameter('roomId', $roomId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByRoomIdPaginated(string $roomId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 'u')
            ->addSelect('u')
            ->where('m.roomId = :roomId')
            ->setParameter('roomId', $roomId)
            ->orderBy('m.createdAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByRoomId(string $roomId): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.roomId = :roomId')
            ->setParameter('roomId', $roomId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMessageCountByRoom(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('m.roomId, COUNT(m.id) as messageCount')
            ->groupBy('m.roomId')
            ->orderBy('messageCount', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}