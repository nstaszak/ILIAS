<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "Services/ADT/classes/class.ilADTFactory.php";

/** 
 * AMD field abstract base class
 * 
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id$
 * 
 * @ingroup ServicesAdvancedMetaData
 */
abstract class ilAdvancedMDFieldDefinition
{
	protected $field_id; // [int]
	protected $record_id; // [int]	
	protected $import_id; // [string]
	protected $position; // [int]
	protected $title; // [string]
	protected $description; // [string]
	protected $searchable; // [bool]
	protected $required; // [bool]
	protected $adt_def; // [ilADTDefinition]
	protected $adt; // [ilADT]
	
	const TYPE_SELECT = 1;
	const TYPE_TEXT = 2;
	const TYPE_DATE = 3;
	const TYPE_DATETIME = 4;
	const TYPE_INTEGER = 5;
	const TYPE_FLOAT = 6;
	const TYPE_LOCATION = 7;
	
	/**
	 * Constructor
	 * 
	 * @param init $a_field_id
	 * @return self
	 */
	public function __construct($a_field_id = null)
	{
		$this->init();		
		$this->read($a_field_id);		
	}
		
	/**
	 * Get definition instance by type 
	 * 
	 * @param int $a_field_id
	 * @param int $a_type
	 * @return self
	 */
	public static function getInstance($a_field_id, $a_type = null)
	{
		global $ilDB;
		
		if(!$a_type)
		{
			$set = $ilDB->query("SELECT field_type".
				" FROM adv_mdf_definition".
				" WHERE field_id = ".$ilDB->quote($a_field_id, "integer"));
			$a_type = $ilDB->fetchAssoc($set);
			$a_type = $a_type["field_type"];			
		}
		
		if(self::isValidType($a_type))
		{						
			$class = "ilAdvancedMDFieldDefinition".self::getTypeString($a_type);
			require_once "Services/AdvancedMetaData/classes/Types/class.".$class.".php";
			return new $class($a_field_id);						
		}
		
		throw new ilException("unknown type ".$a_type);
	}	
	
	/**
	 * Get instance by type string (used by import)
	 * 
	 * @param string $a_type
	 * @return self
	 */
	public static function getInstanceByTypeString($a_type)
	{
		// see self::getTypeString()
		$map = array(
			self::TYPE_TEXT => "Text",
			self::TYPE_SELECT => "Select",
			self::TYPE_DATE => "Date",
			self::TYPE_DATETIME => "DateTime",
			self::TYPE_FLOAT => "Float",
			self::TYPE_LOCATION => "Location",
			self::TYPE_INTEGER => "Integer"				
		);	
		$map = array_flip($map);
		if(array_key_exists($a_type, $map))
		{
			return self::getInstance(null, $map[$a_type]);
		}
	}
	
	/**
	 * Get definitions by record id
	 * 	
	 * @param int $a_record_id
	 * @param bool $a_only_searchable
	 * @return array self
	 */
	public static function getInstancesByRecordId($a_record_id, $a_only_searchable = false)
	{
		global $ilDB;
		
		$defs = array();
		
		$query = "SELECT * FROM adv_mdf_definition".
			" WHERE record_id = ".$ilDB->quote($a_record_id, "integer");
		if($a_only_searchable)
		{
			$query .= " AND searchable = ".$ilDB->quote(1, "integer");
		}
		$query .= " ORDER BY position";
		$set = $ilDB->query($query);		
		while($row = $ilDB->fetchAssoc($set))
		{		
			$field = self::getInstance(null, $row["field_type"]);
			$field->import($row);
			$defs[$row["field_id"]] = $field;
		}
		
		return $defs;	
	}		
	
