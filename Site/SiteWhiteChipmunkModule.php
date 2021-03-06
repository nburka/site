<?php

/**
 * Module for dislaying WhiteChimpmunk tracking pixels.
 *
 * @package   Site
 * @copyright 2016 silverorange
 */
class SiteWhiteChipmunkModule extends SiteApplicationModule
{
	// {{{ protected properties

	protected $enabled = true;
	protected $cookie_name;
	protected $conversions = array();

	// }}}
	// {{{ public function depends()

	/**
	 * Gets the module features this module depends on
	 *
	 * The site account session module depends on the SiteDatabaseModule
	 * feature.
	 *
	 * @return array an array of {@link SiteApplicationModuleDependency}
	 *                        objects defining the features this module
	 *                        depends on.
	 */
	public function depends()
	{
		$depends = parent::depends();
		$depends[] = new SiteApplicationModuleDependency('SiteConfigModule');
		return $depends;
	}

	// }}}
	// {{{ public function init()

	public function init()
	{
		$config = $this->app->getModule('SiteConfigModule');
		$this->enabled = $config->white_chipmunk->enabled;
		$this->cookie_name = sprintf(
			'%s_chipmunk_uuid',
			$config->white_chipmunk->site_shortname
		);
	}

	// }}}
	// {{{ public function addConversion()

	public function addConversion($sku, $value)
	{
		$this->conversions[] = array(
			'sku'   => $sku,
			'value' => $value,
		);
	}

	// }}}
	// {{{ public function displayPixel()

	public function displayPixel()
	{
		if ($this->enabled) {
			$referrer = (isset($_SERVER['HTTP_REFERER']))
				? sprintf(
					'&referer=%s',
					urlencode($_SERVER['HTTP_REFERER'])
				)
				: '';

			$conversions = '';
			$count = 0;
			foreach ($this->conversions as $conversion) {
				$conversions.= sprintf(
					'&sku%1$s=%2$s&conversionvalue%1$s=%3$s',
					$count,
					urlencode($conversion['sku']),
					urlencode($conversion['value'])
				);
				$count++;
			}

			printf(
				'<div style="display: none;">'.
				'<img height="1" width="1" border="0" '.
				'src="https://www.whitechipmunk.com/%s?uid=%s%s%s" /></div>',
				urlencode($this->app->config->white_chipmunk->site_shortname),
				urlencode($this->getUUID()),
				$referrer,
				$conversions
			);
		}
	}

	// }}}
	// {{{ protected function getUUID()

	protected function getUUID()
	{
		$uuid = (isset($this->{$this->cookie_name}))
			? $this->{$this->cookie_name}
			: uniqid();

		$this->setCookie(
			$this->cookie_name,
			$uuid
		);

		return $uuid;
	}

	// }}}
	// {{{ protected function setCookie()

	/**
	 * Sets a cookie
	 *
	 * @param string $name the name of the cookie to set.
	 * @param mixed $value the value of the cookie.
	 * @param mixed $expiry the expiry date as a UNIX timestamp or a
	 *                       string parsable by strtotime().
	 * @param string $path the URL path this cookie is valid for.
	 */
	protected function setCookie(
		$name,
		$value,
		$expiry = null,
		$path = '/'
	) {
		if ($expiry === null) {
			$expiry = strtotime('+90 days');
		} elseif (is_string($expiry)) {
			$expiry = strtotime($expiry);
		}

		$cookie_value = SwatString::signedSerialize(
			$value,
			$this->getSalt()
		);

		setcookie($name, $cookie_value, $expiry, $path);
	}

	// }}}
	// {{{ protected function removeCookie()

	/**
	 * Remove a cookie
	 *
	 * @param string $name the name of the cookie to set.
	 * @param string $path the URL path this cookie is valid for.
	 */
	protected function removeCookie($name, $path = '/')
	{
		// Set expiry time to the past. The expiry of 25 hours in the past is
		// used because time() uses the server's local time and some browsers
		// use local time rather than UTC to trigger cookie deletion. 25 hours
		// takes into account all time-zone differences.
		$expiry = time() - 3600 * 25;

		// Some browsers set the cookie value to 'deleted' when an empty string
		// is used as a cookie value. The value '0' is chosen instead for
		// unsetting cookies.
		$value = 0;

		setcookie($name, $value, $expiry, $path);

		unset($_COOKIE[$name]);
	}

	// }}}
	// {{{ public function __get()

	/**
	 * Gets a cookie value
	 *
	 * @param string $name the name of the cookie to get.
	 *
	 * @return mixed the value of the cookie. If there is an error
	 *               unserializing the cookie value, null is returned.
	 */
	public function __get($name)
	{
		if (!isset($_COOKIE[$name])) {
			throw new SiteCookieException("Cookie '{$name}' is not set.");
		}

		try {
			$value = SwatString::signedUnserialize(
				$_COOKIE[$name],
				$this->getSalt()
			);
		} catch (SwatInvalidSerializedDataException $e) {

			// Ignore common cookie values used to remove cookies.
			$ignored_values = array(0, '');

			if (!in_array($_COOKIE[$name], $ignored_values)) {
				// If the cookie can't be unserialized, then log it and
				// continue execution.
				$e = new SiteCookieException($e);
				$e->process(false);
			}

			// Remove the cookie to prevent further exceptions.
			$value = null;
			$this->removeCookie($name);
		}

		return $value;
	}

	// }}}
	// {{{ public function __isset()

	/**
	 * Checks the existence of a cookie
	 *
	 * @param string $name the name of the cookie to check.
	 */
	public function __isset($name)
	{
		return isset($_COOKIE[$name]);
	}

	// }}}
	// {{{ public function getSalt()

	protected function getSalt()
	{
		return $this->app->config->white_chipmunk->salt;
	}

	// }}}
}

?>
