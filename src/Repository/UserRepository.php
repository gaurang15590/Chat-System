<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findOnlineUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isOnline = :online')
            ->setParameter('online', true)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function updateLastSeen(User $user): void
    {
        $user->setLastSeenAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    public function setOnlineStatus(User $user, bool $isOnline): void
    {
        $user->setIsOnline($isOnline);
        if ($isOnline) {
            $user->setLastSeenAt(new \DateTimeImmutable());
        }
        $this->getEntityManager()->flush();
    }
}