<?php
require __DIR__ . '/vendor/simpletest/simpletest/autorun.php';
require_once( __DIR__ . '/../KnownUser.php');
require_once( __DIR__ . '/../UserInQueueService.php');
require_once( __DIR__ . '/../Models.php');

error_reporting(E_ALL);

class CookieManagerMock implements QueueIT\KnownUserV3\SDK\ICookieManager
{
    public $debugInfoCookie;
    public $cookieArray;
    
    public function getCookie($cookieName) {
        return $this->debugInfoCookie;
    }

    public function setCookie($name, $value, $expire, $domain) {
        if ($domain == NULL) {
            $domain = "";
        }
        $this->debugInfoCookie = $value;
    }

    function getCookieArray()
    {
        return $this->cookieArray;
    }
}
class HttpRequestProviderMock implements QueueIT\KnownUserV3\SDK\IHttpRequestProvider
{
    public $userAgent;
	public $userHostAddress;
    public $cookieManager;
    public $absoluteUri;
	public $headerArray;

    public function getUserAgent() {
        return $this->userAgent;
    }
	public function getUserHostAddress() {
		return $this->userHostAddress;
	}
    public function getCookieManager() {
        return $this->cookieManager;
    }
    public function getAbsoluteUri() {
        return $this->absoluteUri;
    }
    public function getHeaderArray() {
        if($this->headerArray==NULL)
            return array();
        return $this->headerArray;
    }
}

class UserInQueueServiceMock implements QueueIT\KnownUserV3\SDK\IUserInQueueService {

    public $arrayFunctionCallsArgs;
    public $arrayReturns;
    public $validateCancelRequestResult;


    function __construct() {
        $this->arrayFunctionCallsArgs = array(
            'validateRequest' => array(),
            'extendQueueCookie' => array(),
            'validateCancelRequest' => array(),
            'getIgnoreActionResult'=>array()
        );

        $this->arrayReturns = array(
            'validateRequest' => array(),
            'validateCancelRequest' => array(),
            'extendQueueCookie' => array()
        );
    }

    public function validateQueueRequest(
    $currentPageUrl, $queueitToken, QueueIT\KnownUserV3\SDK\QueueEventConfig $config, $customerId, $secretKey) {
        array_push($this->arrayFunctionCallsArgs['validateRequest'], array(
            $currentPageUrl,
            $queueitToken,
            $config,
            $customerId,
            $secretKey));
    }

    public function validateCancelRequest(
        $currentPageUrl,QueueIT\KnownUserV3\SDK\CancelEventConfig $config, $customerId, $secretKey) {
            array_push($this->arrayFunctionCallsArgs['validateCancelRequest'], array(
                $currentPageUrl,
                $config,
                $customerId,
                $secretKey));
                return $this->validateCancelRequestResult;
        }

    public function getIgnoreActionResult() {
            array_push($this->arrayFunctionCallsArgs['getIgnoreActionResult'], "call");
            return new QueueIT\KnownUserV3\SDK\RequestValidationResult( QueueIT\KnownUserV3\SDK\ActionTypes::IgnoreAction,NULL,NULL,NULL);;
        }

    public function extendQueueCookie(
    $eventId, $cookieValidityMinute, $cookieDomain, $secretKey
    ) {
        array_push($this->arrayFunctionCallsArgs['extendQueueCookie'], array(
            $eventId,
            $cookieValidityMinute,
            $cookieDomain,
            $secretKey));
    }

