<?php

/**
 * Turbine
 * http://github.com/SirPepe/Turbine
 * 
 * Copyright (C) 2009 Peter Kröner, Christian Schaefer
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Library General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Library General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


/**
 * Turbine
 * CSS Preprocessor
 * @todo Handle @font-face in apply_copying()
 */
class Cssp extends Parser2 {


	/**
	 * Constructor
	 * @param string $query String of Files to load, separated by ;
	 * @return void
	 */
	public function __construct($query = NULL){
		parent::__construct();
		global $browser;
		if($query){
			$this->load_file($query);
		}
	}


	/**
	 * compile
	 * This is where the magic happens
	 * @return void
	 */
	public function compile(){
		$this->apply_aliases();
		$this->apply_property_expansion();
		$this->apply_inheritance();
		$this->apply_copying();
		$this->apply_constants();
		$this->cleanup();
	}


	/**
	 * apply_constants
	 * Applies constants to the stylesheet
	 * @return void
	 */
	public function apply_constants(){
		// Apply special constants to all blocks
		$this->apply_special_constants();
		// Apply constants, if present, from the global block
		if(isset($this->parsed['global']['@constants'])){
			foreach($this->parsed as $block => $css){
				$this->apply_block_constants($this->parsed['global']['@constants'], $block);
			}
		}
		// Apply constants for @media blocks
		foreach($this->parsed as $block => $css){
			if(isset($this->parsed[$block]['@constants']) && $block != 'global'){
				$this->apply_block_constants($this->parsed[$block]['@constants'], $block);
			}
		}
	}


	/**
	 * apply_block_constants
	 * Applies a set of constants to a specific block of css
	 * @param array $constants Array of constants
	 * @param string $block Block key to apply the constants to
	 * @return void
	 */
	protected function apply_block_constants($constants, $block){
		foreach($constants as $constant => $constant_value){
			// We will only ever need the last value out of the constant's value array
			$constant_value = end($constant_value);
			// Apply the value to the elements in the block
			foreach($this->parsed[$block] as $selector => $styles){
				// Handle everything but @font-face
				if($selector != '@font-face'){
					foreach($styles as $property => $values){
						$num_values = count($values);
						for($i = 0; $i < $num_values; $i++){
							// Get the replacement value
							$replacement = $this->get_constant_replacement($block, $constant_value);
							// Replace the value with the constant's value
							$this->parsed[$block][$selector][$property][$i] = preg_replace('/(\$'.$constant.')\b/', $replacement, $this->parsed[$block][$selector][$property][$i]);
						}
					}
				}
				// Handle @font-face
				else{
					foreach($styles as $key => $properties){
						foreach($properties as $property => $values){
							$num_values = count($values);
							for($i = 0; $i < $num_values; $i++){
								// Get the replacement value
								$replacement = $this->get_constant_replacement($block, $constant_value);
								// Replace the value with the constant's value
								$this->parsed[$block][$selector][$key][$property][$i] = preg_replace('/(\$'.$constant.')\b/', $replacement, $this->parsed[$block][$selector][$key][$property][$i]);
							}
						}
					}
				}
			}
		}
	}


	/**
	 * get_constant_replacement
	 * Finds the real replacement for constants that reference other constants
	 * @param string $block The block where the constant or alias is coming from
	 * @param string $value The value to find a replacement for
	 * @return string The Replacement
	 */
	protected function get_constant_replacement($block, $value){
		// If not a constant, simply return value
		if(!preg_match('/^\$(.*)$/', $value, $matches)){
			return $value;
		}
		// Else search the true replacement
		else{
			// Search in the given block AND in the global block
			$blocks = array('global');
			if($block != 'global'){
				$blocks[] = $block;
			}
			foreach($blocks as $block){
				if(isset($this->parsed[$block]['@constants'][$matches[1]])){
					// We will only ever need the last value out of the constant's value array
					return $this->get_constant_replacement($block, end($this->parsed[$block]['@constants'][$matches[1]]));
				}
			}
		}
	}


