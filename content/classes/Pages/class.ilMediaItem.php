<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


/**
* Class ilMediaItem
*
* Media Item, component of a media object (file or reference)
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
*
* @package content
*/
class ilMediaItem
{
	var $ilias;
	var $purpose;
	var $location;
	var $location_type;
	var $format;
	var $width;
	var $height;
	var $caption;
	var $halign;
	var $parameters;
	var $mob_id;
	var $nr;

	function ilMediaItem()
	{
		global $ilias;

		$this->ilias =& $ilias;
		$this->parameters = array();
	}

	function setMobId($a_mob_id)
	{
		$this->mob_id = $a_mob_id;
	}

	function getMobId()
	{
		return $this->mob_id;
	}

	function setNr($a_nr)
	{
		$this->nr = $a_nr;
	}

	function getNr()
	{
		return $this->nr;
	}

	function create()
	{
		$query = "INSERT INTO media_item (mob_id, purpose, location, ".
			"location_type, format, width, ".
			"height, halign, caption, nr) VALUES ".
			"('".$this->getMobId()."',".
			"'".$this->getPurpose()."','".ilUtil::prepareDBString($this->getLocation())."','".
			$this->getLocationType()."','".$this->getFormat()."','".
			$this->getWidth()."','".$this->getHeight()."','".$this->getHAlign().
			"','".$this->getCaption()."','".($i+1)."')";
		$this->ilias->db->query($query);
//echo "create_mob:$query:<br>";
		$item_id = getLastInsertId();

		// create mob parameters
		$params = $this->getParameters();
		foreach($params as $name => $value)
		{
			$query = "INSERT INTO mob_parameter (med_item_id, name, value) VALUES ".
				"('".$item_id."', '".$name."', '".$value."')";
			$this->ilias->db->query($query);
		}
	}

	/**
	* static
	*/
	function deleteAllItemsOfMob($a_mob_id)
	{
			// delete media parameter
		$query = "SELECT * FROM media_item WHERE mob_id = '".$a_mob_id."'";
		$item_set = $this->ilias->db->query($query);
		while ($item_rec = $item_set->fetchRow(DB_FETCHMODE_ASSOC))
		{
			$query = "DELETE FROM mob_parameter WHERE med_item_id = '".$item_rec["id"]."'";
			$this->ilias->db->query($query);
		}

		// delete media items
		$query = "DELETE FROM media_item WHERE mob_id = '".$a_mob_id."'";
		$this->ilias->db->query($query);
	}

	function setPurpose($a_purpose)
	{
		$this->purpose = $a_purpose;
	}

	function getPurpose()
	{
		return $this->purpose;
	}

	function setLocation($a_location)
	{
		$this->location = $a_location;
	}

	function getLocation()
	{
		return $this->location;
	}

	function setLocationType($a_type)
	{
		$this->location_type = $a_type;
	}

	function getLocationType()
	{
		return $this->location_type;
	}

	function setFormat($a_format)
	{
		$this->format = $a_format;
	}

	function getFormat()
	{
		return $this->format;
	}

	/**
	* get width
	*/
	function getWidth()
	{
		return $this->width;
	}

	/**
	* set width
	*/
	function setWidth($a_width)
	{
		$this->width = $a_width;
	}

	/**
	* get height
	*/
	function getHeight()
	{
		return $this->height;
	}

	/**
	* set height
	*/
	function setHeight($a_height)
	{
		$this->height = $a_height;
	}

	/**
	* set caption
	*/
	function setCaption($a_caption)
	{
		$this->caption = $a_caption;
	}

	/**
	* get caption
	*/
	function getCaption()
	{
		return $this->caption;
	}

	/**
	* set horizontal align
	*/
	function setHAlign($a_halign)
	{
		$this->halign = $a_halign;
	}

	/**
	* get horizontal align
	*/
	function getHAlign()
	{
		return $this->halign;
	}


	/**
	* set parameter
	*
	* @param	string	$a_name		parameter name
	* @param	string	$a_value	parameter value
	*/
	function setParameter($a_name, $a_value)
	{
		$this->parameters[$a_name] = $a_value;
	}

	/**
	* reset parameters
	*/
	function resetParameters()
	{
		$this->parameters = array();
	}

	/**
	* set alle parameters via parameter string (format: par1="value1", par2="value2", ...)
	*
	* @param	string		$a_par		parameter string
	*/
	function setParameters($a_par)
	{
		$this->resetParameters();
		$par_arr = ilUtil::extractParameterString($a_par);
		if(is_array($par_arr))
		{
			foreach($par_arr as $par => $val)
			{
				$this->setParameter($par, $val);
			}
		}
	}


	/**
	* get all parameters (in array)
	*/
	function getParameters()
	{
		return $this->parameters;
	}


	/**
	* get all parameters (as string)
	*/
	function getParameterString()
	{
		return ilUtil::assembleParameterString($this->parameters);
	}


	/**
	* get a single parameter
	*/
	function getParameter($a_name)
	{
		return $this->parameters[$a_name];
	}

}
?>
