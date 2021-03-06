<?php

namespace VCR\Example;

/**
 * Converts temperature units from webservicex
 *
 * @link http://www.webservicex.net/New/Home/ServiceDetail/31
 */
class ExampleSoapClient
{
    private const EXAMPLE_WSDL = 'http://www.dataaccess.com/webservicesserver/numberconversion.wso?WSDL';

    public function call($number = 12): string
    {
        $client = new \SoapClient(self::EXAMPLE_WSDL, ['soap_version' => SOAP_1_2]);
        $response = $client->NumberToWords(['ubiNum' => $number]);

        return trim((string) $response->NumberToWordsResult);
    }

    public function callBadUrl(): void
    {
        // The port is not open. This leads to an error
        $client = new \SoapClient('http://localhost:9945', ['soap_version' => SOAP_1_2]);
    }
}
