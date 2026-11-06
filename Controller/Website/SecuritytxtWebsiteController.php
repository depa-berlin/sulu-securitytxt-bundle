<?php

/*
 * This file is part of the Sulu Securitytxt bundle.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace BitExpert\Sulu\SecuritytxtBundle\Controller\Website;

use BitExpert\Sulu\SecuritytxtBundle\Entity\Securitytxt;
use BitExpert\Sulu\SecuritytxtBundle\Repository\SecuritytxtRepository;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SecuritytxtWebsiteController extends AbstractController
{
    public function __construct(
        private readonly SecuritytxtRepository $repository,
    ) {
    }

    public function getAction(Request $request): Response
    {
        $sulu = $request->attributes->get('_sulu');
        $webspace = $sulu instanceof RequestAttributes ? $sulu->getAttribute('webspace') : null;
        if (!$webspace instanceof Webspace) {
            throw $this->createNotFoundException();
        }

        $entity = $this->repository->findByWebspaceKey($webspace->getKey());
        if (!$entity instanceof Securitytxt) {
            throw $this->createNotFoundException();
        }

        // RFC 9116 date-time in UTC, e.g. 2030-12-31T00:00:00.000Z
        $expires = (new \DateTimeImmutable((string) $entity->getExpires(), new \DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s.v\Z');

        $fields = [
            'Contact' => $entity->getContact(),
            'Expires' => [$expires],
            'Encryption' => $entity->getEncryption(),
            'Acknowledgments' => $entity->getAcknowledgments(),
            'Policy' => $entity->getPolicy(),
            'Hiring' => $entity->getHiring(),
            'Preferred-Languages' => \array_filter([$entity->getPreferredLanguages()]),
            'Canonical' => $entity->getCanonical(),
        ];

        $content = '';
        foreach ($fields as $name => $values) {
            foreach ($values as $value) {
                $content .= $name . ': ' . $value . "\n";
            }
        }

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
