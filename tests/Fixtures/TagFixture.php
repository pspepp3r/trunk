<?php

namespace Trunk\Tests\Fixtures;

use Trunk\Database\ORM\Attributes\Column;
use Trunk\Database\ORM\Attributes\Entity;
use Trunk\Database\ORM\Attributes\ManyToMany;
use Trunk\ORM\BaseEntity;

#[Entity(table: 'fixture_tags')]
class TagFixture extends BaseEntity
{
    #[Column(primary: true)]
    private ?int $id = null;

    #[Column(type: 'VARCHAR', length: 255)]
    private string $name;

    #[ManyToMany(targetEntity: PostFixture::class, mappedBy: 'tags')]
    private array $posts = [];

    public function getId(): ?int
    {
        return $this->id;
    }
}
