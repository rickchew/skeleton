<?php
/**
 * CodeIgniter Skeleton
 *
 * A ready-to-use CodeIgniter skeleton  with tons of new features
 * and a whole new concept of hooks (actions and filters) as well
 * as a ready-to-use and application-free theme and plugins system.
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2018, Kader Bouyakoub <bkader@mail.com>
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
 * @package 	CodeIgniter
 * @author 		Kader Bouyakoub <bkader@mail.com>
 * @copyright	Copyright (c) 2018, Kader Bouyakoub <bkader@mail.com>
 * @license 	http://opensource.org/licenses/MIT	MIT License
 * @link 		https://github.com/bkader
 * @since 		Version 1.0.0
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kbcore_menu Class
 *
 * Handles operations done on site navigations and menus.
 *
 * @package 	CodeIgniter
 * @subpackage 	Skeleton
 * @category 	Libraries
 * @author 		Kader Bouyakoub <bkader@mail.com>
 * @link 		https://github.com/bkader
 * @copyright	Copyright (c) 2018, Kader Bouyakoub (https://github.com/bkader)
 * @since 		Version 1.0.0
 * @version 	1.0.0
 */
class Kbcore_menus extends CI_Driver
{
	/**
	 * Holds the current front-end theme.
	 * @var string
	 */
	private $_theme;

	/**
	 * Holds the front-end theme language index.
	 * @var string
	 */
	private $_theme_domain;

	/**
	 * Cache all current front-end's menus locations.
	 * @var array
	 */
	private $_locations;

	/**
	 * Holds cached menus to reduce DB access.
	 * @var array
	 */
	private $_menus;

	/**
	 * Array of relationship between locations and themes.
	 * @var array
	 */
	private $_locations_menus;

	/**
	 * Holds an array of cached menus items to reduce DB access.
	 * @var array
	 */
	private $_items;

	/**
	 * Cache relations between menus and items.
	 * @var array
	 */
	private $_relations;

	/**
	 * Array of menu columns to select when getting
	 * menus from database.
	 */
	private $_menu_columns = array(
		'entities.id',
		'entities.username AS slug',
		'entities.privacy',
		'groups.name',
		'groups.description',
	);

	/**
	 * Array of items columns to select when getting
	 * menus items from database.
	 * @var array
	 */
	private $_item_columns = array(
		'entities.id',
		'entities.parent_id',
		'entities.owner_id AS menu_id',
		'objects.name AS title',
		'objects.description',
		'objects.content AS href',
	);

	/**
	 * Initialize class to make helpers available to application.
	 * @access 	protected
	 * @return 	void
	 */
	public function initialize()
	{
		// Make sure to load needed helpers.
		$this->ci->load->helper(array('url', 'html', 'inflector'));

		log_message('info', 'Kbcore_menus Class Initialized');
	}

	// --------------------------------------------------------------------
	// Theme Methods.
	// --------------------------------------------------------------------

