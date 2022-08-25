<?php

namespace Tests\E2E\Services\Webhooks;

use CURLFile;
use Tests\E2E\Client;
use Tests\E2E\Scopes\ProjectCustom;
use Tests\E2E\Scopes\Scope;
use Tests\E2E\Scopes\SideServer;
use Utopia\CLI\Console;

class WebhooksCustomServerTest extends Scope
{
    use WebhooksBase;
    use ProjectCustom;
    use SideServer;

    /**
     * @depends testCreateAttributes
     */
    public function testUpdateCollection($data): array
    {
        $id = $data['actorsId'];
        $databaseId = $data['databaseId'];

        /**
         * Test for SUCCESS
         */
        $actors = $this->client->call(Client::METHOD_PUT, '/databases/' . $databaseId . '/collections/' . $id, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'name' => 'Actors1',
            'permission' => 'document',
        ]);

        $this->assertEquals(200, $actors['headers']['status-code']);
        $this->assertNotEmpty($actors['body']['$id']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*.update', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$id}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(true, empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''));
        $this->assertNotEmpty($webhook['data']['$id']);
        $this->assertEquals('Actors1', $webhook['data']['name']);
        $this->assertIsArray($webhook['data']['$read']);
        $this->assertIsArray($webhook['data']['$write']);
        $this->assertCount(1, $webhook['data']['$read']);
        $this->assertCount(1, $webhook['data']['$write']);

        return array_merge(['actorsId' => $actors['body']['$id']]);
    }

