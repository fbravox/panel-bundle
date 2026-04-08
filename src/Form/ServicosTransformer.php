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

use Doctrine\Common\Collections\Collection;
use Novosga\Entity\PainelServicoInterface;
use Novosga\Entity\ServicoUnidadeInterface;
use Novosga\Repository\PainelServicoRepositoryInterface;

/**
 * Transforms between a Collection<PainelServicoInterface> (entity side)
 * and ServicoUnidadeInterface[] (form side).
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class ServicosTransformer
{
    public function __construct(
        private readonly PainelServicoRepositoryInterface $repository,
    ) {
    }

    /**
     * Collection<int, PainelServicoInterface> → ServicoUnidadeInterface[]
     *
     * Returns the ServicoUnidade entries whose Servico matches the existing PainelServicos.
     *
     * @param  Collection<int, PainelServicoInterface> $painelServicos
     * @param  ServicoUnidadeInterface[] $servicosUnidade
     * @return ServicoUnidadeInterface[]
     */
    public function transform(Collection $painelServicos, array $servicosUnidade): array
    {
        // Build servico_id → ServicoUnidadeInterface lookup
        $suMap = [];
        foreach ($servicosUnidade as $su) {
            $id = $su->getServico()?->getId();
            if ($id !== null) {
                $suMap[$id] = $su;
            }
        }

        $result = [];
        foreach ($painelServicos as $ps) {
            $id = $ps->getServico()?->getId();
            if ($id !== null && isset($suMap[$id])) {
                $result[] = $suMap[$id];
            }
        }

        return $result;
    }

    /**
     * ServicoUnidadeInterface[] → PainelServicoInterface[]
     *
     * Creates new PainelServico instances (without painel set).
     * The painel reference is set later by PainelInterface::addServico().
     *
     * @param  ServicoUnidadeInterface[] $servicosUnidade
     * @return PainelServicoInterface[]
     */
    public function reverseTransform(array $servicosUnidade): array
    {
        $className = $this->repository->getClassName();

        return array_map(function (ServicoUnidadeInterface $su) use ($className) {
            /** @var PainelServicoInterface $ps */
            $ps = new $className();
            $ps->setServico($su->getServico());

            return $ps;
        }, $servicosUnidade);
    }
}
