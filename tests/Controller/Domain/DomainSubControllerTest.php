<?php declare(strict_types=1);

namespace App\Tests\Controller\Domain;

use App\Tests\WebTestCase;

class DomainSubControllerTest extends WebTestCase
{
    use DomainFixturesTrait;

    public function testDomainSubController()
    {
        $client = static::createClient();

        $client->loginUser($this->getUserByUsername('testUser'));

        $this->createFixtures();

        $crawler = $client->request('GET', '/d/karab.in');

        $client->submit(
            $crawler->filter('.kbin-domains .kbin-sub')->selectButton('obserwuj')->form()
        );

        $client->followRedirect();

        $crawler = $client->request('GET', '/sub');

        $this->assertEquals(2, $crawler->filter('.kbin-entry-title-domain')->count());
    }

    public function testDomainSubCommentsController()
    {
        $client = static::createClient();

        $client->loginUser($this->getUserByUsername('testUser'));

        $this->createFixtures();

        $this->createEntryComment('comment1', $this->getEntryByTitle('karabin1'));
        $this->createEntryComment('comment2', $this->getEntryByTitle('karabin2'));
        $this->createEntryComment('comment3', $this->getEntryByTitle('google'));

        $crawler = $client->request('GET', '/d/karab.in');

        $client->submit(
            $crawler->filter('.kbin-domain-subscribe')->selectButton('obserwuj')->form()
        );

        $client->followRedirect();

        $crawler = $client->request('GET', '/sub/komentarze');

        $this->assertEquals(2, $crawler->filter('.kbin-entry-title-domain')->count());
    }
}