<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Repository\SurveyAfterReceiptRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SurveyAfterReceiptRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class SurveyAfterReceipt
{
    use Timestampable;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $whiteId;

    /**
     * @ORM\ManyToOne(targetEntity=Action::class)
     */
    private $action;

    /**
     * @ORM\Column(type="integer")
     */
    private $rating;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $reasonForParticipation;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $sourceInfo;

    /**
     * @ORM\Column(type="integer")
     */
    private $userId;

    /**
     * @ORM\Column(type="integer")
     */
    private $actionId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getWhiteId(): ?string
    {
        return $this->whiteId;
    }

    public function setWhiteId(string $whiteId): self
    {
        $this->whiteId = $whiteId;

        return $this;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(?Action $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getReasonForParticipation(): ?string
    {
        return $this->reasonForParticipation;
    }

    public function setReasonForParticipation(?string $reasonForParticipation): self
    {
        $this->reasonForParticipation = $reasonForParticipation;

        return $this;
    }

    public function getSourceInfo(): ?string
    {
        return $this->sourceInfo;
    }

    public function setSourceInfo(?string $sourceInfo): self
    {
        $this->sourceInfo = $sourceInfo;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getActionId(): ?int
    {
        return $this->actionId;
    }

    public function setActionId(int $actionId): self
    {
        $this->actionId = $actionId;

        return $this;
    }

}
