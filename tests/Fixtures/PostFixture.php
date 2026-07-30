<?php

namespace Trunk\Tests\Fixtures;

use Trunk\Database\ORM\Attributes\Column;
use Trunk\Database\ORM\Attributes\Entity;
use Trunk\Database\ORM\Attributes\ManyToMany;
use Trunk\Database\ORM\Attributes\ManyToOne;
use Trunk\ORM\BaseEntity;

#[Entity(table: 'fixture_posts')]
class PostFixture extends BaseEntity
{
    #[Column(primary: true)]
    private ?int $id = null;

    #[Column(type: 'VARCHAR', length: 255)]
    private string $title;

    #[ManyToOne(targetEntity: AuthorFixture::class)]
    private ?AuthorFixture $author = null;

    #[ManyToMany(targetEntity: TagFixture::class)]
    private array $tags = [];

    public function getId(): ?int
    {
        return $this->id;
    }
}