    public function expectCall($functionName, $secquenceNo, array $argument) {
        if (count($this->arrayFunctionCallsArgs[$functionName]) >= $secquenceNo) {

            $argArr = $this->arrayFunctionCallsArgs[$functionName][$secquenceNo - 1];
            if (count($argument) != count($argArr)) {
                return false;
            }

            for ($i = 0; $i <= count($argArr) - 1; ++$i) {
                if ($argArr[$i] !== $argument[$i]) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    public function expectCallAny($functionName) {
        if (count($this->arrayFunctionCallsArgs[$functionName]) >= 1) {
            return true;
        }
        return false;
    }
}

class KnownUserTest extends UnitTestCase {

    function test_cancelRequestByLocalConfig() {
        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);
        
        $cancelEventconfig = new \QueueIT\KnownUserV3\SDK\CancelEventConfig();
        $cancelEventconfig->cookieDomain = "cookiedomain";
        $cancelEventconfig->eventId = "eventid";
        $cancelEventconfig->queueDomain = "queuedomain";
        $cancelEventconfig->version = 1;

        QueueIT\KnownUserV3\SDK\KnownUser::cancelRequestByLocalConfig("url","queueittoken" ,$cancelEventconfig,"customerid","secretkey");

        $this->assertTrue("url"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][0]);
        $this->assertTrue("customerid"== $userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][2]);
        $this->assertTrue("secretkey"== $userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][3]);
        $this->assertTrue("eventid"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->eventId);
        $this->assertTrue("queuedomain"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->queueDomain);
        $this->assertTrue("cookiedomain"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->cookieDomain);
        $this->assertTrue("1"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->version);
    }
    function test_cancelRequestByLocalConfig_empty_eventId() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\CancelEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->version = 12;


        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::cancelRequestByLocalConfig("targeturl", "queueittoken",$eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "eventId from cancelConfig can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_cancelRequestByLocalConfig_empty_secreteKey() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\CancelEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::cancelRequestByLocalConfig("targeturl", "queueittoken", $eventconfig, "customerid", NULL);
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "secretKey can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_cancelRequestByLocalConfig_empty_queueDomain() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\CancelEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->eventId = "eventId";
        //$eventconfig->queueDomain = "queueDomain";
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::cancelRequestByLocalConfig("targeturl", "queueittoken", $eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "queueDomain from cancelConfig can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_cancelRequestByLocalConfig_empty_customerId() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\CancelEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::cancelRequestByLocalConfig("targeturl", "queueittoken",  $eventconfig, NULL, "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "customerId can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }

    function test_cancelRequestByLocalConfig_empty_targeturl() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\CancelEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->version = 12;
        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::cancelRequestByLocalConfig(NULL, "queueittoken",  $eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "targetUrl can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    
    function test_extendQueueCookie_null_EventId() {
        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::extendQueueCookie(NULL, 10, "cookieDomain", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "eventId can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }

    function test_extendQueueCookie_null_SecretKey() {
        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::extendQueueCookie("event1", 10, "cookieDomain", NULL);
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "secretKey can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }

    function test_extendQueueCookie_Invalid_CookieValidityMinute() {
        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::extendQueueCookie("event1", "invalidInt", "cookieDomain", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "cookieValidityMinute should be integer greater than 0.";
        }
        $this->assertTrue($exceptionThrown);
    }

    function test_extendQueueCookie_Negative_CookieValidityMinute() {
        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::extendQueueCookie("event1", -1, "cookieDomain", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "cookieValidityMinute should be integer greater than 0.";
        }
        $this->assertTrue($exceptionThrown);
    }

    function test_extendQueueCookie() {
        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);

        QueueIT\KnownUserV3\SDK\KnownUser::extendQueueCookie("eventid", 10, "cookieDomain", "secretkey");

        $this->assertTrue($userInQueueservice->expectCall('extendQueueCookie', 1, array("eventid", 10, "cookieDomain", "secretkey")));
    }

    function test_resolveRequestByLocalEventConfig_empty_eventId() {

        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        //$eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = true;
        $eventconfig->cookieValidityMinute = 10;
        $eventconfig->version = 12;


        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "eventId from queueConfig can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_resolveRequestByLocalEventConfig_empty_secreteKey() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = true;
        $eventconfig->cookieValidityMinute = 10;
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, "customerid", NULL);
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "secretKey can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_resolveRequestByLocalEventConfig_empty_queueDomain() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        $eventconfig->eventId = "eventId";
        //$eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = true;
        $eventconfig->cookieValidityMinute = 10;
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "queueDomain from queueConfig can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_resolveRequestByLocalEventConfig_empty_customerId() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        //$eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = true;
        $eventconfig->cookieValidityMinute = 10;
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, NULL, "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "customerId can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_resolveRequestByLocalEventConfig_Invalid_extendCookieValidity() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = NULL;
        $eventconfig->cookieValidityMinute = 10;
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "extendCookieValidity from queueConfig should be valid boolean.";
        }
        $this->assertTrue($exceptionThrown);
    }  
    function test_resolveRequestByLocalEventConfig_Invalid_cookieValidityMinute() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = TRUE;
        $eventconfig->cookieValidityMinute = "test";
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "cookieValidityMinute from queueConfig should be integer greater than 0.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_resolveRequestByLocalEventConfig_zero_cookieValidityMinute() {
        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = TRUE;
        $eventconfig->cookieValidityMinute = 0;
        $eventconfig->version = 12;

        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, "customerid", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "cookieValidityMinute from queueConfig should be integer greater than 0.";
        }
        $this->assertTrue($exceptionThrown);
    }
    function test_resolveRequestByLocalEventConfig() {
        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);
        $eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
        $eventconfig->cookieDomain = "cookieDomain";
        $eventconfig->layoutName = "layoutName";
        $eventconfig->culture = "culture";
        $eventconfig->eventId = "eventId";
        $eventconfig->queueDomain = "queueDomain";
        $eventconfig->extendCookieValidity = true;
        $eventconfig->cookieValidityMinute = 10;
        $eventconfig->version = 12;

        QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", "queueIttoken", $eventconfig, "customerid", "secretkey");

        $this->assertTrue($userInQueueservice->expectCall('validateRequest', 1, array("targeturl", "queueIttoken", $eventconfig, "customerid", "secretkey")));
    }

    function test_validateRequestByIntegrationConfig_empty_currentUrl() {
        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("", "queueIttoken", "{}","customerId", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "currentUrlWithoutQueueITToken can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }
     function test_validateRequestByIntegrationConfig_empty_integrationsConfigString() {
        $exceptionThrown = false;
        try {
            QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("currentUrl", "queueIttoken",Null,"customerId", "secretkey");
        } catch (Exception $e) {
            $exceptionThrown = $e->getMessage() == "integrationsConfigString can not be null or empty.";
        }
        $this->assertTrue($exceptionThrown);
    }

  
    function test_validateRequestByIntegrationConfig() {
        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);

        $httpRequestProvider = new HttpRequestProviderMock();
        $httpRequestProvider->userAgent="googlebot";
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'httpRequestProvider');
        $r->setAccessible(true);
        $r->setValue(null, $httpRequestProvider);

        $integrationConfigString = <<<EOT
            {
              "Description": "test",
              "Integrations": [
                {
                  "Name": "event1action",
                  "ActionType": "Queue",
                  "EventId": "event1",
                  "CookieDomain": ".test.com",
                  "LayoutName": "Christmas Layout by Queue-it",
                  "Culture": "",
                  "ExtendCookieValidity": true,
                  "CookieValidityMinute": 20,
                  "Triggers": [
                    {
                      "TriggerParts": [
                        {
							"Operator": "Contains",
							"ValueToCompare": "event1",
							"UrlPart": "PageUrl",
							"ValidatorType": "UrlValidator",
							"IsNegative": false,
							"IsIgnoreCase": true
                        },
                        {
							"Operator": "Contains",
							"ValueToCompare": "googlebot",
							"ValidatorType": "UserAgentValidator",
							"IsNegative": false,
							"IsIgnoreCase": false
                        }
                      ],
                      "LogicalOperator": "And"
                    }
                  ],
                  "QueueDomain": "knownusertest.queue-it.net",
                  "RedirectLogic": "AllowTParameter",
                  "ForcedTargetUrl": ""
                }
              ],
              "CustomerId": "knownusertest",
              "AccountId": "knownusertest",
              "Version": 3,
              "PublishDate": "2017-05-15T21:39:12.0076806Z",
              "ConfigDataVersion": "1.0.0.1"
            }
EOT;

        QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true", "queueIttoken", $integrationConfigString, "customerid", "secretkey");
        $this->assertTrue(count($userInQueueservice->arrayFunctionCallsArgs['validateRequest']) == 1);
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][0] == "http://test.com?event1=true");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][1] == "queueIttoken");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][3] == "customerid");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][4] == "secretkey");

        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->queueDomain == "knownusertest.queue-it.net");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->eventId == "event1");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->culture == "");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->layoutName == "Christmas Layout by Queue-it");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->extendCookieValidity);
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->cookieValidityMinute == 20);
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->cookieDomain == ".test.com");
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][2]->version == 3);
    }

    function test_validateRequestByIntegrationConfig_NotMatch() 
	{
        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);
        $integrationConfigString = <<<EOT
        {
          "Description": "test",
          "Integrations": [
          ],
          "CustomerId": "knownusertest",
          "AccountId": "knownusertest",
          "Version": 3,
          "PublishDate": "2017-05-15T21:39:12.0076806Z",
          "ConfigDataVersion": "1.0.0.1"
        }
