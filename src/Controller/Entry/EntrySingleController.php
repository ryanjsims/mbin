<?php declare(strict_types=1);

namespace App\Controller\Entry;

use App\Controller\AbstractController;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Event\Entry\EntryHasBeenSeenEvent;
use App\PageView\EntryCommentPageView;
use App\Repository\EntryCommentRepository;
use Pagerfanta\PagerfantaInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntrySingleController extends AbstractController
{
    /**
     * @ParamConverter("magazine", options={"mapping": {"magazine_name": "name"}})
     * @ParamConverter("entry", options={"mapping": {"entry_id": "id"}})
     */
    public function __invoke(
        Magazine $magazine,
        Entry $entry,
        ?string $sortBy,
        EntryCommentRepository $repository,
        EventDispatcherInterface $dispatcher,
        Request $request
    ): Response {
        $criteria = new EntryCommentPageView($this->getPageNb($request));
        $criteria->showSortOption($criteria->resolveSort($sortBy));
        $criteria->entry = $entry;

        $comments = $repository->findByCriteria($criteria);

        $repository->hydrate(...$comments);
        $repository->hydrateChildren(...$comments);

        $dispatcher->dispatch((new EntryHasBeenSeenEvent($entry)));

        if ($request->isXmlHttpRequest()) {
            return $this->getJsonResponse($magazine, $entry, $comments);
        }

        return $this->render(
            'entry/single.html.twig',
            [
                'magazine' => $magazine,
                'comments' => $comments,
                'entry'    => $entry,
            ]
        );
    }

    private function getJsonResponse(Magazine $magazine, Entry $entry, PagerfantaInterface $comments): JsonResponse
    {
        return new JsonResponse(
            [
                'html' => $this->renderView(
                    'entry/_single_popup.html.twig',
                    [
                        'magazine' => $magazine,
                        'comments' => $comments,
                        'entry'    => $entry,
                    ]
                ),
            ]
        );
    }
}
