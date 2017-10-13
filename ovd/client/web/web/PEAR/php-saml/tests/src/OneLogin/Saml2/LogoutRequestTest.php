<?php

/**
 * Unit tests for Logout Request
 */
class OneLogin_Saml2_LogoutRequestTest extends PHPUnit_Framework_TestCase
{
    private $_settings;

    /**
    * Initializes the Test Suite
    */
    public function setUp()
    {
        $settingsDir = TEST_ROOT .'/settings/';
        include $settingsDir.'settings1.php';

        $settings = new OneLogin_Saml2_Settings($settingsInfo);
        $this->_settings = $settings;
    }

    /**
    * Tests the OneLogin_Saml2_LogoutRequest Constructor. 
    *
    * @covers OneLogin_Saml2_LogoutRequest
    */
    public function testConstructor()
    {
        $settingsDir = TEST_ROOT .'/settings/';
        include $settingsDir.'settings1.php';

        $settingsInfo['security']['nameIdEncrypted'] = true;

        $settings = new OneLogin_Saml2_Settings($settingsInfo);

        $logoutRequest = new OneLogin_Saml2_LogoutRequest($settings);

        $parameters = array('SAMLRequest' => $logoutRequest->getRequest());
        $logoutUrl = OneLogin_Saml2_Utils::redirect('http://idp.example.com/SingleLogoutService.php', $parameters, true);
        $this->assertRegExp('#^http://idp\.example\.com\/SingleLogoutService\.php\?SAMLRequest=#', $logoutUrl);
        parse_str(parse_url($logoutUrl, PHP_URL_QUERY), $exploded);
        // parse_url already urldecode de params so is not required.
        $payload = $exploded['SAMLRequest'];
        $decoded = base64_decode($payload);
        $inflated = gzinflate($decoded);
        $this->assertRegExp('#^<samlp:LogoutRequest#', $inflated);
    }

    /**
    * Tests the OneLogin_Saml2_LogoutRequest Constructor. 
    * The creation of a deflated SAML Logout Request
    *
    * @covers OneLogin_Saml2_LogoutRequest
    */
    public function testCreateDeflatedSAMLLogoutRequestURLParameter()
    {
        $logoutRequest = new OneLogin_Saml2_LogoutRequest($this->_settings);

        $parameters = array('SAMLRequest' => $logoutRequest->getRequest());
        $logoutUrl = OneLogin_Saml2_Utils::redirect('http://idp.example.com/SingleLogoutService.php', $parameters, true);
        $this->assertRegExp('#^http://idp\.example\.com\/SingleLogoutService\.php\?SAMLRequest=#', $logoutUrl);
        parse_str(parse_url($logoutUrl, PHP_URL_QUERY), $exploded);
        // parse_url already urldecode de params so is not required.
        $payload = $exploded['SAMLRequest'];
        $decoded = base64_decode($payload);
        $inflated = gzinflate($decoded);
        $this->assertRegExp('#^<samlp:LogoutRequest#', $inflated);
    }

    /**
    * Tests the getID method of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::getID
    */
    public function testGetIDFromSAMLLogoutRequest()
    {
        $logoutRequest = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request.xml');
        $id = OneLogin_Saml2_LogoutRequest::getID($logoutRequest);
        $this->assertEquals('ONELOGIN_21584ccdfaca36a145ae990442dcd96bfe60151e', $id);

        $dom = new DOMDocument;
        $dom->loadXML($logoutRequest);
        $id2 = OneLogin_Saml2_LogoutRequest::getID($dom);
        $this->assertEquals('ONELOGIN_21584ccdfaca36a145ae990442dcd96bfe60151e', $id2);
    }

    /**
    * Tests the getID method of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::getID
    */
    public function testGetIDFromDeflatedSAMLLogoutRequest()
    {
        $deflatedLogoutRequest = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request_deflated.xml.base64');
        $decoded = base64_decode($deflatedLogoutRequest);
        $logoutRequest = gzinflate($decoded);
        $id = OneLogin_Saml2_LogoutRequest::getID($logoutRequest);
        $this->assertEquals('ONELOGIN_21584ccdfaca36a145ae990442dcd96bfe60151e', $id);
    }

