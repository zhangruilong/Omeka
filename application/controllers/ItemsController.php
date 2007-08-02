<?php

require_once 'Item.php';
/**
 * @package Omeka
 **/
require_once 'Kea/Controller/Action.php';
class ItemsController extends Kea_Controller_Action
{		
	public function init() 
	{
		$this->_table = $this->getTable('Item');
		$this->_modelClass = 'Item';
	}
	
	/**
	 * This wraps the builtin method with permissions checks
	 *
	 **/
	public function editAction()
	{
		if($user = Kea::loggedIn()) {
			
			$item = $this->findById();
		
			//If the user cannot edit any given item
			if($this->isAllowed('editAll') or 
				//Check if they can edit this specific item
				($this->isAllowed('editSelf') and $item->wasAddedBy($user))) {
				
				return parent::editAction();	
			}
		}

		$this->_forward('index','forbidden');
	}
	
	/**
	 * Wrapping this crap with permissions checks
	 *
	 **/
	public function deleteAction()
	{
		if($user = Kea::loggedIn()) {
			$item = $this->findById();
			
			//Permission check
			if($this->isAllowed('deleteAll') or ( $this->isAllowed('deleteSelf') and $item->wasAddedBy($user) )) {
				$item->delete();
				
				$this->_redirect('delete', array('controller'=>'items'));
			}
		}
		$this->_forward('index', 'forbidden');
	}

	public function tagsAction()
	{
		$this->_forward('Tags', 'browse', array('tagType' => 'Item', 'renderPage'=>'items/tags.php'));
	}

	/**
	 * New Strategy: this will run a SQL query that selects the IDs, then use that to hydrate the Doctrine objects.
	 * Stupid Doctrine.  Maybe their new version will be better.
	 *
	 * @return mixed|void
	 **/
	public function browseAction()
	{	
		$perms = array();
		$filter = array();
		$order = array();
		
		//Show only public items
		if( $this->_getParam('public') ) {
			$perms['public'] = true;
		}
		//Show all items
		elseif( $this->isAllowed('showNotPublic')) {}
		
		//Otherwise check if specific user can see their own items
		elseif($this->isAllowed('showSelfNotPublic')) {			
			$perms['publicAndUser'] = Kea::loggedIn();
		}
		//Find public items by default
		else {
			$perms['public'] = true;
		}
		
		//Here we add some filtering for the request	
		try {
			
			//User-specific item browsing
			if($userToView = $this->_getParam('user')) {
						
				//Must be logged in to view items specific to certain users
				if(!$this->isAllowed('browse', 'Users')) {
					throw new Exception( 'May not browse by specific users.' );
				}
			
				if(is_numeric($userToView)) {
					$filter['user'] = $userToView;
				}
			}
			
			if($this->_getParam('featured')) {
				$filter['featured'] = true;
			}
			
			if($collection = $this->_getParam('collection')) {
				$filter['collection'] = $collection;
			}
			
			if($type = $this->_getParam('type')) {
				$filter['type'] = $type;
			}
			
			if( ($tag = $this->_getParam('tag')) || ($tag = $this->_getParam('tags')) ) {
				$filter['tags'] = $tag;
			}
			
			if(($excludeTags = $this->_getParam('withoutTags'))) {
				$filter['excludeTags'] = $excludeTags;
			}
			
			if($search = $this->_getParam('search')) {
				$filter['search'] = $search;
			}
			
			if($this->_getParam('recent')) {
				$order['recent'] = true;
			}
			
			
		} catch (Exception $e) {
			$this->flash($e->getMessage());
		}
		
		//Get the item count after permissions have been applied, which is the total number of items possible to see
		$total_items = $this->getTable('Item')->findBy($perms, true);
		Zend::register('total_items', $total_items);
		
		
		$params = array_merge($perms, $filter, $order);

		//Get the item count after other filtering has been applied, which is the total number of items found
		$total_results = $this->getTable('Item')->findBy($params, true);
		Zend::register('total_results', $total_results);


		
		/** 
		 * Now process the pagination
		 * 
		 **/
		$paginationUrl = $this->getRequest()->getBaseUrl().'/items/browse/';
		$options = array(	'per_page'=>	4,
							'page'		=> 	1,
							'pagination_url' => $paginationUrl);
							
		//check to see if these options were changed by request vars
		$reqOptions = $this->_getAllParams();
		
		$options = array_merge($options, $reqOptions);
		
		$config_ini = Zend::Registry('config_ini');

		if ($config_ini->pagination->per_page)
		{
			$per_page = $config_ini->pagination->per_page;
		} else {
			echo "copy your config.ini.changeme file over to the config.ini file in the application/config directory";
		}
		
		$params['page'] = $options['page'];
		$params['per_page'] = $per_page;
		
		if($per_page = $this->_getParam('per_page')) {
			$params['per_page'] = $per_page;
		}
		
		//Retrieve the items themselves
		$items = $this->getTable('Item')->findBy($params);

		//Serve up the pagination
		$pagination = array('menu'=>$menu, 'page'=>$options['page'], 'per_page'=>$params['per_page'], 'total_results'=>$total_results, 'link'=>$options['pagination_url']);
		Zend::register('pagination', $pagination);
		
		$this->pluginHook('onBrowseItems', array($items));
		
		return $this->render('items/browse.php', compact('total_items', 'items'));
	}

