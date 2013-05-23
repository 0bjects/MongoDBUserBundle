<?php

namespace Objects\MongoDBUserBundle\Controller;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Objects\MongoDBUserBundle\Document\User;

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

    /**
     * @author Mahmoud
     */
    public function forgotPasswordAction() {
        $request = $this->getRequest();
        $collectionConstraint = new Collection(array(
            'email' => array(
                new Email(),
                new NotBlank()
            )
        ));
        $form = $this->createFormBuilder(null, array(
                    'validation_constraint' => $collectionConstraint,
                ))
                ->add('email', 'email')
                ->getForm();
        $error = false;
        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);
            if ($form->isValid()) {
                $data = $form->getData();
                $email = $data['email'];
                $user = $this->get('doctrine_mongodb')->getManager()->getRepository('ObjectsMongoDBUserBundle:User')->findOneBy(array('email' => $email));
                if ($user) {
                    $user->setConfirmationCode(md5(uniqid(rand())));
                    $this->get('doctrine_mongodb')->getManager()->flush();
                    $body = $this->renderView('ObjectsMongoDBUserBundle:User:Emails\forgot_your_password.html.twig', array('user' => $user));
                    $message = \Swift_Message::newInstance()
                            ->setSubject($this->get('translator')->trans('forgot your password'))
                            ->setFrom($this->container->getParameter('mailer_user'))
                            ->setTo($user->getEmail())
                            ->setBody($body)
                            ->setContentType('text/html')
                    ;
                    $this->get('mailer')->send($message);
                    $request->getSession()->getFlashBag()->set('emailSent', true);
                    return $this->redirect($this->generateUrl('login'));
                } else {
                    $error = true;
                }
            }
        }
        return $this->render('ObjectsMongoDBUserBundle:User:forgot_password.html.twig', array(
                    'form' => $form->createView(),
                    'error' => $error
        ));
    }

    /**
     * @author Mahmoud
     * @param string $confirmationCode
     * @param string $email
     */
    public function changePasswordAction($confirmationCode, $email) {
        $user = $this->get('doctrine_mongodb')->getManager()->getRepository('ObjectsMongoDBUserBundle:User')->findoneBy(array('email' => $email, 'confirmationCode' => $confirmationCode));
        if (!$user) {
            throw $this->createNotFoundException();
        }
        $session = $this->getRequest()->getSession();
        $flashBag = $session->getFlashBag();
        try {
            $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
            $this->get('security.context')->setToken($token);
            $flashBag->set('changeYourPassword', true);
            return $this->redirect($this->generateUrl('user_edit'));
        } catch (\Exception $e) {
            $this->get('security.context')->setToken(null);
            $session->invalidate();
            return $this->redirect($this->generateUrl('login'));
        }
    }

    /**
     * @author Mahmoud
     */
    public function signupAction() {
        //get the request object
        $request = $this->getRequest();
        $user = new User();
        //create a signup form
        $form = $this->createFormBuilder($user, array(
                    'validation_groups' => 'signup'
                ))
                ->add('loginName')
                ->add('email')
                ->add('userPassword', 'repeated', array(
                    'type' => 'password',
                    'first_options' => array('label' => 'Password', 'attr' => array('autocomplete' => 'off')),
                    'second_options' => array('label' => 'Repeat Password', 'attr' => array('autocomplete' => 'off')),
                    'invalid_message' => "The passwords don't match"
                ))
                ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);
            if ($form->isValid()) {
                return $this->finishSignUp($user);
            }
        }
        return $this->render('ObjectsMongoDBUserBundle:User:signup.html.twig', array(
                    'form' => $form->createView()
        ));
    }

    /**
     * @author Mahmoud
     * @param User $user
     * @param boolean $active
     */
    private function finishSignUp(User $user, $active = false) {
        $roleName = 'ROLE_USER';
        $container = $this->container;
        if (!$active) {
            $roleName = 'ROLE_NOTACTIVE_USER';
            $active = $container->getParameter('auto_active');
        }
        $dm = $this->get('doctrine_mongodb')->getManager();
        $dm->persist($user);
        $body = $this->renderView('ObjectsMongoDBUserBundle:User:Emails\welcome_to_site.html.twig', array(
            'user' => $user,
            'password' => $user->getUserPassword(),
            'active' => $active
        ));
        $user->setRoles(array($roleName, 'ROLE_UPDATABLE_USERNAME'));
        $dm->flush();
        $translator = $this->get('translator');
        $welcomeMessage = $translator->trans('welcome') . ' ' . $user->__toString() . ' ' . $translator->trans('to our site');
        $message = \Swift_Message::newInstance()
                ->setSubject($welcomeMessage)
                ->setFrom($container->getParameter('mailer_user'))
                ->setTo($user->getEmail())
                ->setBody($body)
                ->setContentType('text/html')
        ;
        $this->get('mailer')->send($message);
        $session = $this->getRequest()->getSession();
        try {
            $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
            $this->get('security.context')->setToken($token);
        } catch (\Exception $e) {
            $this->get('security.context')->setToken(null);
            $session->invalidate();
            $session->getFlashBag()->set('successSignup', true);
            return $this->redirect($this->generateUrl('login'));
        }
        $session->getFlashBag()->set('successSignup', true);
        return $this->redirect($this->generateUrl('user_edit'));
    }

    /**
     * @author Mahmoud
     * @param string $confirmationCode
     * @param string $email
     */
    public function activationAction($confirmationCode, $email) {
        $session = $this->getRequest()->getSession();
        $sessionFlashBag = $session->getFlashBag();
        $dm = $this->get('doctrine_mongodb')->getManager();
        if (true === $this->get('security.context')->isGranted('ROLE_USER')) {
            $sessionFlashBag->set('accountAlreadyActive', true);
            return $this->redirect($this->generateUrl('user_edit'));
        }
        $user = $dm->getRepository('ObjectsMongoDBUserBundle:User')->findOneBy(array('email' => $email, 'confirmationCode' => $confirmationCode));
        if (!$user) {
            $sessionFlashBag->set('invalidConfrimationCode', false);
            return $this->redirect($this->generateUrl('login'));
        }
        $roles = $user->getRoles();
        foreach ($roles as $key => $role) {
            if ($role === 'ROLE_NOTACTIVE_USER') {
                unset($roles[$key]);
                break;
            }
        }
        $roles [] = 'ROLE_USER';
        $user->setRoles($roles);
        $dm->flush();
        try {
            $token = new UsernamePasswordToken($user, null, 'main', $roles);
            $this->get('security.context')->setToken($token);
        } catch (\Exception $e) {
            $this->get('security.context')->setToken(null);
            $session->invalidate();
            $sessionFlashBag->set('accountActive', true);
            return $this->redirect($this->generateUrl('login'));
        }
        $sessionFlashBag->set('accountActive', true);
        return $this->redirect($this->generateUrl('user_edit'));
    }

    /**
     * @author Mahmoud
     */
    public function editAction() {
        $request = $this->getRequest();
        $dm = $this->get('doctrine_mongodb')->getManager();
        $container = $this->container;
        $user = $this->get('security.context')->getToken()->getUser();
        $relogin = false;
        $formValidationGroups = array('edit');
        $oldPassword = false;
        $changeUserName = false;
        if (false === $this->get('security.context')->isGranted('IS_AUTHENTICATED_FULLY')) {
            $oldPassword = true;
            $formValidationGroups [] = 'oldPassword';
        }
        if (true === $this->get('security.context')->isGranted('ROLE_UPDATABLE_USERNAME')) {
            $changeUserName = true;
            $formValidationGroups [] = 'loginName';
        }
        $oldEmail = $user->getEmail();
        $oldLoginName = $user->getLoginName();
        $formBuilder = $this->createFormBuilder($user, array(
                    'validation_groups' => $formValidationGroups
                ))
                ->add('userPassword', 'repeated', array(
                    'required' => false,
                    'type' => 'password',
                    'first_options' => array('label' => 'Password', 'attr' => array('autocomplete' => 'off')),
                    'second_options' => array('label' => 'Repeat Password', 'attr' => array('autocomplete' => 'off')),
                    'invalid_message' => "The passwords don't match"
                ))
                ->add('gender', 'choice', array(
                    'choices' => $user->getGendersArray(),
                    'required' => false,
                    'expanded' => true,
                    'multiple' => false
                ))
                ->add('firstName')
                ->add('lastName', null, array('required' => false))
                ->add('about', null, array('required' => false))
                ->add('email')
        ;
        if ($oldPassword) {
            $formBuilder->add('oldPassword', 'password');
        }
        if ($changeUserName) {
            $formBuilder->add('loginName');
        }
        //create the form
        $form = $formBuilder->getForm();
        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);
            if ($form->isValid()) {
                if ($oldPassword) {
                    $relogin = true;
                }
                $roles = $user->getRoles();
                if ($user->getEmail() != $oldEmail && !$container->getParameter('auto_active')) {
                    foreach ($roles as $key => $role) {
                        if ($role === 'ROLE_USER') {
                            unset($roles[$key]);
                            break;
                        }
                    }
                    $roles [] = 'ROLE_NOTACTIVE_USER';
                    $user->setRoles($roles);
                    $translator = $container->get('translator');
                    $body = $this->renderView('ObjectsMongoDBUserBundle:User:Emails\activate_email.html.twig', array('user' => $user));
                    $message = \Swift_Message::newInstance()
                            ->setSubject($translator->trans('reactivate your account'))
                            ->setFrom($container->getParameter('mailer_user'))
                            ->setTo($user->getEmail())
                            ->setBody($body)
                            ->setContentType('text/html')
                    ;
                    $this->get('mailer')->send($message);
                    $relogin = true;
                }
                if ($changeUserName && $oldLoginName != $user->getLoginName()) {
                    foreach ($roles as $key => $role) {
                        if ($role === 'ROLE_UPDATABLE_USERNAME') {
                            unset($roles[$key]);
                            break;
                        }
                    }
                    $user->setRoles($roles);
                    $relogin = true;
                }
                $dm->flush();
                $session = $request->getSession();
                $flashBag = $session->getFlashBag();
                if ($relogin) {
                    try {
                        $token = new UsernamePasswordToken($user, null, 'main', $roles);
                        $this->get('security.context')->setToken($token);
                    } catch (\Exception $e) {
                        $this->get('security.context')->setToken(null);
                        $session->invalidate();
                        $flashBag->set('successEdit', true);
                        return $this->redirect($this->generateUrl('login'));
                    }
                    $flashBag->set('successEdit', true);
                    return $this->redirect($this->generateUrl('user_edit'));
                }
                $flashBag->set('successEdit', true);
            }
        }
        return $this->render('ObjectsMongoDBUserBundle:User:edit.html.twig', array(
                    'form' => $form->createView(),
                    'oldPassword' => $oldPassword,
                    'changeUserName' => $changeUserName
        ));
    }

}