EOT;

        $result = QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true", "queueIttoken", $integrationConfigString, "customerid", "secretkey");
        $this->assertTrue(count($userInQueueservice->arrayFunctionCallsArgs['validateRequest']) == 0);
        $this->assertFalse($result->doRedirect());
    }

    function test_validateRequestByIntegrationConfig_ForcedTargeturl() 
	{
        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);

        $integrationConfigString = <<<EOT
            {
              "Description": "test",
              "Integrations": [
                {
                  "Name": "event1action",
                  "ActionType": "Queue",
                  "EventId": "event1",
                  "CookieDomain": ".test.com",
                  "LayoutName": "Christmas Layout by Queue-it",
                  "Culture": "",
                  "ExtendCookieValidity": true,
                  "CookieValidityMinute": 20,
                  "Triggers": [
                    {
                      "TriggerParts": [
                        {
                          "Operator": "Contains",
                          "ValueToCompare": "event1",
                          "UrlPart": "PageUrl",
                          "ValidatorType": "UrlValidator",
                          "IsNegative": false,
                          "IsIgnoreCase": true
                        }
                      ],
                      "LogicalOperator": "And"
                    }
                  ],
                  "QueueDomain": "knownusertest.queue-it.net",
                  "RedirectLogic": "ForcedTargetUrl",
                  "ForcedTargetUrl": "http://test.com"
                }
              ],
              "CustomerId": "knownusertest",
              "AccountId": "knownusertest",
              "Version": 3,
              "PublishDate": "2017-05-15T21:39:12.0076806Z",
              "ConfigDataVersion": "1.0.0.1"
            }