    /**
     * @depends testCreateAttributes
     */
    public function testCreateDeleteIndexes($data): array
    {
        $actorsId = $data['actorsId'];
        $databaseId = $data['databaseId'];

        $index = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections/' . $data['actorsId'] . '/indexes', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'key' => 'fullname',
            'type' => 'key',
            'attributes' => ['lastName', 'firstName'],
            'orders' => ['ASC', 'ASC'],
        ]);

        $indexKey = $index['body']['key'];
        $this->assertEquals(201, $index['headers']['status-code']);
        $this->assertEquals('fullname', $index['body']['key']);

        // wait for database worker to create index
        sleep(5);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*.indexes.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*.indexes.*.create', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$actorsId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$actorsId}.indexes.*", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$actorsId}.indexes.*.create", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(true, empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''));

        // Remove index
        $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $data['actorsId'] . '/indexes/' . $index['body']['key'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]));

        // // wait for database worker to remove index
        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        // $this->assertEquals($webhook['method'], 'DELETE');
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*.indexes.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*.indexes.*.delete', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$actorsId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$actorsId}.indexes.*", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$actorsId}.indexes.*.delete", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(true, empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''));

        return $data;
    }

    public function testDeleteCollection(): array
    {
        /**
         * Create database
         */
        $database = $this->client->call(Client::METHOD_POST, '/databases', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ], $this->getHeaders()), [
            'databaseId' => 'unique()',
            'name' => 'Actors DB',
        ]);

        $databaseId = $database['body']['$id'];

        /**
         * Test for SUCCESS
         */
        $actors = $this->client->call(Client::METHOD_POST, '/databases/' . $databaseId . '/collections', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), [
            'collectionId' => 'unique()',
            'name' => 'Demo',
            'read' => ['role:all'],
            'write' => ['role:all'],
            'permission' => 'document'
        ]);

        $id = $actors['body']['$id'];

        $this->assertEquals(201, $actors['headers']['status-code']);
        $this->assertNotEmpty($actors['body']['$id']);

        $actors = $this->client->call(Client::METHOD_DELETE, '/databases/' . $databaseId . '/collections/' . $actors['body']['$id'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
            'x-appwrite-key' => $this->getProject()['apiKey']
        ]), []);

        $this->assertEquals(204, $actors['headers']['status-code']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('databases.' . $databaseId . '.collections.*.delete', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("databases.{$databaseId}.collections.{$id}.delete", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(true, empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''));
        $this->assertNotEmpty($webhook['data']['$id']);
        $this->assertEquals('Demo', $webhook['data']['name']);
        $this->assertIsArray($webhook['data']['$read']);
        $this->assertIsArray($webhook['data']['$write']);
        $this->assertCount(1, $webhook['data']['$read']);
        $this->assertCount(1, $webhook['data']['$write']);

        return [];
    }

    public function testCreateUser(): array
    {
        $email = uniqid() . 'user@localhost.test';
        $password = 'password';
        $name = 'User Name';

        /**
         * Test for SUCCESS
         */
        $user = $this->client->call(Client::METHOD_POST, '/users', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'userId' => 'unique()',
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ]);

        $this->assertEquals(201, $user['headers']['status-code']);
        $this->assertNotEmpty($user['body']['$id']);

        $id = $user['body']['$id'];

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('users.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('users.*.create', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}.create", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''), ('server' === $this->getSide()));
        $this->assertNotEmpty($webhook['data']['$id']);
        $this->assertEquals($webhook['data']['name'], $name);
        $this->assertIsInt($webhook['data']['registration']);
        $this->assertEquals(true, $webhook['data']['status']);
        $this->assertEquals($webhook['data']['email'], $email);
        $this->assertEquals(false, $webhook['data']['emailVerification']);
        $this->assertEquals([], $webhook['data']['prefs']);

        /**
         * Test for FAILURE
         */
        return ['userId' => $user['body']['$id'], 'name' => $user['body']['name'], 'email' => $user['body']['email']];
    }

    /**
     * @depends testCreateUser
     */
    public function testUpdateUserPrefs(array $data): array
    {
        $id = $data['userId'];

        /**
         * Test for SUCCESS
         */
        $user = $this->client->call(Client::METHOD_PATCH, '/users/' . $id . '/prefs', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'prefs' => ['a' => 'b']
        ]);

        $this->assertEquals(200, $user['headers']['status-code']);
        $this->assertEquals('b', $user['body']['a']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('users.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('users.*.update', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('users.*.update.prefs', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}.update.prefs", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''), ('server' === $this->getSide()));
        $this->assertEquals('b', $webhook['data']['a']);

        return $data;
    }

    /**
     * @depends testUpdateUserPrefs
     */
    public function testUpdateUserStatus(array $data): array
    {
        $id = $data['userId'];

        /**
         * Test for SUCCESS
         */
        $user = $this->client->call(Client::METHOD_PATCH, '/users/' . $data['userId'] . '/status', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'status' => false,
        ]);

        $this->assertEquals(200, $user['headers']['status-code']);
        $this->assertNotEmpty($user['body']['$id']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('users.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('users.*.update', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('users.*.update.status', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}.update.status", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''), ('server' === $this->getSide()));
        $this->assertNotEmpty($webhook['data']['$id']);
        $this->assertEquals($webhook['data']['name'], $data['name']);
        $this->assertIsInt($webhook['data']['registration']);
        $this->assertEquals(false, $webhook['data']['status']);
        $this->assertEquals($webhook['data']['email'], $data['email']);
        $this->assertEquals(false, $webhook['data']['emailVerification']);
        $this->assertEquals('b', $webhook['data']['prefs']['a']);

        return $data;
    }

    /**
     * @depends testUpdateUserStatus
     */
    public function testDeleteUser(array $data): array
    {
        $id = $data['userId'];

        /**
         * Test for SUCCESS
         */
        $user = $this->client->call(Client::METHOD_DELETE, '/users/' . $data['userId'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $this->assertEquals(204, $user['headers']['status-code']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('users.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('users.*.delete', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("users.{$id}.delete", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);
        $this->assertEquals(empty($webhook['headers']['X-Appwrite-Webhook-User-Id'] ?? ''), ('server' === $this->getSide()));
        $this->assertNotEmpty($webhook['data']['$id']);
        $this->assertEquals($webhook['data']['name'], $data['name']);
        $this->assertIsInt($webhook['data']['registration']);
        $this->assertEquals(false, $webhook['data']['status']);
        $this->assertEquals($webhook['data']['email'], $data['email']);
        $this->assertEquals(false, $webhook['data']['emailVerification']);
        $this->assertEquals('b', $webhook['data']['prefs']['a']);

        return $data;
    }

    public function testCreateFunction(): array
    {
        /**
         * Test for SUCCESS
         */
        $function = $this->client->call(Client::METHOD_POST, '/functions', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'functionId' => 'unique()',
            'name' => 'Test',
            'execute' => ['role:all'],
            'runtime' => 'php-8.0',
            'timeout' => 10,
        ]);

        $id = $function['body']['$id'] ?? '';

        $this->assertEquals(201, $function['headers']['status-code']);
        $this->assertNotEmpty($function['body']['$id']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.create', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.create", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        /**
         * Test for FAILURE
         */

        return [
            'functionId' => $id,
        ];
    }

    /**
     * @depends testCreateFunction
     */
    public function testUpdateFunction($data): array
    {
        $id = $data['functionId'];

        /**
         * Test for SUCCESS
         */
        $function = $this->client->call(Client::METHOD_PUT, '/functions/' . $data['functionId'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'name' => 'Test',
            'runtime' => 'php-8.0',
            'execute' => ['role:all'],
            'vars' => [
                'key1' => 'value1',
            ]
        ]);

        $this->assertEquals(200, $function['headers']['status-code']);
        $this->assertEquals($function['body']['$id'], $data['functionId']);
        $this->assertEquals(['key1' => 'value1'], $function['body']['vars']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.update', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        $function = $this->client->call(Client::METHOD_PUT, '/functions/' . $data['functionId'], array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'name' => 'Test Failure',
            'execute' => [ 'not-valid-permission' ]
        ]);

        $this->assertEquals(400, $function['headers']['status-code']);

        return $data;
    }

    /**
     * @depends testUpdateFunction
     */
    public function testCreateDeployment($data): array
    {
        /**
         * Test for SUCCESS
         */
        $stderr = '';
        $stdout = '';
        $folder = 'timeout';
        $code = realpath(__DIR__ . '/../../../resources/functions') . "/{$folder}/code.tar.gz";
        Console::execute('cd ' . realpath(__DIR__ . "/../../../resources/functions") . "/{$folder}  && tar --exclude code.tar.gz -czf code.tar.gz .", '', $stdout, $stderr);

        $deployment = $this->client->call(Client::METHOD_POST, '/functions/' . $data['functionId'] . '/deployments', array_merge([
            'content-type' => 'multipart/form-data',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), [
            'entrypoint' => 'index.php',
            'code' => new CURLFile($code, 'application/x-gzip', \basename($code))
        ]);

        $id = $data['functionId'] ?? '';
        $deploymentId = $deployment['body']['$id'] ?? '';

        $this->assertEquals(201, $deployment['headers']['status-code']);
        $this->assertNotEmpty($deployment['body']['$id']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.deployments.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.deployments.{$deploymentId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.*", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.{$deploymentId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        sleep(5);

        return array_merge($data, ['deploymentId' => $deploymentId]);
    }

    /**
     * @depends testCreateDeployment
     */
    public function testUpdateDeployment($data): array
    {
        $id = $data['functionId'] ?? '';
        $deploymentId = $data['deploymentId'] ?? '';

        /**
         * Test for SUCCESS
         */
        $response = $this->client->call(Client::METHOD_PATCH, '/functions/' . $id . '/deployments/' . $deploymentId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), []);

        $this->assertEquals(200, $response['headers']['status-code']);
        $this->assertNotEmpty($response['body']['$id']);

        // Wait for deployment to be built.
        sleep(5);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.deployments.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.deployments.*.update', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.deployments.{$deploymentId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.deployments.{$deploymentId}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.*", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.*.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.{$deploymentId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.{$deploymentId}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        /**
         * Test for FAILURE
         */

        return $data;
    }

    /**
     * @depends testUpdateDeployment
     */
    public function testExecutions($data): array
    {
        $id = $data['functionId'] ?? '';

        /**
         * Test for SUCCESS
         */
        $execution = $this->client->call(Client::METHOD_POST, '/functions/' . $id . '/executions', array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()), []);

        $executionId = $execution['body']['$id'] ?? '';

        $this->assertEquals(201, $execution['headers']['status-code']);
        $this->assertNotEmpty($execution['body']['$id']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);
        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.executions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.executions.*.create', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.executions.{$executionId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.executions.{$executionId}.create", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.*", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.*.create", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.{$executionId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.{$executionId}.create", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        // wait for timeout function to complete
        sleep(10);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.executions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.executions.*.update', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.executions.{$executionId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.executions.{$executionId}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.*", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.*.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.{$executionId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.executions.{$executionId}.update", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        /**
         * Test for FAILURE
         */

        return $data;
    }

    /**
     * @depends testExecutions
     */
    public function testDeleteDeployment($data): array
    {
        $id = $data['functionId'] ?? '';
        $deploymentId = $data['deploymentId'] ?? '';
        /**
         * Test for SUCCESS
         */
        $deployment = $this->client->call(Client::METHOD_DELETE, '/functions/' . $id . '/deployments/' . $deploymentId, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $this->assertEquals(204, $deployment['headers']['status-code']);
        $this->assertEmpty($deployment['body']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.deployments.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.deployments.*.delete', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.deployments.{$deploymentId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.*.deployments.{$deploymentId}.delete", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.*", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.*.delete", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.{$deploymentId}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.deployments.{$deploymentId}.delete", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        /**
         * Test for FAILURE
         */

        return $data;
    }

    /**
     * @depends testDeleteDeployment
     */
    public function testDeleteFunction($data): array
    {
        $id = $data['functionId'];

        /**
         * Test for SUCCESS
         */
        $function = $this->client->call(Client::METHOD_DELETE, '/functions/' . $id, array_merge([
            'content-type' => 'application/json',
            'x-appwrite-project' => $this->getProject()['$id'],
        ], $this->getHeaders()));

        $this->assertEquals(204, $function['headers']['status-code']);
        $this->assertEmpty($function['body']);

        $webhook = $this->getLastRequest();
        $signatureExpected = self::getWebhookSignature($webhook, $this->getProject()['signatureKey']);

        $this->assertEquals('POST', $webhook['method']);
        $this->assertEquals('application/json', $webhook['headers']['Content-Type']);
        $this->assertEquals('Appwrite-Server vdev. Please report abuse at security@appwrite.io', $webhook['headers']['User-Agent']);
        $this->assertStringContainsString('functions.*', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString('functions.*.delete', $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertStringContainsString("functions.{$id}.delete", $webhook['headers']['X-Appwrite-Webhook-Events']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Signature'], $signatureExpected);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Id'] ?? '', $this->getProject()['webhookId']);
        $this->assertEquals($webhook['headers']['X-Appwrite-Webhook-Project-Id'] ?? '', $this->getProject()['$id']);

        /**
         * Test for FAILURE
         */

        return $data;
    }
}