	public static function getInstancesByObjType($a_obj_type, $a_active_only = true)
	{		
		global $ilDB;
		
		$defs = array();
		
		$query = "SELECT amf.* FROM adv_md_record_objs aro".
			" JOIN adv_md_record amr ON aro.record_id = amr.record_id".
			" JOIN adv_mdf_definition amf ON aro.record_id = amf.record_id".
			" WHERE obj_type = ".$ilDB->quote($a_obj_type,'text');
		if((bool)$a_active_only)
		{
			$query .= " AND active = ".$ilDB->quote(1, "integer");
		}
		$query .= " ORDER BY aro.record_id,position";
		$res = $ilDB->query($query);
		while($row = $ilDB->fetchAssoc($res))
		{
			$field = self::getInstance(null, $row["field_type"]);
			$field->import($row);
			$defs[$row["field_id"]] = $field;
		}
		return $defs;
	}
	
	/**
	 * Get definition instance by import id
	 * 
	 * @param string $a_import_id
	 * @return self
	 */
	public static function getInstanceByImportId($a_import_id)
	{
		global $ilDB;
		
		$query = "SELECT field_id, field_type FROM adv_mdf_definition".
			" WHERE import_id = ".$ilDB->quote($a_import_id,'text');
		$set = $ilDB->query($query);
		if($ilDB->numRows($set))
		{
			$row = $ilDB->fetchAssoc($set);		
			return self::getInstance($row["field_id"], $row["field_type"]);
		}
	}
	
	/**
	 * Get searchable definition ids (performance is key)
	 * 
	 * @return array
	 */
	public static function getSearchableDefinitionIds()
	{
		global $ilDB;
		
		$field_ids = array();		
		
		$query = "SELECT field_id FROM adv_md_record amr".
			" JOIN adv_mdf_definition amfd ON (amr.record_id = amfd.record_id)".
			" WHERE active = ".$ilDB->quote(1, "integer").
			" AND searchable = ".$ilDB->quote(1, "integer");
		$set = $ilDB->query($query);
		while($row = $ilDB->fetchAssoc($set))
		{
			$field_ids[] = $row["field_id"];
		}
		return $field_id; 
	}
	
	/**
	 * Init ADTGroup for definitions
	 * 
	 * @param array $a_defs
	 * @return ilADTGroup
	 */
	public static function getADTGroupForDefinitions(array $a_defs)
	{		
		$factory = ilADTFactory::getInstance();
		$group_def = $factory->getDefinitionInstanceByType("Group");
		foreach($a_defs as $def)
	 	{
			$group_def->addElement($def->getFieldId(), $def->getADTDefinition());
		}
		$group = $factory->getInstanceByDefinition($group_def);				
		
		// bind adt instances to definition
		foreach($group->getElements() as $element_id => $element)
		{
			$a_defs[$element_id]->setADT($element);
		}
		
		return $group;
	}
	
	/**
	 * Init properties
	 */
	protected function init()
	{
		$this->setRequired(false);
		$this->setSearchable(false);
	}
	
	
	//
	// generic types
	// 
	
	/**
	 * Get all valid types
	 * 
	 * @return array
	 */
	public static function getValidTypes()
	{
		return array(self::TYPE_TEXT, self::TYPE_DATE, self::TYPE_DATETIME,
			self::TYPE_SELECT, self::TYPE_INTEGER, self::TYPE_FLOAT,
			self::TYPE_LOCATION);
	}
	
	/**
	 * Is given type valid
	 * 
	 * @param int $a_type
	 * @return bool
	 */
	public static function isValidType($a_type)
	{
		return in_array((int)$a_type, self::getValidTypes());
	}
	
	/**
	 * Get type 
	 * 
	 * @return int
	 */
	abstract public function getType();	
	
	/**
	 * Get type string
	 * 
	 * @param string $a_type
	 * @return string
	 */
	protected static function getTypeString($a_type)
	{
		if(self::isValidType($a_type))
		{		
			$map = array(
				self::TYPE_TEXT => "Text",
				self::TYPE_SELECT => "Select",
				self::TYPE_DATE => "Date",
				self::TYPE_DATETIME => "DateTime",
				self::TYPE_FLOAT => "Float",
				self::TYPE_LOCATION => "Location",
				self::TYPE_INTEGER => "Integer"				
			);		
			return $map[$a_type];
		}		
	}
	
	/**
	 * Get type title (lang id)
	 * 
	 * @return string
	 */
	public function getTypeTitle()
	{
		// :TODO: reuse udf stuff here ?!
		return "udf_type_".strtolower(self::getTypeString($this->getType()));
	}		
	
		
	