    /**
    * Tests the getNameIdData method of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::getNameIdData
    */
    public function testGetNameIdData()
    {
        $expectedNameIdData = array (
            'Value' => 'ONELOGIN_1e442c129e1f822c8096086a1103c5ee2c7cae1c',
            'Format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:unspecified',
            'SPNameQualifier' => 'http://idp.example.com/'
        );

        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request.xml');

        $nameIdData = OneLogin_Saml2_LogoutRequest::getNameIdData($request);

        $this->assertEquals($expectedNameIdData, $nameIdData);

        $dom = new DOMDocument();
        $dom->loadXML($request);
        $nameIdData2 = OneLogin_Saml2_LogoutRequest::getNameIdData($dom);
        $this->assertEquals($expectedNameIdData, $nameIdData2);

        $request2 = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request_encrypted_nameid.xml');

        try {
            $nameIdData3 = OneLogin_Saml2_LogoutRequest::getNameIdData($request2);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('Key is required in order to decrypt the NameID', $e->getMessage());
        }
        
        $key = $this->_settings->getSPkey();
        $nameIdData4 = OneLogin_Saml2_LogoutRequest::getNameIdData($request2, $key);

        $expectedNameIdData = array (
            'Value' => 'ONELOGIN_9c86c4542ab9d6fce07f2f7fd335287b9b3cdf69',
            'Format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:emailAddress',
            'SPNameQualifier' => 'https://pitbulk.no-ip.org/newonelogin/demo1/metadata.php'
        );

        $this->assertEquals($expectedNameIdData, $nameIdData4);

        $invRequest = file_get_contents(TEST_ROOT . '/data/logout_requests/invalids/no_nameId.xml');
        try {
            $nameIdData3 = OneLogin_Saml2_LogoutRequest::getNameIdData($invRequest);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('Not NameID found in the Logout Request', $e->getMessage());
        }

    }

    /**
    * Tests the getNameIdmethod of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::getNameId
    */
    public function testGetNameId()
    {
        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request.xml');

        $nameId = OneLogin_Saml2_LogoutRequest::getNameId($request);
        $this->assertEquals('ONELOGIN_1e442c129e1f822c8096086a1103c5ee2c7cae1c', $nameId);

        $request2 = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request_encrypted_nameid.xml');
        try {
            $nameId2 = OneLogin_Saml2_LogoutRequest::getNameId($request2);
            $this->assertFalse(true);
        } catch (Exception $e) {
            $this->assertContains('Key is required in order to decrypt the NameID', $e->getMessage());
        }
        $key = $this->_settings->getSPkey();
        $nameId3 = OneLogin_Saml2_LogoutRequest::getNameId($request2, $key);
        $this->assertEquals('ONELOGIN_9c86c4542ab9d6fce07f2f7fd335287b9b3cdf69', $nameId3);
    }

    /**
    * Tests the getIssuer of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::getIssuer
    */
    public function testGetIssuer()
    {
        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request.xml');

        $issuer = OneLogin_Saml2_LogoutRequest::getIssuer($request);
        $this->assertEquals('http://idp.example.com/', $issuer);

        $dom = new DOMDocument();
        $dom->loadXML($request);
        $issuer2 = OneLogin_Saml2_LogoutRequest::getIssuer($dom);
        $this->assertEquals('http://idp.example.com/', $issuer2);
    }

    /**
    * Tests the getSessionIndexes of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::getSessionIndexes
    */
    public function testGetSessionIndexes()
    {
        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request.xml');

        $sessionIndexes = OneLogin_Saml2_LogoutRequest::getSessionIndexes($request);
        $this->assertEmpty($sessionIndexes);

        $dom = new DOMDocument();
        $dom->loadXML($request);
        $sessionIndexes = OneLogin_Saml2_LogoutRequest::getSessionIndexes($dom);
        $this->assertEmpty($sessionIndexes);

        $request2 = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request_with_sessionindex.xml');
        $sessionIndexes2 = OneLogin_Saml2_LogoutRequest::getSessionIndexes($request2);
        $this->assertEquals(array('_ac72a76526cb6ca19f8438e73879a0e6c8ae5131'), $sessionIndexes2);
    }

