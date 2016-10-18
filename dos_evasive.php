<?php


/*
 *
 * Example usage:
$cacheMethod = 'APCStore';
$cacheMethod = 'MemcacheStore';
$cacheMethod = 'MemcachedStore';
$mConnection = new $cacheMethod(array('mHost'=>'localhost'));
$parameters = array(
	//in seconds
	'mTimeout' => 1,
	'mBlockingTime' => 600,
	'mPageHits' => 100,
	'mUriExclusion' => '^(\/someApi\/)|(\/someOtherApi)'
);
$monitor = new DosEvasiveMonitor($mConnection, $parameters);
if ($monitor->shouldBlock()) $monitor->blockRequest($parameters);
if ($monitor->shouldNotify()) $monitor->sendEmail(array('myemail@gmail.com', 'techemail@yahoo.com'),$parameters);

$parameters = array(
	//in seconds
	'mTimeout' => 5,
	'mBlockingTime' => 600,
	'mPageHits' => 100,
	'mUriExclusion' => '^(\/someApi\/)|(\/someOtherApi)'
);
$monitor = new DosEvasiveMonitor($mConnection, $parameters);
if ($monitor->shouldBlock()) $monitor->blockRequest($parameters);
if ($monitor->shouldNotify()) $monitor->sendEmail(array('myemail@gmail.com', 'techemail@yahoo.com'),$parameters);
*/

class DosEvasiveMonitor
{
	private $block = false;
	private $notify = false;
	private $mKey;
	private $mailKey;
	private $mConnection;

	public function __construct(CacheMethod &$connection, $parameters)
	{
		$mTimeout = $parameters['mTimeout'];

		$this->mKey = 'dos_evasive'.$mTimeout.':'.$_SERVER['REMOTE_ADDR'];
		$this->mailKey = 'dos_evasive_email:'.$this->mKey;
		$this->mConnection = $connection;

		$connected = $this->mConnection->isValid();
		if (!$connected) return;
		$this->execute($parameters);
	}

	public function execute($parameters)
	{
		$mBlockingTime = $parameters['mBlockingTime'];
		$mPageHits = $parameters['mPageHits'];
		$mTimeout = $parameters['mTimeout'];
		$mUriExclusion = trim($parameters['mUriExclusion']);

		if(''!=$mUriExclusion && preg_match('/'.$mUriExclusion.'/', $_SERVER['REQUEST_URI'])) return;

		$mHits = $this->mConnection->increment($this->mKey, 1);
		if (!$mHits) $this->mConnection->add($this->mKey, 1, $mTimeout);

		if ($mHits>$mPageHits)
		{
			$this->mConnection->set($this->mKey, $mHits,$mBlockingTime);
			$this->block = true;

			$sent = $this->mConnection->get($this->mailKey);
			$this->notify = false;
			if (!$sent)
			{
				$this->mConnection->set($this->mailKey, 1, $mBlockingTime);
				$this->notify = true;
			}
		}

	}

	public function resetMonitor($clearCache = true)
	{
		if ($this->mConnection->isValid() && $clearCache)
		{
			$this->mConnection->delete($this->mKey);
			$this->mConnection->delete($this->mailKey);
		}
		$this->block = false;
		$this->notify = false;
	}

	public function shouldBlock()
	{
		return $this->block;
	}

	public function shouldNotify()
	{
		return $this->notify;
	}

	public function blockRequest($parameters)
	{
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		header('Retry-After: '.$parameters['mBlockingTime']);
		die("503 Error. Please try again in $parameters[mBlockingTime] seconds");
	}

	public function sendEmail($emails, $parameters)
	{
		mail(implode(',',$emails),
			"DoS attack from ip: ".$_SERVER['REMOTE_ADDR'].', '.date("Y-m-d H:i:s"),
			"Monitor Configuration: \nmTimeout: $parameters[mTimeout] \nmBlockingTime: $parameters[mBlockingTime] \nmPageHits: $parameters[mPageHits]\n\n".
			"GEOIP_ADDR: ".$_SERVER["GEOIP_ADDR"]."\nGEOIP_COUNTRY_CODE: ".$_SERVER["GEOIP_COUNTRY_CODE"]."\nREQUEST_URI: ".$_SERVER["REQUEST_URI"]."\nPHPSESSID: ".$_COOKIE['PHPSESSID']."\nHTTP_USER_AGENT: ".$_SERVER['HTTP_USER_AGENT'],
			"From: dos_evasive@zoya.bg");
	}
}