	/**
	 * Get all the collections and all the active plugins for the form
	 *
	 * @return void
	 **/
	protected function loadFormData() 
	{
		$collections = $this->getTable('Collection')->findAll();
		$types = $this->getTable('Type')->findAll();
		
		if($this->_view) {
			$this->_view->assign(compact('collections', 'plugins', 'types'));
		}
	}
	
	public function showAction() 
	{
		$item = $this->findById();
		$user = Kea::loggedIn();
		
		//If the item is not public, check for permissions
		$canSeeNotPublic = 	($this->isAllowed('showNotPublic') or 
				($this->isAllowed('showSelfNotPublic') and $item->wasAddedBy($user)));
		
		if(!$item->public && !$canSeeNotPublic) {
			$this->_redirect('403');
		}
		
		//Add the tags
		 
		if(array_key_exists('modify_tags', $_POST) || !empty($_POST['tags'])) {
			
		 	if($this->isAllowed('tag')) {
				$tagsAdded = $item->commitForm($_POST);
				$item = $this->findById();
			}else {
				$this->flash('User does not have permission to add tags.');
			}
		}

		//@todo Does makeFavorite require a permissions check?
		if($this->getRequest()->getParam('makeFavorite')) {
			$item->toggleFavorite($user);
			$this->pluginHook('onMakeFavoriteItem', array($item, $user));
		}

		$item->refresh();
		
		Zend::register('item', $item);
		
		$this->pluginHook('onShowItem', array($item));
		
		return $this->render('items/show.php', compact("item", 'user'));
	}
	
	/**
	 * 
	 * @since Supports public and featured changes on items
	 * @return void
	 **/
	public function powerEditAction()
	{
		/*POST in this format:
		 			items[1][public],
		 			items[1][featured],
					items[1][id],
					items[2]...etc
		*/
		if(empty($_POST)) {
			$this->_redirect('items/browse');
		}
		
		
		if(!$this->isAllowed('makePublic')) {
			throw new Exception( 'User is not allowed to modify visibility of items.' );
		}

		if(!$this->isAllowed('makeFeatured')) {
			throw new Exception( 'User is not allowed to modify' );
		}
		
		if($item_a = $this->_getParam('items')) {
									
			//Loop through the IDs given and toggle
			foreach ($item_a as $k => $fields) {

				$item = $this->findById($fields['id']);
	
				//Process the public field
								
				//If public has been checked
				$item->public = array_key_exists('public', $fields);
				
				$item->featured = array_key_exists('featured', $fields);
								
				$item->save();
			}		
		}
		
		$this->_redirect('items/browse');
	}
	
}
?>