<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class CommentMessageHandler implements MessageHandlerInterface
{
    private const SPAM_SCORE = 2;

    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly SpamChecker $spamChecker,
        private readonly MessageBusInterface $bus,
        private readonly WorkflowInterface $commentStateMachine,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }

        if ($this->commentStateMachine->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());

            $this->commentStateMachine->apply(
                $comment,
                match ($score) {
                    2 => 'reject_spam',
                    1 => 'might_be_spam',
                    default => 'accept',
                }
            );

            $this->commentRepository->save($comment, true);

            $this->bus->dispatch($message);
        } elseif ($this->commentStateMachine->can($comment, 'publish') || $this->commentStateMachine->can($comment, 'publish_ham')) {
            $this->commentStateMachine->apply($comment, $this->commentStateMachine->can($comment, 'publish') ? 'publish' : 'publish_ham');
            $this->commentRepository->save($comment, true);
        } elseif ($this->logger) {
            $this->logger->debug(
                'Dropping comment message',
                ['comment' => $comment->getId(), 'state' => $comment->getState()]
            );
        }
    }
}
