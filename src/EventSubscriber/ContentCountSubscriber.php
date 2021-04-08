<?php declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Event\EntryCommentCreatedEvent;
use App\Event\EntryCommentDeletedEvent;
use App\Event\EntryCommentPurgedEvent;
use App\Event\PostCommentCreatedEvent;
use App\Event\PostCommentDeletedEvent;
use App\Event\PostCommentPurgedEvent;
use App\Repository\EntryRepository;
use App\Repository\PostRepository;
use App\Event\EntryDeletedEvent;

class ContentCountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntryRepository $entryRepository,
        private PostRepository $postRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntryDeletedEvent::class        => 'onEntryDeleted',
            EntryCommentCreatedEvent::class => 'onEntryCommentCreated',
            EntryCommentDeletedEvent::class => 'onEntryCommentDeleted',
            EntryCommentPurgedEvent::class  => 'onEntryCommentPurged',
            PostCommentCreatedEvent::class  => 'onPostCommentCreated',
            PostCommentDeletedEvent::class  => 'onPostCommentDeleted',
            PostCommentPurgedEvent::class   => 'onPostCommentPurged',
        ];
    }

    public function onEntryDeleted(EntryDeletedEvent $event): void
    {
        $event->entry->magazine->updateEntryCounts();

        $this->entityManager->flush();
    }

    public function onEntryCommentCreated(EntryCommentCreatedEvent $event): void
    {
        $magazine                    = $event->comment->entry->magazine;
        $magazine->entryCommentCount = $this->entryRepository->countEntryCommentsByMagazine($magazine);

        $this->entityManager->flush();
    }

    public function onEntryCommentDeleted(EntryCommentDeletedEvent $event): void
    {
        $magazine                    = $event->comment->entry->magazine;
        $magazine->entryCommentCount = $this->entryRepository->countEntryCommentsByMagazine($magazine) - 1;

        $event->comment->entry->updateCounts();

        $this->entityManager->flush();
    }

    public function onEntryCommentPurged(EntryCommentPurgedEvent $event): void
    {
        $event->magazine->entryCommentCount = $this->entryRepository->countEntryCommentsByMagazine($event->getMagazine());

        $this->entityManager->flush();
    }

    public function onPostCommentCreated(PostCommentCreatedEvent $event): void
    {
        $magazine                   = $event->comment->post->magazine;
        $magazine->postCommentCount = $this->postRepository->countPostCommentsByMagazine($magazine);

        $this->entityManager->flush();
    }

    public function onPostCommentDeleted(PostCommentDeletedEvent $event): void
    {
        $magazine                   = $event->comment->post->magazine;
        $magazine->postCommentCount = $this->postRepository->countPostCommentsByMagazine($magazine) - 1;

        $event->comment->post->updateCounts();

        $this->entityManager->flush();
    }

    public function onPostCommentPurged(PostCommentPurgedEvent $event): void
    {
        $event->comment->postCommentCount = $this->postRepository->countPostCommentsByMagazine($event->getMagazine());

        $this->entityManager->flush();
    }
}
