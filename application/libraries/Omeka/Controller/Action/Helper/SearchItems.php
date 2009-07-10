<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2008
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 **/

/**
 * 
 *
 * @package Omeka
 * @author CHNM
 * @copyright Center for History and New Media, 2007-2008
 **/
class Omeka_Controller_Action_Helper_SearchItems extends Zend_Controller_Action_Helper_Abstract
{    
    public function direct($options = array())
    {
        return $this->search($options);
    }
    
    /**
     * @todo Does this actually need any options, and if so, what are they?
     * An example of one option would be automatically generating the pagination,
     * or automatically assigning to the items to the view object.
     * 
     * @param array Set of options (currently empty)
     * @return array Keyed array containing the following: 'items' = all the Item
     * objects returned by the search, 'page' = the page # of the results, 
     * 'per_page' = the number of items displayed for the given results,
     * 'total_results' = the total # of results returned by the search query,
     * 'total_items' = the total # of items in the database (equivalent to # of
     * items that would be returned by a blank search query).
     **/
    public function search($options = array())
    {   
        $request = $this->getRequest();
        $controller = $this->getActionController();
        $itemTable = Omeka_Context::getInstance()->getDb()->getTable('Item');
        // Page should be passed as the 'page' parameter or it defaults to 1
        $resultPage = $request->get('page') or $resultPage = 1;
        
        // $perms  = array();
        // $filter = array();
        // $order  = array();
        
        // set default params
        $params = array();
        $params['recent'] = true;
        
        
        $requestParams = $request->getParams();
        try {            
            foreach($requestParams as $requestParamName => $requestParamValue) {
                if (trim($requestParamValue) == '') {
                    continue;
                }
                switch($requestParamName) {
                    case 'user':
                        //Must be logged in to view items specific to certain users
                        if (!$controller->isAllowed('browse', 'Users')) {
                            throw new Exception( 'May not browse by specific users.' );
                        }
                        if (is_numeric($requestParamValue)) {
                            $params['user'] = $requestParamValue;
                        }
                    break;
                
                    case 'public':
                        $params['public'] = $this->_isTrue($requestParamValue);
                    break;
                
                    case 'featured':
                        $params['featured'] = $this->_isTrue($requestParamValue);
                    break;
                
                    case 'collection':
                        $params['collection'] = $requestParamValue;
                    break;
                
                    case 'type':
                        $params['type'] = $requestParamValue;
                    break;
                
                    case 'tag':
                    case 'tags':
                        $params['tags'] = $requestParamValue;
                    break;
                
                    case 'excludeTags':
                        $params['excludeTags'] = $requestParamValue;
                    break;
                
                    case 'recent':
                        if (!$this->_isTrue($requestParamValue)) {
                            $params['recent'] = false;
                        }
                    break;
                
                    case 'search':
                        $params['search'] = $requestParamValue;
                        //Don't order by recent-ness if we're doing a search
                        unset($params['recent']);
                    break;
                
                    case 'advanced':                    
                        //We need to filter out the empty entries if any were provided
                        foreach ($requestParamValue as $k => $entry) {                    
                            if (empty($entry['element_id']) || empty($entry['type'])) {
                                unset($requestParamValue[$k]);
                            }
                        }
                        if (count($requestParamValue) > 0) {
                            $params['advanced_search'] = $requestParamValue;
                        }
                    break;
                
                    case 'range':
                        $params['range'] = $requestParamValue;
                    break;
                }
            }
        } catch (Exception $e) {
             $controller->flash($e->getMessage());
        }
        
        //echo var_dump($params);exit;
        
                
        // //Show only public items
        //         if ($request->get('public')) {
        //             $perms['public'] = true;
        //         }
        //         
        //         //Here we add some filtering for the request    
        //         try {
        //             
        //             // User-specific item browsing
        //             if ($userToView = $request->get('user')) {
        //                         
        //                 // Must be logged in to view items specific to certain users
        //                 if (!$controller->isAllowed('browse', 'Users')) {
        //                     throw new Exception( 'May not browse by specific users.' );
        //                 }
        //                 
        //                 if (is_numeric($userToView)) {
        //                     $filter['user'] = $userToView;
        //                 }
        //             }
        // 
        //             if ($request->get('featured')) {
        //                 $filter['featured'] = true;
        //             }
        //             
        //             if ($collection = $request->get('collection')) {
        //                 $filter['collection'] = $collection;
        //             }
        //             
        //             if ($type = $request->get('type')) {
        //                 $filter['type'] = $type;
        //             }
        //             
        //             if ((($tag = $request->get('tag')) != '') || 
        //                 (($tag = $request->get('tags')) != '')) {
        //                 $filter['tags'] = $tag;
        //             }
        //             
        //             if (($excludeTags = $request->get('excludeTags'))) {
        //                 $filter['excludeTags'] = $excludeTags;
        //             }
        //             
        //             $recent = $request->get('recent');
        //             if ($recent !== 'false') {
        //                 $order['recent'] = true;
        //             }
        //             
        //             if ($search = $request->get('search')) {
        //                 $filter['search'] = $search;
        //                 //Don't order by recent-ness if we're doing a search
        //                 unset($order['recent']);
        //             }
        //             
        //             //The advanced or 'itunes' search
        //             if ($advanced = $request->get('advanced')) {
        //                 
        //                 //We need to filter out the empty entries if any were provided
        //                 foreach ($advanced as $k => $entry) {                    
        //                     if (empty($entry['element_id']) || empty($entry['type'])) {
        //                         unset($advanced[$k]);
        //                     }
        //                 }
        //                 $filter['advanced_search'] = $advanced;
        //             };
        //             
        //             if ($range = $request->get('range')) {
        //                 $filter['range'] = $range;
        //             }
        //             
        //         } catch (Exception $e) {
        //             $controller->flash($e->getMessage());
        //         }
        // $params = array_merge($perms, $filter, $order);
        
        //Get the item count after other filtering has been applied, which is the total number of items found
        $totalResults = $itemTable->count($params);
        Zend_Registry::set('total_results', $totalResults);                
        
        //Permissions are checked automatically at the SQL level
        $totalItems = $itemTable->count();
        Zend_Registry::set('total_items', $totalItems);
        
        // Now that we are retrieving the actual result set, limit and offset are applied.        
        $itemsPerPage = $this->getItemsPerPage();
        
        //Retrieve the items themselves
        $items = $itemTable->findBy($params, $itemsPerPage, $resultPage);
        
        return array(
            'items'=>$items, 
            'total_results'=>$totalResults, 
            'total_items' => $totalItems, 
            'page' => $resultPage, 
            'per_page' => $itemsPerPage);
    }
    
