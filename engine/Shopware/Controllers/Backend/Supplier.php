<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Controllers
 * @subpackage Supplier
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Jens Schwehn
 * @author     $Author$
 */

/**
 * Shopware Supplier Management
 *
 * todo
 * @all: Documentation
 */
class Shopware_Controllers_Backend_Supplier extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * @var \Shopware\Models\Article\Repository
     */
    private $repository;

    /**
     * Internal helper function to get access to the form repository.
     *
     * @return \Shopware\Models\Article\Repository
     */
    private function getRepository()
    {
        if ($this->repository === null) {
            $this->repository = Shopware()->Models()->getRepository('Shopware\Models\Article\Article');
        }
        return $this->repository;
    }

    /**
     * Deletes a Supplier from the database
     * Feeds the view with an json encoded array containing
     * - success : boolean Set to true if everything went well otherwise it is set to false
     * - data    : int  Id of the deleted supplier
     *
     * @return void
     */
    public function deleteSupplierAction()
    {
        if (!$this->Request()->isPost()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        $id            = (int) $this->Request()->get('id');
        $supplierModel = Shopware()->Models()->find('Shopware\Models\Article\Supplier', $id);

        Shopware()->Models()->remove($supplierModel);
        Shopware()->Models()->flush();

        $this->View()->assign(array('success' => true, 'data' => $id));
    }

    /**
     * Returns a JSON string containing all Suppliers
     * Json Structure
     * - success : boolean Set to true if everything went well otherwise it is set to false;
     *             Will return false if no suppliers are found
     * - data : array of suppliers containing the following keys
     *          - name : String
     *          - id : Int
     *          - link : String
     *          - articleCounter : Int
     *          - description : String
     *
     * @return void
     */
    public function getSuppliersAction()
    {
        // if id is provided return a single form instead of a collection
        if ($id = $this->Request()->getParam('id')) {
            return $this->getSingleSupplier($id);
        }

        $filter = $this->Request()->getParam('filter', null);
        $sort   = $this->Request()->getParam('sort', array(array('property' => 'name')));
        $limit  = $this->Request()->getParam('limit', 20);
        $offset = $this->Request()->getParam('start', 0);

        $query = $this->getRepository()->getSupplierListQuery($filter, $sort, $limit, $offset);
        $total = Shopware()->Models()->getQueryCount($query);

        $suppliers = $query->getArrayResult();

        foreach ($suppliers as &$supplier){
            $supplier["description"] = strip_tags($supplier["description"]);
        }

        $this->View()->assign(array(
            'success' => !empty($suppliers),
            'data'    => $suppliers,
            'total'   => $total
        ));
    }

    /**
     * This method is called if a new supplier should be written to the database.
     * It works as a wrapper around the saveSupplier method to use ACL
     * ACL configuration is done in initAcl()
     */
    public function createSupplierAction()
    {
        $this->saveSuppliers();
    }

    /**
     * This method is called if a supplier should be updated.
     * It works as a wrapper around the saveSupplier method to use ACL
     * ACL configuration is done in initAcl()
     */
    public function updateSupplierAction()
    {
        $this->saveSuppliers();
    }

    /**
     * Creates a new supplier
     * on the passed values
     *
     * Json Structure
     * - success : boolean Set to true if everything went well otherwise it is set to false
     * - data : Data for the new saved supplier containing
     *          - name : String
     *          - id : Int
     *          - link : String
     *          - articleCounter : Int
     *          - description : String
     * [-errorMsg] : String containing the error message
     *
     * @return void
     */
    public function saveSuppliers()
    {
        $data = null;

        if (!$this->Request()->isPost()) {
            $this->View()->assign(array(
                'success' => false,
                'errorMsg' => $this->namespace->get('empty_post', 'Empty Post request.')
            ));

            return;
        }

        $id = (int)$this->Request()->get('id');
        if ($id > 0) {
            $supplierModel = Shopware()->Models()->find('Shopware\Models\Article\Supplier', $id);
        } else {
            $supplierModel = new \Shopware\Models\Article\Supplier();
        }

        $params              = $this->Request()->getParams();
        $params['attribute'] = $params['attribute'][0];

        // set data to model and overwrite the image field
        $supplierModel->fromArray($params);

        $mediaData = $this->Request()->get('media-manager-selection');
        if (!empty($mediaData) && !is_null($mediaData)) {
            $supplierModel->setImage($this->Request()->get('media-manager-selection'));
        }

        // backend checks
        $name = $supplierModel->getName();
        if (empty($name)) {
            $this->View()->assign(array(
                'success' => false,
                'errorMsg' => $this->namespace->get('no_name_given', 'No supplier name given')
            ));

            return;
        }

        try {
            $manager = Shopware()->Models();
            $manager->persist($supplierModel);
            $manager->flush();
            $params['id'] = $supplierModel->getId();
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            $this->View()->assign(array('success' => false, 'errorMsg' => $errorMsg));
            return;
        }

        $this->View()->assign(array('success' => true, 'data' => $params));
        return;
    }

    /**
     * Gets a single supplier
     *
     * @param $id
     */
    protected function getSingleSupplier($id)
    {
        $data = $this->getRepository()->getSupplierQuery($id)->getArrayResult();


        if (empty($data)) {
            $this->View()->assign(array('success' => false, 'message' => 'Supplier not found'));
            return;
        }

        $this->View()->assign(array('success' => true, 'data' => $data, 'total' => 1));
        return;
    }

    /**
     * Returns all known Suppliers from the database. there are ordered by there name
     *
     * @access private
     * @return mixed
     */
    public function getAllSupplier()
    {
        $filter = $this->Request()->getParam('filter', null);
        $sort   = $this->Request()->getParam('sort', array(array('property' => 'name')));
        $limit  = $this->Request()->getParam('limit', 20);
        $offset = $this->Request()->getParam('start', 0);

        $query = $this->getRepository()->getSupplierListQuery($filter, $sort, $limit, $offset);
        $count = Shopware()->Models()->getQueryCount($query);

        return array(
            'result' => $query->getArrayResult(),
            'total' => $count
        );
    }

    /**
     * Method to define acl dependencies in backend controllers
     * <code>
     * $this->addAclPermission("name_of_action_with_action_prefix","name_of_assigned_privilege","optionally error message");
     * // $this->addAclPermission("indexAction","read","Ops. You have no permission to view that...");
     * </code>
     */
    protected function initAcl()
    {
        $namespace = Shopware()->Snippets()->getNamespace('backend/supplier');

        $this->addAclPermission('getSuppliersAction',   'read',   $namespace->get('no_list_rights', 'Read access denied.'));
        $this->addAclPermission('deleteSupplierAction', 'delete', $namespace->get('no_list_rights', 'Delete access denied.'));
        $this->addAclPermission('updateSupplierAction', 'update', $namespace->get('no_update_rights', 'Update access denied.'));
        $this->addAclPermission('createSupplierAction', 'create', $namespace->get('no_create_rights', 'Create access denied.'));
    }
}
