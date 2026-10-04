<?php

namespace App\Web\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Living style guide: every component of the design system, with the states of a form.
 * Developer page, in English only, showing no data.
 */
class DesignSystemController extends AbstractController
{
    #[Route('/design-system', name: 'app_design_system', methods: ['GET'])]
    public function index(): Response
    {
        $form = $this->createFormBuilder(null, ['csrf_protection' => false])
            ->add('name', TextType::class, ['label' => 'Text field', 'required' => false])
            ->add('invalid', TextType::class, ['label' => 'Field with an error', 'required' => false])
            ->add('choice', ChoiceType::class, ['label' => 'Choice', 'choices' => ['First' => 1, 'Second' => 2]])
            ->add('agree', CheckboxType::class, ['label' => 'Checkbox', 'required' => false])
            ->getForm();
        $form->get('invalid')->addError(new FormError('Example of an error message.'));

        return $this->render('design_system/index.html.twig', ['form' => $form]);
    }
}
