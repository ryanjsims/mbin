<?php declare(strict_types=1);

namespace App\DTO;

use App\Entity\Magazine;
use App\Entity\Image;

class MagazineThemeDto
{
    public Magazine $magazine;
    public ?Image $cover = null;
    public ?string $customCss = null;
    public ?string $customJs = null;

    public function __construct(Magazine $magazine)
    {
        $this->magazine  = $magazine;
        $this->customCss = $magazine->customCss;
    }

    public function create(?Image $cover)
    {
        $this->cover = $cover;
    }
}
