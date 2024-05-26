<?php

namespace App\Controller\Admin;

use SomeClasses;

class RaffleCrudController extends AbstractCrudController
{
    // выпилено всё, кроме кастомных методов

    /**
     * @Route("route", name="route_name")
     */
    public function updateWinnerDataFromSol(
        Request $request,
        ManagerRegistry $doctrine,
        RaffleRepository $raffleRepo,
        RaffleWinnerRepository $raffleWinnerRepo,
        UserWinnerDataRepository $userWinnerDataRepo,
        CrmSolApi $solApi,
    ): JsonResponse
    {
        $eManager = $doctrine->getManager();
        $raffleId = $request->get('raffleId');

        if (!$raffleId) {
            return $this->json([
                'status'  => 'error',
                'message' => 'noRaffleData',
            ]);
        }

        $raffle = $raffleRepo->find($raffleId);
        if ($raffle->getStatus() !==  RaffleStatusRegistry::STATUS_ENDED) {
            return $this->json([
                'status'  => 'error',
                'message' => 'raffleIsNotCompleted',
            ]);
        }

        $raffleWinners = $raffleWinnerRepo->findBy(['raffleId' => $raffleId]);
        if (empty($raffleWinners)) {
            return $this->json([
                'status'  => 'error',
                'message' => 'noRaffleWinners',
            ]);
        }

        $rawWinner = $raffleWinnerRepo->findOneBy(
            [
                'raffleId' => $raffleId,
                'checked'  => RaffleWinnerStatusRegistry::STATUS_UNCHECKED,
            ]
        );
        if (!$rawWinner) {
            $uncheckedWinners = 
                $raffleWinnerRepo->createQueryBuilder('rw')
                    ->join('rw.field1', 'f')
                    ->select('f.whiteId')
                    ->andWhere('rw.raffleId = :raffleId')
                    ->andWhere('rw.checked = :checked')
                    ->setParameter('raffleId', $raffleId)
                    ->setParameter('checked', RaffleWinnerStatusRegistry::STATUS_NO_DATA)
                    ->getQuery()
                    ->getSingleColumnResult()
            ;
            
            if (empty($uncheckedWinners)) {
                $raffle->setChecked(true);
                $eManager->persist($raffle);
                $eManager->flush();
            }

            // обновляем статусы проверки победителей, чтобы кнопка работала повторно
            $this->unmarkChecked($raffleWinners, $eManager);

            return $this->json([
                'status'  => 'ok',
                'uncheckedWinners' => !empty($uncheckedWinners) ? $uncheckedWinners : false,
            ]);
        }

        $whiteId = $rawWinner->getUser()->getWhiteId();

        try {
            $authToken   = $solApi->auth();
            $solResponse = $solApi->getWinner($whiteId, $authToken);

            if ((isset($solResponse['code']) && $solResponse['code'] === 100535) ||
                !isset($solResponse['userData'])
            ) {
                $rawWinner->setChecked(RaffleWinnerStatusRegistry::STATUS_NO_DATA);
                $eManager->persist($rawWinner);
                $eManager->flush();

                return $this->json([
                    'status' => 'continue',
                ]);
            }

            $userId = $rawWinner->getUser()->getId();
            $userWinnerData = $userWinnerDataRepo->findOneBy(['field1' => $userId]);

            if (!$userWinnerData) {
                $userWinnerData = new UserWinnerData();
                $userWinnerData->setUser($rawWinner->getUser());
            }

            $firstName = $solResponse['userData']['givenName'] ?? '';
            $lastName  = $solResponse['userData']['surname'] ?? '';
            $email     = $solResponse['userData']['email'] ?? '';

            $userWinnerData->setFirstName($firstName);
            $userWinnerData->setLastName($lastName);
            $userWinnerData->setEmail($email);
            $rawWinner->setChecked(RaffleWinnerStatusRegistry::STATUS_CHECKED);

            $eManager->persist($userWinnerData);
            $eManager->persist($rawWinner);
            $eManager->flush();

            return $this->json([
                'status' => 'continue',
            ]);
        } catch (Error $error) {
            return $this->json([
                'status'  => 'error',
                'message' => $error->getMessage(),
            ]);
        }
    }

    /**
     * @param $raffleWinners
     * @return void
     */
    public function unmarkChecked($raffleWinners, $eManager): void
    {
        foreach ($raffleWinners as $winner) {
            $winner->setChecked(RaffleWinnerStatusRegistry::STATUS_UNCHECKED);
            $eManager->persist($winner);
        }

        $eManager->flush();
    }

    /**
     * Уведомление всех победителей розыгрыша
     * 
     * @Route("route", name="route_name")
     */
    public function notifyRaffleWinner(
        Request $request, ManagerRegistry $managerRegistry,
        Notifier $notifier,
    ): JsonResponse
    {
        $raffleId = $request->request->get('raffleId');
        if(!$raffleId) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Данные не были получены сервером, пожалуйста попробуйте заново',
            ]);
        }

        /** @var \App\Repository\RaffleWinnerRepository $raffleWinnerRepo */
        $raffleWinnerRepo = $managerRegistry->getRepository(RaffleWinner::class);
        /** @var \App\Repository\UserActivityRepository $userActivityRepo */
        $userActivityRepo = $managerRegistry->getRepository(UserActivity::class);
        
        $raffleWinners = $raffleWinnerRepo->findBy(['raffleId' => $raffleId]);

        if(empty($raffleWinners)) {
            return $this->json([
                'status'  => 'error',
                'message' => 'У данного розыгрыша нет победителей',
            ]);
        }

        $allWinnersNotified = true;
        foreach ($raffleWinners as $winner) {
            if (!$winner->isNotified()) {
                $allWinnersNotified = false;
                $notification = Notification::create(
                    //текст не выводится, его перебивает вёрстка
                    'Text',
                    $winner->getAction(),
                    $winner->getUser(),
                    NotifierWidget::DESIGN_WINNER
                );
                $notifier->push($notification);
    
                $winner->setIsNotified(true);
                $raffleWinnerRepo->add($winner, true);

                $activity = UserActivity::create(
                    UserActivityTypeRegistry::TYPE_WINNER_SELECTED,
                    $winner->getUser(),
                    $winner->getAction(),
                    $winner->getTargetId(),
                    $winner->getPrize()->getTitle(),
                );
    
                $userActivityRepo->add($activity, true);
            }
        }

        if ($allWinnersNotified) {
            return $this->json([
                'status'  => 'success',
                'message' => 'Победители были уведомлены ранее',
            ]);
        }

        return $this->json([
            'status'  => 'success',
            'message' => 'Победители уведомлены',
        ]);
    }
}
