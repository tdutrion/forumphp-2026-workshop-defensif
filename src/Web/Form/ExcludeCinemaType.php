<?php

namespace App\Web\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Picks a cinema to exclude from the settings: an autocomplete over the open cinemas, by city.
 */
class ExcludeCinemaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('cinema', ChoiceType::class, [
            'label' => 'settings.exclude_cinema.label',
            'choices' => $options['cinemas'],
            // Cinema and city names come from the catalog: they are not translated.
            'choice_translation_domain' => false,
            'placeholder' => 'settings.exclude_cinema.placeholder',
            'autocomplete' => true,
            'invalid_message' => 'settings.exclude_cinema.invalid',
            'constraints' => [new NotBlank(message: 'settings.exclude_cinema.invalid')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('cinemas');
        $resolver->setAllowedTypes('cinemas', 'array');
    }
}
