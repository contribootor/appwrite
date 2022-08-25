<?php

namespace Tests\E2E\Services\Databases;

use Tests\E2E\Scopes\ProjectCustom;
use Tests\E2E\Scopes\Scope;
use Tests\E2E\Scopes\SideServer;
use Tests\E2E\Client;
use Utopia\Database\Database;

class DatabasesCustomServerTest extends Scope
{
    use DatabasesBase;
    use ProjectCustom;
    use SideServer;

    public function testListDatabases()
    {
        $test1 = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'databaseId' => 'first',
            'name' => 'Test 1',
        ]);
        $this->assertEquals(201, $test1['headers']['status-code']);
        $this->assertEquals('Test 1', $test1['body']['name']);

        $test2 = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'databaseId' => 'second',
            'name' => 'Test 2',
        ]);
        $this->assertEquals(201, $test2['headers']['status-code']);
        $this->assertEquals('Test 2', $test2['body']['name']);

        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $this->assertEquals(2, $databases['body']['total']);
        $this->assertEquals($test1['body']['$id'], $databases['body']['databases'][0]['$id']);
        $this->assertEquals($test2['body']['$id'], $databases['body']['databases'][1]['$id']);

        /**
         * Test for Order
         */
        $base = array_reverse($databases['body']['databases']);
        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'orderType' => 'DESC'
        ]);

        $this->assertEquals(2, $databases['body']['total']);
        $this->assertEquals($base[0]['$id'], $databases['body']['databases'][0]['$id']);
        $this->assertEquals($base[1]['$id'], $databases['body']['databases'][1]['$id']);

        /**
         * Test for After
         */
        $base = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['databases'][0]['$id']
        ]);

        $this->assertCount(1, $databases['body']['databases']);
        $this->assertEquals($base['body']['databases'][1]['$id'], $databases['body']['databases'][0]['$id']);

        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['databases'][1]['$id']
        ]);

        $this->assertCount(0, $databases['body']['databases']);
        $this->assertEmpty($databases['body']['databases']);

        /**
         * Test for Before
         */
        $base = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['databases'][1]['$id'],
            'cursorDirection' => Database::CURSOR_BEFORE
        ]);

        $this->assertCount(1, $databases['body']['databases']);
        $this->assertEquals($base['body']['databases'][0]['$id'], $databases['body']['databases'][0]['$id']);

        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['databases'][0]['$id'],
            'cursorDirection' => Database::CURSOR_BEFORE
        ]);

        $this->assertCount(0, $databases['body']['databases']);
        $this->assertEmpty($databases['body']['databases']);

        /**
         * Test for Search
         */
        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'search' => 'first'
        ]);

        $this->assertEquals(1, $databases['body']['total']);
        $this->assertEquals('first', $databases['body']['databases'][0]['$id']);

        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'search' => 'Test'
        ]);

        $this->assertEquals(2, $databases['body']['total']);
        $this->assertEquals('Test 1', $databases['body']['databases'][0]['name']);
        $this->assertEquals('Test 2', $databases['body']['databases'][1]['name']);

        $databases = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'search' => 'Nonexistent'
        ]);

        $this->assertEquals(0, $databases['body']['total']);

        /**
         * Test for FAILURE
         */
        $response = $this->client->call(Client::METHOD_GET, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => 'unknown',
        ]);

        $this->assertEquals(400, $response['headers']['status-code']);

        // This collection already exists
        $response = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'name' => 'Test 1',
            'databaseId' => 'first',
        ]);

        $this->assertEquals(409, $response['headers']['status-code']);
        return ['databaseId' => $test1['body']['$id']];
    }

    /**
     * @depends testListDatabases
     */
    public function testGetDatabase(array $data): array
    {
        $databaseId = $data['databaseId'];
        /**
         * Test for SUCCESS
         */
        $database = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId, [
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]);

        $this->assertEquals(200, $database['headers']['status-code']);
        $this->assertEquals($databaseId, $database['body']['$id']);
        $this->assertEquals('Test 1', $database['body']['name']);

        return ['databaseId' => $database['body']['$id']];
    }

    /**
     * @depends testListDatabases
     */
    public function testDeleteDatabase($data)
    {
        $databaseId = $data['databaseId'];

        $response = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], $this->getHeaders()));

        $this->assertEquals(204, $response['headers']['status-code']);
        $this->assertEquals("", $response['body']);

        // Try to get the collection and check if it has been deleted
        $response = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id']
        ], $this->getHeaders()));

        $this->assertEquals(404, $response['headers']['status-code']);
    }

    public function testListCollections()
    {
        $database = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'databaseId' => 'unique()',
            'name' => 'invalidDocumentDatabase',
        ]);
        $this->assertEquals(201, $database['headers']['status-code']);
        $this->assertEquals('invalidDocumentDatabase', $database['body']['name']);

        $databaseId = $database['body']['$id'];
        /**
         * Test for SUCCESS
         */
        $test1 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'name' => 'Test 1',
            'collectionId' => 'first',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document'
        ]);

        $test2 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'name' => 'Test 2',
            'collectionId' => 'second',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document'
        ]);

        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $this->assertEquals(2, $collections['body']['total']);
        $this->assertEquals($test1['body']['$id'], $collections['body']['collections'][0]['$id']);
        $this->assertEquals($test2['body']['$id'], $collections['body']['collections'][1]['$id']);

        /**
         * Test for Order
         */
        $base = array_reverse($collections['body']['collections']);
        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'orderType' => 'DESC'
        ]);

        $this->assertEquals(2, $collections['body']['total']);
        $this->assertEquals($base[0]['$id'], $collections['body']['collections'][0]['$id']);
        $this->assertEquals($base[1]['$id'], $collections['body']['collections'][1]['$id']);

        /**
         * Test for After
         */
        $base = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['collections'][0]['$id']
        ]);

        $this->assertCount(1, $collections['body']['collections']);
        $this->assertEquals($base['body']['collections'][1]['$id'], $collections['body']['collections'][0]['$id']);

        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['collections'][1]['$id']
        ]);

        $this->assertCount(0, $collections['body']['collections']);
        $this->assertEmpty($collections['body']['collections']);

        /**
         * Test for Before
         */
        $base = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['collections'][1]['$id'],
            'cursorDirection' => Database::CURSOR_BEFORE
        ]);

        $this->assertCount(1, $collections['body']['collections']);
        $this->assertEquals($base['body']['collections'][0]['$id'], $collections['body']['collections'][0]['$id']);

        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => $base['body']['collections'][0]['$id'],
            'cursorDirection' => Database::CURSOR_BEFORE
        ]);

        $this->assertCount(0, $collections['body']['collections']);
        $this->assertEmpty($collections['body']['collections']);

        /**
         * Test for Search
         */
        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'search' => 'first'
        ]);

        $this->assertEquals(1, $collections['body']['total']);
        $this->assertEquals('first', $collections['body']['collections'][0]['$id']);

        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'search' => 'Test'
        ]);

        $this->assertEquals(2, $collections['body']['total']);
        $this->assertEquals('Test 1', $collections['body']['collections'][0]['name']);
        $this->assertEquals('Test 2', $collections['body']['collections'][1]['name']);

        $collections = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'search' => 'Nonexistent'
        ]);

        $this->assertEquals(0, $collections['body']['total']);

        /**
         * Test for FAILURE
         */
        $response = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'cursor' => 'unknown',
        ]);

        $this->assertEquals(400, $response['headers']['status-code']);

        // This collection already exists
        $response = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'name' => 'Test 1',
            'collectionId' => 'first',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document'
        ]);

        $this->assertEquals(409, $response['headers']['status-code']);
    }

    public function testDeleteAttribute(): array
    {
        $database = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'databaseId' => 'unique()',
            'name' => 'invalidDocumentDatabase',
        ]);
        $this->assertEquals(201, $database['headers']['status-code']);
        $this->assertEquals('invalidDocumentDatabase', $database['body']['name']);

        $databaseId = $database['body']['$id'];
        /**
         * Test for SUCCESS
         */

        // Create collection
        $actors = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'collectionId' => 'unique()',
            'name' => 'Actors',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document'
        ]);

        $this->assertEquals(201, $actors['headers']['status-code']);
        $this->assertEquals('Actors', $actors['body']['name']);

        $firstName = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'] . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'firstName',
            'size' => 256,
            'required' => true,
        ]);

        $lastName = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'] . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'lastName',
            'size' => 256,
            'required' => true,
        ]);

        $unneeded = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'] . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'unneeded',
            'size' => 256,
            'required' => true,
        ]);

        // Wait for database worker to finish creating attributes
        sleep(2);

        // Creating document to ensure cache is purged on schema change
        $document = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'] . '/documents', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'documentId' => 'unique()',
            'data' => [
                'firstName' => 'lorem',
                'lastName' => 'ipsum',
                'unneeded' =>  'dolor'
            ],
            'read' => ['role:all'],
            'write' => ['role:all'],
        ]);

        $index = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'] . '/indexes', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'key_lastName',
            'type' => 'key',
            'attributes' => [
                'lastName',
            ],
        ]);

        // Wait for database worker to finish creating index
        sleep(2);

        $collection = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), []);

        $unneededId = $unneeded['body']['key'];

        $this->assertEquals(200, $collection['headers']['status-code']);
        $this->assertIsArray($collection['body']['attributes']);
        $this->assertCount(3, $collection['body']['attributes']);
        $this->assertEquals($collection['body']['attributes'][0]['key'], $firstName['body']['key']);
        $this->assertEquals($collection['body']['attributes'][1]['key'], $lastName['body']['key']);
        $this->assertEquals($collection['body']['attributes'][2]['key'], $unneeded['body']['key']);
        $this->assertCount(1, $collection['body']['indexes']);
        $this->assertEquals($collection['body']['indexes'][0]['key'], $index['body']['key']);

        // Delete attribute
        $attribute = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $actors ['body']['$id'] . '/attributes/' . $unneededId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(204, $attribute['headers']['status-code']);

        sleep(2);

        // Check document to ensure cache is purged on schema change
        $document = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'] . '/documents/' . $document['body']['$id'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertNotContains($unneededId, $document['body']);

        $collection = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), []);

        $this->assertEquals(200, $collection['headers']['status-code']);
        $this->assertIsArray($collection['body']['attributes']);
        $this->assertCount(2, $collection['body']['attributes']);
        $this->assertEquals($collection['body']['attributes'][0]['key'], $firstName['body']['key']);
        $this->assertEquals($collection['body']['attributes'][1]['key'], $lastName['body']['key']);

        return [
            'collectionId' => $actors['body']['$id'],
            'key' => $index['body']['key'],
            'databaseId' => $databaseId
        ];
    }

    /**
     * @depends testDeleteAttribute
     */
    public function testDeleteIndex($data): array
    {
        $databaseId = $data['databaseId'];
        $index = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $data['collectionId'] . '/indexes/' . $data['key'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(204, $index['headers']['status-code']);

        // Wait for database worker to finish deleting index
        sleep(2);

        $collection = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $data['collectionId'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), []);

        $this->assertCount(0, $collection['body']['indexes']);

        return $data;
    }

    /**
     * @depends testDeleteIndex
     */
    public function testDeleteIndexOnDeleteAttribute($data)
    {
        $databaseId = $data['databaseId'];
        $attribute1 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $data['collectionId'] . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'attribute1',
            'size' => 16,
            'required' => true,
        ]);

        $attribute2 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $data['collectionId'] . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'attribute2',
            'size' => 16,
            'required' => true,
        ]);

        $this->assertEquals(201, $attribute1['headers']['status-code']);
        $this->assertEquals(201, $attribute2['headers']['status-code']);
        $this->assertEquals('attribute1', $attribute1['body']['key']);
        $this->assertEquals('attribute2', $attribute2['body']['key']);

        sleep(2);

        $index1 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $data['collectionId'] . '/indexes', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'index1',
            'type' => 'key',
            'attributes' => ['attribute1', 'attribute2'],
            'orders' => ['ASC', 'ASC'],
        ]);

        $index2 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $data['collectionId'] . '/indexes', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'index2',
            'type' => 'key',
            'attributes' => ['attribute2'],
        ]);

        $this->assertEquals(201, $index1['headers']['status-code']);
        $this->assertEquals(201, $index2['headers']['status-code']);
        $this->assertEquals('index1', $index1['body']['key']);
        $this->assertEquals('index2', $index2['body']['key']);

        sleep(2);

        // Expected behavior: deleting attribute2 will cause index2 to be dropped, and index1 rebuilt with a single key
        $deleted = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $data['collectionId'] . '/attributes/' . $attribute2['body']['key'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(204, $deleted['headers']['status-code']);

        // wait for database worker to complete
        sleep(2);

        $collection = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $data['collectionId'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(200, $collection['headers']['status-code']);
        $this->assertIsArray($collection['body']['indexes']);
        $this->assertCount(1, $collection['body']['indexes']);
        $this->assertEquals($index1['body']['key'], $collection['body']['indexes'][0]['key']);
        $this->assertIsArray($collection['body']['indexes'][0]['attributes']);
        $this->assertCount(1, $collection['body']['indexes'][0]['attributes']);
        $this->assertEquals($attribute1['body']['key'], $collection['body']['indexes'][0]['attributes'][0]);

        // Delete attribute
        $deleted = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $data['collectionId'] . '/attributes/' . $attribute1['body']['key'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(204, $deleted['headers']['status-code']);

        return $data;
    }

    public function testCleanupDuplicateIndexOnDeleteAttribute()
    {
        $database = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'databaseId' => 'unique()',
            'name' => 'invalidDocumentDatabase',
        ]);
        $this->assertEquals(201, $database['headers']['status-code']);
        $this->assertEquals('invalidDocumentDatabase', $database['body']['name']);

        $databaseId = $database['body']['$id'];
        $collection = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'collectionId' => 'unique()',
            'name' => 'TestCleanupDuplicateIndexOnDeleteAttribute',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document',
        ]);

        $this->assertEquals(201, $collection['headers']['status-code']);
        $this->assertNotEmpty($collection['body']['$id']);

        $collectionId = $collection['body']['$id'];

        $attribute1 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'attribute1',
            'size' => 16,
            'required' => true,
        ]);

        $attribute2 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'attribute2',
            'size' => 16,
            'required' => true,
        ]);

        $this->assertEquals(201, $attribute1['headers']['status-code']);
        $this->assertEquals(201, $attribute2['headers']['status-code']);
        $this->assertEquals('attribute1', $attribute1['body']['key']);
        $this->assertEquals('attribute2', $attribute2['body']['key']);

        sleep(2);

        $index1 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/indexes', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'index1',
            'type' => 'key',
            'attributes' => ['attribute1', 'attribute2'],
            'orders' => ['ASC', 'ASC'],
        ]);

        $index2 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/indexes', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'index2',
            'type' => 'key',
            'attributes' => ['attribute2'],
        ]);

        $this->assertEquals(201, $index1['headers']['status-code']);
        $this->assertEquals(201, $index2['headers']['status-code']);
        $this->assertEquals('index1', $index1['body']['key']);
        $this->assertEquals('index2', $index2['body']['key']);

        sleep(2);

        // Expected behavior: deleting attribute1 would cause index1 to be a duplicate of index2 and automatically removed
        $deleted = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/' . $attribute1['body']['key'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(204, $deleted['headers']['status-code']);

        // wait for database worker to complete
        sleep(2);

        $collection = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $collectionId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(200, $collection['headers']['status-code']);
        $this->assertIsArray($collection['body']['indexes']);
        $this->assertCount(1, $collection['body']['indexes']);
        $this->assertEquals($index2['body']['key'], $collection['body']['indexes'][0]['key']);
        $this->assertIsArray($collection['body']['indexes'][0]['attributes']);
        $this->assertCount(1, $collection['body']['indexes'][0]['attributes']);
        $this->assertEquals($attribute2['body']['key'], $collection['body']['indexes'][0]['attributes'][0]);

        // Delete attribute
        $deleted = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/' . $attribute2['body']['key'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(204, $deleted['headers']['status-code']);
    }

    /**
     * @depends testDeleteIndexOnDeleteAttribute
     */
    public function testDeleteCollection($data)
    {
        $databaseId = $data['databaseId'];
        $collectionId = $data['collectionId'];

        // Add Documents to the collection
        $document1 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/documents', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'documentId' => 'unique()',
            'data' => [
                'firstName' => 'Tom',
                'lastName' => 'Holland',
            ],
            'read' => ['user:' . $this->getUser()['$id']],
            'write' => ['user:' . $this->getUser()['$id']],
        ]);

        $document2 = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/documents', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'documentId' => 'unique()',
            'data' => [
                'firstName' => 'Samuel',
                'lastName' => 'Jackson',
            ],
            'read' => ['user:' . $this->getUser()['$id']],
            'write' => ['user:' . $this->getUser()['$id']],
        ]);

        $this->assertEquals(201, $document1['headers']['status-code']);
        $this->assertIsArray($document1['body']['$read']);
        $this->assertIsArray($document1['body']['$write']);
        $this->assertCount(1, $document1['body']['$read']);
        $this->assertCount(1, $document1['body']['$write']);
        $this->assertEquals('Tom', $document1['body']['firstName']);
        $this->assertEquals('Holland', $document1['body']['lastName']);

        $this->assertEquals(201, $document2['headers']['status-code']);
        $this->assertIsArray($document2['body']['$read']);
        $this->assertIsArray($document2['body']['$write']);
        $this->assertCount(1, $document2['body']['$read']);
        $this->assertCount(1, $document2['body']['$write']);
        $this->assertEquals('Samuel', $document2['body']['firstName']);
        $this->assertEquals('Jackson', $document2['body']['lastName']);

        // Delete the actors collection
        $response = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $collectionId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], $this->getHeaders()));

        $this->assertEquals(204, $response['headers']['status-code']);
        $this->assertEquals("", $response['body']);

        // Try to get the collection and check if it has been deleted
        $response = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $collectionId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id']
        ], $this->getHeaders()));

        $this->assertEquals(404, $response['headers']['status-code']);
    }

    // Adds several minutes to test to replicate coverage in Utopia\Database unit tests
    // and messes with subsequent tests as DatabaseV1 queue gets overwhelmed
    // TODO@kodumbeats either fix or remove testAttributeCountLimit
    // Options to fix:
    // - Enable attribute creation in batches
    // - Use additional database workers
    // - Wait for worker to complete before moving onto next test
    // - Remove since this is unit tested in Utopia\Database
    //
    // public function testAttributeCountLimit()
    // {
    //     $collection = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
    //         'content-type' => 'application/json',
    //         'x-appwrite-project' => $this->getProject()['$id'],
    //         'x-appwrite-key' => $this->getProject()['apiKey']
    //     ]), [
    //         'collectionId' => 'unique()',
    //         'name' => 'attributeCountLimit',
    //         'read' => ['role:all'],
    //         'write' => ['role:all'],
    //         'permission' => 'document',
    //     ]);

    //     $collectionId = $collection['body']['$id'];

    //     // load the collection up to the limit
    //     for ($i=0; $i < 1012; $i++) {
    //         $attribute = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/integer', array_merge([
    //             'content-type' => 'application/json',
    //             'x-appwrite-project' => $this->getProject()['$id'],
    //             'x-appwrite-key' => $this->getProject()['apiKey']
    //         ]), [
    //             'key' => "attribute{$i}",
    //             'required' => false,
    //         ]);

    //         $this->assertEquals(201, $attribute['headers']['status-code']);
    //     }

    //     sleep(30);

    //     $tooMany = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/integer', array_merge([
    //         'content-type' => 'application/json',
    //         'x-appwrite-project' => $this->getProject()['$id'],
    //         'x-appwrite-key' => $this->getProject()['apiKey']
    //     ]), [
    //         'key' => "tooMany",
    //         'required' => false,
    //     ]);

    //     $this->assertEquals(400, $tooMany['headers']['status-code']);
    //     $this->assertEquals('Attribute limit exceeded', $tooMany['body']['message']);
    // }

    public function testAttributeRowWidthLimit()
    {
        $database = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'databaseId' => 'unique()',
            'name' => 'invalidDocumentDatabase',
        ]);
        $this->assertEquals(201, $database['headers']['status-code']);
        $this->assertEquals('invalidDocumentDatabase', $database['body']['name']);

        $databaseId = $database['body']['$id'];
        $collection = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'collectionId' => 'attributeRowWidthLimit',
            'name' => 'attributeRowWidthLimit',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document',
        ]);

        $this->assertEquals(201, $collection['headers']['status-code']);
        $this->assertEquals('attributeRowWidthLimit', $collection['body']['name']);

        $collectionId = $collection['body']['$id'];

        // Add wide string attributes to approach row width limit
        for ($i = 0; $i < 15; $i++) {
            $attribute = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/string', array_merge([
                'content-type' => 'application/json',
                'x-appwrite-project' => $this->getProject()['$id'],
                'x-appwrite-key' => $this->getProject()['apiKey']
            ]), [
                'key' => "attribute{$i}",
                'size' => 1024,
                'required' => true,
            ]);

            $this->assertEquals(201, $attribute['headers']['status-code']);
        }

        sleep(5);

        $tooWide = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/string', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'tooWide',
            'size' => 1024,
            'required' => true,
        ]);

        $this->assertEquals(400, $tooWide['headers']['status-code']);
        $this->assertEquals('Attribute limit exceeded', $tooWide['body']['message']);
    }

    public function testIndexLimitException()
    {
        $database = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'databaseId' => 'unique()',
            'name' => 'invalidDocumentDatabase',
        ]);
        $this->assertEquals(201, $database['headers']['status-code']);
        $this->assertEquals('invalidDocumentDatabase', $database['body']['name']);

        $databaseId = $database['body']['$id'];
        $collection = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'collectionId' => 'testLimitException',
            'name' => 'testLimitException',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document',
        ]);

        $this->assertEquals(201, $collection['headers']['status-code']);
        $this->assertEquals('testLimitException', $collection['body']['name']);

        $collectionId = $collection['body']['$id'];

        // add unique attributes for indexing
        for ($i = 0; $i < 64; $i++) {
            // $this->assertEquals(true, static::getDatabase()->createAttribute('indexLimit', "test{$i}", Database::VAR_STRING, 16, true));
            $attribute = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/attributes/string', array_merge([
                'content-type' => 'application/json',
                'x-appwrite-project' => $this->getProject()['$id'],
                'x-appwrite-key' => $this->getProject()['apiKey']
            ]), [
                'key' => "attribute{$i}",
                'size' => 64,
                'required' => true,
            ]);

            $this->assertEquals(201, $attribute['headers']['status-code']);
        }

        sleep(20);

        $collection = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $collectionId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(200, $collection['headers']['status-code']);
        $this->assertEquals('testLimitException', $collection['body']['name']);
        $this->assertIsArray($collection['body']['attributes']);
        $this->assertIsArray($collection['body']['indexes']);
        $this->assertCount(64, $collection['body']['attributes']);
        $this->assertCount(0, $collection['body']['indexes']);

        foreach ($collection['body']['attributes'] as $attribute) {
            $this->assertEquals('available', $attribute['status'], 'attribute: ' . $attribute['key']);
        }

        // Test indexLimit = 64
        // MariaDB, MySQL, and MongoDB create 5 indexes per new collection
        // Add up to the limit, then check if the next index throws IndexLimitException
        for ($i = 0; $i < 59; $i++) {
            // $this->assertEquals(true, static::getDatabase()->createIndex('indexLimit', "index{$i}", Database::INDEX_KEY, ["test{$i}"], [16]));
            $index = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/indexes', array_merge([
                'content-type' => 'application/json',
                'x-appwrite-project' => $this->getProject()['$id'],
                'x-appwrite-key' => $this->getProject()['apiKey']
            ]), [
                'key' => "key_attribute{$i}",
                'type' => 'key',
                'attributes' => ["attribute{$i}"],
            ]);

            $this->assertEquals(201, $index['headers']['status-code']);
            $this->assertEquals("key_attribute{$i}", $index['body']['key']);
        }

        sleep(5);

        $collection = $this->client->call(Client::METHOD_GET, '/databases/' . $databaseId . '/collections/' . $collectionId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(200, $collection['headers']['status-code']);
        $this->assertEquals('testLimitException', $collection['body']['name']);
        $this->assertIsArray($collection['body']['attributes']);
        $this->assertIsArray($collection['body']['indexes']);
        $this->assertCount(64, $collection['body']['attributes']);
        $this->assertCount(59, $collection['body']['indexes']);

        $tooMany = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $collectionId . '/indexes', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'tooMany',
            'type' => 'key',
            'attributes' => ['attribute61'],
        ]);

        $this->assertEquals(400, $tooMany['headers']['status-code']);
        $this->assertEquals('Index limit exceeded', $tooMany['body']['message']);

        $collection = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $collectionId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        $this->assertEquals(204, $collection['headers']['status-code']);
    }
}
