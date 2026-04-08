<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\PanelBundle\Form;

use Novosga\Entity\PainelInterface;
use Novosga\Entity\ServicoUnidadeInterface;
use Novosga\Repository\ServicoUnidadeRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PainelFormType extends AbstractType
{
    public function __construct(
        private readonly ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        private readonly ServicosTransformer $transformer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nome', TextType::class, [
            'label' => 'label.nome',
            'constraints' => [
                new NotBlank(),
                new Length(['max' => 255]),
            ],
        ]);

        // Placeholder replaced in PRE_SET_DATA with unit-specific choices.
        $builder->add('servicos', ChoiceType::class, [
            'label' => 'label.servicos',
            'mapped' => false,
            'expanded' => true,
            'multiple' => true,
            'choices' => [],
            'choice_value' => fn (?ServicoUnidadeInterface $su) => $su?->getServico()?->getId(),
            'choice_label' => fn (?ServicoUnidadeInterface $su) => sprintf(
                '[%s] %s',
                $su?->getSigla(),
                $su?->getServico()?->getNome(),
            ),
            'constraints' => [
                new Count(min: 1),
            ],
        ]);

        // Populate unit-specific choices and pre-select existing services.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $painel = $event->getData();
            $form = $event->getForm();

            $unidade = $painel instanceof PainelInterface ? $painel->getUnidade() : null;

            $servicosUnidade = $unidade !== null
                ? $this->servicoUnidadeRepository->getAll($unidade)
                : [];

            $selected = $painel instanceof PainelInterface
                ? $this->transformer->transform($painel->getServicos(), $servicosUnidade)
                : [];

            $form->add('servicos', ChoiceType::class, [
                'label' => 'label.servicos',
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $servicosUnidade,
                'choice_value' => fn (?ServicoUnidadeInterface $su) => $su?->getServico()?->getId(),
                'choice_label' => fn (?ServicoUnidadeInterface $su) => sprintf(
                    '%s - %s',
                    $su?->getSigla(),
                    $su?->getServico()?->getNome(),
                ),
                'data' => $selected,
                'constraints' => [
                    new Count(min: 1),
                ],
            ]);
        });

        // Sync the selected ServicoUnidades back to the entity's PainelServico collection.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $painel = $event->getData();

            if (!$painel instanceof PainelInterface) {
                return;
            }

            /** @var ServicoUnidadeInterface[] $selectedSU */
            $selectedSU = $form->get('servicos')->getData() ?? [];

            // Index existing PainelServicos by servico ID.
            $existing = [];
            foreach ($painel->getServicos() as $ps) {
                $id = $ps->getServico()?->getId();
                if ($id !== null) {
                    $existing[$id] = $ps;
                }
            }

            // Index submitted ServicoUnidades by servico ID.
            $selected = [];
            foreach ($selectedSU as $su) {
                $id = $su->getServico()?->getId();
                if ($id !== null) {
                    $selected[$id] = $su;
                }
            }

            // Remove PainelServicos that are no longer selected.
            foreach ($existing as $servicoId => $ps) {
                if (!isset($selected[$servicoId])) {
                    $painel->removeServico($ps);
                }
            }

            // Add new PainelServicos for newly selected services.
            $toAdd = array_filter(
                $selected,
                fn (int $id) => !isset($existing[$id]),
                ARRAY_FILTER_USE_KEY,
            );

            foreach ($this->transformer->reverseTransform(array_values($toAdd)) as $ps) {
                $painel->addServico($ps);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PainelInterface::class,
        ]);
    }
}