EOT;

        QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true", "queueIttoken", $integrationConfigString, "customerid", "secretkey");

        $this->assertTrue(count($userInQueueservice->arrayFunctionCallsArgs['validateRequest']) == 1);
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][0] == "http://test.com");        
    }

    function test_validateRequestByIntegrationConfig_ForecedTargeturl() 
	{
        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);

        $integrationConfigString = <<<EOT
            {
              "Description": "test",
              "Integrations": [
                {
                  "Name": "event1action",
                  "ActionType": "Queue",
                  "EventId": "event1",
                  "CookieDomain": ".test.com",
                  "LayoutName": "Christmas Layout by Queue-it",
                  "Culture": "",
                  "ExtendCookieValidity": true,
                  "CookieValidityMinute": 20,
                  "Triggers": [
                    {
                      "TriggerParts": [
                        {
                          "Operator": "Contains",
                          "ValueToCompare": "event1",
                          "UrlPart": "PageUrl",
                          "ValidatorType": "UrlValidator",
                          "IsNegative": false,
                          "IsIgnoreCase": true
                        }
                      ],
                      "LogicalOperator": "And"
                    }
                  ],
                  "QueueDomain": "knownusertest.queue-it.net",
                  "RedirectLogic": "ForecedTargetUrl",
                  "ForcedTargetUrl": "http://test.com"
                }
              ],
              "CustomerId": "knownusertest",
              "AccountId": "knownusertest",
              "Version": 3,
              "PublishDate": "2017-05-15T21:39:12.0076806Z",
              "ConfigDataVersion": "1.0.0.1"
            }
