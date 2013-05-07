<?php

namespace Objects\MongoDBUserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\SecurityContext;

class UserController extends Controller {

    /**
     * @author Mahmoud
     */
    public function loginAction() {
        $request = $this->getRequest();
        $session = $request->getSession();
        $error = '';
        if ($request->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } else {
            $error = $session->get(SecurityContext::AUTHENTICATION_ERROR);
            $session->remove(SecurityContext::AUTHENTICATION_ERROR);
        }
        return $this->render('ObjectsMongoDBUserBundle:User:login.html.twig', array(
                    'last_username' => $session->get(SecurityContext::LAST_USERNAME),
                    'error' => $error
        ));
    }

}
