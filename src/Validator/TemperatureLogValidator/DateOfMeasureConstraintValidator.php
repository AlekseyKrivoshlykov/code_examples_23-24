<?php

namespace App\Validator\TemperatureLogValidator;

use App\Entity\Organization\Equipment;
use App\Entity\Log\TemperatureLog;
use App\Entity\Organization\EquipmentDateRange;
use Doctrine\Persistence\ManagerRegistry as PersistenceManagerRegistry;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class DateOfMeasureConstraintValidator extends ConstraintValidator
{
    /**
     * @var PersistenceManagerRegistry $doctrine
     */
    private $doctrine;

    /**
     * @var RequestStack $request
     */
    private $request;

   /**
     * @param RequestStack $_request
     * @param PersistenceManagerRegistry $_doctrine
     */
    public function __construct(RequestStack $_request, PersistenceManagerRegistry $_doctrine)
    {
        $this->request = $_request->getCurrentRequest();
        $this->doctrine = $_doctrine;
    }

    public function validate($value, Constraint $constraint)
    {
        $dateOfMeasure = $value->getFixedDate();

        $entityManager = $this->doctrine->getManager();
        $qbEquipment = $entityManager->getRepository(Equipment::class)->createQueryBuilder('eq');
        $qbEqDateRange = $entityManager->getRepository(EquipmentDateRange::class)->createQueryBuilder('edr');

        $equipmentEqTable = [];
        $equipmentEdrTable = [];

        if(null !== $value->getEquipment()) {
            $equipmentId = $value->getEquipment()->getId();
            $equipmentEqTable = $qbEquipment
                                        ->where('eq.id = :id')
                                        ->setParameter('id', $equipmentId)
                                        ->getQuery()
                                        ->getResult();

            $equipmentEdrTable = $qbEqDateRange
                                            ->where('edr.equipment = :id')
                                            ->setParameter('id', $equipmentId)
                                            ->getQuery()
                                            ->getResult();
        }
        
        $shutdownDate = null;
        foreach($equipmentEqTable as $item) {
            $shutdownDate = $item->getShutdownDate();
        }

        foreach($equipmentEdrTable as $element) {
            $dateFrom = $element->getDateFrom();
            $dateTo = $element->getDateTo();

            if($dateOfMeasure == $dateFrom || $dateOfMeasure == $dateTo || 
               ($dateOfMeasure > $dateFrom && $dateOfMeasure < $dateTo)) {
                $this->context->buildViolation($constraint->messageByDowntimeDates)
                              ->addViolation();
            }
        }
       
        if(null !== $shutdownDate) {
            if ($dateOfMeasure == $shutdownDate || $dateOfMeasure > $shutdownDate) {
                $this->context->buildViolation($constraint->messageByShutdownDate)
                              ->addViolation();
            }
        }
        
      
    }
}