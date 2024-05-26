<?php

namespace App\Controller;

use SomeClasses;

/**
 * Подтверждение почты в ЛК
 */
class ConfirmEmailController extends AbstractController
{
    /**
     * @param \Doctrine\Persistence\ManagerRegistry $managerRegistry
     * @param \App\Service\CrmSolApi $solApi
     */
    public function __construct(
        protected ManagerRegistry $managerRegistry,
        protected CrmSolApi $solApi,
    )
    {
    }

    /**
     * @Route("route", name="route_name")
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \App\Repository\UserDataRepository $userDataRepo
     * @param \App\Repository\UserRepository $userRepo
     * @return \Symfony\Component\HttpFoundation\Response
     */

    public function confirmateEmail(
        Request $request, UserDataRepository $userDataRepo, UserRepository $userRepo,
    ): Response
    {   
        $type = CrmSolApi::IDENTIFIER_TYPE_EMAIL;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('user_mail');

            if (!$email) {
                return $this->json([
                    'status'  => 'error',
                    'message' => 'Что-то пошло не так. Пожалуйста, попробуйте снова',
                ]);
            }

            $this->solApi->auth();
            $this->solApi->setTicketGeneratorId(3);

            $ticket = $this->solApi->ticketGenerate($email, $type);

            if (!$ticket) {
                return $this->json([
                    'status'  => 'error',
                    'message' => 'Ошибка генерирования ссылки. Пожалуйста, попробуйте снова',
                ]);
            }

            if ((int) $ticket['code'] !== 0) {
                return $this->json([
                    'status'  => 'error',
                    'message' => $this->solApi->describeError($ticket['code']),
                ]);
            }

            return $this->json([
                'status'  => 'success',
                'message' => 'На указанный адрес электронной почты отправлена ссылка, перейдите по ней, чтобы подтвердить электронную почту',
            ]);
        } else {
            $identifier = $request->get('identifierValue');
            $code       = $request->get('value');

            if (!$identifier || !$code) {
                return $this->render('confirmation-mail.html.twig', 
                    [ 'status'  => 'error',
                      'message' => 'Адрес электронной почты и/или код подтверждения не был указан. Пожалуйста, попробуйте снова',
                    ],
                );
            }
            
            $this->solApi->auth();
            $this->solApi->setTicketGeneratorId(3);

            $solRes = $this->solApi->ticketConfirm($identifier, $code, $type);

            if (!$solRes) {
                return $this->render('confirmation-mail.html.twig', 
                    [ 'status'  => 'error',
                      'message' => 'Ошибка подтверждения электронного адреса. Пожалуйста, попробуйте снова',
                    ],
                );
            }

            $solCode = (int) $solRes['code'];

            if ($solCode !== 0) {
                $message = '';

                if ($solCode === 3) {
                    $message = 'alreadyConfirm';
                } else if ($solCode === 4) {
                    $message = 'linkIsNotValid';
                } else {
                    $message = 'Другая ошибка АПИ';
                }

                return $this->render('confirmation-mail.html.twig', 
                    [ 'status'  => 'error',
                      'message' => $message,
                    ],
                );
            }

            $userData = $userDataRepo->findOneBy(['email' => $identifier]);
            $userData->setIsEmailConfirmed(true);

            $em = $this->managerRegistry->getManager();
            $em->persist($userData);
            $em->flush();

            if (!$userData->isEmailConfirmed()) {
                return $this->render('confirmation-mail.html.twig', 
                    [ 'status'  => 'error',
                      'message' => 'Ошибка подтверждения электронного адреса. Пожалуйста, попробуйте снова',
                    ],
                );
            }

            // чтобы не слетала аутентификация после перехода по ссылке-подтверждении
            $userId = $userData->getUserId();
            $user   = $userRepo->find($userId);
            $this->authenticateUser($user);

            return $this->render('confirmation-mail.html.twig', 
                    [ 'status'  => 'success',
                      'message' => 'mailIsConfirm',
                    ],
            );
        }
    }

    /**
     * @param \App\Entity\User $user
     */
    public function authenticateUser(User $user)
    {
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->container->get('security.token_storage')->setToken($token);
        $this->container->get('session')->set('_security_main', serialize($token));
    }
}
