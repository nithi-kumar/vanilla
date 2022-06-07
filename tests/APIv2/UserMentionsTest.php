<?php
/**
 * @author Olivier Lamy-Canuel <olamy-canuel@higherlogic.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace VanillaTests\APIv2;

use Garden\Web\Exception\ForbiddenException;
use Vanilla\Dashboard\Models\UserMentionsModel;
use VanillaTests\Forum\Utils\CommunityApiTestTrait;
use VanillaTests\SchedulerTestTrait;
use VanillaTests\SiteTestCase;
use VanillaTests\UsersAndRolesApiTestTrait;

/**
 * Tests for the `/apiv/v2/user-mentions` endpoint.
 */
class UserMentionsTest extends SiteTestCase
{
    use UsersAndRolesApiTestTrait;
    use CommunityApiTestTrait;
    use SchedulerTestTrait;

    /** @var UserMentionsModel */
    private $userMentionModel;

    public $baseUrl = "/user-mentions";

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->userMentionModel = $this->container()->get(UserMentionsModel::class);
    }

    /**
     * Test for [GET] `/api/v2/user-mentions/{userID}/user`.
     */
    public function testUserMentionsGetUserSuccess()
    {
        $user = $this->createUser(["name" => "user" . __FUNCTION__]);
        $discussion = $this->createDiscussion(["body" => "test @\"{$user["name"]}\""]);
        $expect = [
            "userID" => $user["userID"],
            "recordType" => "discussion",
            "recordID" => $discussion["discussionID"],
            "mentionedName" => $user["name"],
            "parentRecordType" => "category",
            "parentRecordID" => -1,
            "dateInserted" => $discussion["dateInserted"],
            "status" => "active",
        ];

        $result = $this->api()
            ->get($this->baseUrl . "/users/{$user["userID"]}")
            ->getBody();
        $this->assertEquals($expect, $result[0]);
    }

    /**
     * Test that members can't fetch user mentions.
     */
    public function testUserMentionsGetUserPermissionFail()
    {
        $user = $this->createUser(["roleID" => [\RoleModel::MEMBER_ID]]);
        $this->expectException(ForbiddenException::class);

        $this->runWithUser(function () {
            $this->api()->get($this->baseUrl . "/users/1");
        }, $user);
    }

    /**
     * Test that users mentions are properly indexed.
     */
    public function testUserMentionsIndexing()
    {
        $user = $this->createUser(["name" => "user" . __FUNCTION__]);
        $this->createDiscussion(["body" => "test @\"{$user["name"]}\""]);
        $this->createDiscussion(["body" => "test @\"{$user["name"]}\""]);
        $this->createComment(["body" => "test @\"{$user["name"]}\""]);
        $this->createComment(["body" => "test @\"{$user["name"]}\""]);
        $this->resetTable("userMention");

        $response = $this->api()->post($this->baseUrl . "/indexer-start", ["recordType" => "all"]);
        $this->assertEquals(201, $response->getStatusCode());
        $result = $this->userMentionModel->getByUser($user["userID"]);
        $this->assertEquals(4, count($result));
    }

    /**
     * Test that the user mentions indexing can be resumed with the long runner.
     */
    public function testLongRunnerContinue()
    {
        $user = $this->createUser(["name" => "user" . __FUNCTION__]);
        $this->resetTable("Discussion");
        $this->createDiscussion(["body" => "test @\"{$user["name"]}\""]);
        $this->createDiscussion(["body" => "test @\"{$user["name"]}\""]);
        $this->createComment(["body" => "test @\"{$user["name"]}\""]);
        $this->createComment(["body" => "test @\"{$user["name"]}\""]);
        $this->resetTable("userMention");

        $this->getLongRunner()->setMaxIterations(1);
        $response = $this->api()->post(
            $this->baseUrl . "/indexer-start",
            ["recordType" => "all"],
            [],
            ["throw" => false]
        );
        $this->assertNotNull($response["callbackPayload"]);
        $result = $this->userMentionModel->getByUser($user["userID"]);
        $this->assertEquals(1, count($result));

        // Resume and finish.
        $this->getLongRunner()->setMaxIterations(100);
        $response = $this->resumeLongRunner($response["callbackPayload"]);
        $this->assertEquals(200, $response->getStatusCode());
        $result = $this->userMentionModel->getByUser($user["userID"]);
        $this->assertEquals(4, count($result));
    }
}