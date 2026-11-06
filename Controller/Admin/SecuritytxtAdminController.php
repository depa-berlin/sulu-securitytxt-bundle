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

namespace BitExpert\Sulu\SecuritytxtBundle\Controller\Admin;

use BitExpert\Sulu\SecuritytxtBundle\Admin\SecuritytxtAdmin;
use BitExpert\Sulu\SecuritytxtBundle\Common\DoctrineListRepresentationFactory;
use BitExpert\Sulu\SecuritytxtBundle\Entity\Securitytxt;
use BitExpert\Sulu\SecuritytxtBundle\Repository\SecuritytxtRepository;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Fields that may occur multiple times (Contact, Encryption, ...) are exchanged with the admin form
 * as text with one entry per line.
 *
 * @phpstan-type SecuritytxtData array{
 *     id: int|null,
 *     webspace_key: string|null,
 *     contact: string,
 *     expires: string,
 *     encryption: string,
 *     acknowledgments: string,
 *     policy: string,
 *     hiring: string,
 *     preferred_languages: string,
 *     canonical: string,
 * }
 */
#[Route('/admin/api')]
class SecuritytxtAdminController extends AbstractController implements SecuredControllerInterface
{
    private const WEB_URI = ['https://'];

    public function __construct(
        private readonly SecuritytxtRepository $repository,
        private readonly DoctrineListRepresentationFactory $doctrineListRepresentationFactory,
        private readonly WebspaceManagerInterface $webspaceManager,
    ) {
    }

    /**
     * Sulu's SuluSecurityListener checks this context per HTTP method:
     * GET = view, POST = add, PUT = edit, DELETE = delete.
     */
    public function getSecurityContext(): string
    {
        return SecuritytxtAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return null;
    }

    #[Route(path: '/securitytxt/{id}', name: 'bitexpert.get_securitytxt', methods: ['GET'])]
    public function getAction(int $id): Response
    {
        $entity = $this->repository->findById($id);
        if (!$entity instanceof Securitytxt) {
            throw $this->createNotFoundException();
        }

        return $this->json($this->getDataForEntity($entity));
    }

    #[Route(path: '/securitytxt/{id}', name: 'bitexpert.put_securitytxt', methods: ['PUT'])]
    public function putAction(int $id, Request $request): Response
    {
        $entity = $this->repository->findById($id);
        if (!$entity instanceof Securitytxt) {
            throw $this->createNotFoundException();
        }

        return $this->save($entity, $request, 200);
    }