	/**
	 * apply_special_constants
	 * Applies special constants to all blocks
	 * @return void
	 */
	protected function apply_special_constants(){
		foreach($this->global_constants as $g_constant => $g_value){
			foreach($this->parsed as $block => $css){
				foreach($this->parsed[$block] as $selector => $styles){
					// Handle everything but @font-face
					if($selector != '@font-face'){
						foreach($styles as $property => $values){
							$num_values = count($values);
							for($i = 0; $i < $num_values; $i++){
								$this->parsed[$block][$selector][$property][$i] = preg_replace('/(\$_'.$g_constant.')\b/', $g_value, $this->parsed[$block][$selector][$property][$i]);
							}
						}
					}
					// Handle @font-face
					else{
						foreach($styles as $key => $properties){
							foreach($properties as $property => $values){
								$num_values = count($values);
								for($i = 0; $i < $num_values; $i++){
									$this->parsed[$block][$selector][$key][$property][$i] = preg_replace('/(\$_'.$g_constant.')\b/', $g_value, $this->parsed[$block][$selector][$key][$property][$i]);
								}
							}
						}
					}
				}
			}
		}
	}


	/**
	 * apply_aliases
	 * Applies selector aliases
	 * @return void
	 */
	public function apply_aliases(){
		// Apply global aliases, if present, to all blocks
		if(isset($this->parsed['global']['@aliases'])){
			foreach($this->parsed as $block => $css){
				$this->apply_block_aliases($this->parsed['global']['@aliases'], $block);
			}
		}
		// Apply aliases for @media blocks
		foreach($this->parsed as $block => $css){
			if(isset($this->parsed[$block]['@aliases']) && $block != 'global'){
				$this->apply_block_aliases($this->parsed[$block]['@aliases'], $block);
			}
		}
	}


	/**
	 * apply_block_aliases
	 * Applies a set of aliases to a specific block of css
	 * @param array $aliases Array of aliases
	 * @param string $block Block key to apply the aliases to
	 * @return void
	 */
	protected function apply_block_aliases($aliases, $block){
		foreach($aliases as $alias => $alias_value){
			// We will only ever need the last value out of the constant's value array
			$alias_value = end($alias_value);
			foreach($this->parsed[$block] as $selector => $styles){
				// Replace in selectors: add a new element with the full selector and delete the old one
				$newselector = preg_replace('/(\$'.$alias.')\b/', $alias_value, $selector);
				if($newselector != $selector){
					$elements = array($newselector => $styles);
					$this->insert($elements, $block, $selector);
					unset($this->parsed[$block][$selector]);
				}
				// Replace in values
				foreach($styles as $property => $value){
					if(isset($this->parsed[$block][$selector][$property])){
						$num_property_values = count($this->parsed[$block][$selector][$property]);
						for($i = 0; $i < $num_property_values; $i++){
							$matches = array();
							if($property == 'extends' && isset($this->parsed[$block][$selector]['extends'][$i])){
								$this->parsed[$block][$selector]['extends'][$i] = preg_replace('/(\$'.$alias.')\b/', $alias_value, $this->parsed[$block][$selector]['extends'][$i]);
							}
							else{
								// Ignore @font-face and @import
								if($selector != '@font-face' && $selector != '@import'){
									if(preg_match('/copy\((.*)[\s]+(.*)\)/', $this->parsed[$block][$selector][$property][$i], $matches)){
										$matches[1] = preg_replace('/(\$'.$alias.')\b/', $alias_value, $matches[1]);
										$this->parsed[$block][$selector][$property][$i] = 'copy('.$matches[1].' '.$matches[2].')';
									}
								}
							}
						}
					}
				}
			}
		}
	}


