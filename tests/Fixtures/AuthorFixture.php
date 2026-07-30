<?php

namespace Trunk\Tests\Fixtures;

use Trunk\Database\ORM\Attributes\Column;
use Trunk\Database\ORM\Attributes\Entity;
use Trunk\Database\ORM\Attributes\OneToMany;
use Trunk\ORM\BaseEntity;

#[Entity(table: 'fixture_authors')]
class AuthorFixture extends BaseEntity
{
    #[Column(primary: true)]
    private ?int $id = null;

    #[Column(type: 'VARCHAR', length: 255)]
    private string $name;

    #[OneToMany(targetEntity: PostFixture::class, mappedBy: 'author')]
    private array $posts = [];

    public function getId(): ?int
    {
        return $this->id;
    }
}
