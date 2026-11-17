<?php

declare(strict_types=1);

namespace Nex\Events\Contracts;

/**
 * An event subscriber that registers multiple listeners at once.
 *
 * Return a map of event class → [listener method, priority].
 * Priority is optional (defaults to 0). Higher priority = runs first.
 *
 * Example:
 *   public function getSubscribedEvents(): array {
 *       return [
 *           UserRegistered::class => ['onUserRegistered', 10],
 *           UserDeleted::class    => 'onUserDeleted',
 *       ];
 *   }
 *
 * @return array<class-string, string|array{0:string, 1?:int}>
 */
interface SubscriberInterface
{
    public function getSubscribedEvents(): array;
}
