<?php
namespace App\Listener;

use App\Entity\UserData;
use Doctrine\ORM\Event\PostUpdateEventArgs;

class UserChangeListener
{
    /**
     * @param \Doctrine\ORM\Event\PostUpdateEventArgs $args
     * @return void
     */
    public function postUpdate(PostUpdateEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof UserData && $args->hasChangedField('email')) {
            $manager = $args->getObjectManager();
            $entity->setIsEmailConfirmed(false);
            $manager->persist($entity);
            $manager->flush();
        }
    }
}
