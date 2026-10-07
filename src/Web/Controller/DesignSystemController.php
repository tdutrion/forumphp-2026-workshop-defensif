<?php

declare(strict_types=1);

namespace App\Web\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Living style guide: every component of the design system and the states of a form, in the light
 * and the dark theme side by side. Developer page, in English only, showing no data.
 */
class DesignSystemController extends AbstractController
{
    #[Route('/design-system', name: 'app_design_system', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('design_system/index.html.twig', [
            // One form per preview: a form view can only be rendered once.
            'forms' => ['light' => $this->exampleForm('light')->createView(), 'dark' => $this->exampleForm('dark')->createView()],
        ]);
    }

    private function exampleForm(string $name): FormInterface
    {
        $form = $this->container->get('form.factory')->createNamedBuilder($name, options: [
            'csrf_protection' => false,
            // Developer page, in English only: the example labels are not translation keys.
            'translation_domain' => false,
        ])
            ->add('name', TextType::class, ['label' => 'Text field', 'required' => false])
            ->add('invalid', TextType::class, ['label' => 'Field with an error', 'required' => false])
            ->add('choice', ChoiceType::class, ['label' => 'Choice', 'choices' => ['First' => 1, 'Second' => 2]])
            ->add('autocomplete', ChoiceType::class, [
                'label' => 'Autocomplete',
                'choices' => ['Dijon' => 'dijon', 'Lyon' => 'lyon', 'Paris' => 'paris'],
                'required' => false,
                'placeholder' => 'Choose a city',
                'autocomplete' => true,
            ])
            ->add('time', TimeType::class, ['label' => 'Time', 'widget' => 'single_text', 'required' => false])
            ->add('agree', CheckboxType::class, ['label' => 'Switch (a yes-or-no choice)', 'required' => false])
            ->getForm();
        $form->get('invalid')->addError(new FormError('Example of an error message.'));

        return $form;
    }
}