abstract class CacheMethod
{
	public function __construct($parameters)
	{
		$this->init($parameters);
	}

	public function add($key, $value, $expiration = null)
	{
		return $this->addValue($key, $value, $expiration);
	}

	public function set($key, $value, $expiration = null)
	{
		return $this->storeValue($key, $value, $expiration);
	}

	public function get($key)
	{
		return $this->retrieveValue($key);
	}

	public function increment($key, $value = null)
	{
		return $this->incrementValue($key, $value);
	}

	public function delete($key)
	{
		return $this->removeValue($key);
	}

	protected abstract function init($parameters);
	protected abstract function addValue($key, $value, $expiration = null);
	protected abstract function storeValue($key, $value, $expiration = null);
	protected abstract function retrieveValue($key);
	protected abstract function incrementValue($key, $value = null);
	protected abstract function removeValue($key);
	public abstract function isValid();
}

class MemcacheStore extends CacheMethod
{
	private $connection;
	private $host;
	private $connected;

	protected function addValue($key, $value, $expiration = null)
	{
		if ($this->connection)
		{
			return $this->connection->add($key, $value,null, $expiration);
		}
		return false;
	}

	protected function storeValue($key, $value, $expiration = null)
	{
		if ($this->connection)
		{
			return $this->connection->set($key, $value,null, $expiration);
		}
		return false;
	}

	protected function retrieveValue($key)
	{
		if ($this->connection)
		{
			return $this->connection->get($key);
		}
		return false;
	}

	protected function incrementValue($key, $value = null)
	{
		if ($this->connection)
		{
			return $this->connection->increment($key, $value);
		}
		return false;
	}

	public function init($parameters)
	{
		$this->host = $parameters['mHost'];

		if (!$this->connection)
		{
			$this->connection = new Memcache();
			$this->connected = @$this->connection->connect($this->host);
		}
	}

	public function isValid()
	{
		return $this->connected != false;
	}

	protected function removeValue($key)
	{
		if ($this->connection)
		{
			return $this->connection->delete($key);
		}
		return false;
	}
}


class MemcachedStore extends CacheMethod
{
	private $connection;
	private $host;
	private $connected;

	protected function addValue($key, $value, $expiration = null)
	{
		if ($this->connection)
		{
			return $this->connection->add($key, $value, $expiration);
		}
		return false;
	}

	protected function storeValue($key, $value, $expiration = null)
	{
		if ($this->connection)
		{
			return $this->connection->set($key, $value, $expiration);
		}

		return false;
	}

	protected function retrieveValue($key)
	{
		if ($this->connection)
		{
			return $this->connection->get($key);
		}
		return false;
	}

	protected function incrementValue($key, $value = null)
	{
		if ($this->connection)
		{
			return $this->connection->increment($key, $value);
		}
		return false;
	}

	public function init($parameters)
	{
		$this->host = $parameters['mHost'];

		if (!$this->connection)
		{
			$this->connection = new Memcached();
			$this->connected = @$this->connection->addServer($this->host, 11211);
		}
	}

	public function isValid()
	{
		return $this->connected != false;
	}

	protected function removeValue($key)
	{
		if ($this->connection)
		{
			return $this->connection->delete($key);
		}
		return false;
	}
}


class APCStore extends CacheMethod
{

	protected function addValue($key, $value, $expiration = null)
	{
		return apc_add($key, $value, $expiration);
	}

	protected function storeValue($key, $value, $expiration = null)
	{
		return apc_store($key, $value, $expiration);
	}

	protected function retrieveValue($key)
	{
		return apc_fetch($key);
	}

	protected function incrementValue($key, $value = null)
	{
		if (apc_exists($key)) return apc_inc($key, $value);

		return false;
	}

	public function init($parameters)
	{
		//no need to set anything as APC is always accessible if installed
	}

	public function isValid()
	{
		return true;
	}

	protected function removeValue($key)
	{
		return apc_delete($key);
	}
}