	/**
	 * apply_inheritance
	 * Applies inheritance to the stylesheet
	 * @return void
	 */
	public function apply_inheritance(){
		foreach($this->parsed as $block => $css){
			foreach($this->parsed[$block] as $selector => $styles){
				// Full inheritance
				if(isset($this->parsed[$block][$selector]['extends'])){
					$num_extends = count($this->parsed[$block][$selector]['extends']);
					for($i = 0; $i < $num_extends; $i++){
						$found = false;
						// Parse ancestors
						$ancestors = $this->tokenize($this->parsed[$block][$selector]['extends'][$i], array('"', "'", ','));
						// First merge all the ancestor's rules into one...
						$ancestors_rules = array();
						foreach($ancestors as $ancestor){
							// Find ancestor
							$ancestor_key = $this->find_ancestor_key($ancestor, $block);
							// Merge ancestor's rules with own rules
							if($ancestor_key){
								$ancestors_rules = $this->merge_rules(
									$ancestors_rules,
									$this->parsed[$block][$ancestor_key],
									array(),
									true
								);
								$found = true;
							}
						}
						// ... then merge the combined ancestor's rules into $parsed
						$this->parsed[$block][$selector] = $this->merge_rules(
							$this->parsed[$block][$selector],
							$ancestors_rules,
							array(),
							false
						);
						// Report error if no ancestor was found
						if(!$found){
							$this->report_error($selector.' could not find '.$this->parsed[$block][$selector]['extends'][$i].' to inherit properties from.');
						}
					}
					// Unset the extends property
					unset($this->parsed[$block][$selector]['extends']);
				}
			}
		}
	}


	/**
	 * apply_copying
	 * Applies property copying to the stylesheet
	 * @return void
	 */
	public function apply_copying(){
		$inheritance_pattern = '/copy\((.*)[\s]+(.*)\)/';
		foreach($this->parsed as $block => $css){
			foreach($this->parsed[$block] as $selector => $styles){
				// Handle everything but @font-face
				if($selector != '@font-face'){
					foreach($styles as $property => $values){
						$values_num = count($values);
						for($i = 0; $i < $values_num; $i++){
							if(preg_match($inheritance_pattern, $values[$i])){
								$found = false;
								preg_match_all($inheritance_pattern, $values[$i], $matches);
								// Exact selector matches
								if(isset($this->parsed[$block][$matches[1][0]][$matches[2][0]])){
									$this->parsed[$block][$selector][$property][$i] = $this->get_final_value($this->parsed[$block][$matches[1][0]][$matches[2][0]], $property);
									$found = true;
								}
								// Search for partial selector matches, ie. "#foo" in "#bar, #foo, #blah"
								else{
									foreach($this->parsed[$block] as $full_selectors => $v){
										$tokenized_selectors = $this->tokenize($full_selectors, ',');
										if(in_array($matches[1][0], $tokenized_selectors)){
											if(isset($this->parsed[$block][$full_selectors][$matches[2][0]])){
												$this->parsed[$block][$selector][$property][$i] = $this->get_final_value($this->parsed[$block][$full_selectors][$matches[2][0]], $property);
												$found = true;
											}
										}
									}
								}
								// Report error if no source was found
								if(!$found){
									$this->report_error($selector.' could not find '.$matches[1][0].' to copy '.$matches[2][0].' from.');
								}
							}
						}
					}
				}
				// Handle @font-face
				else{
					// TODO
				}
			}
		}
	}


	/**
	 * apply_property_expansion
	 * Expands comma-sepperated properties
	 * @return void
	 */
	public function apply_property_expansion(){
		foreach($this->parsed as $block => $css){
			foreach($this->parsed[$block] as $selector => $styles){
				foreach($styles as $property => $value){
					// Find possible expandable properties
					if(strpos($property, ',') !== false){
						$properties = $this->tokenize($property, ',');
						if(count($properties) > 1){
							// Rebuild the selector's contents with the expanded selectors
							$newcontents = array();
							foreach($this->parsed[$block][$selector] as $p => $v){
								if($p == $property){
									foreach($properties as $expanded){
										$newcontents[$expanded] = $v;
									}
								}
								else{
									$newcontents[$p] = $v;
								}
							}
							// Set the selector's contents to the new array with the expanded selectors
							$this->parsed[$block][$selector] = $newcontents;
						}
					}
				}
			}
		}
	}


