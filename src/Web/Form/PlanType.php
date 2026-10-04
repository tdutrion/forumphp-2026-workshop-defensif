<?php

namespace App\Web\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Planning form, shared by the website (GET) and the API.
 */
class PlanType extends AbstractType
{
    public const VERSIONS = ['VF' => 'vf', 'VOST' => 'vost', 'VO' => 'vo', 'VFST' => 'vfst'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dates = [];
        foreach ($options['dates'] as $date) {
            $dates[date('d/m/Y', strtotime($date))] = $date;
        }

        $cities = [];
        foreach ($options['cities'] as $city) {
            $cities[$city['name']] = $city['slug'];
        }

        $builder
            ->add('date', ChoiceType::class, [
                'label' => 'Date',
                'choices' => $dates,
                'constraints' => [new NotBlank(message: 'Choose a date.')],
            ])
            ->add('city', ChoiceType::class, [
                'label' => 'City',
                'choices' => $cities,
                'required' => false,
                'placeholder' => 'Choose a city',
                'autocomplete' => true,
            ])
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['data-geolocation-target' => 'position'],
            ])
            ->add('radius', IntegerType::class, [
                'label' => 'Radius (km)',
                'data' => 10,
                'constraints' => [new Range(min: 1, max: 50, notInRangeMessage: 'The radius must be between {{ min }} and {{ max }} km.')],
            ])
            ->add('films', IntegerType::class, [
                'label' => 'Number of films',
                'data' => 3,
                'constraints' => [new Range(min: 2, max: 5, notInRangeMessage: 'Choose between {{ min }} and {{ max }} films.')],
            ])
            ->add('version', ChoiceType::class, [
                'label' => 'Version',
                'choices' => self::VERSIONS,
                'required' => false,
                'placeholder' => 'Any',
            ])
            ->add('acceptAds', CheckboxType::class, [
                'label' => 'I accept arriving during the ads (15 minutes)',
                'required' => false,
                // API clients send "0" or "false" to say no (a browser sends nothing).
                'false_values' => [null, '', '0', 'false'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['dates', 'cities']);
        $resolver->setAllowedTypes('dates', 'array');
        $resolver->setAllowedTypes('cities', 'array');
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'constraints' => [new Callback($this->validateLocation(...))],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'plan';
    }

    public function validateLocation(?array $data, ExecutionContextInterface $context): void
    {
        if (empty($data['city']) && empty($data['position'])) {
            $context->buildViolation('Choose a city or use your position.')->atPath('[city]')->addViolation();
        }
    }
}