	//
	// ADT
	//
	
	/**
	 * Init adt instance
	 * 
	 * @return ilADTDefinition
	 */
	abstract protected function initADTDefinition();
	
	/**
	 * Get ADT definition instance
	 * 
	 * @return ilADTDefinition
	 */
	public function getADTDefinition()
	{
		if(!$this->adt_def instanceof ilADTDefinition)
		{
			$this->adt_def = $this->initADTDefinition();
		}
		return $this->adt_def;
	}	
	
	/**
	 * Get ADT instance
	 * 
	 * @return ilADT
	 */
	public function getADT()
	{	
		if(!$this->adt instanceof ilADT)	
		{
			$this->adt = ilADTFactory::getInstance()->getInstanceByDefinition($this->getADTDefinition());
		}
		return $this->adt;
	}	
	
	/**
	 * Set ADT instance 
	 * 
	 * @see self::getADTGroupForDefinitions()
	 * @param ilADT $a_adt
	 */
	protected function setADT(ilADT $a_adt)
	{	
		if(!$this->adt instanceof ilADT)	
		{
			$this->adt = $a_adt;
		}
	}	
	
	// 
	// properties
	// 
	
	/**
	 * Set field_id
	 *
	 * @param int $a_id 
	 */
	protected function setFieldId($a_id)
	{
	 	$this->field_id = (int)$a_id;
	}
	
	/**
	 * Get field_id
	 *
	 * @return int	 
	 */
	public function getFieldId()
	{
	 	return $this->field_id;
	}
	
	/**
	 * Set record id
	 * 
	 * @param int $a_id
	 */
	public function setRecordId($a_id)
	{
	 	$this->record_id = (int)$a_id;
	}
	
	/**
	 * Get record id
	 *
	 * @return int
	 */
	public function getRecordId()
	{
	 	return $this->record_id;
	}
	
	/**
	 * Set import id
	 *
	 * @param string $a_id_string	  
	 */
	public function setImportId($a_id_string)
	{
		if($a_id_string !== null)
		{
			$a_id_string = trim($a_id_string);
		}
	 	$this->import_id = $a_id_string;
	}
	
	/**
	 * Get import id
	 *
	 * @return string	 
	 */
	public function getImportId()
	{
	 	return $this->import_id;
	}
	
	/**
	 * Set position
	 *
	 * @param int $a_pos
	 */
	public function setPosition($a_pos)
	{
	 	$this->position = (int)$a_pos;
	}
	
	/**
	 * Get position
	 *
	 * @return int
	 */
	public function getPosition()
	{
	 	return $this->position;
	}
	
	/**
	 * Get title
	 *
	 * @param string $a_title
	 */
	public function setTitle($a_title)
	{
		if($a_title !== null)
		{
			$a_title = trim($a_title);
		}
	 	$this->title = $a_title;
	}
	
	/**
	 * Get title
	 * 
	 * @return string
	 */
	public function getTitle()
	{
	 	return $this->title;
	}
	
	/**
	 * Set description
	 *
	 * @param string $a_desc
	 */
	public function setDescription($a_desc)
	{
		if($a_desc !== null)
		{
			$a_desc = trim($a_desc);
		}
	 	$this->description = $a_desc;
	}
	
	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription()
	{
	 	return $this->description;
	}
	
	/**
	 * Toggle searchable
	 *
	 * @param bool searchable	 
	 */
	public function setSearchable($a_status)
	{
	 	$this->searchable = (bool)$a_status;
	}
	
	/**
	 * Is searchable
	 * 
	 * @return bool
	 */
	public function isSearchable()
	{
	 	return $this->searchable;
	}
	
	/**
	 * Toggle required
	 *
	 * @param bool $a_status	 
	 */
	public function setRequired($a_status)
	{
	 	$this->required = (bool)$a_status;
	}
	
	/**
	 * Is required field
	 *
	 * @return bool	 
	 */
	public function isRequired()
	{
	 	return $this->required;
	}
	
	
	//
	// definition (NOT ADT-based)
	// 		
	
