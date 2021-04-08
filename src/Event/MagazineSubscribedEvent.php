<?php declare(strict_types=1);

namespace App\Event;

use App\Entity\Magazine;
use App\Entity\User;

class MagazineSubscribedEvent
{
    public function __construct(public Magazine $magazine, public User $user)
    {
    }
}
