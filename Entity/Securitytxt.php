<?php
/*
 * This file is part of the Sulu Securitytxt bundle.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace BitExpert\Sulu\SecuritytxtBundle\Entity;

use BitExpert\Sulu\SecuritytxtBundle\Repository\SecuritytxtRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One security.txt (RFC 9116) per webspace. Fields that may occur multiple times are stored as ordered lists;
 * for Contact the order is the preference.
 */
#[ORM\Entity(repositoryClass: SecuritytxtRepository::class)]
// prefixed like Sulu's own tables (se_, we_, ...)
#[ORM\Table(name: 'st_securitytxt')]
class Securitytxt
{
    final public const RESOURCE_KEY = 'securitytxt';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    // @phpstan-ignore-next-line
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    private ?string $webspaceKey = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $contact = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $expires = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $encryption = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $acknowledgments = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $policy = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $hiring = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $preferredLanguages = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $canonical = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWebspaceKey(): ?string
    {
        return $this->webspaceKey;
    }

    public function setWebspaceKey(?string $webspaceKey): self
    {
        $this->webspaceKey = $webspaceKey;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getContact(): array
    {
        return $this->contact;
    }

    /**
     * @param list<string> $contact
     */
    public function setContact(array $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getExpires(): ?string
    {
        return $this->expires;
    }

    public function setExpires(string $expires): self
    {
        $this->expires = $expires;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEncryption(): array
    {
        return $this->encryption;
    }

    /**
     * @param list<string> $encryption
     */
    public function setEncryption(array $encryption): self
    {
        $this->encryption = $encryption;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAcknowledgments(): array
    {
        return $this->acknowledgments;
    }

    /**
     * @param list<string> $acknowledgments
     */
    public function setAcknowledgments(array $acknowledgments): self
    {
        $this->acknowledgments = $acknowledgments;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPolicy(): array
    {
        return $this->policy;
    }

    /**
     * @param list<string> $policy
     */
    public function setPolicy(array $policy): self
    {
        $this->policy = $policy;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getHiring(): array
    {
        return $this->hiring;
    }

    /**
     * @param list<string> $hiring
     */
    public function setHiring(array $hiring): self
    {
        $this->hiring = $hiring;

        return $this;
    }

    public function getPreferredLanguages(): ?string
    {
        return $this->preferredLanguages;
    }

    public function setPreferredLanguages(?string $preferredLanguages): self
    {
        $this->preferredLanguages = $preferredLanguages;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCanonical(): array
    {
        return $this->canonical;
    }

    /**
     * @param list<string> $canonical
     */
    public function setCanonical(array $canonical): self
    {
        $this->canonical = $canonical;

        return $this;
    }
}