	/**
	 * Import (type-specific) field definition from DB
	 * 
	 * @param array $a_def
	 */
	protected function importFieldDefinition(array $a_def)
	{

	}
	
	/**
	 * Get (type-specific) field definition 
	 * 
	 * @return array 
	 */
	protected function getFieldDefinition()
	{
		// type-specific properties
	}
	
	/**
	 * Parse properties for table gui
	 * 
	 * @return array 
	 */
	public function getFieldDefinitionForTableGUI()
	{
		// type-specific properties
	}
	
	/**
	 * Add custom input elements to definition form
	 * 
	 * @param ilPropertyFormGUI $a_form
	 * @param bool $a_disabled
	 */
	protected function addCustomFieldToDefinitionForm(ilPropertyFormGUI $a_form, $a_disabled = false)
	{
		// type-specific
	}
	
	/**
	 * Add input elements to definition form
	 *
	 * @param ilPropertyFormGUI $a_form
	 * @param ilAdvancedMDPermissionHelper $a_form
	 */
	public function addToFieldDefinitionForm(ilPropertyFormGUI $a_form, ilAdvancedMDPermissionHelper $a_permissions)
	{
		global $lng;
		
		$perm = $a_permissions->hasPermissions(
			ilAdvancedMDPermissionHelper::CONTEXT_FIELD,
			$this->getFieldId(),
			array(
				array(ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY,  
					ilAdvancedMDPermissionHelper::SUBACTION_FIELD_TITLE)
				,array(ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY,  
					ilAdvancedMDPermissionHelper::SUBACTION_FIELD_DESCRIPTION)
				,array(ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY,  
					ilAdvancedMDPermissionHelper::SUBACTION_FIELD_SEARCHABLE)
				,array(ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY,  
					ilAdvancedMDPermissionHelper::SUBACTION_FIELD_PROPERTIES)
		));
				
		// title
		$title = new ilTextInputGUI($lng->txt('title'), 'title');
		$title->setValue($this->getTitle());
		$title->setSize(20);
		$title->setMaxLength(70);
		$title->setRequired(true);
		$a_form->addItem($title);
		
		if(!$perm[ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY][ilAdvancedMDPermissionHelper::SUBACTION_FIELD_TITLE])
		{
			$title->setDisabled(true);
		}
		
		// desc
		$desc = new ilTextAreaInputGUI($lng->txt('description'), 'description');
		$desc->setValue($this->getDescription());
		$desc->setRows(3);
		$desc->setCols(50);
		$a_form->addItem($desc);
		
		if(!$perm[ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY][ilAdvancedMDPermissionHelper::SUBACTION_FIELD_DESCRIPTION])
		{
			$desc->setDisabled(true);
		}
		
		// searchable
		$check = new ilCheckboxInputGUI($lng->txt('md_adv_searchable'), 'searchable');
		$check->setChecked($this->isSearchable());
		$check->setValue(1);
		$a_form->addItem($check);
		
		if(!$perm[ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY][ilAdvancedMDPermissionHelper::SUBACTION_FIELD_SEARCHABLE])
		{
			$check->setDisabled(true);
		}
		
		/* required
		$check = new ilCheckboxInputGUI($lng->txt('md_adv_required'), 'required');
		$check->setChecked($this->isRequired());
		$check->setValue(1);
		$a_form->addItem($check);		 
		*/
		
		$this->addCustomFieldToDefinitionForm($a_form,
			!$perm[ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY][ilAdvancedMDPermissionHelper::SUBACTION_FIELD_PROPERTIES]);		
	}
	
	/**
	 * Import custom post values from definition form
	 * 
	 * @param ilPropertyFormGUI $a_form
	 */
	public function importCustomDefinitionFormPostValues(ilPropertyFormGUI $a_form)
	{
		// type-specific
	}
	
