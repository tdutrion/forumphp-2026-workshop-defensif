<?php

namespace App\Account\Entity;

use App\Account\Repository\SeenFilmRepository;
use App\Catalog\Entity\Work;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SeenFilmRepository::class)]
#[ORM\UniqueConstraint(columns: ['user_id', 'work_id'])]
class SeenFilm
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** The work, so that the mark holds for the films of every chain. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Work $work = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $seenAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->seenAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getWork(): ?Work
    {
        return $this->work;
    }

    public function setWork(Work $work): static
    {
        $this->work = $work;

        return $this;
    }

    public function getSeenAt(): \DateTimeImmutable
    {
        return $this->seenAt;
    }
}
