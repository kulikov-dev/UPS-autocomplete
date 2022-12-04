<?php

namespace kulikov_dev\ups;

use Exception;
use SoapClient;
use SoapFault;
use SoapHeader;

/**
 * Class addpress_processor for validating and auto-complete an address
 * @package kulikov_dev\ups
 */
class address_processor
{
    /**
     * @var SoapClient
     */
    private $SOAP_client;

    /**
     * @var string URL to UPS endpoint
     */
    private $endpoint_url = 'https://onlinetools.ups.com/webservices/Ship';

    /**
     * @var string WSDL path to address validator
     */
    private $wsdl_path = '';

    /**
     * @var bool Flag if service works in test mode
     */
    private $is_test_mode;

    /**
     * @var string Credentials: UPS user name
     */
    private $user_name = '';

    /**
     * @var string Credentials: UPS password
     */
    private $password = '';

    /**
     * @var string Credentials: UPS license number
     */
    private $license_number = '';

    /**
     * addpress_processor constructor.
     * @param $is_test_mode bool. Flag if service works in test mode
     */
    public function __construct($is_test_mode)
    {
        $this->is_test_mode = $is_test_mode;
        $this->set_credentials();
    }

    /**
     * Setup API credentials
     */
    public function set_credentials()
    {
        $this->user_name = '';          // TODO initialize credentials from your storage.
        $this->password = '';
        $this->license_number = '';
    }

    /** Init SOAP client
     * @throws SoapFault SOAP exception
     */
    private function init_client()
    {
        $this->init_endpoint();

        if (empty($this->wsdl_path)) {
            die('Fatal error: SOAP WSDL location not specified!');
        }

        $mode = array('soap_version' => 'SOAP_1_1', 'trace' => 1);

        try {
            $this->SOAP_client = new SoapClient($this->wsdl_path, $mode);
        } catch (SoapFault $e) {
            throw $e;
        }

        $this->SOAP_client->__setLocation($this->endpoint_url);

        $ups_headers = ['UsernameToken' => array('Username' => $this->user_name, 'Password' => $this->password)];
        $ups_headers['ServiceAccessToken'] = array('AccessLicenseNumber' => $this->license_number);

        $header = new SoapHeader('http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0', 'UPSSecurity', $ups_headers);
        $this->SOAP_client->__setSoapHeaders($header);
    }

    /** Init SOAP endpoint and WSDL
     * @throws Exception Wrong WSDL file
     */
    private function init_endpoint()
    {
        $type = 'XAV';
        if (!file_exists(__DIR__ . '/wsdl/' . $type . '.wsdl')) {
            throw new Exception('UPS WSDL is missing!');
        }
        $this->wsdl_path = __DIR__ . '/wsdl/' . $type . '.wsdl';
        $this->endpoint_url = 'https://onlinetools.ups.com/webservices/' . $type;

        if ($this->is_test_mode) {
            $this->endpoint_url = 'https://wwwcie.ups.com/webservices/' . $type;
        }
    }

    /** Validate address via UPS service
     * @param $country string Country
     * @param $zip string Zip code
     * @param $state string state
     * @param $city string state
     * @param $address string address line
     * @return bool|array False if un validatable, array if has possible variants
     * @throws Exception SOAP exception
     */
    public function validate_address($country, $zip, $state, $city, $address)
    {
        $this->init_client();

        try {
            $resp = $this->SOAP_client->__soapCall('ProcessXAV', array($this->get_validating_ups_record($country, $zip, $state, $city, $address)));
        } catch (Exception $ex) {
            throw new Exception('UPS SOAP error: #' . $ex->detail->Errors->ErrorDetail->PrimaryErrorCode->Code . ' - ' . $ex->detail->Errors->ErrorDetail->PrimaryErrorCode->Description);
        }

        if (isset($resp->NoCandidatesIndicator)) {
            return false;       // Address formed so badly that we don't have any variants
        }

        if (is_array($resp->Candidate)) {
            return $resp->Candidate;
        }

        return array($resp->Candidate);
    }

    /** Get possible candidates for an address
     * @param $country string Country
     * @param $zip string Zip code
     * @param $state string state
     * @param $city string state
     * @param $address string address line
     * @param integer $item_type 0 - PostCode, 1 - State, 2 - City, 3 - Address
     * @return array|bool False if un validatable, array if has possible variants
     * @throws Exception Wrong item type
     */
    public function get_address_field_candidates($country, $zip, $state, $city, $address, $item_type)
    {
        $result = $this->validate_address($country, $zip, $state, $city, $address);
        if (!$result) {
            return $result;
        }

        $index = -1;
        $candidates = [];
        foreach ($result as $item) {
            $index = $index + 1;
            switch ($item_type) {
                case 0:
                    $candidates[$index] = $item->AddressKeyFormat->PostcodePrimaryLow;
                    break;
                case 1:
                    $candidates[$index] = $item->AddressKeyFormat->PoliticalDivision1;
                    break;
                case 2:
                    $candidates[$index] = $item->AddressKeyFormat->PoliticalDivision2;
                    break;
                case 3:
                    if (is_array($item->AddressKeyFormat->AddressLine)) {
                        $candidates[$index] = implode(", ", $item->AddressKeyFormat->AddressLine);
                    } else {
                        $candidates[$index] = $item->AddressKeyFormat->AddressLine;
                    }

                    break;
                default:
                    throw new Exception("Wrong type");
            }
        }

        return $candidates;
    }

    /** Create SOAP request data
     * @param $country string Country
     * @param $zip string Zip code
     * @param $state string state
     * @param $city string state
     * @param $address string address line
     * @return array SOAP request data
     */
    private function get_validating_ups_record($country, $zip, $state, $city, $address)
    {
        $option['RequestOption'] = '1';             // 1 -Address Validation 2 -Address Classification 3 -Address Validation and Address Classification
        $request['Request'] = $option;
        $request['MaximumCandidateListSize'] = 10;

        $address_format['AddressLine'] = $address;
        $address_format['$PoliticalDivision2'] = $city;
        $address_format['$PoliticalDivision1'] = $state;
        $address_format['PostcodePrimaryLow'] = $zip;
        $address_format['CountryCode'] = $country;

        $request['AddressKeyFormat'] = $address_format;

        return $request;
    }
}