    /**
    * Tests the isValid method of the OneLogin_Saml2_LogoutRequest
    * Case Invalid Issuer
    *
    * @covers OneLogin_Saml2_LogoutRequest::isValid
    */
    public function testIsInvalidIssuer()
    {
        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/invalids/invalid_issuer.xml');
        $currentURL = OneLogin_Saml2_Utils::getSelfURLNoQuery();
        $request = str_replace('http://stuff.com/endpoints/endpoints/sls.php', $currentURL, $request);
        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request));

        $this->_settings->setStrict(true);
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('Invalid issuer in the Logout Request', $e->getMessage());
        }
    }

    /**
    * Tests the isValid method of the OneLogin_Saml2_LogoutRequest
    * Case Invalid Destination
    *
    * @covers OneLogin_Saml2_LogoutRequest::isValid
    */
    public function testIsInvalidDestination()
    {
        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request.xml');
        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request));

        $this->_settings->setStrict(true);
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('The LogoutRequest was received at', $e->getMessage());
        }
    }

    /**
    * Tests the isValid method of the OneLogin_Saml2_LogoutRequest
    * Case Invalid NotOnOrAfter
    *
    * @covers OneLogin_Saml2_LogoutRequest::isValid
    */
    public function testIsInvalidNotOnOrAfter()
    {
        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/invalids/not_after_failed.xml');
        $currentURL = OneLogin_Saml2_Utils::getSelfURLNoQuery();
        $request = str_replace('http://stuff.com/endpoints/endpoints/sls.php', $currentURL, $request);

        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request));

        $this->_settings->setStrict(true);
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('Timing issues (please check your clock settings)', $e->getMessage());
        }
    }

    /**
    * Tests the isValid method of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::isValid
    */
    public function testIsValid()
    {
        $request = file_get_contents(TEST_ROOT . '/data/logout_requests/logout_request.xml');

        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request));

        $this->_settings->setStrict(true);
        $this->assertFalse(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request));

        $this->_settings->setStrict(false);
        $dom = new DOMDocument();
        $dom->loadXML($request);
        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $dom));

        $this->_settings->setStrict(true);
        $this->assertFalse(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $dom));

        $currentURL = OneLogin_Saml2_Utils::getSelfURLNoQuery();
        $request2 = str_replace('http://stuff.com/endpoints/endpoints/sls.php', $currentURL, $request);
        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request2));
    }

    /**
    * Tests the isValid method of the OneLogin_Saml2_LogoutRequest
    *
    * @covers OneLogin_Saml2_LogoutRequest::isValid
    */
    public function testIsInValidSign()
    {
        $currentURL = OneLogin_Saml2_Utils::getSelfURLNoQuery();

        $this->_settings->setStrict(false);
        $_GET = array (
            'SAMLRequest' => 'lVLBitswEP0Vo7tjeWzJtki8LIRCYLvbNksPewmyPc6K2pJqyXQ/v1LSQlroQi/DMJr33rwZbZ2cJysezNms/gt+X9H55G2etBOXlx1ZFy2MdMoJLWd0wvfieP/xQcCGCrsYb3ozkRvI+wjpHC5eGU2Sw35HTg3lA8hqZFwWFcMKsStpxbEsxoLXeQN9OdY1VAgk+YqLC8gdCUQB7tyKB+281D6UaF6mtEiBPudcABcMXkiyD26Ulv6CevXeOpFlVvlunb5ttEmV3ZjlnGn8YTRO5qx0NuBs8kzpAd829tXeucmR5NH4J/203I8el6gFRUqbFPJnyEV51Wq30by4TLW0/9ZyarYTxt4sBsjUYLMZvRykl1Fxm90SXVkfwx4P++T4KSafVzmpUcVJ/sfSrQZJPphllv79W8WKGtLx0ir8IrVTqD1pT2MH3QAMSs4KTvui71jeFFiwirOmprwPkYW063+5uRq4urHiiC4e8hCX3J5wqAEGaPpw9XB5JmkBdeDqSlkz6CmUXdl0Qae5kv2F/1384wu3PwE=',
            'RelayState' => '_1037fbc88ec82ce8e770b2bed1119747bb812a07e6',
            'SigAlg' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
            'Signature' => 'XCwCyI5cs7WhiJlB5ktSlWxSBxv+6q2xT3c8L7dLV6NQG9LHWhN7gf8qNsahSXfCzA0Ey9dp5BQ0EdRvAk2DIzKmJY6e3hvAIEp1zglHNjzkgcQmZCcrkK9Czi2Y1WkjOwR/WgUTUWsGJAVqVvlRZuS3zk3nxMrLH6f7toyvuJc='
        );

        $request = gzinflate(base64_decode($_GET['SAMLRequest']));

        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request));

        $this->_settings->setStrict(true);
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('The LogoutRequest was received at', $e->getMessage());
        }

        $this->_settings->setStrict(false);
        $oldSignature = $_GET['Signature'];
        $_GET['Signature'] = 'vfWbbc47PkP3ejx4bjKsRX7lo9Ml1WRoE5J5owF/0mnyKHfSY6XbhO1wwjBV5vWdrUVX+xp6slHyAf4YoAsXFS0qhan6txDiZY4Oec6yE+l10iZbzvie06I4GPak4QrQ4gAyXOSzwCrRmJu4gnpeUxZ6IqKtdrKfAYRAcVf3333=';

        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('Signature validation failed. Logout Request rejected', $e->getMessage());
        }

        $_GET['Signature'] = $oldSignature;
        $oldSigAlg = $_GET['SigAlg'];
        unset($_GET['SigAlg']);

        $this->assertTrue(OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request));

        $oldRelayState = $_GET['RelayState'];
        $_GET['RelayState'] = 'http://example.com/relaystate';
        
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('Signature validation failed. Logout Request rejected', $e->getMessage());
        }

        $this->_settings->setStrict(true);
        
        $request2 = str_replace('https://pitbulk.no-ip.org/newonelogin/demo1/index.php?sls', $currentURL, $request);
        $request2 = str_replace('https://pitbulk.no-ip.org/simplesaml/saml2/idp/metadata.php', 'http://idp.example.com/', $request2);

        $_GET['SAMLRequest'] = base64_encode(gzdeflate($request2));
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request2);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('Signature validation failed. Logout Request rejected', $e->getMessage());
        }

        $this->_settings->setStrict(false);
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request2);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('Signature validation failed. Logout Response rejected', $e->getMessage());
        }

        $_GET['SigAlg'] = 'http://www.w3.org/2000/09/xmldsig#dsa-sha1';
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($this->_settings, $request2);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('Invalid signAlg in the recieved Logout Request', $e->getMessage());
        }

        $settingsDir = TEST_ROOT .'/settings/';
        include $settingsDir.'settings1.php';
        $settingsInfo['strict'] = true;
        $settingsInfo['security']['wantMessagesSigned'] = true;
        
        $settings = new OneLogin_Saml2_Settings($settingsInfo);

        $_GET['SigAlg'] = $oldSigAlg;
        $oldSignature = $_GET['Signature'];
        unset($_GET['Signature']);
        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($settings, $request2);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('The Message of the Logout Request is not signed and the SP require it', $e->getMessage());
        }

        $_GET['Signature'] = $oldSignature;
       
        $settingsInfo['idp']['certFingerprint'] = 'afe71c28ef740bc87425be13a2263d37971da1f9';
        unset($settingsInfo['idp']['x509cert']);
        $settings2 = new OneLogin_Saml2_Settings($settingsInfo);

        try {
            $valid = OneLogin_Saml2_LogoutRequest::isValid($settings2, $request2);
            $this->assertFalse($valid);
        } catch (Exception $e) {
            $this->assertContains('In order to validate the sign on the Logout Request, the x509cert of the IdP is required', $e->getMessage());
        }
    }
}
