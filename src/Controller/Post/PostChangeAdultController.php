<?php declare(strict_types=1);

namespace App\Controller\Post;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PostChangeAdultController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }


    #[ParamConverter('magazine', options: ['mapping' => ['magazine_name' => 'name']])]
    #[ParamConverter('post', options: ['mapping' => ['post_id' => 'id']])]
    #[IsGranted('moderate', 'magazine')]
    public function __invoke(Magazine $magazine, Post $post, Request $request): Response
    {
        $this->validateCsrf('change_adult', $request->request->get('token'));

        $post->isAdult = $request->get('adult') === 'on';

        $this->entityManager->flush();

        return $this->redirectToRefererOrHome($request);
    }
}
