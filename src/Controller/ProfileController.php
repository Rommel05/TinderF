<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('profile/profile.html.twig', [
            'usuario' => $user,
        ]);
    }

    #[Route('/profileMatch/{id}', name: 'profile_match')]
    public function profile_match($id, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);

        return $this->render('profile/profile.html.twig', [
            'usuario' => $user,
        ]);
    }


    #[Route('/profile/remove', name: 'app_profile_remove', methods: ['POST'])]
    public function removeUser(SessionInterface $session, ManagerRegistry $managerRegistry): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $entityManager = $managerRegistry->getManager();

        $entityManager->remove($user);
        $entityManager->flush();

        $session->invalidate();

        $this->container->get('security.token_storage')->setToken(null);

        return $this->redirectToRoute('app_index');
    }

    #[Route('/profile/edit', name: 'app_profile_edit')]
    public function editProfile(Request $request, ManagerRegistry $managerRegistry): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createFormBuilder($user)
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'required' => false,
                'mapped' => false,
                'attr' => ['placeholder' => 'Leave blank to keep current password'],
            ])
            ->add('name', TextType::class, ['label' => 'Name'])
            ->add('age', NumberType::class, ['label' => 'Age'])
            ->add('gender', ChoiceType::class, ['choices' => ['man' => 'man', 'woman' => 'woman']])
            ->add('description', TextType::class, ['label' => 'Description'])
            ->add('imagePath', FileType::class, [
                'label' => 'Profile Image',
                'required' => false,
                'mapped' => false,
            ])
            ->add('save', SubmitType::class, ['label' => 'Save'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $managerRegistry->getManager();

            $imageFile = $form->get('imagePath')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('images_directory'),
                    $newFilename
                );
                $user->setImagePath($newFilename);
            }

            $newPassword = $form->get('password')->getData();
            if ($newPassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
