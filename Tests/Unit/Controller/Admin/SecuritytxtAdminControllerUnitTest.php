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

namespace BitExpert\Sulu\SecuritytxtBundle\Tests\Unit\Controller\Admin;

use BitExpert\Sulu\SecuritytxtBundle\Common\DoctrineListRepresentationFactory;
use BitExpert\Sulu\SecuritytxtBundle\Controller\Admin\SecuritytxtAdminController;
use BitExpert\Sulu\SecuritytxtBundle\Entity\Securitytxt;
use BitExpert\Sulu\SecuritytxtBundle\Repository\SecuritytxtRepository;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecuritytxtAdminControllerUnitTest extends TestCase
{
    private const VALID = [
        'contact' => "mailto:security@example.com\n\nhttps://example.com/contact\n",
        'expires' => '2030-12-31',
        'policy' => "https://example.com/de/security\r\nhttps://example.com/en/security",
        'preferred_languages' => 'de,en',
        'canonical' => 'https://www.example.com/.well-known/security.txt',
    ];

    public function testValidDataIsStoredAsLists(): void
    {
        [$response, $entity] = $this->put(self::VALID + ['webspace_key' => 'other']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['mailto:security@example.com', 'https://example.com/contact'], $entity->getContact());
        self::assertSame(['https://example.com/de/security', 'https://example.com/en/security'], $entity->getPolicy());
        self::assertSame([], $entity->getHiring());
        self::assertSame('de, en', $entity->getPreferredLanguages());
        self::assertSame('website', $entity->getWebspaceKey(), 'the webspace must not be taken from the request');
    }

    public function testInvalidDataIsRejected(): void
    {
        $cases = [
            'Contact is required' => ['contact', ''],
            'https URL with user info' => ['contact', 'https://mailto:security@example.com'],
            'invalid e-mail' => ['contact', 'mailto:security'],
            'tel with spaces' => ['contact', 'tel:+49 30 123'],
            'plain http' => ['policy', 'http://example.com/security'],
            'unknown scheme for Encryption' => ['encryption', 'ftp://example.com/key.asc'],
            'impossible date' => ['expires', '2030-02-31'],
            'not a language tag' => ['preferred_languages', 'de, german'],
        ];

        foreach ($cases as $case => [$field, $value]) {
            [$response, $entity] = $this->put([$field => $value] + self::VALID);

            self::assertSame(400, $response->getStatusCode(), $case);
            self::assertStringContainsString('"detail"', (string) $response->getContent(), $case);
            self::assertSame([], $entity->getContact(), $case.': nothing may be half-applied');
        }
    }

    /**
     * @param array<string, string> $data
     *
     * @return array{Response, Securitytxt}
     */
    private function put(array $data): array
    {
        $entity = (new Securitytxt())->setWebspaceKey('website');
        $repository = $this->createMock(SecuritytxtRepository::class);
        $repository->method('findById')->willReturn($entity);

        $controller = new SecuritytxtAdminController(
            $repository,
            $this->createMock(DoctrineListRepresentationFactory::class),
            $this->createMock(WebspaceManagerInterface::class),
        );
        $controller->setContainer(new Container());

        return [$controller->putAction(1, new Request(content: json_encode($data, \JSON_THROW_ON_ERROR))), $entity];
    }
}
