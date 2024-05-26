<?php

namespace App\Form;

use App\Entity\Action;
use App\Repository\ActionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Фильтр статистики раздела Опросы после загрузки чека
 */
class SurveyAfterReceiptType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', EntityType::class, [
                'label'         => 'Акция',
                'class'         => Action::class,
                'required'      => false,
                'query_builder' => function (ActionRepository $ar) {
                    return $ar->createQueryBuilder('a')
                                ->orderBy('a.createdAt', 'DESC')
                    ;
                },
                'placeholder'   => 'Общая статистика по акциям',
            ])
            ->add('dateStart', DateType::class, [
                'label'       => 'Дата начала',
                'widget'      => 'single_text',
                'required'    => false,
                'input'       => 'string',
                'row_attr'    => ['class' => 'field-date col-md-3',],
                'constraints' => [new Type(['type' => 'string'])],
            ])
            ->add('dateEnd', DateType::class, [
                'label'       => 'Дата окончания',
                'widget'      => 'single_text',
                'required'    => false,
                'input'       => 'string',
                'row_attr'    => ['class' => 'field-date col-md-3',],
                'constraints' => [new Type(['type' => 'string'])],
            ])
        ;
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data' => [
                'action'    => null,
                'dateStart' => null,
                'dateEnd'   => null,
            ],
        ]);
    }
}