    #[Route(path: '/securitytxt', name: 'bitexpert.post_securitytxt', methods: ['POST'])]
    public function postAction(Request $request, #[MapQueryParameter] string $webspace): Response
    {
        if (null === $this->webspaceManager->findWebspaceByKey($webspace)) {
            return $this->badRequest(\sprintf('Unknown webspace "%s".', $webspace));
        }

        if (null !== $this->repository->findByWebspaceKey($webspace)) {
            return $this->badRequest('This webspace already has a security.txt, please edit the existing entry.');
        }

        return $this->save((new Securitytxt())->setWebspaceKey($webspace), $request, 201);
    }

    #[Route(path: '/securitytxt/{id}', name: 'bitexpert.delete_securitytxt', methods: ['DELETE'])]
    public function deleteAction(int $id): Response
    {
        $this->repository->remove($id);

        return $this->json(null, 204);
    }

    #[Route(path: '/securitytxt', name: 'bitexpert.get_securitytxt_list', methods: ['GET'])]
    public function getListAction(#[MapQueryParameter] string $webspace): Response
    {
        $listRepresentation = $this->doctrineListRepresentationFactory->createDoctrineListRepresentation(
            Securitytxt::RESOURCE_KEY,
            ['webspace_key' => $webspace],
        );

        return $this->json($listRepresentation->toArray());
    }

    /**
     * @return SecuritytxtData
     */
    protected function getDataForEntity(Securitytxt $entity): array
    {
        return [
            'id' => $entity->getId(),
            'webspace_key' => $entity->getWebspaceKey(),
            'contact' => \implode("\n", $entity->getContact()),
            'expires' => $entity->getExpires() ?? '',
            'encryption' => \implode("\n", $entity->getEncryption()),
            'acknowledgments' => \implode("\n", $entity->getAcknowledgments()),
            'policy' => \implode("\n", $entity->getPolicy()),
            'hiring' => \implode("\n", $entity->getHiring()),
            'preferred_languages' => $entity->getPreferredLanguages() ?? '',
            'canonical' => \implode("\n", $entity->getCanonical()),
        ];
    }

    /**
     * Validates everything against RFC 9116 before setting anything, so an invalid request never
     * half-updates the entity. The webspace is not read from the data: it is fixed on creation.
     *
     * @param array<mixed> $data
     *
     * @throws \InvalidArgumentException with a message for the editor
     */
    protected function mapDataToEntity(array $data, Securitytxt $entity): void
    {
        $contact = $this->uriList($data, 'contact', ['mailto:', 'tel:', 'https://']);
        if ([] === $contact) {
            throw new \InvalidArgumentException('Contact: at least one entry is required.');
        }

        $expires = $this->text($data, 'expires');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $expires);
        if (false === $date || $date->format('Y-m-d') !== $expires) {
            throw new \InvalidArgumentException('Expires: a valid date is required.');
        }

        $languages = \array_values(\array_filter(
            \array_map(trim(...), \explode(',', $this->text($data, 'preferred_languages'))),
            static fn (string $tag): bool => '' !== $tag,
        ));
        foreach ($languages as $tag) {
            if (1 !== \preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i', $tag)) {
                throw new \InvalidArgumentException(\sprintf('Preferred-Languages: "%s" is not a language tag like "de" or "en-US".', $tag));
            }
        }

        $encryption = $this->uriList($data, 'encryption', ['https://', 'dns:', 'openpgp4fpr:']);
        $acknowledgments = $this->uriList($data, 'acknowledgments', self::WEB_URI);
        $policy = $this->uriList($data, 'policy', self::WEB_URI);
        $hiring = $this->uriList($data, 'hiring', self::WEB_URI);
        $canonical = $this->uriList($data, 'canonical', self::WEB_URI);

        $entity->setContact($contact)
            ->setExpires($expires)
            ->setEncryption($encryption)
            ->setAcknowledgments($acknowledgments)
            ->setPolicy($policy)
            ->setHiring($hiring)
            ->setPreferredLanguages([] === $languages ? null : \implode(', ', $languages))
            ->setCanonical($canonical);
    }

    private function save(Securitytxt $entity, Request $request, int $status): Response
    {
        try {
            $this->mapDataToEntity($request->toArray(), $entity);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        }

        $this->repository->save($entity);

        return $this->json($this->getDataForEntity($entity), $status);
    }

    /**
     * Sulu's form overlay shows "detail" to the editor.
     */
    private function badRequest(string $detail): JsonResponse
    {
        return $this->json(['title' => 'Invalid security.txt', 'detail' => $detail], 400);
    }

    /**
     * @param array<mixed> $data
     */
    private function text(array $data, string $field): string
    {
        $value = $data[$field] ?? '';
        if (!\is_string($value)) {
            throw new \InvalidArgumentException(\sprintf('%s: text expected.', \ucfirst($field)));
        }

        return \trim($value);
    }

    /**
     * One URI per line, empty lines are dropped.
     *
     * @param array<mixed> $data
     * @param list<string> $schemes
     *
     * @return list<string>
     */
    private function uriList(array $data, string $field, array $schemes): array
    {
        $uris = \array_values(\array_filter(
            \array_map(trim(...), \preg_split('/\R/', $this->text($data, $field)) ?: []),
            static fn (string $line): bool => '' !== $line,
        ));

        foreach ($uris as $uri) {
            if (!self::isValidUri($uri, $schemes)) {
                throw new \InvalidArgumentException(\sprintf('%s: "%s" is not valid, allowed are %s URIs.', \ucfirst($field), $uri, \implode(' ', $schemes)));
            }
        }

        return $uris;
    }

    /**
     * @param list<string> $schemes
     */
    private static function isValidUri(string $uri, array $schemes): bool
    {
        foreach ($schemes as $scheme) {
            if (0 !== \stripos($uri, $scheme)) {
                continue;
            }

            return match ($scheme) {
                'mailto:' => false !== \filter_var(\strtok(\substr($uri, \strlen($scheme)), '?'), \FILTER_VALIDATE_EMAIL),
                // no user info: rejects mix-ups like "https://mailto:security@example.com"
                'https://' => false !== \filter_var($uri, \FILTER_VALIDATE_URL) && null === \parse_url($uri, \PHP_URL_USER),
                // tel:, dns:, openpgp4fpr:
                default => \strlen($uri) > \strlen($scheme) && 1 === \preg_match('/^\S+$/', $uri),
            };
        }

        return false;
    }
}
