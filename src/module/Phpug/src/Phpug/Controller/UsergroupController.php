<?php
/**
 * Copyright (c) 2011-2012 Andreas Heigl<andreas@heigl.org>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  php.ug
 * @package   Phpug
 * @author    Andreas Heigl<andreas@heigl.org>
 * @copyright 2011-2012 php.ug
 * @license   http://www.opensource.org/licenses/mit-license.php MIT-License
 * @version   0.0
 * @since     06.03.2012
 * @link      http://github.com/heiglandreas/php.ug
 */

namespace Phpug\Controller;

use Phpug\Entity\Usergroup;
use Phpug\Form\PromoteUsergroupForm;
use Zend\View\Helper\ViewModel;

use Zend\Mvc\Controller\AbstractActionController;
use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject;


/**
 * The Controller for de default actions
 *
 * @category  php.ug
 * @package   Phpug
 * @author    Andreas Heigl<andreas@heigl.org>
 * @copyright 2011-2012 php.ug
 * @license   http://www.opensource.org/licenses/mit-license.php MIT-License
 * @version   0.0
 * @since     06.03.2012
 * @link      http://github.com/heiglandreas/php.ug
 */
class UsergroupController extends AbstractActionController
{

    protected $config = null;

    /**
     * Store the EntityManager
     *
     * @var EntityManager $em
     */
    protected $em = null;

    /**
     * Get the EntityManager for this Controller
     * 
     * @return EntityManager
     */
    public function getEntityManager()
	{
	    if (null === $this->em) {
	        $this->em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
	    }
   		return $this->em;
    }

    public function editAction()
    {
        $currentUser = $this->getServiceLocator()->get('OrgHeiglHybridAuthToken');
        if (! $currentUser) {
            $this->getResponse()->setStatusCode(401);
            return;
        }

        $id    = $this->getEvent()->getRouteMatch()->getParam('id');
        $group = $this->getEntityManager()->getRepository('Phpug\Entity\Usergroup')->findBy(array('shortname' => $id));
        if (! $group)  {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $acl = $this->getServiceLocator()->get('acl');
        if (! $acl) {
            $this->getResponse()->setSTatusCode(500);
            return true;
        }

        /** @var Phpug\Acl\UsersGroupAssertion $assertion */
        $assertion = $this->getServiceLocator()->get('usersGroupAssertion');
        $assertion->setUser($currentUser)->setGroup($group);

        $role = $this->getServiceLocator()->get('roleManager')->setUserToken($currentUser);
        if (! $acl->isAllowed((string) $role, 'ug', 'edit')) {
            $this->getResponse()->setStatusCode(401);
            return true;
        }

        return array();
    }

    public function promoteAction()
    {
        $currentUser = $this->getServiceLocator()->get('OrgHeiglHybridAuthToken');
        if (! $currentUser) {
            $this->getResponse()->setStatusCode(401);
            return;
        }

        $acl = $this->getServiceLocator()->get('acl');
        if (! $acl) {
            $this->getResponse()->setSTatusCode(500);
            return true;
        }

        $role = $this->getServiceLocator()->get('roleManager')->setUserToken($currentUser);
        if (! $acl->isAllowed((string) $role, 'ug', 'promote')) {
            $this->getResponse()->setStatusCode(401);
            return true;
        }

        $form = $this->getServiceLocator()->get('PromoteUsergroupForm');

        $objectManager = $this->getEntityManager();
        $usergroup = new Usergroup();

        $form->bind($usergroup);

        $request = $this->getRequest();
        if ($request->isPost()) {
            // Handle form sending
            $form->setData($request->getPost());
            if ($form->isValid()) {
                // Handle storage of form data
                try {
                   // var_Dump($form->getData());
                    // Store content
                    $objectManager->persist($form->getData());
                    $objectManager->flush();
                }catch(Exception $e){var_dump($e);}

               // return $this->redirect()->toRoute('ug/thankyou');
            } else {
//                var_Dump($form->getMessages());
            }
        }
        return array('form' => $form);

    }

    public function validateAction()
    {
        $id = (int) $this->params()->fromRoute('id', 0);
        $promote = false;
        if (!$id) {
            $promote = true;
        }

        $currentUser = $this->getServiceLocator()->get('OrgHeiglHybridAuthToken');
        if (! $currentUser) {
            $this->getResponse()->setStatusCode(401);
            return;
        }

        $acl = $this->getServiceLocator()->get('acl');
        if (! $acl) {
            $this->getResponse()->setSTatusCode(500);
            return true;
        }

        $role = $this->getServiceLocator()->get('roleManager')->setUserToken($currentUser);
        if (! $acl->isAllowed((string) $role, 'ug', 'promote')) {
            $this->getResponse()->setStatusCode(401);
            return true;
        }

        $form = new PromoteUsergroupForm();
        $form->init();

        $request = $this->getRequest();
        if (! $request->isPost()) {
            if ($promote) {
                return $this->redirect()->toRoute('ug/promote');
            }
            return $this->redirect()->toRoute('ug/edit', array('id' => $id));
        }

        //    $form->setInputFilter($album->getInputFilter());
        $form->setData($request->getPost());

        if (! $form->isValid()) {
            return array('form' => $form);
        }

try {
        // Store content
        $objectManager = $this->getEntityManager();
        $hydrator = new DoctrineObject(
            $objectManager,
            'Phpug\Entity\Usergroup'
        );

        $usergroup = new Usergroup();
        $data = $form->getValues();

        $usergroup = $hydrator->hydrate($data, $usergroup);
        $objectManager->persist($usergroup);
        $objectManager->flush();
}catch(Exception $e){var_dump($e);}
    //    return $this->redirect()->toRoute('ug/thankyou');
    }

    public function thankYouAction()
    {
    }
}
