<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends Controller
{
    /**
     * @Route("/admin/users", name="admin_users")
     */
    public function adminUsersAction(Request $request){
        $repository = $this->getDoctrine()->getManager()
            ->getRepository(User::class);

        return $this->render('admin/users.html.twig', [
            'users' => $repository->findAll()
        ]);
    }

    /**
     * @Route("/admin/user/enable/{id}", name="admin_enable_user")
     */
    public function adminEnableUserAction(Request $request, $id){
        // On récupère l'utilisateur grâce à son ID.
        $user = $this->getDoctrine()->getRepository(User::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        // Si l'utilisateur est actif, alors on le désactive. Sinon, on l'active. Le flashbag précise ce qui a été fait.
        if ($user->isEnabled()){
            $user->setEnabled(false);
            $request->getSession()->getFlashBag()->add('info', 'L\'utilisateur a bien été désactivé');
        } else {
            $user->setEnabled(true);
            $request->getSession()->getFlashBag()->add('info', 'L\'utilisateur a bien été activé');
        }
        // On persiste les changements en base de données.
        $em->persist($user);
        $em->flush();
        // On redirige l'utilisateur vers la route "admin".
        return $this->redirectToRoute('admin_users');
    }

    /**
     * @Route("/admin/user/delete/{id}", name="admin_delete_user")
     */
    public function adminDeleteUserAction(Request $request, $id){
        // On récupère l'utilisateur à supprimer grâce à son ID et le gestionnaire d'entité.
        $user = $this->getDoctrine()->getRepository(User::class)->find($id);
        $em = $this->getDoctrine()->getManager();

        // On revérifie que l'utilisateur ne s'est jamais connecté.
        if ($user->getLastLogin()==null and $user->isEnabled() == false){
            $em->remove($user);
            $em->flush();
            $request->getSession()->getFlashBag()->add('info', 'L\'utilisateur a bien été supprimé.');
            // TODO : Envoyer un mail à la personne qui s'est inscrite pour l'en informer.
        }

        return $this->redirectToRoute('admin_users');
    }
}