	/**
	 * Import post values from definition form
	 * 
	 * @param ilPropertyFormGUI $a_form
	 * @param ilAdvancedMDPermissionHelper $a_permissions
	 */
	public function importDefinitionFormPostValues(ilPropertyFormGUI $a_form, ilAdvancedMDPermissionHelper $a_permissions)
	{
		if(!$a_form->getItemByPostVar("title")->getDisabled())
		{		
			$this->setTitle($a_form->getInput("title"));
		}
		if(!$a_form->getItemByPostVar("description")->getDisabled())
		{	
			$this->setDescription($a_form->getInput("description"));
		}
		if(!$a_form->getItemByPostVar("searchable")->getDisabled())
		{	
			$this->setSearchable($a_form->getInput("searchable"));
		}
		
		if($a_permissions->hasPermission(
			ilAdvancedMDPermissionHelper::CONTEXT_FIELD,
			$this->getFieldId(),			
			ilAdvancedMDPermissionHelper::ACTION_FIELD_EDIT_PROPERTY,  
			ilAdvancedMDPermissionHelper::SUBACTION_FIELD_PROPERTIES))
		{
			$this->importCustomDefinitionFormPostValues($a_form);
		}
	}
	
	public function importDefinitionFormPostValuesNeedsConfirmation()
	{
		return false;
	}
	
	public function prepareCustomDefinitionFormConfirmation(ilPropertyFormGUI $a_form)
	{
		// type-specific
	}
	
	public function prepareDefinitionFormConfirmation(ilPropertyFormGUI $a_form)
	{
		$a_form->getItemByPostVar("title")->setDisabled(true);
		$a_form->getItemByPostVar("description")->setDisabled(true);
		$a_form->getItemByPostVar("searchable")->setDisabled(true);
		
		// checkboxes have no hidden on disabled
		if($a_form->getInput("searchable"))
		{
			$hidden = new ilHiddenInputGUI("searchable");
			$hidden->setValue(1);
			$a_form->addItem($hidden);
		}
		
		$this->prepareCustomDefinitionFormConfirmation($a_form);
	}
	
	
	//
	// definition CRUD 
	//
	
	/**
	 * Get last position of record
	 *
	 * @return int	 
	 */
	protected function getLastPosition()
	{
		global $ilDB;
		
		$sql = "SELECT max(position) pos".
			" FROM adv_mdf_definition".
			" WHERE record_id = ".$ilDB->quote($this->getRecordId(), "integer");
		$set = $ilDB->query($sql);
		if($ilDB->numRows($set))
		{		
			$pos = $ilDB->fetchAssoc($set);
			return (int)$pos["pos"];
		}
		
		return 0;
	}
	
	/**
	 * Generate unique record id
	 *
	 * @param int $a_field_id
	 * @return string
	 */
	protected function generateImportId($a_field_id)
	{
		return 'il_'.IL_INST_ID.'_adv_md_field_'.$a_field_id;
	}
	
	/**
	 * Get all definition properties for DB
	 * 
	 * @return array
	 */
	protected function getDBProperties()
	{	
		$fields = array(
			"field_type" => array("integer", $this->getType()),
			"record_id" => array("integer", $this->getRecordId()),
			"import_id" => array("text", $this->getImportId()),
			"title" => array("text", $this->getTitle()),
			"description" => array("text", $this->getDescription()),
			"position" => array("integer", $this->getPosition()),
			"searchable" => array("integer", $this->isSearchable()),
			"required" => array("integer", $this->isRequired())
		);
		
		$def = $this->getFieldDefinition();
		if(is_array($def))
		{
			$fields["field_values"] = array("text", serialize($def));
		}
		
		return $fields;
	}
	
	/**
	 * Import from DB
	 * 
	 * @param array $a_data
	 */
	protected function import(array $a_data)
	{
		$this->setFieldId($a_data["field_id"]);
		
		$this->setRecordId($a_data["record_id"]);
		$this->setImportId($a_data["import_id"]);		
		$this->setTitle($a_data["title"]);
		$this->setDescription($a_data["description"]);
		$this->setPosition($a_data["position"]);		
		$this->setSearchable($a_data["searchable"]);
		$this->setRequired($a_data["required"]);
		
		if($a_data["field_values"])
		{
			$this->importFieldDefinition(unserialize($a_data["field_values"]));	
		}
	}
	
