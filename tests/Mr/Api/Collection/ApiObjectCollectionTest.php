<?php 

namespace MrTest\Api\Collection;

// Client
use Mr\Api\Http\Client;
use Mr\Api\Http\Response;
use Mr\Api\Http\Adapter\MockAdapter;

// Repository
use Mr\Api\Repository\ApiRepository;
use Mr\Api\Repository\ChannelRepository;

// Collection
use Mr\Api\Collection\ApiObjectCollection;

class ApiObjectCollectionMock extends ApiObjectCollection
{
    public function isInitialized()
    {
        return $this->_isInitialized;
    }

    public function getRepository()
    {
        return $this->_repository;
    }

    public function isObjectLoadedMockProperty($id)
    {
        return $this->isObjectLoaded($id);
    }

    public function isPageLoadedMockProperty($page)
    {
        return $this->isPageLoaded($page);
    }

    public function isFullyLoadedMockProperty()
    {
        return $this->isFullyLoaded();
    }

    public function isAnyObjectLoaded()
    {
        return !empty($this->_objects) || !empty($this->_pages);
    }

    public function getMetadataMockProperty()
    {
        return $this->getMetadata();
    }
};

class ApiObjectCollectionTest extends \PHPUnit_Framework_TestCase
{
    const MODEL_NAMESPACE = 'Mr\\Api\\Model\\';
    const REPOSITORY_NAMESPACE = 'Mr\\Api\\Model\\';

    protected $page1ObjectsData = array(
        'status' => ApiRepository::STATUS_OK,
        'meta' => array(
            'total' => 3,
            'page' => 1,
            'pages' => 2,
            'limit' => 2
        ),
        'objects' => array(
             array(
                'id' => 1,
                'url' => 'http://site.channel.com',
                'name' => 'Channel 1'
            ),
            array(
                'id' => 2,
                'url' => 'http://site.channel.com',
                'name' => 'Channel 2'
            )
        )
    );

    protected $page2ObjectsData = array(
        'status' => ApiRepository::STATUS_OK,
        'meta' => array(
            'total' => 3,
            'page' => 2,
            'pages' => 2,
            'limit' => 2
        ),
        'objects' => array(
            array(
                'id' => 3,
                'url' => 'http://site.channel.com',
                'name' => 'Channel 3'
            )
        )
    );

    protected $_collection;
    protected $_repository;

    public function __construct()
    {
        $client = new Client('anyhost', 'anyusername', 'anypassword');
        $this->_clientMockAdapter = new MockAdapter(); 
        $client->setAdapter($this->_clientMockAdapter);
        $this->_repository = new ChannelRepository($client);
    }

    private function addMockResponses()
    {
        $this->_clientMockAdapter->addResponseBy(Response::STATUS_OK, 'api/channel', json_encode($this->page1ObjectsData));
        $this->_clientMockAdapter->addResponseBy(Response::STATUS_OK, 'api/channel', json_encode($this->page2ObjectsData));
        $this->_clientMockAdapter->addExceptionReponse();
    }

    public function setUp()
    {
        $this->addMockResponses();
        $this->_collection = new ApiObjectCollectionMock($this->_repository);
    }

    public function testRepositoryReference()
    {
        $this->assertEquals($this->_repository, $this->_collection->getRepository());
    }

    public function testNotInitialized()
    {
        // At this point collection should not be initilized yet
        $this->assertFalse($this->_collection->isInitialized());
        $this->assertFalse($this->_collection->isObjectLoadedMockProperty(1));
        $this->assertFalse($this->_collection->isPageLoadedMockProperty(1));
        $this->assertFalse($this->_collection->isFullyLoadedMockProperty());
    }

    public function testInitialData()
    {
        $this->assertEquals($this->_collection->getCurrentPage(), 1);
        $this->assertFalse($this->_collection->isAnyObjectLoaded());
    }

