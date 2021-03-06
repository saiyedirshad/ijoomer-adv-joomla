<?php
/**
 * @package     IJoomer.Backend
 * @subpackage  com_ijoomeradv.models
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

/**
 * The Class For IJoomeradvModelReport which will Extends The JModelList
 *
 * @package     IJoomer.Backdend
 * @subpackage  com_ijoomeradv.models
 * @since       1.0
 */
class IjoomeradvModelReport extends JModelList
{
	var $db;

/**
 * Constructor
 */
	public function __construct()
	{
		$this->db = JFactory::getDBO();

		$config = null;

		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'created', 'a.created',
				'status', 'a.status'
			);
		}

		parent::__construct($config);
	}

	/**
	 * The Function For Getting The Report
	 *
	 * @return  it will returns the loadobjectList
	 */
	public function getReports()
	{
		$sql = "SELECT *
				FROM #__ijoomeradv_report";
		$this->db->setQuery($sql);

		return $this->db->loadObjectList();
	}

	/**
	 * The Function For Getting The Extension
	 *
	 * @return  it will returns the loadobjectList
	 */
	public function getExtensions()
	{
		$sql = "SELECT name,classname
				FROM #__ijoomeradv_extensions
				Where published=1";
		$this->db->setQuery($sql);

		return $this->db->loadObjectList();
	}

	/**
	 * The Function For PopulateState
	 *
	 * @param   [type]  $ordering   contains the value of ordering
	 * @param   [type]  $direction  contains the value of directions
	 *
	 * @return  void
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app = JFactory::getApplication('administrator');

		$extension = JRequest::getVar('extensiontype', null);

		if (!empty($extension))
		{
			$app->setUserState($this->context . '.extensiontype', $extension);
		}

		$extensiontype = $app->getUserState($this->context . '.extensiontype');
		$this->setState('filter.extensiontype', $extensiontype);

		// List state information.
		parent::populateState('id', 'asc');
	}

	/**
	 * The Function For The Delete
	 *
	 * @return  boolean it will returns the value in true or false
	 */
	public function delete()
	{
		$cids = JRequest::getVar('cid', array());
		$table = $this->getTable('report', 'IjoomeradvTable');

		foreach ($cids as $cid)
		{
			if (!$table->delete($cid))
			{
				$this->setError($table->getErrorMsg());

				return false;
			}
		}

		return true;
	}

	/**
	 * The Function For The Ignore
	 *
	 * @return  boolean it will returns the value in true or false
	 */
	public function ignore()
	{
		$cid = JRequest::getInt('cid', 0);

		if ($cid)
		{
			$table = $this->getTable('report', 'IjoomeradvTable');
			$table->load($cid);

			if ($table->status != 1)
			{
				$table->status = 2;

				if ($table->store())
				{
					return true;
				}
				else
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * The Function Deletereport is used for Deleting The Report
	 *
	 * @return  void
	 */
	public function deletereport()
	{
		$cid = JRequest::getInt('cid', 0);
		$result = false;

		if ($cid)
		{
			$table = $this->getTable('report', 'IjoomeradvTable');
			$table->load($cid);

			$extension = $table->extension;
			$params = json_decode($table->params);

			if ($extension == 'jomsocial')
			{
				require_once JPATH_ROOT . '/components/com_community/libraries/' . 'core.php';

				switch ($params->type)
				{
					case 'activity':
						CFactory::load('libraries', 'activities');
						$activity = JTable::getInstance('Activity', 'CTable');
						$activity->load($params->content->id);
						$jomparams = json_decode($activity->params);

						switch ($activity->app)
						{
							case 'profile':
								$profile = JTable::getInstance('Profile', 'CTable');
								$profile->load($activity->actor);
								$profile->status = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');

								$activity->title = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');

								$result = ($activity->store()) ? true : false;
								$result = ($profile->store()) ? true : false;
								break;

							case 'albums.comment':
							case 'albums':
								$activity->content = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
								$result = ($activity->store()) ? true : false;

								$wall = JTable::getInstance('Wall', 'CTable');
								$wall->load($jomparams->wallid);
								$wall->comment = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
								break;

							case 'photos':
								if ($jomparams->action == 'upload')
								{
									$activity->title = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
									$photo = JTable::getInstance('Photo', 'CTable');
									$photo->load($jomparams->photoid);
									$photo->caption = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
									$photo->store();
								}
								else
								{
									$activity->content = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
									$wall = JTable::getInstance('Wall', 'CTable');
									$wall->load($jomparams->wallid);
									$wall->comment = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
									$wall->store();
								}

								$result = ($activity->store()) ? true : false;
								break;

							case 'events.wall':
							case 'groups.wall':
								$activity->title = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
								$result = ($activity->store()) ? true : false;
								break;

							default:
								// @TODO : Default
								break;
						}

						break;
					case 'wall':
						switch ($params->content->app)
						{
							case 'videos':
							case 'profile.status':
							case 'albums':
							case 'groups.wall':
							case 'events.wall':
								$wall = JTable::getInstance('Wall', 'CTable');
								$wall->load($params->content->id);
								$wall->comment = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
								$result = ($wall->store()) ? true : false;
								break;
						}
						break;

					case 'photos':
						$photo = JTable::getInstance('Photo', 'CTable');
						$photo->load($params->content->id);
						$photo->caption = JText::_('COM_IJOOMERADV_REPORT_REMOVED_TEXT');
						$result = ($photo->store()) ? true : false;
						break;

					default:
						// @TODO : Default
						break;
				}
			}

			// Set status in report table
			if ($result)
			{
				$table = $this->getTable('report', 'IjoomeradvTable');
				$table->load($cid);
				$table->status = 1;

				if ($table->store())
				{
					return true;
				}
				else
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}
	}

	/**
	 * The Function GetListQuery
	 *
	 * @return  it will returns the query
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$select = 'SELECT a.* ';
		$where = 'WHERE 1 ';
		$groupby = '';

		// Filter by extension type
		$extensiontype = $this->getState('filter.extensiontype');

		if (!empty($extensiontype) && $extensiontype != 'default')
		{
			$where .= "AND extension='$extensiontype' ";
		}

		$cid = JRequest::getVar('cid', 0);

		if ($cid)
		{
			$where .= 'AND a.params=(SELECT params FROM #__ijoomeradv_report WHERE id=' . $cid . ')';
		}
		else
		{
			$select .= ',count(a.id) as itemcount ';
			$groupby .= 'GROUP BY a.params ';
		}

		$query = $select .
			'FROM `#__ijoomeradv_report` AS a ' .
			$where . ' ' .
			$groupby . 'ORDER BY ' . $this->getState('list.ordering', 'id') . ' ' . $this->getState('list.direction', 'ASC');

		return $query;
	}
}