	/**
	 * Read field definition	 
	 */
	protected function read($a_field_id)
	{
		global $ilDB;
		
		if(!(int)$a_field_id)
		{
			return;
		}
				
		$sql = "SELECT * FROM adv_mdf_definition".
			" WHERE field_id = ".$ilDB->quote($a_field_id, "integer");
		$set = $ilDB->query($sql);
		if($ilDB->numRows($set))
		{
			$row = $ilDB->fetchAssoc($set);
			$this->import($row);					
		}
	}
	
	/**
	 * Create new field entry
	 */
	public function save()
	{
		global $ilDB;
		
		if($this->getFieldId())
		{
			return $this->update();
		}
		
		$next_id = $ilDB->nextId("adv_mdf_definition");
		
		// append		
		$this->setPosition($this->getLastPosition()+1);
		
		// needs unique import id
		if(!$this->getImportId())
		{
			$this->setImportId($this->generateImportId($next_id));
		}
		
		$fields = $this->getDBProperties();		
		$fields["field_id"] = array("integer", $next_id);
		
		$ilDB->insert("adv_mdf_definition", $fields);
	}
	
	/**
	 * Update field entry
	 */
	public function update()
	{
		global $ilDB;
		
		if(!$this->getFieldId())
		{
			return $this->save();
		}
		
		$ilDB->update("adv_mdf_definition", 
			$this->getDBProperties(),
			array("field_id"=>array("integer", $this->getFieldId())));
	}
	
	/**
	 * Delete field entry 
	 */
	public function delete()
	{
	 	global $ilDB;
		
		if(!$this->getFieldId())
		{
			return;
		}
	
		// delete all values
	 	include_once("Services/AdvancedMetaData/classes/class.ilAdvancedMDValues.php");
	 	ilAdvancedMDValues::_deleteByFieldId($this->getFieldId(), $this->getADT());
	 	
	 	$query = "DELETE FROM adv_mdf_definition".
	 		" WHERE field_id = ".$ilDB->quote($this->getFieldId(), "integer");
		$ilDB->manipulate($query);	 	
	}
	
	
	//
	// export/import
	// 
	
	/**
	 * To Xml.
	 * This method writes only the subset Field
	 * Use class.ilAdvancedMDRecordXMLWriter to generate a complete xml presentation.
	 *
	 * @param ilXmlWriter $a_writer
	 */
	public function toXML(ilXmlWriter $a_writer)
	{	 	
	 	$a_writer->xmlStartTag('Field',array(
	 		'id' => $this->generateImportId($this->getFieldId()),
			'searchable' => ($this->isSearchable() ? 'Yes' : 'No'),
			'fieldType'	 => self::getTypeString($this->getType())));
		
		$a_writer->xmlElement('FieldTitle',null,$this->getTitle());
		$a_writer->xmlElement('FieldDescription',null,$this->getDescription());
		$a_writer->xmlElement('FieldPosition',null,$this->getPosition());
		
		$this->addPropertiesToXML($a_writer);
		
		$a_writer->xmlEndTag('Field');
	}
	
	/**
	 * Add (type-specific) properties to xml export
	 * 
	 * @param ilXmlWriter $a_writer
	 */
	protected function addPropertiesToXML(ilXmlWriter $a_writer)
	{
		// type-specific properties
	}

	/**
	 * Import property from XML
	 * 
	 * @param string $a_key
	 * @param string $a_value
	 */
	public function importXMLProperty($a_key, $a_value)
	{
		
	}	
		
	/**
	 * Parse ADT value for xml (export)
	 * 
	 * @param ilADT $element
	 * @return string
	 */
	abstract public function getValueForXML(ilADT $element);	
	
	/**
	 * Import value from xml 
	 * 
	 * @param string $a_cdata	
	 */
	abstract public function importValueFromXML($a_cdata);
		
	/**
	 * Import meta data from ECS
	 * 
	 * @param int $a_ecs_type
	 * @param mixed $a_value
	 * @param string $a_sub_id
	 * @return  bool
	 */
	public function importFromECS($a_ecs_type, $a_value, $a_sub_id)
	{
		return false;
	}			
}

?>