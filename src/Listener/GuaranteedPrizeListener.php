<?php
namespace App\Listener;

use App\Entity\Notification;
use App\Entity\Receipt;
use App\Entity\UserActivity;
use App\Registry\ReceiptStatusInnerRegistry;
use App\Registry\UserActivityTypeRegistry;
use App\Repository\UserActivityRepository;
use App\Service\Notifier;
use App\Widget\NotifierWidget;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class GuaranteedPrizeListener
{
    /**
     * @var \App\Entity\UserActivity|null
     */
    protected $activity;

    /**
     * @var \App\Entity\Notification|null
     */
    protected $notification;

    public function __construct(
        protected UserActivityRepository $userActivityRepo,
        protected Notifier $notifier,
    )
    {
    }

    /**
     * @param \Doctrine\ORM\Event\PreUpdateEventArgs $args
     * @return void
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Receipt && $args->hasChangedField('statusInner') &&
            $args->getNewValue('statusInner') === ReceiptStatusInnerRegistry::STATUS_ACCEPTED
        ) 
        {
            $action = $entity->getAction();
            if ($action->getPrizeName() && $action->getPrizeLink()) {
                $userId  = $entity->getUser()->getId();
                $actionId = $action->getId();

                $hasActivity = $this->userActivityRepo->findOneBy([
                    'type'     => UserActivityTypeRegistry::TYPE_GUARANTEED_PRIZE,
                    'userId'   => $userId,
                    'actionId' => $actionId,
                ]);

                if (!$hasActivity) {
                    $info = '<a href="' . $action->getPrizeLink() . '" ' . 'target="_blank">' . 
                            $action->getPrizeName() . '</a>'
                    ;

                    $this->activity = UserActivity::create(
                        UserActivityTypeRegistry::TYPE_GUARANTEED_PRIZE,
                        $entity->getUser(),
                        $entity->getAction(),
                        $entity->getId(),
                        $info
                    );

                    $this->notification = Notification::create(
                        $info,
                        $entity->getAction(),
                        $entity->getUser(),
                        NotifierWidget::DESIGN_GUARANT_PRIZE
                    );
                }
            }
        }
    }

    /**
     * @param \Doctrine\ORM\Event\PostUpdateEventArgs $args
     * @return void
     */
    public function postUpdate(PostUpdateEventArgs $args)
    {
        if ($this->activity) {
            $this->userActivityRepo->add($this->activity, true);
        }

        if ($this->notification) {
            $this->notifier->push($this->notification);
        }
    }
}
