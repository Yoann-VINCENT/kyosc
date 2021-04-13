<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\EditEmailType;
use App\Form\EditProfilType;
use App\Repository\CategoryRepository;
use App\Repository\ChallengeRepository;
use App\Repository\SportRepository;
use App\Security\EmailVerifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @Route ("/profil", name="profil_")
 */
class ProfilController extends AbstractController
{

    private UserPasswordEncoderInterface $passwordEncoder;
    private EmailVerifier $emailVerifier;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, EmailVerifier $emailVerifier)
    {
        $this->passwordEncoder = $passwordEncoder;
        $this->emailVerifier = $emailVerifier;
    }

    /**
     * @Route("/", name="my_profil")
     * @param SportRepository $sportRepository
     * @param CategoryRepository $categoryRepository
     * @param ChallengeRepository $challengeRepository
     * @param Request $request
     * @return Response
     */
    public function index(
        SportRepository $sportRepository,
        CategoryRepository $categoryRepository,
        ChallengeRepository $challengeRepository,
        Request $request
    ): Response {
        /* @phpstan-ignore-next-line */
        $user = $this->getUser();
        $challenges = $challengeRepository->findBy(
        /* @phpstan-ignore-next-line */
            ['creator' => $user->getId()]
        );
        $sports = $sportRepository->findAll();
        $categories = $categoryRepository->findAll();
        $form = $this->createForm(EditProfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            /* @phpstan-ignore-next-line */
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre profil a bien été modifié');
            return $this->redirectToRoute('profil_my_profil');
        }
        return $this->render('profil/show.html.twig', [
            'form' => $form->createView(),
            'categories' => $categories,
            'challenges' => $challenges,
            'user' => $user,
            'sports' => $sports
        ]);
    }

    /**
     * @Route("/{id}", name="show", methods={"GET"}, requirements={"id"="^\d+$"})
     * @param User $user
     * @param ChallengeRepository $challengeRepository
     * @return Response
     */
    public function show(
        User $user,
        ChallengeRepository $challengeRepository
    ): Response {
        $challenges = $challengeRepository->findBy(
            ['creator' => $user->getId()]
        );
        $sports = $user->getFavoriteSports();

        return $this->render('profil/user.html.twig', [
            'challenges' => $challenges,
            'user' => $user,
            'sports' => $sports
        ]);
    }

    /**
     * @Route("/mail", name="edit_email")
     * @param Request $request
     * @return Response
     */
    public function editEmail(Request $request): Response
    {
        $form = $this->createForm(EditEmailType::class, $this->getUser());
        $form->handleRequest($request);
        $error = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $mdp = $request->get('password');
            /* @phpstan-ignore-next-line */
            $user = $this->getUser();
            /* @phpstan-ignore-next-line */
            if ($request->isMethod('POST') && ($this->passwordEncoder->isPasswordValid($user, $mdp))) {
                $entityManager = $this->getDoctrine()->getManager();
                /* @phpstan-ignore-next-line */
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Votre email a bien été modifié');
                return $this->redirectToRoute('profil_my_profil');
            } else {
                $this->addFlash('danger', 'Vous avez entré un mauvais mot de passe. Vous avez été déconnecté
                 par mesure de sécurité. Votre email n\'a pas été modifié.');
                return $this->redirectToRoute('home');
            }
        }
        return $this->render('profil/editEmail.html.twig', ['form' => $form->createView(), 'error' => $error]);
    }
}
