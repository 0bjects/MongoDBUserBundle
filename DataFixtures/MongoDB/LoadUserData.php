<?php

namespace Objects\MongoDBUserBundle\DataFixtures\MongoDB;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Objects\MongoDBUserBundle\Document\User;

class LoadUserData implements FixtureInterface {

    public function load(ObjectManager $manager) {

        // create admin user
        $adminUser = new User();
        $adminUser->setLoginName('admin');
        $adminUser->setUserPassword('0bjects123');
        $adminUser->setEmail('dev@isymfony.com');
        $adminUser->setRoles(array('ROLE_ADMIN'));
        $manager->persist($adminUser);

        // create active user
        $user = new User();
        $user->setLoginName('user');
        $user->setUserPassword('userPass');
        $user->setEmail('dev1@isymfony.com');
        $user->setRoles(array('ROLE_USER'));
        $manager->persist($user);

        // create a  not active user
        $notActiveUser = new User();
        $notActiveUser->setLoginName('notactive');
        $notActiveUser->setUserPassword('notactive');
        $notActiveUser->setEmail('dev2@isymfony.com');
        $notActiveUser->setRoles(array('ROLE_NOTACTIVE_USER'));
        $manager->persist($notActiveUser);

        $manager->flush();
    }

}