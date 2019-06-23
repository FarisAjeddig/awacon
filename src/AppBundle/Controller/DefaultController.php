<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\Form\Factory\FactoryInterface;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DefaultController extends Controller
{
    private $eventDispatcher;
    private $formFactory;
    private $userManager;
    private $tokenStorage;

    public function __construct(EventDispatcherInterface $eventDispatcher, FactoryInterface $formFactory, UserManagerInterface $userManager, TokenStorageInterface $tokenStorage)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->formFactory = $formFactory;
        $this->userManager = $userManager;
        $this->tokenStorage = $tokenStorage;
    }
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }


    /**
     * @param Request $request
     * @Route("/register/", name="register")
     * @return Response
     */
    public function registerAction(Request $request, \Swift_Mailer $mailer)
    {
        $user = $this->userManager->createUser();
        $user->setEnabled(true);

        $event = new GetResponseUserEvent($user, $request);
        $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $this->formFactory->createForm()
            ->add('lastname', TextType::class, ['label' => 'Nom'])
            ->add('firstname', TextType::class, ['label' => 'Prénom'])
            ->add('position', TextType::class, ['label' => 'Poste'])
            ->add('phonenumber', TextType::class, ['label' => 'Numéro de téléphone'])
            ->add('birthdate', DateType::class, ['label' => 'Date de naissance', 'years' => range(1960, 2019)]);

        $form->setData($user);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $event = new FormEvent($form, $request);
                $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

                $this->userManager->updateUser($user);

                if (null === $response = $event->getResponse()) {
                    $url = $this->generateUrl('fos_user_registration_confirmed');
                    $response = new RedirectResponse($url);
                }

//                $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_COMPLETED, new FilterUserResponseEvent($user, $request, $response));
                // TODO ; Envoyer un mail aux administrateurs pour valider la demande d'inscription.
                $admins = $this->getDoctrine()->getRepository(User::class)->findAll();
                foreach ($admins as $admin){
                    if (in_array("ROLE_ADMIN", $admin->getRoles())){
                        $message = (new \Swift_Message('Inscription de ' . $user->getFirstname()))
                            ->setFrom('consulting.awalee@gmail.com')
                            ->setTo($admin->getEmail())
                            ->setBody(
                                $this->renderView(
                                    'emails/validRegister.html.twig',
                                    ['user' => $user]
                                ),
                                'text/html'
                            );

                        $mailer->send($message);
                    }
                }
                return $response;
            }

            $event = new FormEvent($form, $request);
            $this->eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_FAILURE, $event);

            if (null !== $response = $event->getResponse()) {
                return $response;
            }
        }

        return $this->render('@FOSUser/Registration/register.html.twig', array(
            'form' => $form->createView(),
        ));
    }


    /**
     * @Route("/profile/{id}", name="profile")
     */
    public function profileAction($id, Request $request)
    {
        if ($id = "edit"){
            return $this->redirectToRoute('fos_user_profile_show');
        }
        $user = $this->getDoctrine()->getRepository(User::class)-> find($id);
        return $this->render('@FOSUser/Profile/show.html.twig', array(
            'user' => $user,
        ));
    }

    /**
     * @Route("/profile/edit/", name="profile_edit")
     */
    public function profileEditAction(Request $request){
        $user = $this->getUser();
        $form = $this->createFormBuilder($user)
            ->add('lastname')
            ->add('username')
            ->add('firstname')
            ->add('position')
            ->add('phonenumber')
            ->add('birthdate')
            ->add('Modifier', SubmitType::class, ['attr' => ['class'=>'btn btn-primary btn-block']])
            ->getForm();


        $form->handleRequest($request);

        if ($form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $request->getSession()->getFlashBag()->add('info', 'Les informations ont bien étés modifiées.');
            return $this->redirectToRoute('fos_user_profile_show');
        }

        return $this->render('@FOSUser/Profile/edit.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/profile/edit/desc", name="profile_edit_desc")
     */
    public function profileEditDescAction(Request $request){
        $user = $this->getUser();
        $form = $this->createFormBuilder($user)
            ->add('description', CKEditorType::class)
            ->add('Modifier', SubmitType::class, ['attr' => ['class'=>'btn btn-primary btn-block']])
            ->getForm();


        $form->handleRequest($request);

        if ($form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $request->getSession()->getFlashBag()->add('info', 'Les informations ont bien étés modifiées.');
            return $this->redirectToRoute('fos_user_profile_show');
        }

        return $this->render('@FOSUser/Profile/edit.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
