<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Registry\ActionContestItemStatusesRegistry;
use App\Repository\ActionContestItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ActionContestItemRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class ActionContestItem
{
    use Timestampable;
    
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Action::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $action;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="smallint")
     */
    private $moderate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $file;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $place;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $votes = 0;

    /**
     * @ORM\Column(type="integer")
     */
    private $actionId;

    public function __construct()
    {

    }

    /**
     * @param \App\Entity\User $user
     * @param \App\Entity\Action $action
     * @param string $file
     * @param string $title
     * @return \App\Entity\ActionContestItem
     */
    public static function create(User $user, Action $action, string $file, string $title): ActionContestItem
    {
        $actionContestItem = new self;

        $actionContestItem->setAction($action);
        $actionContestItem->setUser($user);
        $actionContestItem->setModerate(ActionContestItemStatusesRegistry::STATUS_AWAITING);
        $actionContestItem->setFile($file);
        $actionContestItem->setTitle($title);

        return $actionContestItem;
    }

    // для конвертации объекта в строку в форме редактирования Голоса творческой работы
    public function __toString()
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getModerate(): ?int
    {
        return $this->moderate;
    }

    public function setModerate(int $moderate): self
    {
        $this->moderate = $moderate;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getPlace(): ?int
    {
        return $this->place;
    }

    public function setPlace(?int $place): self
    {
        $this->place = $place;

        return $this;
    }

    public function getVotes(): int
    {
        return $this->votes;
    }

    public function setVotes(int $votes): self
    {
        $this->votes = $votes;

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
