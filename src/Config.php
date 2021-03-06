<?php

/**
 * \AppserverIo\Doppelgaenger\Config
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH - <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/doppelgaenger
 * @link      http://www.appserver.io/
 */

namespace AppserverIo\Doppelgaenger;

use AppserverIo\Doppelgaenger\Interfaces\ConfigInterface;
use AppserverIo\Doppelgaenger\Exceptions\ConfigException;
use AppserverIo\Doppelgaenger\Utils\Formatting;
use AppserverIo\Doppelgaenger\Utils\InstanceContainer;
use AppserverIo\Doppelgaenger\Dictionaries\ReservedKeywords;

/**
 * This class implements the access point for our global (oh no!) configuration
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH - <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/doppelgaenger
 * @link      http://www.appserver.io/
 */
class Config implements ConfigInterface
{
    /**
     * @const string DEFAULT_CONFIG Name of the default configuration file
     */
    const DEFAULT_CONFIG = 'config.default.json';

    /**
     * The delimiter for values names as they are used externally
     *
     * @const string VALUE_NAME_DELIMITER
     */
    const VALUE_NAME_DELIMITER = '/';

    /**
     * @var string $context The context for this instance e.g. app based configurations
     */
    protected $context;

    /**
     * @var array $config Configuration array
     */
    protected $config = array();

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->load(__DIR__ . DIRECTORY_SEPARATOR . self::DEFAULT_CONFIG);
    }

    /**
     * Will flatten a multi-level associative array into a one-level one
     *
     * @param array  $array       The array to flatten
     * @param string $parentKey   The key of the parent array, used within recursion
     * @param bool   $initialCall Is this the initial call or recursion?
     *
     * @return array
     */
    protected function flattenArray(array $array, $parentKey = '', $initialCall = true)
    {
        $result = array();
        foreach ($array as $key => $value) {
            // If it is an array not containing integer keys (so no nested config element) we have to get recursive
            if (is_array($value) && @!is_int(array_keys($value)[0])) {
                $value = $this->flattenArray($value, $key, false);
            }

            // Save the result with a newly combined key
            $result[trim($parentKey . self::VALUE_NAME_DELIMITER . $key, self::VALUE_NAME_DELIMITER)] = $value;
        }

        // If we are within the initial call we would like to do a final flattening and sorting process
        if ($initialCall === true) {
            // No iterate all the entries and array shift them if they are arrays
            foreach ($result as $key => $value) {
                if (is_array($value)) {
                    unset($result[$key]);
                    $result = array_merge($result, $value);
                }
            }

            // Sort the whole thing as we might have mixed it up little
            ksort($result);
        }
        return $result;
    }

    /**
     * Sets a value to specific content
     *
     * @param string $valueName The value to set
     * @param mixed  $value     The actual content for the value
     *
     * @return void
     */
    public function setValue($valueName, $value)
    {
        // Set the value
        $this->config[$valueName] = $value;
    }

    /**
     * Might be called after the all config data has been loaded.
     * Will store all config object instances which might be needed later in the instance container util.
     *
     * @return void
     */
    public function storeInstances()
    {
        $instanceContainer = new InstanceContainer();

        // One thing we need is the logger instance (if any)
        if ($this->hasValue('enforcement/logger')) {
            $logger = $this->extractLoggerInstance($this->config);

            if ($logger !== false) {
                $instanceContainer[ReservedKeywords::LOGGER_CONTAINER_ENTRY] = $logger;
            }
        }
    }

    /**
     * Extends a value by specific content. If the value is an array it will be merged, otherwise it will be
     * string-concatinated to the end of the current value
     *
     * @param string $valueName The value to extend
     * @param string $value     The actual content for the value we want to add to the original
     *
     * @return void
     */
    public function extendValue($valueName, $value)
    {
        // Get the original value
        $originalValue = $this->getValue($valueName);

        // If we got an array
        if (is_array($value)) {
            if (is_array($originalValue)) {
                $newValue = array_merge($originalValue, $value);

            } else {
                $newValue = array_merge(array($originalValue), $value);
            }

        } else {
            $newValue = $originalValue . $value;
        }

        // Finally set the new value
        $this->setValue($valueName, $newValue);
    }

    /**
     * Will extract an logger instance from the configuration array.
     * Returns false on error.
     *
     * @param array $configArray The config to extract the logger instance from
     *
     * @return object|boolean
     */
    protected function extractLoggerInstance(array $configArray)
    {
        if (isset($configArray['enforcement/logger'])) {
            // Get the logger
            $logger = $configArray['enforcement/logger'];
            if (is_string($logger)) {
                $logger = new $logger;
            }

            // Return the logger
            return $logger;

        } else {
            return false;
        }
    }

    /**
     * Unsets a specific config value
     *
     * @param string $value The value to unset
     *
     * @return void
     */
    public function unsetValue($value)
    {
        if (isset($this->config[$value])) {
            // unset the value
            unset($this->config[$value]);
        }
    }

    /**
     * Returns the content of a specific config value
     *
     * @param string $value The value to get the content for
     *
     * @throws \AppserverIo\Doppelgaenger\Exceptions\ConfigException
     *
     * @return mixed
     */
    public function getValue($value)
    {
        // check if server var is set
        if (isset($this->config[$value])) {
            // return server vars value
            return $this->config[$value];
        }
        // throw exception
        throw new ConfigException(sprintf("Config value %s does not exist.", $value));
    }

    /**
     * Checks if value exists for given value
     *
     * @param string $value The value to check
     *
     * @return boolean Weather it has value (true) or not (false)
     */
    public function hasValue($value)
    {
        // check if server var is set
        if (!isset($this->config[$value])) {
            return false;
        }

        return true;
    }

    /**
     * Will load a certain configuration file into this instance. Might throw an exception if the file is not valid
     *
     * @param string $file The path of the configuration file we should load
     *
     * @return \AppserverIo\Doppelgaenger\Config
     *
     * @throws \AppserverIo\Doppelgaenger\Exceptions\ConfigException
     */
    public function load($file)
    {
        // Do we load a valid config?
        $configCandidate = $this->validate($file);
        if ($configCandidate === false) {
            throw new ConfigException(sprintf('Attempt to load invalid configuration file %s', $file));
        }

        $this->config = array_replace_recursive($this->config, $configCandidate);

        return $this;
    }

    /**
     * Will validate a potential configuration file. Returns false if file is no valid Doppelgaenger configuration, true otherwise
     *
     * @param string $file Path of the potential configuration file
     *
     * @return boolean
     * @throws \AppserverIo\Doppelgaenger\Exceptions\ConfigException
     */
    public function isValidConfigFile($file)
    {
        return is_array($this->validate($file));
    }

    /**
     * Will normalize directories mentioned within a configuration aspect.
     * If there is an error false will be returned. If not we will return the given configuration array containing only
     * normalized paths.
     *
     * @param string $configAspect The aspect to check for non-normal dirs
     * @param array  $configArray  The array to check within
     *
     * @return array|bool
     */
    protected function normalizeConfigDirs($configAspect, array $configArray)
    {
        // Are there dirs within this config aspect?
        if (isset($configArray[$configAspect . self::VALUE_NAME_DELIMITER . 'dirs'])) {
            // Get ourselves a format utility
            $formattingUtil = new Formatting();

            // Iterate over all dir entries and normalize the paths
            foreach ($configArray[$configAspect . self::VALUE_NAME_DELIMITER . 'dirs'] as $key => $projectDir) {
                // Do the normalization
                $tmp = $formattingUtil->sanitizeSeparators($formattingUtil->normalizePath($projectDir));

                if (is_readable($tmp)) {
                    $configArray[$configAspect . self::VALUE_NAME_DELIMITER . 'dirs'][$key] = $tmp;

                } elseif (preg_match('/\[|\]|\*|\+|\.|\(|\)|\?|\^/', $tmp)) {
                    // Kill the original path entry so the iterators wont give us a bad time
                    unset($configArray[$configAspect . self::VALUE_NAME_DELIMITER . 'dirs'][$key]);

                    // We will open up the paths with glob
                    foreach (glob($tmp, GLOB_ERR) as $regexlessPath) {
                        // collect the cleaned path
                        $configArray[$configAspect . self::VALUE_NAME_DELIMITER . 'dirs'][] = $regexlessPath;
                    }

                } else {
                    // Somethings wrong with the path, that should not be
                    return false;
                }
            }

        }

        // Everything seems fine, lets return the changes config array
        return $configArray;
    }

    /**
     * Will return the whole configuration or, if $aspect is given, certain parts of it
     *
     * @param string $aspect The aspect of the configuration we are interested in e.g. 'autoloader'
     *
     * @return array
     */
    public function getConfig($aspect = null)
    {
        if (!is_null($aspect)) {
            // Filter the aspect our of the config
            $tmp = array();
            foreach ($this->config as $key => $value) {
                // Do we have an entry belonging to the certain aspect? If so filter it and cut the aspect key part
                if (strpos($key, $aspect . self::VALUE_NAME_DELIMITER) === 0) {
                    $tmp[str_replace($aspect . self::VALUE_NAME_DELIMITER, '', $key)] = $value;
                }
            }

            return $tmp;

        } else {
            // Just return the whole config

            return $this->config;
        }
    }

    /**
     * Will validate a potential configuration file. Returns false if file is no valid Doppelgaenger configuration.
     * Will return the validated configuration on success
     *
     * @param string $file Path of the potential configuration file
     *
     * @return array|boolean
     * @throws \AppserverIo\Doppelgaenger\Exceptions\ConfigException
     */
    protected function validate($file)
    {
        $configCandidate = json_decode(file_get_contents($file), true);

        // Did we even get an array?
        if (!is_array($configCandidate)) {
            throw new ConfigException(sprintf('Could not parse configuration file %s.', $file));

        } else {
            $configCandidate = $this->flattenArray($configCandidate);
        }

        // We need some formatting utilities
        $formattingUtil = new Formatting();

        // We will normalize the paths we got and check if they are valid
        if (isset($configCandidate['cache' . self::VALUE_NAME_DELIMITER . 'dir'])) {
            $tmp = $formattingUtil->normalizePath($configCandidate['cache' . self::VALUE_NAME_DELIMITER . 'dir']);

            if (is_writable($tmp)) {
                $configCandidate['cache' . self::VALUE_NAME_DELIMITER . 'dir'] = $tmp;

            } else {
                throw new ConfigException(sprintf('The configured cache directory %s is not writable.', $tmp));
            }
        }

        // Same for enforcement dirs
        $configCandidate = $this->normalizeConfigDirs('enforcement', $configCandidate);

        // Do we still have an array here?
        if (!is_array($configCandidate)) {
            return false;
        }

        // Do the same for the autoloader dirs
        $configCandidate = $this->normalizeConfigDirs('autoloader', $configCandidate);

        // Lets check if there is a valid processing in place
        if ($configCandidate === false || !$this->validateProcessing($configCandidate)) {
            return false;
        }

        // Return what we got
        return $configCandidate;
    }

    /**
     * Will return true if the processing part of the config candidate array is valid. Will return false if not
     *
     * @param array $configCandidate The config candidate we want to validate in terms of processing
     *
     * @return boolean
     *
     * @todo move everything other than logger check to JSON scheme validation
     */
    protected function validateProcessing(array $configCandidate)
    {
        // Merge it with the current config as a standalone config might not be valid at all
        $configCandidate = array_replace_recursive($this->config, $configCandidate);

        $validEntries = array_flip(array('none', 'exception', 'logging'));

        // Do we have an entry at all?
        if (!isset($configCandidate['enforcement/processing'])) {
            return false;
        }

        // Did we even get something useful?
        if (!isset($validEntries[$configCandidate['enforcement/processing']])) {
            return false;
        }

        // If we got the option "logger" we have to check if there is a logger. If not, we fail, if yes
        // we have to check if we got something PSR-3 compatible
        if ($configCandidate['enforcement/processing'] === 'logging') {
            return $this->extractLoggerInstance($configCandidate) instanceof \Psr\Log\LoggerInterface;
        }

        // Still here? Sounds good
        return true;
    }
}
