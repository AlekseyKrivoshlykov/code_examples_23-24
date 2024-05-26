<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Repository\ActionContestVoteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ActionContestVoteRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class ActionContestVote
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
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity=ActionContestItem::class, inversedBy="votes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $actionContestItem;

    /**
     * @ORM\ManyToOne(targetEntity=Action::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $action;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ipAddress;

    /**
     * @ORM\Column(type="integer")
     */
    private $isAccepted;

    /**
     * @param \App\Entity\User $user
     * @param \App\Entity\Action $action
     * @param \App\Entity\ActionContestItem $actionContestItem
     * @param string $ipAddress
     * @return \App\Entity\ActionContestVote
     */
    public static function create(
        User $user, ActionContestItem $actionContestItem, Action $action, string $ipAddress
    ): ActionContestVote
    {
        $actionContestVote = new self;

        $actionContestVote->setUser($user);
        $actionContestVote->setActionContestItem($actionContestItem);
        $actionContestVote->setAction($action);
        $actionContestVote->setIpAddress($ipAddress);
        $actionContestVote->setIsAccepted(1);

        return $actionContestVote;
    }

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

    public function getActionContestItem(): ?ActionContestItem
    {
        return $this->actionContestItem;
    }

    public function setActionContestItem(?ActionContestItem $actionContestItem): self
    {
        $this->actionContestItem = $actionContestItem;

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

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getIsAccepted(): ?int
    {
        return $this->isAccepted;
    }

    public function setIsAccepted(int $isAccepted): self
    {
        $this->isAccepted = $isAccepted;

        return $this;
    }
}
