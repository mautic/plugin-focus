<?php

declare(strict_types=1);

/*
 * @copyright   2021 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticFocusBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PageBundle\Entity\Redirect;
use MauticPlugin\MauticFocusBundle\Entity\Focus;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PublicControllerTest extends MauticMysqlTestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGenerateActionWithContactTokenInLinkUrl(): void
    {
        $linkUrl = 'https://{contactfield=site_url}/tour';
        $focus   = new Focus();
        $focus->setName('Test');
        $focus->setType('link');
        $focus->setStyle('modal');
        $focus->setProperties([
            'content' => [
                'link_text' => 'Link text',
                'link_url'  => $linkUrl,
            ],
        ]);
        $this->em->persist($focus);
        $this->em->flush();
        $this->em->clear();

        $this->client->request(Request::METHOD_GET, sprintf('/focus/%s.js', $focus->getId()));
        $content = $this->client->getResponse()->getContent();

        $redirects = $this->em->getRepository(Redirect::class)->findAll();
        Assert::assertCount(1, $redirects);

        /** @var Redirect $redirect */
        $redirect = reset($redirects);
        Assert::assertSame($linkUrl, $redirect->getUrl());

        $url = $this->router->generate('mautic_url_redirect', ['redirectId' => $redirect->getRedirectId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $url = $this->container->get('templating.engine.php')->escape($url, 'js');
        Assert::assertStringContainsString($url, $content);
    }
}
