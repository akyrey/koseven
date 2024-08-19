<?php
/**
 * Wrapper for configuration arrays. Multiple configuration readers can be
 * attached to allow loading configuration from files, database, etc.
 *
 * Configuration directives cascade across config sources in the same way that
 * files cascade across the filesystem.
 *
 * Directives from sources high in the sources list will override ones from those
 * below them.
 *
 * @package    KO7
 * @category   Configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Config {

	/** @var array<array-key, Config_Source> $_sources Configuration readers */
	protected $_sources = [];

	/** @var array<string, Config_Group> $_groups Array of config groups */
	protected $_groups = [];

	/**
	 * Attach a configuration reader. By default, the reader will be added as
	 * the first used reader. However, if the reader should be used only when
	 * all other readers fail, use `FALSE` for the second parameter.
	 *
	 *     $config->attach($reader);        // Try first
	 *     $config->attach($reader, FALSE); // Try last
	 *
	 * @param   KO7_Config_Source    $source instance
	 * @param   boolean              $first  add the reader as the first used object
	 *
	 * @return  static
	 */
	public function attach(KO7_Config_Source $source, $first = TRUE)
	{
		if ($first === TRUE)
		{
			// Place the log reader at the top of the stack
			array_unshift($this->_sources, $source);
		}
		else
		{
			// Place the reader at the bottom of the stack
			$this->_sources[] = $source;
		}

		// Clear any cached _groups
		$this->_groups = [];

		return $this;
	}

	/**
	 * Detach a configuration reader.
	 *
	 *     $config->detach($reader);
	 *
	 * @param   KO7_Config_Source    $source instance
	 *
	 * @return  static
	 */
	public function detach(KO7_Config_Source $source)
	{
		if (($key = array_search($source, $this->_sources)) !== FALSE)
		{
			// Remove the writer
			unset($this->_sources[$key]);
		}

		return $this;
	}

	/**
	 * Load a configuration group. Searches all the config sources, merging all the
	 * directives found into a single config group.  Any changes made to the config
	 * in this group will be mirrored across all writable sources.
	 *
	 *     $array = $config->load($name);
	 *
	 * See [KO7_Config_Group] for more info
	 *
	 * @param   string  $group  configuration group name
	 *
	 * @return  Config_Group|stdClass|null
	 *
	 * @throws  KO7_Exception
	 */
	public function load($group)
	{
		if ( ! count($this->_sources))
		{
			throw new KO7_Exception('No configuration sources attached');
		}

		if (empty($group))
		{
			throw new KO7_Exception("Need to specify a config group");
		}

		if ( ! is_string($group))
		{
			throw new KO7_Exception("Config group must be a string");
		}

		if (strpos($group, '.') !== FALSE)
		{
			// Split the config group and path
			list($group, $path) = explode('.', $group, 2);
		}

		if (isset($this->_groups[$group]))
		{
			if (isset($path))
			{
				return Arr::path($this->_groups[$group], $path, NULL, '.');
			}
			return $this->_groups[$group];
		}

		$config = [];

		// We search from the "lowest" source and work our way up
		$sources = array_reverse($this->_sources);

		foreach ($sources as $source)
		{
			if ($source instanceof KO7_Config_Reader)
			{
				if ($source_config = $source->load($group))
				{
					$config = Arr::merge($config, $source_config);
				}
			}
		}

		$this->_groups[$group] = new Config_Group($this, $group, $config);

		if (isset($path))
		{
			return Arr::path($config, $path, NULL, '.');
		}

		return $this->_groups[$group];
	}

	/**
	 * Copy one configuration group to all of the other writers.
	 *
	 *     $config->copy($name);
	 *
	 * @param   string  $group  configuration group name
	 *
	 * @return  static
	 */
	public function copy($group)
	{
		// Load the configuration group
		$config = $this->load($group);

		foreach ($config->as_array() as $key => $value)
		{
			$this->_write_config($group, $key, $value);
		}

		return $this;
	}

	/**
	 * Callback used by the config group to store changes made to configuration
	 *
	 * @param string    $group  Group name
	 * @param string    $key    Variable name
	 * @param mixed     $value  The new value
	 *
	 * @return static Chainable instance
	 */
	public function _write_config($group, $key, $value)
	{
		foreach ($this->_sources as $source)
		{
			if ( ! ($source instanceof KO7_Config_Writer))
			{
				continue;
			}

			// Copy each value in the config
			$source->write($group, $key, $value);
		}

		return $this;
	}

}
