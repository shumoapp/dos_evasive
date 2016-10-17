<?php
/*
 * Setup environment vars that the monitor relies on
 */
$_SERVER['REQUEST_URI'] = 'tests/MemcachedStoreTest.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

include_once('/home/veliko/Zoya/shumoapp/dos_evasive/dos_evasive.php');

class DosEvasiveMonitorTest extends \PHPUnit_Framework_TestCase{

	public function testDosEvasiveMonitor()
	{

		$cacheMethod = 'MemcachedStore';
		$mConnection = new $cacheMethod(array('mHost'=>'localhost'));

		$parameters = array(
			//in seconds
			'mTimeout' => 1,
			'mBlockingTime' => 5,
			'mPageHits' => 10,
			'mUriExclusion' => '^(\/someApi\/)|(\/someOtherApi)'
		);

		$monitor = new DosEvasiveMonitor($mConnection, $parameters);
		$monitor->resetMonitor();
		$monitor->execute($parameters);
		$this->assertEquals(false, $monitor->shouldBlock());

		//Simulate not enough hits for a DOS attack
		$monitor->resetMonitor();
		$i = 0;
		while($i++<$parameters['mPageHits']-1)
		{
			$monitor->execute($parameters);
		}
		$this->assertEquals(false, $monitor->shouldBlock());

		//Simulate DOS attack
		$monitor->resetMonitor();
		$i = 0;
		while($i++<=$parameters['mPageHits'])
		{
			$monitor->execute($parameters);
		}
		$this->assertEquals(true, $monitor->shouldBlock());

		//Wait just before the block end, so we can test the incremental timeout
		sleep($parameters['mBlockingTime']-1);
		$monitor = new DosEvasiveMonitor($mConnection, $parameters);
		$this->assertEquals(true, $monitor->shouldBlock());

		//Wait for the block to end, and test again
		sleep($parameters['mBlockingTime']);
		$monitor = new DosEvasiveMonitor($mConnection, $parameters);
		$this->assertEquals(false, $monitor->shouldBlock());

		//Test regular expression exclusions
		$_SERVER['REQUEST_URI'] = '/someApi/MemcachedStoreTest.php';
		//Simulate API usage, that should not be blocked
		$monitor->resetMonitor();
		$i = 0;
		while($i++<=$parameters['mPageHits'])
		{
			$monitor->execute($parameters);
		}
		$this->assertEquals(false, $monitor->shouldBlock());

		$_SERVER['REQUEST_URI'] = '/someOtherApi/';
		//Simulate API usage, that should not be blocked
		$monitor->resetMonitor();
		$i = 0;
		while($i++<=$parameters['mPageHits'])
		{
			$monitor->execute($parameters);
		}
		$this->assertEquals(false, $monitor->shouldBlock());
		//TODO Test that email should be sent only when the block is triggered

	}
} 