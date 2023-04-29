<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Magazine;
use App\Entity\User;
use App\Exception\InvalidApPostException;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/*
 * source:
 * https://github.com/aaronpk/Nautilus/blob/master/app/ActivityPub/HTTPSignature.php
 * https://github.com/pixelfed/pixelfed/blob/dev/app/Util/ActivityPub/HttpSignature.php
 */

class ApHttpClient
{
    public const TIMEOUT = 5;

    public function __construct(
        private readonly PersonFactory $personFactory,
        private readonly GroupFactory $groupFactory,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository
    ) {
    }

    public function getActivityObject(string $url, bool $decoded = true): array|string|null
    {

        $resp = $this->cache->get('ap_'.hash('sha256', $url), function (ItemInterface $item) use ($url) {
            $this->logger->info("ApHttpClient:getActivityObject:url: {$url}");

            try {
                $client = new CurlHttpClient();
                $r = $client->request('GET', $url, [
                    'timeout' => self::TIMEOUT,
                    'headers' => [
                        'Accept' => 'application/activity+json,application/ld+json,application/json',
                        'User-Agent' => 'kbinBot v0.1 - https://kbin.pub',
                    ],
                ])->getContent();
            } catch (\Exception $e) {
                throw $e;
            }

            $item->expiresAt(new \DateTime('+1 hour'));

            return $r;
        });

        if (!$resp) {
            return null;
        }

        return $decoded ? json_decode($resp, true) : $resp;
    }

    public function getInboxUrl(string $apProfileId): string
    {
        $actor = $this->getActorObject($apProfileId);

        return $actor['endpoints']['sharedInbox'] ?? $actor['inbox'];
    }

    public function getActorObject(string $apProfileId): ?array
    {
        $resp = $this->cache->get(
            'ap_'.hash('sha256', $apProfileId),
            function (ItemInterface $item) use ($apProfileId) {
                $this->logger->info("ApHttpClient:getActorObject:url: {$apProfileId}");

                try {
                    $client = new CurlHttpClient();
                    $r = $client->request('GET', $apProfileId, [
                        'timeout' => self::TIMEOUT,
                        'headers' => [
                            'Accept' => 'application/activity+json,application/ld+json,application/json',
                            'User-Agent' => 'kbinBot v0.1 - https://kbin.pub',
                        ],
                    ]);
                    if ($r->getStatusCode() === 404 || $r->getStatusCode() === 410) {
                        if ($user = $this->userRepository->findOneByApProfileId($apProfileId)) {
                            $user->apDeletedAt = new \DateTime();
                            $this->userRepository->save($user, true);
                        }
                        if ($magazine = $this->magazineRepository->findOneByApProfileId($apProfileId)) {
                            $magazine->apDeletedAt = new \DateTime();
                            $this->userRepository->save($user, true);
                        }
                    }
                } catch (\Exception $e) {
                    if ($user = $this->userRepository->findOneByApProfileId($apProfileId)) {
                        $user->apTimeoutAt = new \DateTime();
                        $this->userRepository->save($user, true);
                    }
                    if ($magazine = $this->magazineRepository->findOneByApProfileId($apProfileId)) {
                        $magazine->apTimeoutAt = new \DateTime();
                        $this->magazineRepository->save($user, true);
                    }
                    throw $e;
                }

                $item->expiresAt(new \DateTime('+1 hour'));

                return $r->getContent();
            }
        );

        return $resp ? json_decode($resp, true) : null;
    }

    public function post(string $url, User|Magazine $actor, ?array $body = null): void
    {
        $cacheKey = 'ap_'.hash('sha256', $url.':'.$body['id']);

        if ($this->cache->hasItem($cacheKey)) {
            return;
        }

        $this->logger->info("ApHttpClient:post:url: {$url}");
        $this->logger->info('ApHttpClient:post:body '.json_encode($body ?? []));

        $client = new CurlHttpClient();
        $req = $client->request('POST', $url, [
            'timeout' => self::TIMEOUT,
            'body' => json_encode($body),
            'headers' => $this->getHeaders($url, $actor, $body),
        ]);

        if (!str_starts_with((string)$req->getStatusCode(), '2')) {
            throw new InvalidApPostException("Post fail: {$url}, ".json_encode($body));
        }

        // build cache
        $item = $this->cache->getItem($cacheKey);
        $item->set(true);
        $item->expiresAt(new \DateTime('+45 minutes'));
        $this->cache->save($item);
    }

    private static function headersToCurlArray($headers): array
    {
        return array_map(function ($k, $v) {
            return "$k: $v";
        }, array_keys($headers), $headers);
    }

    private function getHeaders(string $url, User|Magazine $actor, ?array $body = null): array
    {
        $headers = self::headersToSign($url, $body ? self::digest($body) : null);
        $stringToSign = self::headersToSigningString($headers);
        $signedHeaders = implode(' ', array_map('strtolower', array_keys($headers)));
        $key = openssl_pkey_get_private($actor->privateKey);
        openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);

        $keyId = $actor instanceof User
            ? $this->personFactory->getActivityPubId($actor).'#main-key'
            : $this->groupFactory->getActivityPubId($actor).'#main-key';

        $signatureHeader = 'keyId="'.$keyId.'",headers="'.$signedHeaders.'",algorithm="rsa-sha256",signature="'.$signature.'"';
        unset($headers['(request-target)']);
        $headers['Signature'] = $signatureHeader;
        $headers['User-Agent'] = 'kbinBot v0.1 - https://kbin.pub';
        $headers['Accept'] = 'application/activity+json, application/json';
        $headers['Content-Type'] = 'application/activity+json';

        return $headers;
    }

    private function getInstanceHeaders()
    {
    }

    #[ArrayShape([
        '(request-target)' => 'string',
        'Date' => 'string',
        'Host' => 'mixed',
        'Accept' => 'string',
        'Digest' => 'string',
    ])]
    protected static function headersToSign(string $url, ?string $digest = null): array
    {
        $date = new \DateTime('UTC');

        $headers = [
            '(request-target)' => 'post '.parse_url($url, PHP_URL_PATH),
            'Date' => $date->format('D, d M Y H:i:s \G\M\T'),
            'Host' => parse_url($url, PHP_URL_HOST),
        ];

        if ($digest) {
            $headers['Digest'] = 'SHA-256='.$digest;
        }

        return $headers;
    }

    private static function digest(array $body): string
    {
        return base64_encode(hash('sha256', json_encode($body), true));
    }

    private static function headersToSigningString(array $headers): string
    {
        return implode(
            "\n",
            array_map(function ($k, $v) {
                return strtolower($k).': '.$v;
            }, array_keys($headers), $headers)
        );
    }
}