	/**
	 * Return the currently active menu.
	 * @access 	private
	 * @param 	none
	 * @return 	string
	 */
	private function _theme()
	{
		/**
		 * We make sure to always cache things to reduce DB access;
		 * If the theme is not already cached, get it and cache it.
		 */
		if ( ! isset($this->_theme))
		{
			$this->_theme = $this->_parent->options->item('theme', 'default');
		}

		// Return the cached version.
		return $this->_theme;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns the current theme's language index.
	 * For internal use only, never publicly accessible.
	 * @access 	private
	 */
	private function _theme_domain()
	{
		if (isset($this->_theme_domain))
		{
			return $this->_theme_domain;
		}

		$this->_theme_domain = $this->ci->theme->theme_domain();
		return $this->_theme_domain;
	}

	// --------------------------------------------------------------------
	// Locations Methods.
	// --------------------------------------------------------------------

	/**
	 * Add a single or multiple menus locations for the current theme.
	 * @access 	public
	 * @param 	string 	$location 		the location slug.
	 * @param 	string 	$description 	The location's description or name.
	 * @return 	object
	 */
	public function add_location($location, $description = null)
	{
		// Add multiple locations?
		if (is_array($location))
		{
			$locations = array();

			foreach ($location as $key => $val)
			{
				if (is_int($key))
				{
					$locations[$val] = $description;
				}
				else
				{
					$locations[$key] = $val;
				}
			}

			if ($locations <> $this->_locations())
			{
				$this->_set_locations($locations);
			}

			return $this;
		}

		// Add a single location.

		// Add it if it does not exists!
		$locations = $this->_locations();
		if ( ! isset($locations[$location]))
		{
			$locations[$location] = htmlentities($description, ENT_QUOTES, 'UTF-8');
			$this->_set_locations($locations);
		}

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Delete a menu location for the current theme.
	 * @access 	public
	 * @param 	string 	$location 	location identifier.
	 * @return 	object
	 */
	public function delete_location($location)
	{
		// Get all locations first.
		$locations = $this->_locations();

		// The location exists?
		if (isset($locations[$location]))
		{
			// Unset it and update locations.
			unset($locations[$location]);
			$this->_set_locations($locations);
		}

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Update locations for the current theme.
	 * For internal use only, never accessible.
	 * @access 	private
	 * @param 	array 	$locations
	 * @return 	object
	 */
	private function _set_locations($locations = array())
	{
		// Prepare the option's name.
		$option_name = 'theme_menus_'.$this->_theme();

		// Try to get the option from database.
		$option = $this->_parent->options->get($option_name);

		// Update cached locations.
		$this->_locations = $locations;

		// If locations are already stored, update them.
		if ($option)
		{
			return $this->_parent->options->set_item($option_name, $locations);
		}

		// If the option does not exist, create it.
		return $this->_parent->options->create(array(
			'name'     => $option_name,
			'value'    => $locations,
			'tab'      => 'menus',
			'required' => 0,
		));
	}

	// --------------------------------------------------------------------

	/**
	 * Retrieve registered locations.
	 * For internal use only, not publicly accessible.
	 * @access 	private
	 * @return 	array
	 */
	private function _locations()
	{
		// Already cached? Return them.
		if (isset($this->_locations))
		{
			return $this->_locations;
		}

		// Get locations from database and cache them to reduce DB access.
		$this->_locations = $this->_parent->options->item(
			'theme_menus_'.$this->_theme(),
			array()
		);
		return $this->_locations;
	}

	// --------------------------------------------------------------------

	/**
	 * Return the current theme's menus location.
	 * @access 	public
	 * @return 	array
	 */
	public function get_locations()
	{
		// Get the locations first.
		$locations = $this->_locations();

		// Loop through locations and see if they need to be translated.
		foreach ($locations as $slug => &$location)
		{
			if (sscanf($location, 'lang:%s', $translated) === 1)
			{
				$location = $this->ci->lang->line($translated, $this->_theme_domain());
			}
		}

		// Return the result.
		return $locations;
	}

	// ------------------------------------------------------------------------

	/**
	 * Retrieve all menus assigned to the select location.
	 * @access 	public
	 * @param 	string 	$location 	The location's slug.
	 * @return 	array 	array of menu if found, else empty array
	 */
	public function get_location_menu($location)
	{
		// Prepare menu id.
		$menu_id = 0;

		// Already cached? Return it.
		if (isset($this->_locations_menus[$location]))
		{
			$menu_id = $this->_locations_menus[$location];
		}
		// Not cached? Get it from databaes.
		else
		{
			$meta = $this->_parent->metadata->get_by(array(
				'name' => 'menu_location',
				'value' => $location,
			));

			// Found? Cache it to reduce DB access.
			if ($meta && $menu = $this->get_menu($meta->guid))
			{
				$menu_id = $meta->guid;

				// Cache the result.
				$this->_locations_menus[$location] = $menu_id;
			}

			return ($meta) ? $this->get_menu($meta->guid) : null;
		}

		return $this->get_menu($menu_id);
	}

	// ------------------------------------------------------------------------

	/**
	 * Assign a location to the selected menu.
	 * @access 	public
	 * @param 	mixed 	$location 	location slug or array.
	 * @param 	int|null $menu_id 	the menu's id or null if location is an array.
	 * @return 	bool.
	 */
	public function set_menu_location($location, $menu_id = null)
	{
		// Multiple locations and menus?
		if (is_array($location))
		{
			// $location = array_unique($location);
			foreach ($location as $_location => $_menu_id)
			{
				$this->set_menu_location($_location, $_menu_id);
			}

			return true;
		}

		// TODO:
		// This was removed because it was causing menus
		// not to be removed.
		// Make sure the menu exists.
		// if ( ! $this->get_menu($menu_id))
		// {
		// 	return false;
		// }

		// Make sure the location exists.
		$locations = $this->_locations();
		if ( ! isset($locations[$location]))
		{
			return false;
		}

		// If $menu_id is set to 0, we remove location from all menus.
		if ($menu_id == 0)
		{
			return $this->_parent->metadata->update_by(
				array(
					'name'  => 'menu_location',
					'value' => $location,
				),
				array('value' => null)
			);
		}

		return $this->_parent->metadata->update_meta($menu_id, 'menu_location', $location);
	}

	// ------------------------------------------------------------------------
	// Menus Methods.
	// ------------------------------------------------------------------------

	/**
	 * Create a new menu.
	 * @access 	public
	 * @param 	string 	$name 			The menu's name.
	 * @param 	string 	$description 	The menu's description.
	 * @return 	int 	the menu's id if created, else false.
	 */
	public function add_menu($name, $description = null)
	{
		// Let's prepare $data to be inserted.
		$data['subtype'] = 'menu';

		// Let's generate the slug.
		$slug = url_title($name, '-', true);

		// Add the name, slug and description.
		$data['name']        = $name;
		$data['username']    = $slug;
		$data['description'] = $description;

		return $this->_parent->groups->create($data);
	}

	// ------------------------------------------------------------------------

	/**
	 * Update an existing menu by its id.
	 * @access 	public
	 * @param 	int 	$id 	the menu's id.
	 * @param 	string 	$name 	the menu's new name.
	 * @param 	string 	$slug 	the menu's new slug.
	 * @param 	string 	$description the menu's description.
	 * @return 	boolean
	 */
	public function update_menu($id, $name, $slug = null, $description = null)
	{
		// Make sure the menu exists first.
		$menu = $this->get_menu($id);
		if ( ! $menu)
		{
			return false;
		}

		// Prepare $data to update.
		$data['name'] = $name;

		/**
		 * If a new slug is provided and is different from
		 * the menu's actual slug, we add it.
		 */
		if ( ! empty($slug) && $slug !== $menu->slug)
		{
			$data['username'] = $slug;
		}

		// Add the description if any.
		(empty($description)) OR $data['description'] = $description;

		// Attempt to update the menu.
		return $this->_parent->groups->update($id, $data);
	}

	// ------------------------------------------------------------------------

	/**
	 * Delete the selected menu.
	 * @access 	public
	 * @param 	int 	$id 	the menu's id.
	 * @return 	boolean
	 */
	public function delete_menu($id)
	{
		// Process status.
		$status = false;

		// The menu does not exist?
		if ( ! $this->get_menu($id))
		{
			return $status;
		}

		// Proceed
		$status = $this->_parent->entities->remove($id);

		// Deleted? Remove all items as well.
		if ($status === true)
		{
			$this->_delete_menu_items($id);
		}

		return $status;
	}

	// ------------------------------------------------------------------------

	/**
	 * Retrieve a single menu by its id or slug.
	 * @access 	public
	 * @param 	mixed 	$id 	id or slug.
	 * @return 	object if found, else null.
	 */
	public function get_menu($id)
	{
		// Empty menu to start.
		$menu = null;

		// Already cached? Get it.
		if (isset($this->_menus[$id]))
		{
			// By ID?
			if (is_numeric($id) && isset($this->_menus[$id]))
			{
				$menu = $this->_menus[$id];
				$menu->cached = true;
			}
			// By slug?
			else
			{
				foreach ($this->_menus as &$menu)
				{
					if ($menu->slug === $id)
					{
						$menu->cached = true;
						break;
					}
				}
			}
		}
		// Not cached? Get it from database
		else
		{
			// Prepare the search criteria.
			$where['entities.type']    = 'group';
			$where['entities.subtype'] = 'menu';

			// In case of using an id.
			if (is_numeric($id))
			{
				$where['entities.id'] = $id;
			}
			// By slug?
			else
			{
				$where['entities.username'] = $id;
			}

			// Attempt to get the menu from database.
			$menu = $this->ci->db
				->select($this->_menu_columns)
				->where($where)
				->join('groups', 'groups.guid = entities.id')
				->get('entities')
				->row();

			// If the menu was found, add location and location's name.
			if ($menu)
			{
				// Get all locations to user their name and slug.
				$locations = $this->get_locations();


				// $menu->location = $this->_parent->metadata->get_meta($menu->id, 'menu_location', true);
				$menu->location      = $this->_get_menu_location($menu->id);
				$menu->location_name = null;

				// Get the name of the location.
				if ($menu->location && isset($locations[$menu->location]))
				{
					$menu->location_name = $locations[$menu->location];
				}

				// Cache the menu to reduce DB access.
				$this->_menus[$id] = $menu;
			}
		}

		// Return the menu.
		return $menu;
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns the selected menu location if found.
	 * @access 	private
	 * @param 	int 	$menu_id
	 * @return 	string 	if found, else null.
	 */
	private function _get_menu_location($menu_id = 0)
	{
		return $this->_parent->metadata->get_meta($menu_id, 'menu_location', true);
	}

	// ------------------------------------------------------------------------

	/**
	 * Retrieve all site's menus.
	 * @access 	public
	 * @return 	array of objects.
	 */
	public function get_menus()
	{
		// Already cached? Return theme.
		if (isset($this->_menus))
		{
			return $this->_menus;
		}

		// Try to get menus from database.
		$menus = $this->_parent->groups
			->select($this->_menu_columns)
			->get_many('entities.subtype', 'menu');

		// If there are any menus, get their location.
		if ($menus)
		{
			// Get all locations to user their name and slug.
			$locations = $this->get_locations();

			// Loop through menus and add location's name and slug.
			foreach ($menus as $menu)
			{
				$menu->location = $this->_parent->metadata->get_meta($menu->id, 'menu_location', true);
				$menu->location_name = null;
				if ($menu->location && isset($locations[$menu->location]))
				{
					$menu->location_name = $locations[$menu->location];
				}
			}
		}

		// Cache menus to reduce DB access.
		$this->_menus = $menus;

		// $menus = array();
		return $this->_menus;
	}

	// ------------------------------------------------------------------------
	// Menus Items Methods.
	// ------------------------------------------------------------------------

	/**
	 * Add a new item to the selected menu.
	 * @access 	public
	 * @param 	int 	$menu_id 	the menu's id.
	 * @param 	string 	$title 		the item' title.
	 * @param 	string 	$href 		the item's URL.
	 * @param 	string 	$description the item's description.
	 * @param 	array 	$attrs 		array of additional attributes.
	 * @param 	int  	$parent_id 	the parent's ID.
	 * @return 	int 	the item's id if created, else false.
	 */
	public function add_item($menu_id, $title, $href = '#', $description = null, $attrs = array(), $parent_id = 0)
	{
		// Make sure the menu exists.
		if ( ! $this->get_menu($menu_id) OR empty($title))
		{
			return false;
		}

		// Prepare data to insert.
		$data = array(
			'parent_id'   => $parent_id,
			'owner_id'    => $menu_id,
			'subtype'     => 'menu_item',
			'name'        => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
			'description' => $description,
			'content'     => $href,
		);

		// Try to create the item.
		$item_id = $this->_parent->objects->create($data);

		// If the item was created, add attributes.
		if ($item_id > 0)
		{
			$this->_parent->metadata->add_meta($item_id, array(
				'attributes' => $attrs,
				'order'      => count($this->get_menu_items($menu_id)),
			));
		}

		// Return the item id.
		return $item_id;
	}

	// ------------------------------------------------------------------------

	/**
	 * Update an existing menu item.
	 * @access 	public
	 * @param 	int 	$id 	the item's id.
	 * @param 	string 	$title 		the item' title.
	 * @param 	string 	$href 		the item's URL.
	 * @param 	string 	$description the item's description.
	 * @param 	array 	$attrs 		array of additional attributes.
	 * @return 	int 	the item's id if created, else false.
	 */
	public function update_item($id, $title, $href = '#', $description = null, $attrs = array())
	{
		// Make sure the item exists.
		$item = $this->get_item($id);
		if ( ! $item)
		{
			return false;
		}

		/**
		 * Prepare the update array and add
		 * elements only if they are different.
		 */
		$data = array();

		// The title.
		if ($title <> $item->title)
		{
			$data['name'] = htmlentities($title, ENT_QUOTES, 'UTF-8');
		}

		// The description.
		if ($description <> $item->description)
		{
			$data['description'] = htmlentities($description, ENT_QUOTES, 'UTF-8');
		}

		// The URL.
		if ($href <> $item->href)
		{
			$data['content'] = htmlentities($href, ENT_QUOTES, 'UTF-8');
		}

		/**
		 * We add attributes to $data array because objects
		 * library will split it anyways
		 */
		$data['attributes'] = $attrs;

		/**
		 * If the update $data is empty, nothing to do
		 * concerning the menu item, so we set $status to true.
		 * Otherwise, the status will be the update process status.
		 */
		return (empty($data))
			? true
			: $this->_parent->objects->update($id, $data);
	}

	// ------------------------------------------------------------------------

	/**
	 * Delete an existing item.
	 * @access 	public
	 * @param 	int 	$id 	the item's id.
	 * @return 	boolean
	 */
	public function delete_item($id)
	{
		return $this->_parent->entities->remove($id);
	}

	// ------------------------------------------------------------------------

	/**
	 * Retrieve a single item by it's id.
	 * @access 	public
	 * @param 	int 	$id 	the item's id.
	 * @return 	object if found, else null.
	 */
	public function get_item($id)
	{
		// Already cached? Return it.
		if (isset($this->_items[$id]))
		{
			$item = $this->_items[$id];
			$item->cached = true;
		}
		// Not cached?
		else
		{
			// Try to get it from database.
			$item = $this->_parent->objects
				->select($this->_item_columns)
				->get_by(array(
					'entities.id'      => $id,
					'entities.subtype' => 'menu_item',
				));

			// Found? Get attributes and order.
			if ($item)
			{
				$item->order      = abs($this->_parent->metadata->get_meta($id, 'order', true));
				$item->attributes = $this->_parent->metadata->get_meta($id, 'attributes', true);

				// Cache it to reduce DB access.
				$this->_items[$id] = $item;
			}
		}

		return $item;
	}

	// ------------------------------------------------------------------------

	/**
	 * Retrieve all registered menu items from database.
	 * @access 	public
	 * @return 	array of object if found, else empty array.
	 */
	public function get_items()
	{
		// Array of cached items IDs and menu items.
		$cached_ids = array();
		$items      = array();

		// Area there any items cached?
		if (isset($this->_items))
		{
			$cached_ids = array_keys($this->_items);
			$items      = array_values($this->_items);

			// Set items as cached.
			array_walk($items, function($item) {
				$item->cached = true;
			});
		}

		// Get the rest from database.

		// No items cached?
		if (empty($cached_ids))
		{
			$db_items = $this->_parent->objects
				->select($this->_item_columns)
				->get_many('subtype', 'menu_item');
		}
		// If there are cached items, exclude them.
		else
		{
			$db_items = $this->_parent->objects
				->select($this->_item_columns)
				->where_not_in('id', $cached_ids)
				->get_many('subtype', 'menu_item');
		}

		// Found? Get attributes and order.
		if ($db_items)
		{
			foreach ($db_items as &$item)
			{
				$item->order      = abs($this->_parent->metadata->get_meta($item->id, 'order', true));
				$item->attributes = $this->_parent->metadata->get_meta($item->id, 'attributes', true);

				// Cache items to reduce DB access.
				$this->_items[$item->id] = $item;
			}

			// Merge everything.
			$items = array_merge($items, $db_items);
		}

		// Sort elements
		if ($items)
		{
			usort($items, array($this, '_sort_items'));
		}

		return $items;
	}

	// ------------------------------------------------------------------------

	/**
	 * Retrieve all items listed under the selected menu's id OR slug.
	 * @access 	public
	 * @param 	mixed 	$id 	The menu's id or slug.
	 * @return 	array of objects if found, else empty array.
	 */
	public function get_menu_items($id)
	{
		// Get the menu and make sure it exists.
		$menu = $this->get_menu($id);
		if ( ! $menu)
		{
			return array();
		}
		(is_numeric($id)) OR $id = $menu->id;

		// Array of cached items.
		$cached_ids = array();
		$items      = array();

		// Are there any elements cached?
		if (isset($this->_items))
		{
			foreach ($this->_items as $_item)
			{
				if ($_item->menu_id == $id)
				{
					$cached_ids[]  = $_item->id;
					$_item->cached = true;
					$items[]       = $_item;
				}
			}
		}

		// Get the rest from database.
		if (empty($cached_ids))
		{
			$db_items = $this->_parent->objects
				->select($this->_item_columns)
				->get_many(array(
					'entities.owner_id' => $id,
					'entities.subtype' => 'menu_item',
				));
		}
		else
		{
			$db_items = $this->ci->db
				->select($this->_item_columns)
				->join('objects', 'objects.guid = entities.id')
				->where('entities.type', 'object')
				->where('entities.subtype', 'menu_item')
				->where_not_in('entities.id', $cached_ids)
				->get('entities')
				->result();
		}

		// Found? Get attributes and order.
		if ($db_items)
		{
			foreach ($db_items as &$item)
			{
				$item->order      = abs($this->_parent->metadata->get_meta($item->id, 'order', true));
				$item->attributes = $this->_parent->metadata->get_meta($item->id, 'attributes', true);

				// Cache items to reduce DB access.
				$this->_items[$item->id] = $item;
			}

			// Merge everything.
			$items = array_merge($items, $db_items);
		}

		// Sort elements
		if ($items)
		{
			usort($items, array($this, '_sort_items'));
		}

		// Return the result.
		return $items;
	}

	// ------------------------------------------------------------------------

	/**
	 * Update items order for the given menu.
	 * @access 	public
	 * @param 	int 	$id 	the menu's 	id.
	 * @param 	array 	$items 	array of item's IDs.
	 * @return 	boolean
	 */
	public function items_order($id, array $items = array())
	{
		// Get the menu.
		$menu = $this->get_menu($id);

		// Make sure the menu exists and $items are provided.
		if ( ! $menu OR empty($items))
		{
			return false;
		}

		// We start at 0.
		$i = 0;

		// Loop through items and update their order.
		foreach ($items['item'] as $item_id)
		{
			/**
			 * We try to update menu item' order! If it fails, we
			 * return FALSE right away.
			 */
			if ( ! $this->_parent->metadata->update_meta($item_id, 'order', $i))
			{
				return false;
			}

			// Iterate $i.
			$i++;
		}

		// Everything went well?
		return true;
	}

	// ------------------------------------------------------------------------

	/**
	 * Delete all the selected menu items.
	 * @param 	int 	$id 	the menus id.
	 * @return 	boolean
	 */
	private function _delete_menu_items($id)
	{
		// Get menu items.
		return $this->_parent->entities
			->remove_by(array(
				'owner_id' => $id,
				'type'     => 'object',
				'subtype'  => 'menu_item',
			));
	}

	// ------------------------------------------------------------------------

	/**
	 * Order items by 'order' column.
	 * @access 	private
	 * @param 	object 	$a
	 * @param 	object 	$b
	 * @return 	int
	 */
	private function _sort_items($a, $b)
	{
		return $a->order - $b->order;
	}

	// ------------------------------------------------------------------------
	// Menu Builder Methods.
	// ------------------------------------------------------------------------

	public function build_menu(array $args = array())
	{
		// Prepare default array.
		$defaults = array(
			// Location.
			'location' => null,

			// Menu.
			'menu'      => null,
			'menu_tag'  => 'ul',
			'menu_attr' => array(),

			// Container.
			'container'      => 'div',
			'container_attr' => array(),

			// Text before and after the link markup.
			'before' => null,
			'after'  => null,

			// Anchor class and text before and after the link text.
			'link_before'      => null,
			'link_after'       => null,
			'link_attr'        => array(),
			'parent_link_attr' => array(),
		);

		// Merge everything.
		$args = array_replace_recursive($defaults, $args);

		// Fire any filters targeting arguments.
		$args = apply_filters('menu_args', $args);

		// Now we extract everything.
		extract($args);

		// Make sure at least the location or menu are set.
		if (empty($menu) && empty($location))
		{
			return null;
		}

		// Attempt to get the menu.
		$menu = $this->get_menu($menu);

		// If not found, try with the location.
		($menu) OR $menu = $this->get_location_menu($location);

		// No menu found? Nothing to do.
		if ( ! $menu)
		{
			return null;
		}

		// Retrieve menus items.
		$items = $this->get_menu_items($menu->id);

		// If there are no items, nothing to do.
		if ( ! $items)
		{
			return null;
		}

		// Start generating our output.
		$output = '%s';

		// Add menu ID and class if not already added.
		(empty($menu_attr['id'])) && $menu_attr['id'] = 'menu-'.$menu->slug;
		$menu_class = "menu-{$menu->id} menu-{$menu->slug}";
		(isset($menu_attr['class'])) && $menu_class .= ' '.$menu_attr['class'];
		$menu_attr['class'] = $menu_class;

		// Container.
		$container_allowed_tags = apply_filters('menu_container_allowed_tags', array('div', 'nav'));
		if (isset($container) && in_array($container, $container_allowed_tags))
		{
			$container_attr['aria-label'] = $menu->name;
			$container_attr['class'] = (isset($container_attr['class']))
				? "menu-container menu-container-{$menu->id} menu-{$menu->slug}-container ".$container_attr['class']
				: "menu-container menu-container-{$menu->id} menu-{$menu->slug}-container";
			$output = html_tag($container, $container_attr, '%s');
		}
		else
		{
			$menu_attr['aria-label'] = $menu->name;
		}

		// Menu.
		$menu_allowed_tags = apply_filters('menu_allowed_tags', array('div', 'ul'));
		$menu_tag = ( ! empty($menu_tag) && in_array($menu_tag, $menu_allowed_tags))
			? $menu_tag
			: 'ul';

		$output = sprintf($output, html_tag($menu_tag, $menu_attr, '%s'));

		// Before anchor.
		if ($menu_tag === 'ul')
		{
			$before = ( ! isset($before)) ? '<li>' : $before;
			$after  = ( ! isset($after)) ? '</li>' : $after;
		}

		(is_array($link_attr)) OR $link_attr = (array) $link_attr;

		// Build menu items.
		$items_output = '';
		foreach ($items as $item)
		{
			// Add the link location to attributes.
			$item_attr['href'] = (filter_var($item->href, FILTER_VALIDATE_URL) === false)
				? site_url($item->href)
				: $item->href;

			// Generate item class to be targeted.
			$item_attr['class'] = 'menu-item menu-item-'.$item->id;

			// If there is any extra class, append it.
			if (isset($link_attr['class']))
			{
				$item_attr['class'] .= ' '.$link_attr['class'];
				unset($link_attr['class']);
			}

			// Make sure to add the item id as well so we can target
			// it on the front-end.
			$item_attr['id'] = 'menu-item-'.$item->id;

			// There are attributes stored in database? Add them.
			if ( ! empty($item->attributes))
			{
				// We append the class to avoid overriding generic ones.
				if (isset($item->attributes['class']))
				{
					$item_attr['class'] .= ' '.$item->attributes['class'];
					unset($item->attributes['class']);
				}

				// Merge all attributes together.
				$item_attr = array_merge($item_attr, $link_attr, $item->attributes);
			}

			// Add to output.
			$items_output .= $before.html_tag('a', $item_attr, $link_before.htmlspecialchars_decode($item->title).$link_after).$after;
		}

		// Prepare the final output then return it.
		$output = sprintf($output, $items_output);
		return $output;
	}

}

// --------------------------------------------------------------------

if ( ! function_exists('register_menu'))
{
	/**
	 * Add a single or multiple menus locations for the current theme.
	 * @access 	public
	 * @param 	string 	$location 		the location slug.
	 * @param 	string 	$description 	The location's description or name.
	 * @return 	object
	 */
	function register_menu($location, $description = null)
	{
		return get_instance()->kbcore->menus->add_location($location, $description);
	}
}

// --------------------------------------------------------------------

if ( ! function_exists('unregister_menu'))
{
	/**
	 * Delete a menu location for the current theme.
	 * @access 	public
	 * @param 	string 	$location 	location identifier.
	 * @return 	object
	 */
	function unregister_menu($location)
	{
		return get_instance()->kbcore->menus->delete_location($location);
	}
}

// ------------------------------------------------------------------------

if ( ! function_exists('has_menu'))
{
	/**
	 * Returns true if a menu is register on the selected location.
	 * @param 	string 	$location 	the location's slug.
	 * @return 	boolean
	 */
	function has_menu($location)
	{
		return ( ! empty(get_instance()->kbcore->menus->get_location_menu($location)));
	}
}

// --------------------------------------------------------------------

if ( ! function_exists('build_menu'))
{
	/**
	 * Build a menu.
	 * @param 	array 	$args
	 * @return 	string
	 */
	function build_menu(array $args = array())
	{
		return get_instance()->kbcore->menus->build_menu($args);
	}
}