EOT;

        QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true", "queueIttoken", $integrationConfigString, "customerid", "secretkey");

        $this->assertTrue(count($userInQueueservice->arrayFunctionCallsArgs['validateRequest']) == 1);
        $this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][0] == "http://test.com");
    }

    function test_validateRequestByIntegrationConfig_EventTargetUrl() {

        $userInQueueservice = new UserInQueueServiceMock();
        $r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
        $r->setAccessible(true);
        $r->setValue(null, $userInQueueservice);

        $var = "some text";
        $integrationConfigString = <<<EOT
            {
              "Description": "test",
              "Integrations": [
                {
                  "Name": "event1action",
                  "ActionType": "Queue",
                  "EventId": "event1",
                  "CookieDomain": ".test.com",
                  "LayoutName": "Christmas Layout by Queue-it",
                  "Culture": "",
                  "ExtendCookieValidity": true,
                  "CookieValidityMinute": 20,
                  "Triggers": [
                    {
                      "TriggerParts": [
                        {
                          "Operator": "Contains",
                          "ValueToCompare": "event1",
                          "UrlPart": "PageUrl",
                          "ValidatorType": "UrlValidator",
                          "IsNegative": false,
                          "IsIgnoreCase": true
                        }
                      ],
                      "LogicalOperator": "And"
                    }
                  ],
                  "QueueDomain": "knownusertest.queue-it.net",
                  "RedirectLogic": "EventTargetUrl"
                }
              ],
              "CustomerId": "knownusertest",
              "AccountId": "knownusertest",
              "Version": 3,
              "PublishDate": "2017-05-15T21:39:12.0076806Z",
              "ConfigDataVersion": "1.0.0.1"
            }
EOT;

		QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true", "queueIttoken", $integrationConfigString, "customerid", "secretkey");
		$this->assertTrue(count($userInQueueservice->arrayFunctionCallsArgs['validateRequest']) == 1);
		$this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateRequest'][0][0] == "");
	}

    function test_validateRequestByIntegrationConfig_CancelAction() 
    {
		$userInQueueservice = new UserInQueueServiceMock();
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
		$r->setAccessible(true);
		$r->setValue(null, $userInQueueservice);
		$userInQueueservice->validateCancelRequestResult =  new QueueIT\KnownUserV3\SDK\RequestValidationResult("Cancel","eventid","queueid","redirectUrl");

		$var = "some text";
		$integrationConfigString = <<<EOT
			{
				"Description": "test",
				"Integrations": [
				{
					"Name": "event1action",
					"EventId": "event1",
					"CookieDomain": ".test.com",
					"ActionType":"Cancel",
					"Triggers": [
					{
						"TriggerParts": [
						{
							"Operator": "Contains",
							"ValueToCompare": "event1",
							"UrlPart": "PageUrl",
							"ValidatorType": "UrlValidator",
							"IsNegative": false,
							"IsIgnoreCase": true
						}
						],
						"LogicalOperator": "And"
					}
					],
					"QueueDomain": "knownusertest.queue-it.net"
				}
				],
				"CustomerId": "knownusertest",
				"AccountId": "knownusertest",
				"Version": 3,
				"PublishDate": "2017-05-15T21:39:12.0076806Z",
				"ConfigDataVersion": "1.0.0.1"
			}
EOT;

		$result = QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true", "queueIttoken", $integrationConfigString, "customerid", "secretkey");
		$this->assertTrue($result->redirectUrl =="redirectUrl");
		$this->assertTrue(count($userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest']) == 1);
		$this->assertTrue($userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][0] == "http://test.com?event1=true");
		$this->assertTrue("customerid"== $userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][2]);
		$this->assertTrue("secretkey"== $userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][3]);
		$this->assertTrue("event1"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->eventId);
		$this->assertTrue("knownusertest.queue-it.net"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->queueDomain);
		$this->assertTrue(".test.com"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->cookieDomain);
		$this->assertTrue("3"==$userInQueueservice->arrayFunctionCallsArgs['validateCancelRequest'][0][1]->version);
	}

    function test_validateRequestByIntegrationConfig_IgnoreAction() 
    {
		$userInQueueservice = new UserInQueueServiceMock();
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
		$r->setAccessible(true);
		$r->setValue(null, $userInQueueservice);


		$var = "some text";
		$integrationConfigString = <<<EOT
			{
				"Description": "test",
				"Integrations": [
				{
					"Name": "event1action",
					"EventId": "event1",
					"CookieDomain": ".test.com",
					"ActionType":"Ignore",
					"Triggers": [
					{
						"TriggerParts": [
						{
							"Operator": "Contains",
							"ValueToCompare": "event1",
							"UrlPart": "PageUrl",
							"ValidatorType": "UrlValidator",
							"IsNegative": false,
							"IsIgnoreCase": true
						}
						],
						"LogicalOperator": "And"
					}
					],
					"QueueDomain": "knownusertest.queue-it.net"
				}
				],
				"CustomerId": "knownusertest",
				"AccountId": "knownusertest",
				"Version": 3,
				"PublishDate": "2017-05-15T21:39:12.0076806Z",
				"ConfigDataVersion": "1.0.0.1"
			}
EOT;

		$result = QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true", "queueIttoken", $integrationConfigString, "customerid", "secretkey");
		$this->assertTrue($result->actionType =="Ignore");
		$this->assertTrue(count($userInQueueservice->arrayFunctionCallsArgs['getIgnoreActionResult']) == 1);
    }
    
	function test_validateRequestByIntegrationConfig_debug() 
	{
		$userInQueueservice = new UserInQueueServiceMock();
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
		$r->setAccessible(true);
		$r->setValue(null, $userInQueueservice);

		$httpRequestProvider = new HttpRequestProviderMock();
		$httpRequestProvider->cookieManager = new CookieManagerMock();
		$httpRequestProvider->userHostAddress ="userIP";
		$httpRequestProvider->headerArray = array(
			"via" => "v", 
			"forwarded" => "f", 
			"x-forwarded-for" => "xff", 
			"x-forwarded-host" => "xfh", 
			"x-forwarded-proto" => "xfp");
		$httpRequestProvider->absoluteUri="OriginalURL";
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'httpRequestProvider');
		$r->setAccessible(true);
		$r->setValue(null, $httpRequestProvider);
		$userInQueueservice->validateCancelRequestResult =  new QueueIT\KnownUserV3\SDK\RequestValidationResult("Cancel","eventid","redirectUrl","queueid");

		$var = "some text";
		$integrationConfigString = <<<EOT
			{
				"Description": "test",
				"Integrations": [
				{
					"Name": "event1action",
					"EventId": "event1",
					"CookieDomain": ".test.com",
					"ActionType":"Cancel",
					"Triggers": [
					{
						"TriggerParts": [
						{
							"Operator": "Contains",
							"ValueToCompare": "event1",
							"UrlPart": "PageUrl",
							"ValidatorType": "UrlValidator",
							"IsNegative": false,
							"IsIgnoreCase": true
						}
						],  
						"LogicalOperator": "And"
					}
					],
					"QueueDomain": "knownusertest.queue-it.net"
				}
				],
				"CustomerId": "knownusertest",
				"AccountId": "knownusertest",
				"Version": 3,
				"PublishDate": "2017-05-15T21:39:12.0076806Z",
				"ConfigDataVersion": "1.0.0.1"
			}