    /**
      * Returns whether the parameter value is true or not.  
      * If the value is a string and its lowercased value is 'true' or '1', it returns true.
      * If the value is an integer and equal to 1, then it returns true.
      * Otherwise it returns false.
      * @param string $paramValue
      * @return boolean
      **/
    private function _isTrue($paramValue) 
    {
        if ($paramValue === null) {
            return false;
        }
        $paramValue = strtolower(trim($paramValue));
        return ($paramValue == '1' || $paramValue == 'true');
    }
    
    /**
     * Retrieve the number of items to display on any given browse page.
     * This can be modified as a query parameter provided that a user is actually logged in.
     *
     * @return integer
     **/
    public function getItemsPerPage()
    {
        //Retrieve the number from the options table
        $options = Omeka_Context::getInstance()->getOptions();
        
        if (is_admin_theme()) {
            $perPage = (int) $options['per_page_admin'];
        } else {
            $perPage = (int) $options['per_page_public'];
        }
        
        // If users are allowed to modify the # of items displayed per page, 
        // then they can pass the 'per_page' query parameter to change that.        
        if ($this->_actionController->isAllowed('modifyPerPage', 'Items') && ($queryPerPage = $this->getRequest()->get('per_page'))) {
            $perPage = $queryPerPage;
        }     
        
        // We can never show less than one item per page (bombs out).
        if ($perPage < 1) {
            $perPage = 1;
        }
        
        return $perPage;
    }
}
