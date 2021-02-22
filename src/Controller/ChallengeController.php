<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Challenge;
use App\Entity\ChallengeSearch;
use App\Entity\Sport;
use App\Form\ChallengeSearchType;
use App\Form\ChallengeType;
use App\Repository\ChallengeRepository;
use App\Repository\SportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use PhpParser\Node\Expr\Array_;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use DateTime;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route(
 *     "/challenge",
 *     name="challenge_"
 * )
 */
class ChallengeController extends AbstractController
{
    /** @Route(
     *     "/",
     *     name="index",
     *     methods={"GET"}
     * )
     * @param ChallengeRepository $challengeRepository
     * @param Request $request
     * @param PaginatorInterface $paginator
     * @return Response
     */
    public function index(
        ChallengeRepository $challengeRepository,
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        $queryBuilder = $challengeRepository->findAllByDateQueryBuilder();
        return $this->createChallengeDisplay(
            $challengeRepository,
            $request,
            $paginator,
            $queryBuilder
        );
    }

    /**
     * @Route(
     *     "/nouveau",
     *     name="new",
     *     methods={"GET", "POST"}
     * )
     * @param SportRepository $sportRepository
     * @param Request $request
     * @return Response
     */
    public function new(SportRepository $sportRepository, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $sports = $sportRepository->findAll();
        $challenge = new Challenge();
        $form = $this->createForm(ChallengeType::class, $challenge);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $challenge->setCreatedAt(new DateTime());
            $challenge->setUpdatedAt(new DateTime());
            /* @phpstan-ignore-next-line */
            $challenge->setCreator($this->getUser());
            $entityManager->persist($challenge);
            $entityManager->flush();

            $this->addFlash(
                'success',
                "Votre challenge a été correctement soumis et sera validé très prochainement !"
            );

            return $this->redirectToRoute('challenge_index');
        }
        return $this->render('challenge/new.html.twig', ['form' => $form->createView(), 'sports' => $sports]);
    }

    /**
     * @Route(
     *     "/category/{name}",
     *     name="by_category",
     *     methods={"GET"},
     *     requirements={"name"="^[a-z-]+$"},
     * )
     * @param ChallengeRepository $challengeRepository
     * @param Request $request
     * @param PaginatorInterface $paginator
     * @param Category $category
     * @return Response
     */
    public function challengeByCategory(
        ChallengeRepository $challengeRepository,
        Request $request,
        PaginatorInterface $paginator,
        Category $category
    ): Response {
        $queryBuilder = $challengeRepository->findByCategoryQueryBuilder($category->getName());
        return $this->createChallengeDisplay(
            $challengeRepository,
            $request,
            $paginator,
            $queryBuilder,
            [ 'category' => $category ]
        );
    }

    /**
     * @Route(
     *     "/{slug}",
     *     name="by_sport",
     *     methods={"GET"},
     *     requirements={"slug"="^[a-z-]+$"},
     * )
     * @param ChallengeRepository $challengeRepository
     * @param Request $request
     * @param PaginatorInterface $paginator
     * @param Sport $sport
     * @return Response
     */
    public function challengeBySport(
        ChallengeRepository $challengeRepository,
        Request $request,
        PaginatorInterface $paginator,
        Sport $sport
    ): Response {
        $queryBuilder = $challengeRepository->findBySportQueryBuilder($sport->getSlug());
        return $this->createChallengeDisplay(
            $challengeRepository,
            $request,
            $paginator,
            $queryBuilder,
            [ 'sport' => $sport ]
        );
    }

    /**
     * @Route(
     *     "/{id}/rejoindre",
     *     name="join",
     *     methods={"POST"},
     *     requirements={"id"="^\d+$"},
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ChallengeRepository $challengeRepository
     * @return Response
     */
    public function join(
        Request $request,
        EntityManagerInterface $entityManager,
        ChallengeRepository $challengeRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $challengeId = $request->request->get('challengeId');
        $submittedToken = $request->request->get('token');
        if (
            $this->isCsrfTokenValid('challenge-join', $submittedToken) &&
            filter_var($challengeId, FILTER_VALIDATE_INT)
        ) {
            $challenge = $challengeRepository->find($challengeId);
            if ($challenge && $user) {
                /* @phpstan-ignore-next-line */
                $challenge->addParticipant($user);
                $entityManager->flush();
                return $this->redirectToRoute('challenge_show', [
                    'id' => $challengeId,
                ]);
            }
        }
        return $this->redirectToRoute('challenge_index');
    }

    /**
     * @Route(
     *     "/{id}/quitter",
     *     name="leave",
     *     methods={"POST"},
     *     requirements={"id"="^\d+$"},
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ChallengeRepository $challengeRepository
     * @return Response
     */
    public function leave(
        Request $request,
        EntityManagerInterface $entityManager,
        ChallengeRepository $challengeRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $challengeId = $request->request->get('challengeId');
        $submittedToken = $request->request->get('token');
        if (
            $this->isCsrfTokenValid('challenge-leave', $submittedToken) &&
            filter_var($challengeId, FILTER_VALIDATE_INT)
        ) {
            $challenge = $challengeRepository->find($challengeId);
            if ($challenge && $user) {
                /* @phpstan-ignore-next-line */
                $challenge->removeParticipant($user);
                $entityManager->flush();
                return $this->redirectToRoute('challenge_show', [
                    'id' => $challengeId,
                ]);
            }
        }
        return $this->redirectToRoute('challenge_index');
    }

    /**
     * @Route(
     *     "/{id}/invitation",
     *     name="invite",
     *     methods={"POST"},
     *     requirements={"id"="^\d+$"},
     * )
     * @param Request $request
     * @param MailerInterface $mailer
     * @param Challenge $challenge
     * @return Response
     * @throws TransportExceptionInterface
     */
    public function invite(Request $request, MailerInterface $mailer, Challenge $challenge): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $emailAddress = $request->request->get('email');
        $submittedToken = $request->request->get('token');
        if (
            $this->isCsrfTokenValid('challenge-invite', $submittedToken) &&
            filter_var($emailAddress, FILTER_VALIDATE_EMAIL)
        ) {
            $email = (new Email())
                ->from($this->getParameter('mailer_from'))
                ->to($emailAddress)
                ->subject('Invitation à un challenge Kyosc')
                ->html(
                    $this->renderView(
                        'email/challenge-invitation.html.twig',
                        ['challenge' => $challenge, 'user' => $this->getUser()]
                    )
                );
            $mailer->send($email);
            $this->addFlash(
                'success',
                'Votre invitation a bien été envoyée à l\'adresse suivante ' . $emailAddress . '.'
            );
        } else {
            $this->addFlash('danger', 'L\'adresse email ' . $emailAddress . ' est invalide.');
        }
        return $this->redirectToRoute('challenge_show', [
            'id' => $challenge->getId(),
        ]);
    }

    /**
     * @Route(
     *     "/{id}",
     *     name="show",
     *     methods={"GET"},
     *     requirements={"id"="^\d+$"},
     * )
     * @param Challenge $challenge
     * @return Response
     */
    public function show(Challenge $challenge): Response
    {
        return $this->render('challenge/show.html.twig', [
            'challenge' => $challenge,
        ]);
    }

    /**
     * @Route(
     *     "/{id}/edition",
     *     name="edit",
     *     methods={"GET", "POST", "PUT"},
     *     requirements={"id"="^\d+$"},
     * )
     * @param Challenge $challenge
     * @return Response
     */
    public function edit(Challenge $challenge, Request $request): Response
    {
        if (!($this->getUser() == $challenge->getCreator())) {
            throw new AccessDeniedException('Seul le créateur.la créatrice d\'un challenge peut le modifier.');
        }

        $form = $this->createForm(ChallengeType::class, $challenge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash(
                'success',
                "Les informations sur votre challenge ont bien été modifiées!"
            );

            return $this->redirectToRoute('profil_show');
        }

        return $this->render('challenge/edit.html.twig', [
            'form' => $form->createView(),
            'challenge' => $challenge,
        ]);
    }

    /**
     * @Route(
     *     "/{id}",
     *     name="delete",
     *     methods={"DELETE"},
     *     requirements={"id"="^\d+$"},
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param Challenge $challenge
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager, Challenge $challenge): Response
    {
        if (!($this->getUser() == $challenge->getCreator())) {
            throw new AccessDeniedException('Seul le créateur.la créatrice d\'un challenge peut le supprimer.');
        }
        if ($this->isCsrfTokenValid('delete' . $challenge->getId(), $request->request->get('_token'))) {
            $entityManager->remove($challenge);
            $entityManager->flush();
            $this->addFlash(
                'success',
                "Votre challenge a bien été supprimé."
            );
        }
        return $this->redirectToRoute("profil_show");
    }

    /**
     * @param array<object> $options
     */
    private function createChallengeDisplay(
        ChallengeRepository $challengeRepository,
        Request $request,
        PaginatorInterface $paginator,
        QueryBuilder $query,
        array $options = []
    ): Response {
        $category = $options['category'] ?? null;
        $sport = $options['sport'] ?? null;

        $search = new ChallengeSearch();
        $maxParticipants = $challengeRepository->maxParticipants();
        $minParticipants = $challengeRepository->minParticipants();
        if ($minParticipants === $maxParticipants) {
            $maxParticipants++;
        }
        $minMaxDistance = $challengeRepository->minMaxDistance();
        $form = $this->createForm(ChallengeSearchType::class, $search);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $queryBuilder = $challengeRepository->searchChallenges($search);
            $sport = $form->getData()->getSport();
            $category = null;
        } else {
            $queryBuilder = $query;
        }
        $pagination = $paginator->paginate(
            $queryBuilder, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            16 /*limit per page*/,
            ['wrap-queries' => true]
        );

        return $this->render('challenge/index.html.twig', [
            'form' => $form->createView(),
            'pagination' => $pagination,
            'max' => $maxParticipants,
            'min' => $minParticipants,
            'minMaxDistance' => $minMaxDistance,
            'category' => $category,
            'sport' => $sport,
        ]);
    }
}