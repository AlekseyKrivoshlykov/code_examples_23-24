<?php

namespace App\Validator\TemperatureLogValidator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DateOfMeasureConstraint extends Constraint
{

    public $messageByShutdownDate = 'Дата замера не может совпадать с датой отключения оборудования
                        / дата замера не может быть установлена позже даты отключения оборудования.';
                        
    public $messageByDowntimeDates = 'Дата замера не может совпадать с датой/датами простоя оборудования.';

    public function validatedBy()
    {
        return static::class.'Validator';
    }

    public function getTargets()
    {
        // этот метод нужен для покрытия валидацией всего класса - в validate передаётся объект класса
        return self::CLASS_CONSTRAINT; 
    }
}