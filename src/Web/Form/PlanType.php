<?php

namespace App\Web\Form;

use App\Catalog\ShowtimeVersion;
use App\Planner\PlanRequest;
use App\Planner\TravelMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Planning form of the website (GET), filling a PlanRequest, which holds the validation rules (the API binds the
 * same PlanRequest from its query string).
 */
class PlanType extends AbstractType implements DataMapperInterface
{
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
                'attr' => ['data-geolocation-target' => 'city'],
            ])
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['data-geolocation-target' => 'position'],
            ])
            // Draw of the programmes (see ProgrammeSelector): the same seed, the same programmes.
            ->add('seed', HiddenType::class, [
                'required' => false,
                'invalid_message' => 'planner.seed.invalid',
            ])
            ->add('travelMode', EnumType::class, [
                'label' => 'planner.form.travel_mode',
                'class' => TravelMode::class,
                'choice_label' => static fn (TravelMode $mode): string => 'planner.form.travel_mode.'.$mode->value,
                'data' => TravelMode::Transit,
            ])
            ->add('films', IntegerType::class, [
                'label' => 'planner.form.films',
                'data' => 2,
            ])
            ->add('version', EnumType::class, [
                'label' => 'planner.form.version',
                'class' => ShowtimeVersion::class,
                'choice_label' => static fn (ShowtimeVersion $version): string => $version->label(),
                'required' => false,
                'placeholder' => 'planner.form.version_any',
                'help' => 'planner.form.version_help',
            ])
            ->add('acceptAds', CheckboxType::class, [
                'label' => 'planner.form.accept_ads',
                'required' => false,
                // A link written by hand may say "0" or "false" for no (a browser sends nothing).
                'false_values' => [null, '', '0', 'false'],
            ])
            ->setDataMapper($this);

        $builder->get('seed')->addModelTransformer(new CallbackTransformer(
            static fn (?int $seed): string => (string) $seed,
            static function (?string $seed): ?int {
                if (null === $seed) {
                    return null;
                }
                if (!ctype_digit($seed)) {
                    throw new TransformationFailedException('A seed is a natural number.');
                }

                return (int) $seed;
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['dates', 'cities']);
        $resolver->setAllowedTypes('dates', 'array');
        $resolver->setAllowedTypes('cities', 'array');
        $resolver->setDefaults([
            'data_class' => PlanRequest::class,
            // Built by mapFormsToData() from every field at once.
            'empty_data' => null,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'plan';
    }

    public function mapDataToForms(mixed $viewData, \Traversable $forms): void
    {
        // Reading a readonly object is like reading any other one.
        new DataMapper()->mapDataToForms($viewData, $forms);
    }

    /**
     * A readonly PlanRequest cannot be filled field by field: it is built once from all the fields,
     * named like its constructor arguments. An empty field keeps the default value of its argument.
     */
    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void
    {
        $arguments = array_map(static fn (FormInterface $field): mixed => $field->getData(), iterator_to_array($forms));
        $viewData = new PlanRequest(...array_filter($arguments, static fn (mixed $value): bool => null !== $value));
    }
}
