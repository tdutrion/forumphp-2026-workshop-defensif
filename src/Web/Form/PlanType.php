<?php

namespace App\Web\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Planning form, shared by the website (GET) and the API.
 */
class PlanType extends AbstractType
{
    public const VERSIONS = ['planner.form.version.vf' => 'vf', 'planner.form.version.vost' => 'vost', 'planner.form.version.vo' => 'vo', 'planner.form.version.vfst' => 'vfst'];

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Day names in the language of the page ("Thursday, January 10, 2030", "jeudi 10 janvier 2030").
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'UTC');
        $dates = [];
        foreach ($options['dates'] as $date) {
            $dates[$formatter->format(new \DateTimeImmutable($date, new \DateTimeZone('UTC')))] = $date;
        }

        $cities = [];
        foreach ($options['cities'] as $city) {
            $cities[$city['name']] = $city['slug'];
        }

        $builder
            ->add('date', ChoiceType::class, [
                'label' => 'planner.form.date',
                'choices' => $dates,
                'choice_translation_domain' => false,
                'constraints' => [new NotBlank(message: 'planner.date.required')],
            ])
            ->add('from', TimeType::class, [
                'label' => 'planner.form.from',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
                'required' => false,
            ])
            ->add('until', TimeType::class, [
                'label' => 'planner.form.until',
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
                'required' => false,
            ])
            ->add('city', ChoiceType::class, [
                'label' => 'planner.form.city',
                'choices' => $cities,
                // City names come from the catalog: they are not translated.
                'choice_translation_domain' => false,
                'required' => false,
                'placeholder' => 'planner.form.city_placeholder',
                'autocomplete' => true,
            ])
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['data-geolocation-target' => 'position'],
            ])
            // Draw of the programmes (see ProgrammeSelector): the same seed, the same programmes.
            ->add('seed', HiddenType::class, [
                'required' => false,
                'constraints' => [new Regex(pattern: '/^\d{1,9}$/', message: 'planner.seed.invalid')],
            ])
            ->add('travelMode', ChoiceType::class, [
                'label' => 'planner.form.travel_mode',
                'choices' => ['planner.form.travel_mode.walking' => 'walking', 'planner.form.travel_mode.cycling' => 'cycling', 'planner.form.travel_mode.transit' => 'transit', 'planner.form.travel_mode.car' => 'car'],
                'data' => 'transit',
            ])
            ->add('films', IntegerType::class, [
                'label' => 'planner.form.films',
                'data' => 2,
                'constraints' => [new Range(min: 1, max: 8, notInRangeMessage: 'planner.films.range')],
            ])
            ->add('version', ChoiceType::class, [
                'label' => 'planner.form.version',
                'choices' => self::VERSIONS,
                'required' => false,
                'placeholder' => 'planner.form.version_any',
                'help' => 'planner.form.version_help',
            ])
            ->add('acceptAds', CheckboxType::class, [
                'label' => 'planner.form.accept_ads',
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
            $context->buildViolation('planner.location.required')->atPath('[city]')->addViolation();
        }

        // 'H:i' strings compare in time order; a range ending after midnight is refused.
        if (!empty($data['from']) && !empty($data['until']) && $data['until'] <= $data['from']) {
            $context->buildViolation('planner.time_range.order')->atPath('[until]')->addViolation();
        }
    }
}