EOT;
		$token = $this->generateHashDebugValidHash("secretkey");
		$timestamp = gmdate("Y-m-d\TH:i:s\Z");
		$result = QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true&queueittoken=". $this->generateHashDebugValidHash("secretkey"), 
						$token , $integrationConfigString, "customerid", "secretkey");
               
		$expectedCookie= 
		"TargetUrl=http://test.com?event1=true&queueittoken=e_eventId~rt_debug~h_0aa4b0e41d4cceae77d8fa63890a778f2b5c9cf962239f2862150517844bc0ce".
		"|QueueitToken=e_eventId~rt_debug~h_0aa4b0e41d4cceae77d8fa63890a778f2b5c9cf962239f2862150517844bc0ce".
		"|CancelConfig=EventId:event1&Version:3&QueueDomain:knownusertest.queue-it.net&CookieDomain:.test.com".
		"|OriginalUrl=OriginalURL".
		"|ServerUtcTime=".$timestamp.
		"|RequestIP=userIP".
		"|RequestHttpHeader_Via=v".
		"|RequestHttpHeader_Forwarded=f".
		"|RequestHttpHeader_XForwardedFor=xff".
		"|RequestHttpHeader_XForwardedHost=xfh".
		"|RequestHttpHeader_XForwardedProto=xfp".
		"|MatchedConfig=event1action".
		"|ConfigVersion=3".
		"|PureUrl=http://test.com?event1=true&queueittoken=e_eventId~rt_debug~h_0aa4b0e41d4cceae77d8fa63890a778f2b5c9cf962239f2862150517844bc0ce";
		
		$this->assertTrue($httpRequestProvider->cookieManager->debugInfoCookie ==$expectedCookie );
	}


	function test_validateRequestByIntegrationConfig_withoutmatch_debug() 
	{

		$userInQueueservice = new UserInQueueServiceMock();
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
		$r->setAccessible(true);
		$r->setValue(null, $userInQueueservice);

		$httpRequestProvider = new HttpRequestProviderMock();
		$httpRequestProvider->cookieManager = new CookieManagerMock();
		$httpRequestProvider->absoluteUri="OriginalURL";
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'httpRequestProvider');
		$r->setAccessible(true);
		$r->setValue(null, $httpRequestProvider);

		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'debugInfoArray');
		$r->setAccessible(true);
		$r->setValue(null, NULL);

		$userInQueueservice->validateCancelRequestResult =  new QueueIT\KnownUserV3\SDK\RequestValidationResult("Cancel","eventid","redirectUrl","queueid");

	$integrationConfigString = <<<EOT
		{
			"Description": "test",
			"Integrations": [
			{
				"Name": "event1action",
				"EventId": "event1",
				"CookieDomain": ".test.com",
				"ActionType":"Cancel",
				"Triggers": [
				{
					"TriggerParts": [
					{
						"Operator": "Contains",
						"ValueToCompare": "notmatch",
						"UrlPart": "PageUrl",
						"ValidatorType": "UrlValidator",
						"IsNegative": false,
						"IsIgnoreCase": true
					}
					],  
					"LogicalOperator": "And"
				}
				],
				"QueueDomain": "knownusertest.queue-it.net"
			}
			],
			"CustomerId": "knownusertest",
			"AccountId": "knownusertest",
			"Version": 3,
			"PublishDate": "2017-05-15T21:39:12.0076806Z",
			"ConfigDataVersion": "1.0.0.1"
		}
