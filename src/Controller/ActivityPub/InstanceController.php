<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Factory\ActivityPub\InstanceFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class InstanceController
{
    public function __invoke(Request $request, InstanceFactory $instanceFactory, CacheInterface $cache): JsonResponse
    {
        $instance = $cache->get('instance', function (ItemInterface $item) use ($instanceFactory) {
            return $instanceFactory->create();
        });

        return new JsonResponse($instance);
    }
}