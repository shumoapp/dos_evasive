<?php

include_once('/home/veliko/Zoya/shumoapp/dos_evasive/dos_evasive.php');

/**
 * Class MemcachedStoreTest
 */
class MemcachedStoreTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * Cache key prefix
	 * @var string
	 */
	private $mKey = 'dos_evasive_tests';

	/**
	 *	Test Memcached storage and retrieval. Must have Memcached up and running.
	 */
	public function testMemcachedStore()
	{
		$mConnection = new MemcachedStore(array('mHost'=>'localhost'));
		$mConnection->delete($this->mKey);

		//Test incrementing, when no such key exists
		$mHits = $mConnection->increment($this->mKey, 1);
		$this->assertEquals(false, $mHits);

		//Test adding a value when no such key exists
		$added = $mConnection->add($this->mKey, 1, 1);
		$this->assertEquals(1, $mConnection->get($this->mKey));
		$this->assertEquals(true, $added);

		//Test adding a value when the key exists
		$added = $mConnection->add($this->mKey, 1, 1);
		$this->assertEquals(false, $added);

		//Test incrementing, when value exists
		$mHits = $mConnection->increment($this->mKey, 1);
		$this->assertEquals(2, $mHits);
		$this->assertEquals(2, $mConnection->get($this->mKey));

		//Test setting a value when the key exists
		$set = $mConnection->set($this->mKey, 123, 1);
		$this->assertEquals(true, $set);
		$this->assertEquals(123, $mConnection->get($this->mKey));

		//Testing deleting a value
		$deleted = $mConnection->delete($this->mKey);
		$this->assertEquals(true, $deleted);
		$this->assertEquals(false, $mConnection->get($this->mKey));

	}

} 