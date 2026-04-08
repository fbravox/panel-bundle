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

namespace Novosga\PanelBundle\Controller;

use Exception;
use Novosga\Entity\PainelInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\PanelBundle\Form\PainelFormType;
use Novosga\PanelBundle\NovosgaPanelBundle;
use Novosga\Service\PainelServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route('/', name: 'novosga_panel_')]
class DefaultController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PainelServiceInterface $service): Response
    {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $paineis = $service->findByUnidade($unidade);

        return $this->render('@NovosgaPanel/default/index.html.twig', [
            'paineis' => $paineis,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        PainelServiceInterface $service,
        TranslatorInterface $translator,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $entity = $service->build()->setUnidade($unidade);

        return $this->form($request, $service, $translator, $entity);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        PainelServiceInterface $service,
        TranslatorInterface $translator,
        int $id,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        /** @var PainelInterface|null */
        $entity = $service->getById($id);
        if (!$entity || $entity->getUnidade()?->getId() !== $unidade->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->form($request, $service, $translator, $entity);
    }

    private function form(
        Request $request,
        PainelServiceInterface $service,
        TranslatorInterface $translator,
        PainelInterface $entity,
    ): Response {
        $form = $this
            ->createForm(PainelFormType::class, $entity)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->save($entity);

                $this->addFlash('success', $translator->trans(
                    'label.add_success',
                    [],
                    NovosgaPanelBundle::getDomain(),
                ));

                return $this->redirectToRoute('novosga_panel_edit', [
                    'id' => $entity->getId(),
                ]);
            } catch (Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@NovosgaPanel/default/form.html.twig', [
            'entity' => $entity,
            'form' => $form,
        ]);
    }
}