    public function testFirstPageLoad()
    {
        $metadataData = $this->page1ObjectsData['meta'];
        $objects = $this->_collection->getObjects();
        $firstObject = $objects[0];

        $this->assertTrue($this->_collection->isInitialized());
        $this->assertTrue($this->_collection->isObjectLoadedMockProperty($firstObject->getId()));
        $this->assertTrue($this->_collection->isPageLoadedMockProperty(1));
        $this->assertEquals(3, $this->_collection->count());
        // Not fully loaded yet
        $this->assertFalse($this->_collection->isFullyLoadedMockProperty());

        $this->assertInternalType(\PHPUnit_Framework_Constraint_IsType::TYPE_ARRAY, $objects);
        // Check metadata
        $this->assertEquals($metadataData, $this->_collection->getMetadataMockProperty());
        // Check object count per page, it should be same as page limit
        $this->assertEquals(count($objects), $metadataData['limit']);
        // Check entity returned type
        $this->assertInstanceOf(self::MODEL_NAMESPACE . 'Channel', $objects[0]);

        $objectsSamePage = $this->_collection->getObjects();
        // Check if the returned objects are always the same if the page remains
        $this->assertEquals($objects, $objectsSamePage);

        // Object 3 not loaded
        $this->assertFalse($this->_collection->isObjectLoadedMockProperty(3));
        // Page 2 not loaded
        $this->assertFalse($this->_collection->isPageLoadedMockProperty(2));
    }

    public function testObjectsAccesibility()
    {
        $objects = $this->_collection->getObjects();
        $firstObject = $objects[0];

        // Check existence by object entity
        $this->assertTrue($this->_collection->exists($firstObject));
        // Check existence by object entity
        $this->assertTrue($this->_collection->exists($firstObject->getId()));

        $returnedObject = $this->_collection->get($firstObject->getId());
        $this->assertEquals($returnedObject, $firstObject);

        $returnedObject = $this->_collection->getByIndex(0);
        $this->assertEquals($returnedObject, $firstObject);
    }

    public function testSecondPageLoad()
    {
        $metadataData = $this->page1ObjectsData['meta'];
        $this->_collection->getObjects();
        // Move to page 2
        $this->_collection->increasePage();
        $objects = $this->_collection->getObjects();
        $thirdObject = $objects[0];

        // Is still initialized
        $this->assertTrue($this->_collection->isInitialized());
        $this->assertTrue($this->_collection->isObjectLoadedMockProperty($thirdObject->getId()));
        $this->assertTrue($this->_collection->isPageLoadedMockProperty(2));
        // Now should be fully loaded
        $this->assertTrue($this->_collection->isFullyLoadedMockProperty());
        $this->assertEquals(3, $this->_collection->count());

        $this->assertInternalType(\PHPUnit_Framework_Constraint_IsType::TYPE_ARRAY, $objects);
        // Check object count, should be the same as items left in data -> 1 (for second page)
        $this->assertEquals(count($objects), 1);
        // Check entity returned type again
        $this->assertInstanceOf(self::MODEL_NAMESPACE . 'Channel', $objects[0]);

        $objectsSamePage = $this->_collection->getObjects();
        // Check if the returned objects are always the same if the page remains
        $this->assertEquals($objects, $objectsSamePage);
    }

    public function testClearCollection()
    {
        $this->_collection->getObjects();
        // Clear collection data
        $this->_collection->clear();

        // Is still initialized
        $this->assertTrue($this->_collection->isInitialized());
        $this->assertFalse($this->_collection->isObjectLoadedMockProperty(1));
        $this->assertFalse($this->_collection->isPageLoadedMockProperty(1));
        $this->assertFalse($this->_collection->isAnyObjectLoaded());
        $this->assertEquals(3, $this->_collection->count());

        $metadataData = $this->page1ObjectsData['meta'];
        // Metadata persist
        $this->assertEquals($metadataData, $this->_collection->getMetadataMockProperty());

        // Objects can be reloaded (from second mock response)
        $this->_collection->setCurrentPage(2);
        $this->_collection->getObjects();
        $this->assertTrue($this->_collection->isObjectLoadedMockProperty(3));
        $this->assertTrue($this->_collection->isPageLoadedMockProperty(2));
    }

    public function testToArrayForFullLoad()
    {
        $objArray = $this->_collection->toArray();

        // Objects were returned
        $this->assertEquals(3, count($objArray));
        $this->assertTrue($this->_collection->isFullyLoadedMockProperty());
    }

    public function testArrayAccess()
    {
        $objects = $this->_collection->getObjects();
        $firstObject = $objects[0];

        $this->assertTrue(isset($this->_collection[0]));
        $this->assertEquals($this->_collection[0], $firstObject);

    }
}