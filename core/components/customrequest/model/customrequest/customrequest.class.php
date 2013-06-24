<?php

/**
 * CustomRequest
 *
 * Copyright 2013 by Thomas Jakobi <thomas.jakobi@partout.info>
 *
 * CustomRequest is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * CustomRequest is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * CustomRequest; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package customrequest
 * @subpackage classfile
 *
 * @author      Thomas Jakobi (thomas.jakobi@partout.info)
 * @copyright   Copyright 2013, Thomas Jakobi
 * @version     1.0
 */
class CustomRequest {

	/**
	 * A reference to the modX instance
	 * @var modX $modx
	 */
	public $modx;

	/**
	 * A configuration array
	 * @var array $config
	 */
	public $config;

	/**
	 * A setting array
	 * @var array $setting
	 */
	public $requests;

	/**
	 * The found resource id
	 * @var int $resourceId
	 */
	public $resourceId;

	/**
	 * The found alias
	 * @var int $alias
	 */
	private $alias;

    /**
     * The found request
     * @var string $request
     */
    private $request;

	/**
	 * The found url params
	 * @var int $resourceId
	 */
	private $urlParams;

	/**
	 * The found regular expression to parse the url
	 * @var int $alias
	 */
	private $regEx;

	/**
	 * CustomRequest constructor
	 *
	 * @param modX &$modx A reference to the modX instance.
	 * @param array $config An array of configuration options. Optional.
	 */
	function __construct(modX &$modx, array $config = array()) {
		$this->modx = & $modx;

		$corePath = $this->modx->getOption('customrequest.core_path', NULL, MODX_CORE_PATH . 'components/customrequest/');

		/* loads some default paths for easier management */
		$this->config = array_merge(array(
			'corePath' => $corePath,
			'modelPath' => $corePath . 'model/',
			'pluginsPath' => $corePath . 'elements/plugins/',
			'configsPath' => $this->modx->getOption('customrequest.configsPath', NULL, $corePath . 'configs/'),
			'debug' => $this->modx->getOption('customrequest.debug', NULL, FALSE),
				), $config);
            $this->requests = isset($this->config['aliases'])?$this->modx->fromJson($this->config['aliases']):array();
	}

	/**
	 * Load all config files and prepare the values.
	 *
	 * @access public
	 * @return void
	 */
	public function initialize() {
		// TODO: Caching of these calculated values.
		$configFiles = glob($this->config['configsPath'] . '*.config.inc.php');
		// import config files
		foreach ($configFiles as $configFile) {
			$requestPrefix = pathinfo($configFile, PATHINFO_BASENAME);
            $requestPrefix = substr($requestPrefix, 0, -strlen('.config.inc.php')) . '/';
			// $settings will be defined in each config file
			$settings = array();
			include $configFile;
			foreach ($settings as $request => $setting) {
				// fill urlParams if defined
				$urlParams = (isset($setting['urlParams']) && is_array($setting['urlParams'])) ? $setting['urlParams'] : array();
				$regEx = (isset($setting['regEx']) && is_array($setting['regEx'])) ? $setting['regEx'] : FALSE;
                $urlPattern = (isset($setting['urlPattern']) && is_string($setting['urlPattern'])) ? $setting['urlPattern'] : FALSE;
				if (isset($setting['alias'])) {
					// if alias is defined, calculate the other values
					if (isset($setting['resourceId'])) {
						$resourceId = $setting['resourceId'];
					} elseif ($res = $this->modx->getObject('modResource', array('uri' => $setting['alias']))) {
						$resourceId = $res->get('id');
					} else {
						// if resourceId could not be calculated, don't use that setting
						if ($this->config['debug']) {
							$this->modx->log(modX::LOG_LEVEL_INFO, 'CustomRequest Plugin: Could not calculate the resourceId for the given alias');
						}
						break;
					}
					$alias = $setting['alias'];
				} elseif (isset($setting['resourceId'])) {
					// else if resourceId is defined, calculate the other values
					$resourceId = $setting['resourceId'];
					if (isset($setting['alias'])) {
						$alias = $setting['alias'];
					} elseif ($url = $this->modx->makeUrl($setting['resourceId'])) {
						$alias = $url;
					} else {
						// if alias could not be calculated, don't use that setting
						if ($this->config['debug']) {
							$this->modx->log(modX::LOG_LEVEL_INFO, 'CustomRequest Plugin: Could not calculate the alias for the given resourceId');
						}
						break;
					}
				}
				$this->requests[$requestPrefix . $request] = array(
					'resourceId' => $resourceId,
					'alias' => $alias,
					'urlParams' => $urlParams,
					'regEx' => $regEx,
                    'urlPattern' => $urlPattern
				);
			}
		}
		return;
	}

	/**
	 * Check if the search string starts with one of the allowed aliases and
	 * prepare the url param string if successful.
	 *
	 * @access public
	 * @return boolean
	 */
	public function searchAliases($search) {
		$valid = FALSE;
		// loop through the allowed aliases
		foreach ($this->requests as $request_key => $request) {
			// check if searched string starts with the alias
			if (0 === strpos($search, $request['alias'])) {
				// strip alias from seached string
				$this->urlParams = substr($search, strlen($request['alias']));
				// set the found resource id
				$this->resourceId = $request['resourceId'];
				// set the found alias
				$this->alias = $request['alias'];
                // set the found alias
                $this->request = $request_key;
				// and set the found regEx
				$this->regEx = $request['regEx'];
				$valid = TRUE;
				break;
			}
		}
		return $valid;
	}

	/**
	 * Prepare the request parameters.
	 *
	 * @access public
	 * @return void
	 */
	public function setRequest() {
		$params = trim(str_replace('.html', '', $this->urlParams), '/');
		if ($this->regEx) {
			$params = preg_match($this->regEx, $params);
		} else {
			$params = explode('/', $params);
		}
		if (count($params) >= 1) {
			$setting = $this->requests[$this->request];
			// set the request parameters
			foreach ($params as $key => $value) {
				if (isset($setting['urlParams'][$key])) {
					$_REQUEST[$setting['urlParams'][$key]] = $value;
				} else {
					$_REQUEST['p' . ($key + 1)] = $value;
				}
			}
		}
		$this->modx->sendForward($this->resourceId);
		return;
	}

    /**
     * Build url
     *
     * @param $request
     * @param $args
     * @param $scheme
     * @return string
     */
    public function makeUrl($request, $args, $scheme = -1) {
        if (!isset($this->requests[$request]))
            return '';
        $setting = $this->requests[$request];
        $urlParams = $setting['urlParams'];
        $params = array_intersect_key($args, array_flip($urlParams));
        if ($setting['urlPattern']) {
            $url = $setting['urlPattern'];
            foreach($params as $k=>$v)
                $url = str_replace('[[+'.$k.']]', $v, $url);
        } else
            $url = rtrim($setting['alias'],'/') . '/' . implode('/', $params);

        switch($scheme) {
            case 'abs':
                $url = $this->modx->getOption('base_url').$url;
                break;
            case 'full':
                $url = $this->modx->getOption('site_url').$url;
                break;
        }
        return $url;
    }

}

?>