EOT;
	
		$token = $this->generateHashDebugValidHash("secretkey");
		$timestamp = gmdate("Y-m-d\TH:i:s\Z");
		$result = QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true&queueittoken=". $this->generateHashDebugValidHash("secretkey"), 
						$token , $integrationConfigString, "customerid", "secretkey");

		$expectedCookie= 
		"MatchedConfig=NULL".
		"|ConfigVersion=3".
		"|QueueitToken=e_eventId~rt_debug~h_0aa4b0e41d4cceae77d8fa63890a778f2b5c9cf962239f2862150517844bc0ce".
		"|PureUrl=http://test.com?event1=true&queueittoken=e_eventId~rt_debug~h_0aa4b0e41d4cceae77d8fa63890a778f2b5c9cf962239f2862150517844bc0ce".
		"|OriginalUrl=OriginalURL".
		"|ServerUtcTime=".$timestamp.
		"|RequestIP=".
		"|RequestHttpHeader_Via=".
		"|RequestHttpHeader_Forwarded=".
		"|RequestHttpHeader_XForwardedFor=".
		"|RequestHttpHeader_XForwardedHost=".
		"|RequestHttpHeader_XForwardedProto=";

		$this->assertTrue($httpRequestProvider->cookieManager->debugInfoCookie ==$expectedCookie );
	}

	function test_validateRequestByIntegrationConfig_notvalidhash_debug() 
	{
		$userInQueueservice = new UserInQueueServiceMock();
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
		$r->setAccessible(true);
		$r->setValue(null, $userInQueueservice);

		$httpRequestProvider = new HttpRequestProviderMock();
		$httpRequestProvider->cookieManager = new CookieManagerMock();
		$httpRequestProvider->absoluteUri="OriginalURL";
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'httpRequestProvider');
		$r->setAccessible(true);
		$r->setValue(null, $httpRequestProvider);

		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'debugInfoArray');
		$r->setAccessible(true);
		$r->setValue(null, NULL);

		$userInQueueservice->validateCancelRequestResult =  new QueueIT\KnownUserV3\SDK\RequestValidationResult("Cancel","eventid","redirectUrl","queueid");

	$integrationConfigString = <<<EOT
		{
			"Description": "test",
			"Integrations": [
			{
				"Name": "event1action",
				"EventId": "event1",
				"CookieDomain": ".test.com",
				"ActionType":"Cancel",
				"Triggers": [
				{
					"TriggerParts": [
					{
						"Operator": "Contains",
						"ValueToCompare": "notmatch",
						"UrlPart": "PageUrl",
						"ValidatorType": "UrlValidator",
						"IsNegative": false,
						"IsIgnoreCase": true
					}
					],  
					"LogicalOperator": "And"
				}
				],
				"QueueDomain": "knownusertest.queue-it.net"
			}
			],
			"CustomerId": "knownusertest",
			"AccountId": "knownusertest",
			"Version": 3,
			"PublishDate": "2017-05-15T21:39:12.0076806Z",
			"ConfigDataVersion": "1.0.0.1"
	    }