	/***
	 * merge_rules
	 * Merges possible conflicting css rules
	 * @param mixed $old The OLD rules (overridden by the new rules)
	 * @param mixed $new The NEW rules (override the old rules)
	 * @param array $exclude A list of properties NOT to merge
	 * @param array $allow_overwrite Allow new rules to overwrite old ones?
	 * @return mixed $rule The new, merged rule
	 */
	public function merge_rules($old, $new, $exclude = array(), $allow_overwrite = true){
		$rule = $old;
		foreach($new as $property => $values){
			// If the property is not excluded...
			if(!in_array($property, $exclude)){
				// ... apply the values one by one...
				if(isset($rule[$property])){
					if($allow_overwrite){
						foreach($values as $value){
							$rule[$property][] = $value;
						}
					}
				}
				// ... or copy the whole set of values
				else{
					$rule[$property] = $values;
				}
			}
		}
		return $rule;
	}


	/**
	 * find_ancestor
	 * Find selectors matching (partially) $selector
	 * @param string $selector The selector to search
	 * @param string $block The block to search in
	 * @return string $key The matching key (if any)
	 */
	protected function find_ancestor_key($selector, $block){
		foreach($this->parsed[$block] as $key => $value){
			$tokens = $this->tokenize($key, ',');
			if(in_array($selector, $tokens)){
				return $key;
			}
		}
	}


	/**
	 * cleanup
	 * Deletes empty elements, templates and cssp-only elements
	 * @return void
	 */
	public function cleanup(){
		// Remove @constants and @aliases blocks
		foreach($this->parsed as $block => $css){
			if(isset($this->parsed[$block]['@constants'])){
				unset($this->parsed[$block]['@constants']);
			}
			if(isset($this->parsed[$block]['@aliases'])){
				unset($this->parsed[$block]['@aliases']);
			}
			// Remove empty elements, templates and alias ruins
			foreach($this->parsed[$block] as $selector => $styles){
				if(empty($styles) || $selector{0} == '?' || $selector{0} == '$'){
					unset($this->parsed[$block][$selector]);
				}
			}
		}
	}


	/**
	 * insert
	 * Inserts an element at a specific position in the block
	 * @param array $elements The element to insert
	 * @param string $block The block to insert into
	 * @param string $before The element after which the new element is inserted
	 * @return void
	 */
	public function insert($elements, $block, $before){
		$newblock = array();
		foreach($this->parsed[$block] as $selector => $styles){
			$newblock[$selector] = $styles;
			if($selector == $before){
				foreach($elements as $newselector => $newstyles){
					$newblock[$newselector] = $newstyles;
				}
			}
		}
		$this->parsed[$block] = $newblock;
	}


	 /**
	 * insert_properties
	 * Inserts an array of css rules (property => Array of values) into an element at a specific position
	 * @param array $rules The css rules to insert
	 * @param string $block The block where the element $element is to be found
	 * @param string $element The element to insert the rules into
	 * @param string $before The property after which the rules are to be inserted
	 * @return void
	 */
	public function insert_properties($rules, $block, $element, $before = null){
		$newelement = array();
		// If there's no $before, insert the new rules at the top
		if(!$before){
			foreach($rules as $newproperty => $newvalues){
				$newelement = $this->insert_property($newelement, $newproperty, $newvalues);
			}
		}
		// Add the properties one after another, insert new ones after $before
		foreach($this->parsed[$block][$element] as $property => $values){
			$newelement[$property] = $values;
			if($property == $before){
				foreach($rules as $newproperty => $newvalues){
					$newelement = $this->insert_property($newelement, $newproperty, $newvalues);
				}
			}
		}
		$this->parsed[$block][$element] = $newelement;
	}


	/**
	 * insert_property
	 * Inserts a new property into an array without overwriting any other properties
	 * @param array $set The array to insert into
	 * @param string $property The property name
	 * @param array $values The properties' values
	 * @return array $set The set with the new property inserted
	 */
	private function insert_property($set, $property, $values){
		// take care of legacy plugins that might pass a single value as a string
		if(!is_array($values)){
			$values = array($values);
		}
		// Insert the property
		if(isset($set[$property])){
			$set[$property] = array_merge($set[$property], $values);
		}
		else{
			$set[$property] = $values;
		}
		return $set;
	}


}


?>