EOT;
		$token = $this->generateHashDebugValidHash("secretkey");
		$result = QueueIT\KnownUserV3\SDK\KnownUser::validateRequestByIntegrationConfig("http://test.com?event1=true&queueittoken=". $this->generateHashDebugValidHash("secretkey"), 
						$token."ss" , $integrationConfigString, "customerid", "secretkey");


		$this->assertTrue($httpRequestProvider->cookieManager->debugInfoCookie ==Null );
	}

	function test_resolveRequestByLocalEventConfig_debug() {
		$userInQueueservice = new UserInQueueServiceMock();
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
		$r->setAccessible(true);
		$r->setValue(null, $userInQueueservice);
		$httpRequestProvider = new HttpRequestProviderMock();
		$httpRequestProvider->userHostAddress ="userIP";
		$httpRequestProvider->headerArray = array(
			"via" => "v", 
			"forwarded" => "f", 
			"x-forwarded-for" => "xff", 
			"x-forwarded-host" => "xfh", 
			"x-forwarded-proto" => "xfp");
		$httpRequestProvider->cookieManager = new CookieManagerMock();
		$httpRequestProvider->absoluteUri="OriginalURL";
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'httpRequestProvider');
		$r->setAccessible(true);
		$r->setValue(null, $httpRequestProvider);

		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'debugInfoArray');
		$r->setAccessible(true);
		$r->setValue(null, NULL);

		$eventconfig = new \QueueIT\KnownUserV3\SDK\QueueEventConfig();
		$eventconfig->cookieDomain = "cookieDomain";
		$eventconfig->layoutName = "layoutName";
		$eventconfig->culture = "culture";
		$eventconfig->eventId = "eventId";
		$eventconfig->queueDomain = "queueDomain";
		$eventconfig->extendCookieValidity = true;
		$eventconfig->cookieValidityMinute = 10;
		$eventconfig->version = 12;

		$token = $this->generateHashDebugValidHash("secretkey");
		$timestamp = gmdate("Y-m-d\TH:i:s\Z");
		QueueIT\KnownUserV3\SDK\KnownUser::resolveRequestByLocalEventConfig("targeturl", $token, $eventconfig, "customerid", "secretkey");

		$expectedCookie= 
		"TargetUrl=targeturl".
		"|QueueitToken=e_eventId~rt_debug~h_0aa4b0e41d4cceae77d8fa63890a778f2b5c9cf962239f2862150517844bc0ce".
		"|QueueConfig=EventId:eventId&Version:12&QueueDomain:queueDomain&CookieDomain:cookieDomain&ExtendCookieValidity:1&CookieValidityMinute:10&LayoutName:layoutName&Culture:culture".
		"|OriginalUrl=OriginalURL".
		"|ServerUtcTime=".$timestamp.
		"|RequestIP=userIP".
		"|RequestHttpHeader_Via=v".
		"|RequestHttpHeader_Forwarded=f".
		"|RequestHttpHeader_XForwardedFor=xff".
		"|RequestHttpHeader_XForwardedHost=xfh".
		"|RequestHttpHeader_XForwardedProto=xfp";
		
		$this->assertTrue($httpRequestProvider->cookieManager->debugInfoCookie == $expectedCookie );
	}

	function test_cancelRequestByLocalConfig_debug() {
		$userInQueueservice = new UserInQueueServiceMock();
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'userInQueueService');
		$r->setAccessible(true);
		$r->setValue(null, $userInQueueservice);
		$httpRequestProvider = new HttpRequestProviderMock();
		$httpRequestProvider->userHostAddress ="userIP";
		$httpRequestProvider->headerArray = array(
			"via" => "v", 
			"forwarded" => "f", 
			"x-forwarded-for" => "xff", 
			"x-forwarded-host" => "xfh", 
			"x-forwarded-proto" => "xfp");
		$httpRequestProvider->cookieManager = new CookieManagerMock();
		$httpRequestProvider->absoluteUri="OriginalURL";
		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'httpRequestProvider');
		$r->setAccessible(true);
		$r->setValue(null, $httpRequestProvider);

		$r = new ReflectionProperty('QueueIT\KnownUserV3\SDK\KnownUser', 'debugInfoArray');
		$r->setAccessible(true);
		$r->setValue(null, NULL);

		$cancelEventconfig = new \QueueIT\KnownUserV3\SDK\CancelEventConfig();
		$cancelEventconfig->cookieDomain = "cookiedomain";
		$cancelEventconfig->eventId = "eventid";
		$cancelEventconfig->queueDomain = "queuedomain";
		$cancelEventconfig->version = 1;
		
		$token = $this->generateHashDebugValidHash("secretkey");
		$timestamp = gmdate("Y-m-d\TH:i:s\Z");
		QueueIT\KnownUserV3\SDK\KnownUser::cancelRequestByLocalConfig("targeturl", $token, $cancelEventconfig, "customerid", "secretkey");
		
		$expectedCookie= 
		"TargetUrl=targeturl".
		"|QueueitToken=e_eventId~rt_debug~h_0aa4b0e41d4cceae77d8fa63890a778f2b5c9cf962239f2862150517844bc0ce".
		"|CancelConfig=EventId:eventid&Version:1&QueueDomain:queuedomain&CookieDomain:cookiedomain".
		"|OriginalUrl=OriginalURL".
		"|ServerUtcTime=".$timestamp.
		"|RequestIP=userIP".
		"|RequestHttpHeader_Via=v".
		"|RequestHttpHeader_Forwarded=f".
		"|RequestHttpHeader_XForwardedFor=xff".
		"|RequestHttpHeader_XForwardedHost=xfh".
		"|RequestHttpHeader_XForwardedProto=xfp";
		
		$this->assertTrue($httpRequestProvider->cookieManager->debugInfoCookie == $expectedCookie );
	}
	
	public function generateHashDebugValidHash( $secretKey) {
		$token = 'e_eventId' .   '~rt_debug';
		return $token . '~h_' . hash_hmac('sha256', $token, $secretKey);
	}
}
